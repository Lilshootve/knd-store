<?php
/**
 * Admin User - Detalle y acciones (solo owner).
 * Create, edit role, toggle active, reset password.
 * Reglas: no desactivar/cambiar role del último owner; reason obligatorio.
 */
require_once __DIR__ . '/../config/bootstrap.php';
ini_set('display_errors', '0');
require_once __DIR__ . '/_guard.php';
admin_require_login();
require_once BASE_PATH . '/includes/csrf.php';
require_once BASE_PATH . '/includes/rate_limit.php';
require_once __DIR__ . '/_audit.php';

$pdo = getDBConnection();
if (!$pdo) { header('Content-Type: text/plain'); echo 'DB connection failed.'; exit; }

$isCreate = isset($_GET['create']) && $_GET['create'] === '1';
$adminId = $isCreate ? 0 : (int) ($_GET['id'] ?? 0);

if (!$isCreate && $adminId <= 0) {
    header('Location: /admin/admin-users.php');
    exit;
}

$csrfToken = csrf_token();
$flashMsg = '';
$flashType = '';
$tempPassword = null; // Mostrar una sola vez en modal

// Helper: rate limit con redirect en admin
function admin_rate_limit_redirect(PDO $pdo, string $key, int $maxHits, int $windowSecs, string $redirectUrl): void {
    if (!rate_limit_check($pdo, $key, $maxHits, $windowSecs)) {
        $_SESSION['au_flash'] = ['msg' => 'Too many requests. Please wait.', 'type' => 'danger'];
        header('Location: ' . $redirectUrl);
        exit;
    }
}

// Helper: contar owners activos
function count_active_owners(PDO $pdo): int {
    $stmt = $pdo->query("SELECT COUNT(*) FROM admin_users WHERE role = 'owner' AND active = 1");
    return (int) $stmt->fetchColumn();
}

// ── POST actions ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $reason = trim($_POST['reason'] ?? '');

    $requireReason = in_array($action, ['change_role', 'toggle_active', 'reset_password'], true);
    if ($requireReason && $reason === '') {
        $_SESSION['au_flash'] = ['msg' => 'Reason is required.', 'type' => 'danger'];
        header('Location: ' . ($adminId > 0 ? '/admin/admin-user.php?id=' . $adminId : '/admin/admin-user.php?create=1'));
        exit;
    }

    try {
        csrf_guard();

        switch ($action) {
            case 'create':
                admin_require_perm('admin_users.create');
                admin_rate_limit_redirect($pdo, "admin_create:{$ip}", 5, 60, '/admin/admin-user.php?create=1');

                $username = trim($_POST['username'] ?? '');
                $password = $_POST['password'] ?? '';
                $role = $_POST['role'] ?? 'viewer';
                $allowedRoles = ['owner', 'manager', 'support', 'viewer'];
                if (!in_array($role, $allowedRoles, true)) $role = 'viewer';
                if (strlen($username) < 2 || strlen($username) > 64) {
                    throw new \Exception('Username must be 2–64 characters.');
                }
                if (strlen($password) < 8) {
                    throw new \Exception('Password must be at least 8 characters.');
                }

                $hash = password_hash($password, PASSWORD_DEFAULT);
                $currentAdminId = admin_id();
                $cols = 'username, password_hash, role, active';
                $vals = '?, ?, ?, 1';
                $params = [$username, $hash, $role];
                $hasCreatedBy = false;
                try {
                    $c = $pdo->query("SHOW COLUMNS FROM admin_users LIKE 'created_by_admin_id'")->rowCount();
                    if ($c > 0) { $cols .= ', created_by_admin_id'; $vals .= ', ?'; $params[] = $currentAdminId; $hasCreatedBy = true; }
                } catch (\Throwable $e) {}

                $pdo->prepare("INSERT INTO admin_users ({$cols}) VALUES ({$vals})")->execute($params);
                $newId = (int) $pdo->lastInsertId();
                admin_log_action('admin_user_created', [
                    'target_admin_id' => $newId,
                    'username' => $username,
                    'role' => $role,
                    'reason' => $reason,
                ]);
                $_SESSION['au_flash'] = ['msg' => 'Admin user created.', 'type' => 'success'];
                header('Location: /admin/admin-user.php?id=' . $newId);
                exit;

            case 'change_role':
                admin_require_perm('admin_users.edit');
                admin_rate_limit_redirect($pdo, "admin_role:{$ip}", 10, 60, '/admin/admin-user.php?id=' . $adminId);

                $targetId = (int) ($_POST['target_id'] ?? 0);
                $newRole = $_POST['new_role'] ?? '';
                $allowedRoles = ['owner', 'manager', 'support', 'viewer'];
                if (!in_array($newRole, $allowedRoles, true)) throw new \Exception('Invalid role.');
                if ($targetId <= 0) throw new \Exception('Invalid target.');

                $stmt = $pdo->prepare('SELECT id, role FROM admin_users WHERE id = ? LIMIT 1');
                $stmt->execute([$targetId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) throw new \Exception('Admin user not found.');
                $oldRole = $row['role'];

                if ($oldRole === 'owner' && count_active_owners($pdo) <= 1) {
                    throw new \Exception('Cannot change role of the last owner.');
                }

                $pdo->prepare('UPDATE admin_users SET role = ?, updated_at = NOW() WHERE id = ?')->execute([$newRole, $targetId]);
                admin_log_action('admin_user_role_changed', [
                    'target_admin_id' => $targetId,
                    'old_role' => $oldRole,
                    'new_role' => $newRole,
                    'reason' => $reason,
                ]);
                $_SESSION['au_flash'] = ['msg' => 'Role updated.', 'type' => 'success'];
                header('Location: /admin/admin-user.php?id=' . $targetId);
                exit;

            case 'toggle_active':
                admin_require_perm('admin_users.edit');

                $targetId = (int) ($_POST['target_id'] ?? 0);
                if ($targetId <= 0) throw new \Exception('Invalid target.');

                $stmt = $pdo->prepare('SELECT id, role, active FROM admin_users WHERE id = ? LIMIT 1');
                $stmt->execute([$targetId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) throw new \Exception('Admin user not found.');

                if ($row['role'] === 'owner' && (int) $row['active'] === 1 && count_active_owners($pdo) <= 1) {
                    throw new \Exception('Cannot deactivate the last owner.');
                }

                $newActive = (int) $row['active'] === 1 ? 0 : 1;
                $pdo->prepare('UPDATE admin_users SET active = ?, updated_at = NOW() WHERE id = ?')->execute([$newActive, $targetId]);
                admin_log_action($newActive ? 'admin_user_activated' : 'admin_user_deactivated', [
                    'target_admin_id' => $targetId,
                    'active' => $newActive,
                    'reason' => $reason,
                ]);
                $_SESSION['au_flash'] = ['msg' => $newActive ? 'Admin activated.' : 'Admin deactivated.', 'type' => 'success'];
                header('Location: /admin/admin-user.php?id=' . $targetId);
                exit;

            case 'reset_password':
                admin_require_perm('admin_users.reset_password');
                admin_rate_limit_redirect($pdo, "admin_reset_pw:{$ip}", 5, 60, '/admin/admin-user.php?id=' . $adminId);

                $targetId = (int) ($_POST['target_id'] ?? 0);
                if ($targetId <= 0) throw new \Exception('Invalid target.');

                $stmt = $pdo->prepare('SELECT id, username FROM admin_users WHERE id = ? LIMIT 1');
                $stmt->execute([$targetId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) throw new \Exception('Admin user not found.');

                $tempPass = bin2hex(random_bytes(8)); // 16 chars
                $hash = password_hash($tempPass, PASSWORD_DEFAULT);
                $hasForceReset = false;
                try {
                    $c = $pdo->query("SHOW COLUMNS FROM admin_users LIKE 'force_password_reset'")->rowCount();
                    $hasForceReset = ($c > 0);
                } catch (\Throwable $e) {}

                if ($hasForceReset) {
                    $pdo->prepare('UPDATE admin_users SET password_hash = ?, force_password_reset = 1, updated_at = NOW() WHERE id = ?')->execute([$hash, $targetId]);
                } else {
                    $pdo->prepare('UPDATE admin_users SET password_hash = ?, updated_at = NOW() WHERE id = ?')->execute([$hash, $targetId]);
                }

                admin_log_action('admin_user_password_reset', [
                    'target_admin_id' => $targetId,
                    'reason' => $reason,
                ]);

                $_SESSION['au_temp_password'] = $tempPass;
                $_SESSION['au_flash'] = ['msg' => 'Password reset. Temporary password shown below (copy it now).', 'type' => 'success'];
                header('Location: /admin/admin-user.php?id=' . $targetId . '&show_temp=1');
                exit;

            default:
                throw new \Exception('Unknown action.');
        }
    } catch (\Throwable $e) {
        $_SESSION['au_flash'] = ['msg' => $e->getMessage(), 'type' => 'danger'];
        $redir = $adminId > 0 ? '/admin/admin-user.php?id=' . $adminId : '/admin/admin-user.php?create=1';
        header('Location: ' . $redir);
        exit;
    }
}

// Flash y temp password (mostrar una sola vez)
if (!empty($_SESSION['au_flash'])) {
    $flashMsg = $_SESSION['au_flash']['msg'];
    $flashType = $_SESSION['au_flash']['type'];
    unset($_SESSION['au_flash']);
}
if (isset($_GET['show_temp']) && !empty($_SESSION['au_temp_password'])) {
    $tempPassword = $_SESSION['au_temp_password'];
    unset($_SESSION['au_temp_password']);
}

// ── Create form ──
if ($isCreate) {
    admin_require_perm('admin_users.create');
    require_once BASE_PATH . '/includes/header.php';
    echo generateHeader('Create Admin', 'Create new admin user');
    echo generateAdminBar();
    ?>
    <style>
    .au-dash { --cyan: var(--c, #00e8ff); min-height:100vh; background: var(--void, #010508); color:#e8ecf0; padding-top:24px; padding-bottom:60px; }
    .au-card { background:rgba(12,15,22,.85); border:1px solid rgba(255,255,255,.08); border-radius:12px; padding:1.5rem; max-width:420px; }
    .au-topbar { margin-bottom:1.5rem; }
    </style>
    <div class="au-dash"><div class="container">
        <div class="au-topbar">
            <a href="/admin/admin-users.php" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back to list</a>
        </div>
        <div class="au-card">
            <h5 class="mb-4"><i class="fas fa-user-plus me-2" style="color:var(--cyan)"></i>Create Admin User</h5>
            <?php if ($flashMsg): ?>
            <div class="alert alert-<?php echo $flashType; ?>"><?php echo htmlspecialchars($flashMsg); ?></div>
            <?php endif; ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="action" value="create">
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control bg-dark text-light border-secondary" required minlength="2" maxlength="64" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control bg-dark text-light border-secondary" required minlength="8">
                </div>
                <div class="mb-3">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-select bg-dark text-light border-secondary">
                        <option value="viewer">Viewer</option>
                        <option value="support">Support</option>
                        <option value="manager">Manager</option>
                        <option value="owner">Owner</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Reason (optional)</label>
                    <input type="text" name="reason" class="form-control bg-dark text-light border-secondary" placeholder="e.g. New team member" value="<?php echo htmlspecialchars($_POST['reason'] ?? ''); ?>">
                </div>
                <button type="submit" class="btn btn-primary">Create Admin</button>
            </form>
        </div>
    </div></div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script></body></html>
    <?php
    exit;
}

// ── Detail view ──
admin_require_perm('admin_users.view');

$stmt = $pdo->prepare('SELECT id, username, role, active, created_at, last_login_at, last_login_ip FROM admin_users WHERE id = ? LIMIT 1');
$stmt->execute([$adminId]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$admin) {
    header('Location: /admin/admin-users.php');
    exit;
}

$roleBadges = ['owner' => 'bg-danger', 'manager' => 'bg-primary', 'support' => 'bg-info', 'viewer' => 'bg-secondary'];
$isActive = (int) ($admin['active'] ?? 1) === 1;
$ownerCount = count_active_owners($pdo);
$isLastOwner = $admin['role'] === 'owner' && $ownerCount <= 1;
$canEdit = admin_has_perm('admin_users.edit');
$canReset = admin_has_perm('admin_users.reset_password');

require_once BASE_PATH . '/includes/header.php';
echo generateHeader('Admin: ' . htmlspecialchars($admin['username']), 'Admin user detail');
echo generateAdminBar();
?>
<style>
.au-dash { --cyan: var(--c, #00e8ff); min-height:100vh; background: var(--void, #010508); color:#e8ecf0; padding-top:24px; padding-bottom:60px; }
.au-card { background:rgba(12,15,22,.85); border:1px solid rgba(255,255,255,.08); border-radius:12px; padding:1.5rem; margin-bottom:1.5rem; }
.au-topbar { margin-bottom:1.5rem; }
</style>

<div class="au-dash"><div class="container">
    <div class="au-topbar d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <a href="/admin/admin-users.php" class="btn btn-outline-light btn-sm me-2"><i class="fas fa-arrow-left me-1"></i>Back</a>
            <strong><?php echo htmlspecialchars($admin['username']); ?></strong>
            <span class="badge <?php echo $roleBadges[$admin['role']] ?? 'bg-secondary'; ?> ms-2"><?php echo htmlspecialchars($admin['role']); ?></span>
            <?php if ($isActive): ?><span class="badge bg-success ms-1">Active</span><?php else: ?><span class="badge bg-secondary ms-1">Disabled</span><?php endif; ?>
        </div>
    </div>

    <?php if ($flashMsg): ?>
    <div class="alert alert-<?php echo $flashType; ?> mb-4"><?php echo htmlspecialchars($flashMsg); ?></div>
    <?php endif; ?>

    <?php if ($tempPassword): ?>
    <div class="alert alert-warning mb-4">
        <strong>Temporary password (copy now, shown only once):</strong>
        <code class="d-block mt-2 p-2 bg-dark rounded" style="font-size:1.1rem;"><?php echo htmlspecialchars($tempPassword); ?></code>
    </div>
    <?php endif; ?>

    <div class="au-card">
        <h6 class="mb-3">Details</h6>
        <dl class="row mb-0">
            <dt class="col-sm-3 text-white-50">ID</dt>
            <dd class="col-sm-9"><?php echo (int) $admin['id']; ?></dd>
            <dt class="col-sm-3 text-white-50">Created</dt>
            <dd class="col-sm-9"><?php echo date('Y-m-d H:i', strtotime($admin['created_at'])); ?></dd>
            <dt class="col-sm-3 text-white-50">Last Login</dt>
            <dd class="col-sm-9">
                <?php if (!empty($admin['last_login_at'])): ?>
                <?php echo date('Y-m-d H:i:s', strtotime($admin['last_login_at'])); ?>
                <?php if (!empty($admin['last_login_ip'])): ?> <span class="text-white-50">(<?php echo htmlspecialchars($admin['last_login_ip']); ?>)</span><?php endif; ?>
                <?php else: ?>never<?php endif; ?>
            </dd>
        </dl>
    </div>

    <?php if ($canEdit || $canReset): ?>
    <div class="au-card">
        <h6 class="mb-3">Actions</h6>

        <?php if ($canEdit): ?>
        <form method="post" class="mb-4">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <input type="hidden" name="action" value="change_role">
            <input type="hidden" name="target_id" value="<?php echo (int) $admin['id']; ?>">
            <div class="row g-2 align-items-end">
                <div class="col-auto">
                    <label class="form-label small">Change role</label>
                    <select name="new_role" class="form-select form-select-sm bg-dark text-light border-secondary" style="width:auto;">
                        <?php foreach (['owner','manager','support','viewer'] as $r): ?>
                        <option value="<?php echo $r; ?>" <?php echo $admin['role'] === $r ? 'selected' : ''; ?> <?php echo $isLastOwner && $r !== 'owner' ? 'disabled' : ''; ?>><?php echo $r; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <input type="text" name="reason" class="form-control form-control-sm bg-dark text-light border-secondary" placeholder="Reason (required)" required style="min-width:180px;">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-primary" <?php echo $isLastOwner ? 'disabled' : ''; ?> title="<?php echo $isLastOwner ? 'Cannot change role of last owner' : ''; ?>">Update Role</button>
                </div>
            </div>
        </form>

        <form method="post" class="mb-4">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <input type="hidden" name="action" value="toggle_active">
            <input type="hidden" name="target_id" value="<?php echo (int) $admin['id']; ?>">
            <div class="row g-2 align-items-end">
                <div class="col-auto">
                    <input type="text" name="reason" class="form-control form-control-sm bg-dark text-light border-secondary" placeholder="Reason (required)" required style="min-width:180px;">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm <?php echo $isActive ? 'btn-warning' : 'btn-success'; ?>" <?php echo ($isActive && $isLastOwner) ? 'disabled' : ''; ?> title="<?php echo ($isActive && $isLastOwner) ? 'Cannot deactivate last owner' : ''; ?>">
                        <?php echo $isActive ? 'Deactivate' : 'Activate'; ?>
                    </button>
                </div>
            </div>
        </form>
        <?php endif; ?>

        <?php if ($canReset): ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="target_id" value="<?php echo (int) $admin['id']; ?>">
            <div class="row g-2 align-items-end">
                <div class="col-auto">
                    <input type="text" name="reason" class="form-control form-control-sm bg-dark text-light border-secondary" placeholder="Reason (required)" required style="min-width:180px;">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-warning">Reset Password</button>
                </div>
            </div>
        </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

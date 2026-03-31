<?php
/**
 * Admin Users - Lista de administradores (solo owner).
 * Permisos: admin_users.view (solo owner), admin_users.create para crear.
 */
ini_set('display_errors', '0');
require_once __DIR__ . '/_guard.php';
admin_require_login();
admin_require_perm('admin_users.view');

$pdo = getDBConnection();
if (!$pdo) { header('Content-Type: text/plain'); echo 'DB connection failed.'; exit; }

// Verificar columnas opcionales (updated_at, force_password_reset, created_by_admin_id)
$hasForceReset = false;
$hasUpdatedAt = false;
$hasCreatedBy = false;
try {
    $cols = $pdo->query("SHOW COLUMNS FROM admin_users")->fetchAll(PDO::FETCH_COLUMN);
    $hasForceReset = in_array('force_password_reset', $cols);
    $hasUpdatedAt = in_array('updated_at', $cols);
    $hasCreatedBy = in_array('created_by_admin_id', $cols);
} catch (\Throwable $e) {}

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 30;
$offset = ($page - 1) * $perPage;
$search = trim($_GET['q'] ?? '');

$whereClauses = [];
$params = [];
if ($search !== '') {
    $whereClauses[] = 'username LIKE ?';
    $params[] = '%' . $search . '%';
}
$whereSQL = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

$countSQL = "SELECT COUNT(*) FROM admin_users {$whereSQL}";
$stmt = $pdo->prepare($countSQL);
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));

$selectCols = 'id, username, role, active, created_at, last_login_at, last_login_ip';
$listSQL = "SELECT {$selectCols} FROM admin_users {$whereSQL} ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}";
$stmt = $pdo->prepare($listSQL);
$stmt->execute($params);
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

$roleBadges = [
    'owner'   => ['class' => 'bg-danger', 'label' => 'Owner'],
    'manager' => ['class' => 'bg-primary', 'label' => 'Manager'],
    'support' => ['class' => 'bg-info', 'label' => 'Support'],
    'viewer'  => ['class' => 'bg-secondary', 'label' => 'Viewer'],
];

require_once __DIR__ . '/../includes/header.php';
echo generateHeader('Admin Users', 'Admin user management');
echo generateAdminBar();
?>
<style>
.au-dash { --cyan: #00d4ff; min-height:100vh; background:#0a0a0f; color:#e8ecf0; padding-top:100px; padding-bottom:60px; }
.au-card { background:rgba(12,15,22,.85); border:1px solid rgba(255,255,255,.08); border-radius:12px; padding:1.25rem; }
.au-topbar { display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem; }
.au-topbar h1 { font-size:1.5rem; font-weight:700; margin:0; }
.au-tbl { font-size:.8rem; }
.au-tbl th { color:rgba(255,255,255,.5); font-weight:600; text-transform:uppercase; letter-spacing:.5px; font-size:.7rem; }
.au-tbl td { vertical-align:middle; }
</style>

<div class="au-dash">
<div class="container">
    <div class="au-topbar">
        <h1><i class="fas fa-user-shield me-2" style="color:var(--cyan)"></i>Admin Users</h1>
        <div class="d-flex gap-2">
            <a href="/admin/" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Dashboard</a>
            <?php if (admin_has_perm('admin_users.create')): ?>
            <a href="/admin/admin-user.php?create=1" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>Create Admin</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="au-card mb-4">
        <form method="get" class="d-flex gap-2 flex-wrap">
            <input type="text" name="q" class="form-control form-control-sm" placeholder="Search by username…" value="<?php echo htmlspecialchars($search); ?>" style="max-width:280px;">
            <button class="btn btn-sm btn-primary"><i class="fas fa-search me-1"></i>Search</button>
            <?php if ($search !== ''): ?>
            <a href="/admin/admin-users.php" class="btn btn-sm btn-outline-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="au-card mb-4">
        <div class="table-responsive">
            <table class="table table-sm table-dark au-tbl mb-0" style="--bs-table-bg:transparent;">
                <thead><tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Last Login</th>
                    <th>Actions</th>
                </tr></thead>
                <tbody>
                <?php if (empty($admins)): ?>
                <tr><td colspan="7" class="text-center text-white-50 py-4">No admin users found.</td></tr>
                <?php else: foreach ($admins as $a):
                    $roleInfo = $roleBadges[$a['role']] ?? ['class' => 'bg-secondary', 'label' => $a['role']];
                    $isActive = (int)($a['active'] ?? 1) === 1;
                ?>
                <tr>
                    <td><?php echo (int) $a['id']; ?></td>
                    <td><strong><?php echo htmlspecialchars($a['username']); ?></strong></td>
                    <td><span class="badge <?php echo $roleInfo['class']; ?>"><?php echo htmlspecialchars($roleInfo['label']); ?></span></td>
                    <td>
                        <?php if ($isActive): ?>
                        <span class="badge bg-success">Active</span>
                        <?php else: ?>
                        <span class="badge bg-secondary">Disabled</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo date('M j, Y', strtotime($a['created_at'])); ?></td>
                    <td>
                        <?php if (!empty($a['last_login_at'])): ?>
                        <small><?php echo date('M j H:i', strtotime($a['last_login_at'])); ?></small>
                        <?php if (!empty($a['last_login_ip'])): ?><br><small class="text-white-50"><?php echo htmlspecialchars($a['last_login_ip']); ?></small><?php endif; ?>
                        <?php else: ?>
                        <small class="text-white-50">never</small>
                        <?php endif; ?>
                    </td>
                    <td class="text-nowrap">
                        <a href="/admin/admin-user.php?id=<?php echo (int) $a['id']; ?>" class="btn btn-outline-info btn-sm" style="font-size:.7rem; padding:.15rem .4rem;" title="View Detail"><i class="fas fa-eye"></i></a>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <small class="text-white-50">Showing <?php echo $offset + 1; ?>–<?php echo min($offset + $perPage, $total); ?> of <?php echo $total; ?></small>
        <div class="d-flex gap-1">
            <?php if ($page > 1): ?>
            <a href="?q=<?php echo urlencode($search); ?>&page=<?php echo $page - 1; ?>" class="btn btn-sm btn-outline-secondary">&laquo;</a>
            <?php endif; ?>
            <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
            <a href="?q=<?php echo urlencode($search); ?>&page=<?php echo $p; ?>" class="btn btn-sm <?php echo $p === $page ? 'btn-primary' : 'btn-outline-secondary'; ?>"><?php echo $p; ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
            <a href="?q=<?php echo urlencode($search); ?>&page=<?php echo $page + 1; ?>" class="btn btn-sm btn-outline-secondary">&raquo;</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
ini_set('display_errors', '0');
require_once __DIR__ . '/_guard.php';
admin_require_login();
admin_require_perm('payments.view');
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/rate_limit.php';
require_once __DIR__ . '/../includes/support_credits.php';

$pdo = getDBConnection();
if (!$pdo) { echo 'DB connection failed.'; exit; }

$csrfToken = csrf_token();
$flashMsg = '';
$flashType = '';

// ── Handle POST actions ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        csrf_guard();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        rate_limit_guard($pdo, "admin_wp:{$ip}", 30, 300);

        $targetUserId = (int) ($_POST['target_user_id'] ?? 0);
        if ($targetUserId <= 0) throw new \Exception('Invalid user ID.');

        $now = gmdate('Y-m-d H:i:s');
        $adminLabel = 'admin_session';

        switch ($_POST['action']) {
            case 'grant_available':
                admin_require_perm('economy.adjust_kp');
                $amount = (int) ($_POST['amount'] ?? 0);
                if ($amount <= 0 || $amount > 1000000) throw new \Exception('Amount must be 1–1,000,000.');
                admin_check_economy_limits($pdo, $amount);
                $expiresAt = gmdate('Y-m-d H:i:s', strtotime('+12 months'));
                $pdo->beginTransaction();
                $pdo->prepare(
                    "INSERT INTO points_ledger (user_id, source_type, source_id, entry_type, status, points, available_at, expires_at, created_at)
                     VALUES (?, 'adjustment', 0, 'earn', 'available', ?, ?, ?, ?)"
                )->execute([$targetUserId, $amount, $now, $expiresAt, $now]);
                $pdo->commit();
                require_once __DIR__ . '/_audit.php';
                $reason = trim($_POST['reason'] ?? '') ?: null;
                admin_log_action('kp_grant_available', ['user_id' => $targetUserId, 'amount' => $amount, 'reason' => $reason]);
                $flashMsg = "Granted {$amount} KP (available now) to user #{$targetUserId}.";
                $flashType = 'success';
                break;

            case 'grant_pending':
                admin_require_perm('economy.adjust_kp');
                $amount = (int) ($_POST['amount'] ?? 0);
                if ($amount <= 0 || $amount > 1000000) throw new \Exception('Amount must be 1–1,000,000.');
                admin_check_economy_limits($pdo, $amount);
                $holdDays = compute_hold_business_days($pdo, $targetUserId);
                $availableAt = add_business_days(new DateTime('now', new DateTimeZone('UTC')), $holdDays);
                $expiresAt = (clone $availableAt)->modify('+12 months');
                $pdo->beginTransaction();
                $pdo->prepare(
                    "INSERT INTO points_ledger (user_id, source_type, source_id, entry_type, status, points, available_at, expires_at, created_at)
                     VALUES (?, 'adjustment', 0, 'earn', 'pending', ?, ?, ?, ?)"
                )->execute([$targetUserId, $amount, $availableAt->format('Y-m-d H:i:s'), $expiresAt->format('Y-m-d H:i:s'), $now]);
                $pdo->commit();
                require_once __DIR__ . '/_audit.php';
                $reason = trim($_POST['reason'] ?? '') ?: null;
                admin_log_action('kp_grant_pending', ['user_id' => $targetUserId, 'amount' => $amount, 'reason' => $reason]);
                $flashMsg = "Granted {$amount} KP (pending, available after {$holdDays} business days) to user #{$targetUserId}.";
                $flashType = 'success';
                break;

            case 'remove_points':
                admin_require_perm('economy.adjust_kp');
                $amount = (int) ($_POST['amount'] ?? 0);
                if ($amount <= 0 || $amount > 1000000) throw new \Exception('Amount must be 1–1,000,000.');
                admin_check_economy_limits($pdo, -$amount);
                $pdo->beginTransaction();
                $avail = get_available_points($pdo, $targetUserId);
                if ($avail < $amount) {
                    $pdo->rollBack();
                    throw new \Exception("Insufficient available KP. User has {$avail}, tried to remove {$amount}.");
                }
                $pdo->prepare(
                    "INSERT INTO points_ledger (user_id, source_type, source_id, entry_type, status, points, created_at)
                     VALUES (?, 'adjustment', 0, 'spend', 'spent', ?, ?)"
                )->execute([$targetUserId, -$amount, $now]);
                $pdo->commit();
                require_once __DIR__ . '/_audit.php';
                $reason = trim($_POST['reason'] ?? '') ?: null;
                admin_log_action('kp_remove_points', ['user_id' => $targetUserId, 'amount' => $amount, 'reason' => $reason]);
                $flashMsg = "Removed {$amount} KP from user #{$targetUserId}.";
                $flashType = 'success';
                break;

            case 'force_release':
                admin_require_perm('economy.adjust_kp');
                admin_check_economy_limits($pdo, 1);
                $mode = $_POST['release_mode'] ?? 'safe';
                $pdo->beginTransaction();
                if ($mode === 'override') {
                    $stmt = $pdo->prepare(
                        "UPDATE points_ledger SET status = 'available', available_at = ?
                         WHERE user_id = ? AND status = 'pending' AND entry_type = 'earn'"
                    );
                    $stmt->execute([$now, $targetUserId]);
                    $count = $stmt->rowCount();
                } else {
                    $count = release_available_points_if_due($pdo, $targetUserId);
                }
                $pdo->commit();
                require_once __DIR__ . '/_audit.php';
                $reason = trim($_POST['reason'] ?? '') ?: null;
                admin_log_action('kp_force_release', ['user_id' => $targetUserId, 'mode' => $mode, 'count' => $count, 'reason' => $reason]);
                $flashMsg = "Force release ({$mode}): {$count} entries moved to available for user #{$targetUserId}.";
                $flashType = 'success';
                break;

            case 'toggle_risk':
                $stmt = $pdo->prepare('SELECT risk_flag FROM users WHERE id = ?');
                $stmt->execute([$targetUserId]);
                $row = $stmt->fetch();
                if (!$row) throw new \Exception('User not found.');
                $newFlag = (int) $row['risk_flag'] === 1 ? 0 : 1;
                $pdo->prepare('UPDATE users SET risk_flag = ? WHERE id = ?')->execute([$newFlag, $targetUserId]);
                require_once __DIR__ . '/_audit.php';
                admin_log_action('kp_user_risk_flag_toggle', ['user_id' => $targetUserId, 'risk_flag' => $newFlag]);
                $flashMsg = "Risk flag for user #{$targetUserId} set to {$newFlag}.";
                $flashType = $newFlag ? 'warning' : 'success';
                break;

            case 'add_note':
                $note = trim($_POST['note'] ?? '');
                if ($note === '') throw new \Exception('Note cannot be empty.');
                $pdo->prepare(
                    'INSERT INTO admin_user_notes (user_id, admin_session, note, created_at) VALUES (?, ?, ?, ?)'
                )->execute([$targetUserId, $adminLabel, $note, $now]);
                $flashMsg = "Note added for user #{$targetUserId}.";
                $flashType = 'success';
                break;

            default:
                throw new \Exception('Unknown action.');
        }
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $flashMsg = $e->getMessage();
        $flashType = 'danger';
    }

    if (isset($_POST['target_user_id'])) {
        $redir = '/admin/knd-points.php?u=' . (int) $_POST['target_user_id'];
    } else {
        $redir = '/admin/knd-points.php';
    }
    $_SESSION['wi_flash'] = ['msg' => $flashMsg, 'type' => $flashType];
    header('Location: ' . $redir);
    exit;
}

if (!empty($_SESSION['wi_flash'])) {
    $flashMsg  = $_SESSION['wi_flash']['msg'];
    $flashType = $_SESSION['wi_flash']['type'];
    unset($_SESSION['wi_flash']);
}

// ── Search user ──
$searchQuery = trim($_GET['q'] ?? '');
$selectedUserId = (int) ($_GET['u'] ?? 0);
$userData = null;
$userBalance = null;
$ledgerRows = [];
$userNotes = [];

if ($searchQuery !== '' && $selectedUserId === 0) {
    if (ctype_digit($searchQuery)) {
        $stmt = $pdo->prepare('SELECT id, username, email, risk_flag, created_at FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([(int) $searchQuery]);
    } else {
        $stmt = $pdo->prepare('SELECT id, username, email, risk_flag, created_at FROM users WHERE username LIKE ? LIMIT 20');
        $stmt->execute(['%' . $searchQuery . '%']);
    }
    $searchResults = $stmt->fetchAll();
    if (count($searchResults) === 1) {
        $selectedUserId = (int) $searchResults[0]['id'];
    }
}

if ($selectedUserId > 0) {
    $stmt = $pdo->prepare('SELECT id, username, email, email_verified, risk_flag, created_at FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$selectedUserId]);
    $userData = $stmt->fetch();

    if ($userData) {
        release_available_points_if_due($pdo, $selectedUserId);
        expire_points_if_due($pdo, $selectedUserId);
        $userBalance = get_points_balance($pdo, $selectedUserId);
        $userBalance['available_now'] = get_available_points($pdo, $selectedUserId);

        $statusFilter = $_GET['status'] ?? '';
        $sourceFilter = $_GET['source'] ?? '';
        $ledgerSql = "SELECT * FROM points_ledger WHERE user_id = ?";
        $ledgerParams = [$selectedUserId];
        if ($statusFilter !== '') {
            $ledgerSql .= " AND status = ?";
            $ledgerParams[] = $statusFilter;
        }
        if ($sourceFilter !== '') {
            $ledgerSql .= " AND source_type = ?";
            $ledgerParams[] = $sourceFilter;
        }
        $ledgerSql .= " ORDER BY id DESC LIMIT 100";
        $stmt = $pdo->prepare($ledgerSql);
        $stmt->execute($ledgerParams);
        $ledgerRows = $stmt->fetchAll();

        try {
            $stmt = $pdo->prepare('SELECT * FROM admin_user_notes WHERE user_id = ? ORDER BY created_at DESC LIMIT 20');
            $stmt->execute([$selectedUserId]);
            $userNotes = $stmt->fetchAll();
        } catch (\Throwable $e) {
            $userNotes = [];
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
echo generateHeader('KND Points Wallet Inspector', 'Admin wallet inspector');
echo generateAdminBar();
?>
<style>
.wi-dash { --cyan: #00d4ff; min-height:100vh; background:#0a0a0f; color:#e8ecf0; padding-top:100px; padding-bottom:60px; }
.wi-card { background:rgba(12,15,22,.85); border:1px solid rgba(255,255,255,.08); border-radius:12px; padding:1.25rem; }
.wi-kpi { font-size:1.8rem; font-weight:700; color:var(--cyan); line-height:1.1; font-family:'Orbitron',monospace; }
.wi-kpi-sm { font-size:.75rem; text-transform:uppercase; letter-spacing:.8px; color:rgba(255,255,255,.5); margin-top:.25rem; }
.wi-topbar { display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem; }
.wi-topbar h1 { font-size:1.5rem; font-weight:700; margin:0; }
.wi-actions .btn { font-size:.8rem; }
.wi-tbl { font-size:.78rem; }
.wi-tbl th { color:rgba(255,255,255,.5); font-weight:600; text-transform:uppercase; letter-spacing:.5px; font-size:.7rem; }
.wi-risk-on { color:#f87171; font-weight:700; }
.wi-risk-off { color:#4ade80; }
</style>

<div class="wi-dash">
<div class="container">

    <div class="wi-topbar">
        <h1><i class="fas fa-wallet me-2" style="color:var(--cyan)"></i>KND Points Wallet Inspector</h1>
        <div class="d-flex gap-2">
            <a href="/admin/" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Dashboard</a>
            <a href="/admin/knd-points.php?logout=1" class="btn btn-outline-light btn-sm">Logout</a>
        </div>
    </div>

    <?php if ($flashMsg): ?>
    <div class="alert alert-<?php echo $flashType; ?> alert-dismissible fade show"><?php echo htmlspecialchars($flashMsg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Search -->
    <div class="wi-card mb-4">
        <form method="get" class="d-flex gap-2">
            <input type="text" name="q" class="form-control form-control-sm" placeholder="Username or User ID" value="<?php echo htmlspecialchars($searchQuery); ?>" style="max-width:300px;">
            <button class="btn btn-sm btn-primary"><i class="fas fa-search me-1"></i>Search</button>
            <?php if ($selectedUserId > 0): ?>
            <a href="/admin/knd-points.php" class="btn btn-sm btn-outline-secondary">Clear</a>
            <?php endif; ?>
        </form>

        <?php if (!empty($searchResults) && count($searchResults) > 1): ?>
        <div class="mt-2">
            <small class="text-white-50"><?php echo count($searchResults); ?> results:</small>
            <div class="d-flex flex-wrap gap-2 mt-1">
                <?php foreach ($searchResults as $sr): ?>
                <a href="/admin/knd-points.php?u=<?php echo $sr['id']; ?>" class="btn btn-outline-info btn-sm">
                    #<?php echo $sr['id']; ?> <?php echo htmlspecialchars($sr['username']); ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php if (!$userData && $selectedUserId === 0 && empty($searchResults)): ?>
    <div class="wi-card text-center text-white-50 py-5">
        <i class="fas fa-search fa-2x mb-3" style="opacity:.3"></i>
        <p class="mb-0">Search a user to inspect their wallet.</p>
    </div>
    <?php elseif ($selectedUserId > 0 && !$userData): ?>
    <div class="alert alert-warning">User #<?php echo $selectedUserId; ?> not found.</div>
    <?php elseif ($userData): ?>

    <!-- User Header -->
    <div class="wi-card mb-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4 class="mb-0" style="color:var(--cyan);">#<?php echo $userData['id']; ?> — <?php echo htmlspecialchars($userData['username']); ?></h4>
                <small class="text-white-50">
                    <?php echo htmlspecialchars($userData['email'] ?? '—'); ?>
                    <?php if (!empty($userData['email_verified'])): ?><span class="badge bg-success ms-1">verified</span><?php else: ?><span class="badge bg-secondary ms-1">unverified</span><?php endif; ?>
                    &middot; Joined: <?php echo date('M j, Y', strtotime($userData['created_at'])); ?>
                </small>
            </div>
            <div>
                <?php if ((int) $userData['risk_flag'] === 1): ?>
                <span class="badge bg-danger"><i class="fas fa-exclamation-triangle me-1"></i>RISK FLAGGED</span>
                <?php else: ?>
                <span class="badge bg-success"><i class="fas fa-check me-1"></i>CLEAN</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Balance KPIs -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="wi-card text-center">
                <div class="wi-kpi"><?php echo number_format($userBalance['available_now']); ?></div>
                <div class="wi-kpi-sm">Available</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="wi-card text-center">
                <div class="wi-kpi" style="color:#facc15;"><?php echo number_format($userBalance['pending']); ?></div>
                <div class="wi-kpi-sm">Pending</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="wi-card text-center">
                <div class="wi-kpi" style="color:#f87171;"><?php echo number_format($userBalance['spent_total']); ?></div>
                <div class="wi-kpi-sm">Spent</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="wi-card text-center">
                <div class="wi-kpi" style="color:#fb923c;"><?php echo count($userBalance['expiring_soon']); ?></div>
                <div class="wi-kpi-sm">Expiring Soon</div>
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div class="wi-card mb-4">
        <h5 class="mb-3" style="color:var(--cyan);"><i class="fas fa-tools me-2"></i>Actions</h5>
        <div class="row g-3">
            <!-- Grant Available -->
            <div class="col-md-4">
                <form method="post" class="p-2 rounded" style="border:1px solid rgba(255,255,255,.06);">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="target_user_id" value="<?php echo $userData['id']; ?>">
                    <input type="hidden" name="action" value="grant_available">
                    <label class="form-label small text-white-50">Grant Available (now)</label>
                    <div class="input-group input-group-sm mb-2">
                        <input type="number" name="amount" class="form-control" min="1" max="1000000" required placeholder="KP">
                        <button class="btn btn-success btn-sm" onclick="return confirm('Grant available KP?')"><i class="fas fa-plus me-1"></i>Grant</button>
                    </div>
                </form>
            </div>
            <!-- Grant Pending -->
            <div class="col-md-4">
                <form method="post" class="p-2 rounded" style="border:1px solid rgba(255,255,255,.06);">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="target_user_id" value="<?php echo $userData['id']; ?>">
                    <input type="hidden" name="action" value="grant_pending">
                    <label class="form-label small text-white-50">Grant Pending (with hold)</label>
                    <div class="input-group input-group-sm mb-2">
                        <input type="number" name="amount" class="form-control" min="1" max="1000000" required placeholder="KP">
                        <button class="btn btn-warning btn-sm" onclick="return confirm('Grant pending KP (hold applies)?')"><i class="fas fa-clock me-1"></i>Grant</button>
                    </div>
                </form>
            </div>
            <!-- Remove Points -->
            <div class="col-md-4">
                <form method="post" class="p-2 rounded" style="border:1px solid rgba(255,255,255,.06);">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="target_user_id" value="<?php echo $userData['id']; ?>">
                    <input type="hidden" name="action" value="remove_points">
                    <label class="form-label small text-white-50">Remove Available KP</label>
                    <div class="input-group input-group-sm mb-2">
                        <input type="number" name="amount" class="form-control" min="1" max="1000000" required placeholder="KP">
                        <button class="btn btn-danger btn-sm" onclick="return confirm('Remove KP from available balance?')"><i class="fas fa-minus me-1"></i>Remove</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="row g-3 mt-1">
            <!-- Force Release -->
            <div class="col-md-4">
                <form method="post" class="p-2 rounded" style="border:1px solid rgba(255,255,255,.06);">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="target_user_id" value="<?php echo $userData['id']; ?>">
                    <input type="hidden" name="action" value="force_release">
                    <label class="form-label small text-white-50">Force Release Pending</label>
                    <select name="release_mode" class="form-select form-select-sm mb-2">
                        <option value="safe">Safe (only if available_at passed)</option>
                        <option value="override">Override (release ALL pending now)</option>
                    </select>
                    <button class="btn btn-info btn-sm w-100" onclick="return confirm('Force release pending entries?')"><i class="fas fa-unlock me-1"></i>Release</button>
                </form>
            </div>
            <!-- Toggle Risk -->
            <div class="col-md-4">
                <form method="post" class="p-2 rounded" style="border:1px solid rgba(255,255,255,.06);">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="target_user_id" value="<?php echo $userData['id']; ?>">
                    <input type="hidden" name="action" value="toggle_risk">
                    <label class="form-label small text-white-50">Risk Flag</label>
                    <p class="mb-2 small">
                        Current: <?php echo (int) $userData['risk_flag'] === 1
                            ? '<span class="wi-risk-on">ON (flagged)</span>'
                            : '<span class="wi-risk-off">OFF (clean)</span>'; ?>
                    </p>
                    <button class="btn btn-outline-warning btn-sm w-100" onclick="return confirm('Toggle risk flag?')"><i class="fas fa-flag me-1"></i>Toggle</button>
                </form>
            </div>
            <!-- Add Note -->
            <div class="col-md-4">
                <form method="post" class="p-2 rounded" style="border:1px solid rgba(255,255,255,.06);">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="target_user_id" value="<?php echo $userData['id']; ?>">
                    <input type="hidden" name="action" value="add_note">
                    <label class="form-label small text-white-50">Admin Note</label>
                    <textarea name="note" class="form-control form-control-sm mb-2" rows="2" required placeholder="Write a note…"></textarea>
                    <button class="btn btn-outline-light btn-sm w-100"><i class="fas fa-sticky-note me-1"></i>Save Note</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Ledger -->
    <div class="wi-card mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="mb-0" style="color:var(--cyan);"><i class="fas fa-list me-2"></i>Points Ledger (last 100)</h5>
            <form method="get" class="d-flex gap-2 align-items-center">
                <input type="hidden" name="u" value="<?php echo $userData['id']; ?>">
                <select name="status" class="form-select form-select-sm" style="width:auto;">
                    <option value="">All Status</option>
                    <?php foreach (['pending','available','locked','spent','expired'] as $s): ?>
                    <option value="<?php echo $s; ?>" <?php echo ($statusFilter ?? '') === $s ? 'selected' : ''; ?>><?php echo ucfirst($s); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="source" class="form-select form-select-sm" style="width:auto;">
                    <option value="">All Source</option>
                    <?php foreach (['support_payment','redemption','adjustment'] as $sr): ?>
                    <option value="<?php echo $sr; ?>" <?php echo ($sourceFilter ?? '') === $sr ? 'selected' : ''; ?>><?php echo $sr; ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-sm btn-outline-info"><i class="fas fa-filter"></i></button>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-dark wi-tbl mb-0" style="--bs-table-bg:transparent;">
                <thead><tr>
                    <th>#</th><th>Date</th><th>Type</th><th>Amount</th><th>Status</th><th>Source</th><th>Ref</th><th>Available At</th><th>Expires</th>
                </tr></thead>
                <tbody>
                <?php foreach ($ledgerRows as $lr): ?>
                <tr>
                    <td><?php echo $lr['id']; ?></td>
                    <td><?php echo date('M j H:i', strtotime($lr['created_at'])); ?></td>
                    <td><?php echo $lr['entry_type']; ?></td>
                    <td style="font-weight:700; color:<?php echo (int) $lr['points'] >= 0 ? '#4ade80' : '#f87171'; ?>;">
                        <?php echo (int) $lr['points'] >= 0 ? '+' : ''; ?><?php echo number_format((int) $lr['points']); ?>
                    </td>
                    <td><span class="badge bg-<?php
                        echo match($lr['status']) {
                            'available' => 'success',
                            'pending' => 'warning',
                            'spent' => 'secondary',
                            'expired' => 'dark',
                            'locked' => 'danger',
                            default => 'secondary',
                        };
                    ?>"><?php echo $lr['status']; ?></span></td>
                    <td><?php echo htmlspecialchars($lr['source_type']); ?></td>
                    <td><?php echo $lr['source_id'] ?: '—'; ?></td>
                    <td><?php echo $lr['available_at'] ? date('M j', strtotime($lr['available_at'])) : '—'; ?></td>
                    <td><?php echo $lr['expires_at'] ? date('M j, Y', strtotime($lr['expires_at'])) : '—'; ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($ledgerRows)): ?>
                <tr><td colspan="9" class="text-center text-white-50">No entries found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Admin Notes -->
    <?php if (!empty($userNotes)): ?>
    <div class="wi-card mb-4">
        <h5 class="mb-3" style="color:var(--cyan);"><i class="fas fa-sticky-note me-2"></i>Admin Notes</h5>
        <?php foreach ($userNotes as $n): ?>
        <div class="mb-2 p-2 rounded" style="border:1px solid rgba(255,255,255,.06);">
            <small class="text-white-50"><?php echo date('M j, Y H:i', strtotime($n['created_at'])); ?> — <?php echo htmlspecialchars($n['admin_session'] ?? 'admin'); ?></small>
            <p class="mb-0 small"><?php echo nl2br(htmlspecialchars($n['note'])); ?></p>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php endif; // end userData ?>

</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

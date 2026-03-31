<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/config.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Robots-Tag: noindex, nofollow');

if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 86400, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    header('Location: /');
    exit;
}

$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_user'], $_POST['admin_pass'])) {
    $userInput = trim($_POST['admin_user']);
    $passInput = $_POST['admin_pass'];
    require_once __DIR__ . '/_audit.php';

    try {
        $pdo = getDBConnection();
        if (!$pdo) throw new \Exception('Database unavailable.');
        $tables = $pdo->query("SHOW TABLES LIKE 'admin_users'")->rowCount();
        if ($tables === 0) throw new \Exception('Admin users table not found. Run sql/admin_users.sql and seed an owner.');

        $stmt = $pdo->prepare('SELECT id, username, password_hash, role FROM admin_users WHERE username = ? AND active = 1 LIMIT 1');
        $stmt->execute([$userInput]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && password_verify($passInput, $row['password_hash'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = (int) $row['id'];
            $_SESSION['admin_role'] = $row['role'];
            $_SESSION['admin_username'] = $row['username'];

            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $pdo->prepare('UPDATE admin_users SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?')->execute([$ip, $row['id']]);
            admin_log_action('admin_login_success', ['admin_id' => (int) $row['id'], 'role' => $row['role']]);
            header('Location: /admin/');
            exit;
        }
    } catch (\Throwable $e) {
        $loginError = $e->getMessage();
    }
    admin_log_action('admin_login_failed', ['username' => $userInput]);
    if ($loginError === '') $loginError = 'Invalid credentials.';
}

if (empty($_SESSION['admin_logged_in'])) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><title>KND Admin</title><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"></head>';
    echo '<body class="bg-dark text-light min-vh-100 d-flex align-items-center"><div class="container" style="max-width:400px;">';
    echo '<h2 class="mb-4">KND Admin</h2>';
    if (!empty($loginError)) echo '<div class="alert alert-danger">' . htmlspecialchars($loginError) . '</div>';
    echo '<form method="post"><div class="mb-3"><label class="form-label">User</label><input type="text" name="admin_user" class="form-control bg-dark text-light border-secondary" required></div>';
    echo '<div class="mb-3"><label class="form-label">Password</label><input type="password" name="admin_pass" class="form-control bg-dark text-light border-secondary" required></div>';
    echo '<button type="submit" class="btn btn-primary w-100">Login</button></form></div></body></html>';
    exit;
}

require_once __DIR__ . '/_guard.php';
admin_require_login();

require_once __DIR__ . '/../includes/storage.php';
ensure_storage_ready();

$ordersFile = storage_path('orders.json');
$bankFile   = storage_path('bank_transfer_requests.json');
$otherFile  = storage_path('other_payment_requests.json');

clearstatcache(true);
$allOrders     = read_json_array($ordersFile);
$bankRequests  = read_json_array($bankFile);
$otherRequests = read_json_array($otherFile);

$totalOrders = count($allOrders) + count($bankRequests) + count($otherRequests);

$pendingCount = 0;
$paidCount = 0;
$lastTimestamp = null;

foreach (array_merge($allOrders, $bankRequests, $otherRequests) as $r) {
    $st = $r['status'] ?? 'pending';
    if ($st === 'pending') $pendingCount++;
    if (in_array($st, ['paid', 'completed', 'delivered'])) $paidCount++;
    $ts = $r['created_at'] ?? null;
    if ($ts && ($lastTimestamp === null || strtotime($ts) > strtotime($lastTimestamp))) {
        $lastTimestamp = $ts;
    }
}

$storagePath = storage_path();
$storageWritable = is_writable($storagePath);
$logFile = storage_path('logs/payments.log');
$lastLogTime = null;
if (file_exists($logFile) && filesize($logFile) > 0) {
    $fh = @fopen($logFile, 'r');
    if ($fh) {
        fseek($fh, max(0, filesize($logFile) - 512));
        $tail = fread($fh, 512);
        fclose($fh);
        if (preg_match_all('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $tail, $m)) {
            $lastLogTime = end($m[1]);
        }
    }
}

// Support Credits counters
$scPendingPayments = 0;
$scRequestedRedemptions = 0;
$totalRegisteredUsers = 0;
try {
    $pdo = getDBConnection();
    if ($pdo) {
        $scPendingPayments = (int) $pdo->query("SELECT COUNT(*) FROM support_payments WHERE status='pending'")->fetchColumn();
        $scRequestedRedemptions = (int) $pdo->query("SELECT COUNT(*) FROM reward_redemptions WHERE status='requested'")->fetchColumn();
        $totalRegisteredUsers = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    }
} catch (\Throwable $e) {
    error_log('admin dashboard SC counters: ' . $e->getMessage());
}

$navCards = [
    ['title' => 'Dashboard', 'desc' => 'Analytics, KP metrics, Top XP and alerts', 'href' => '/admin/dashboard.php', 'icon' => 'fa-chart-line', 'perm' => 'dashboard.view', 'badge' => null],
    ['title' => 'User Management', 'desc' => 'List, search, filter and inspect all users', 'href' => '/admin/users.php', 'icon' => 'fa-users', 'perm' => 'users.view', 'badge' => $totalRegisteredUsers],
    ['title' => 'Order Management', 'desc' => 'View, filter and update all orders', 'href' => '/admin/orders.php', 'icon' => 'fa-clipboard-list', 'perm' => 'orders.view', 'badge' => null],
    ['title' => 'KND Points', 'desc' => 'Review pending point purchases + redemptions', 'href' => '/admin/support-credits.php', 'icon' => 'fa-coins', 'perm' => 'payments.view', 'badge' => $scPendingPayments],
    ['title' => 'Wallet Inspector', 'desc' => 'Audit and manage user KND Points balances', 'href' => '/admin/knd-points.php', 'icon' => 'fa-wallet', 'perm' => 'payments.view', 'badge' => null],
    ['title' => 'Rewards Catalog', 'desc' => 'Manage rewards catalog and stock', 'href' => '/admin/rewards.php', 'icon' => 'fa-gift', 'perm' => 'rewards.edit', 'badge' => $scRequestedRedemptions],
    ['title' => 'Leaderboard', 'desc' => 'Reset season, stats or all XP', 'href' => '/admin/leaderboard.php', 'icon' => 'fa-trophy', 'perm' => 'leaderboard.view', 'badge' => null],
    ['title' => 'Avatar Stats', 'desc' => 'Edit mind, focus, speed, luck per avatar', 'href' => '/admin/avatar-balance.php', 'icon' => 'fa-chart-bar', 'perm' => 'system.storage_diag', 'badge' => null],
    ['title' => 'Audit Logs', 'desc' => 'View admin audit logs with filters', 'href' => '/admin/logs.php', 'icon' => 'fa-list-alt', 'perm' => 'logs.view', 'badge' => null],
    ['title' => 'Admin Users', 'desc' => 'Manage admin accounts, roles and permissions (owner only)', 'href' => '/admin/admin-users.php', 'icon' => 'fa-user-shield', 'perm' => 'admin_users.view', 'badge' => null],
    ['title' => 'Create Test Order', 'desc' => 'Generate a synthetic order for testing', 'href' => '/admin/test_order.php', 'icon' => 'fa-flask', 'perm' => 'system.create_test_order', 'badge' => null],
    ['title' => 'Storage Diagnostics', 'desc' => 'Inspect JSON files, permissions and sizes', 'href' => '/admin/debug_storage.php', 'icon' => 'fa-database', 'perm' => 'system.storage_diag', 'badge' => null, 'show' => file_exists(__DIR__ . '/debug_storage.php')],
    ['title' => 'Path Diagnostics', 'desc' => 'Verify file paths and config', 'href' => '/admin/diag_paths.php', 'icon' => 'fa-folder-open', 'perm' => 'system.storage_diag', 'badge' => null],
    ['title' => 'Labs Settings', 'desc' => 'ComfyUI provider (Local / RunPod / Auto)', 'href' => '/admin/labs_settings.php', 'icon' => 'fa-microscope', 'perm' => 'system.storage_diag', 'badge' => null],
    ['title' => 'Purge Cache', 'desc' => 'Clear OPcache and LiteSpeed cache', 'href' => '/admin/purge_cache.php', 'icon' => 'fa-sync-alt', 'perm' => 'system.purge_cache', 'badge' => null],
    ['title' => 'Email Test', 'desc' => 'Send a test confirmation email', 'href' => '/admin/email-test.php', 'icon' => 'fa-envelope', 'perm' => 'system.storage_diag', 'badge' => null, 'show' => file_exists(__DIR__ . '/email-test.php')],
];

require_once __DIR__ . '/../includes/header.php';
echo generateHeader('KND Admin', 'Admin dashboard');
echo generateAdminBar();
?>
<style>
.admin-dash { --cyan: var(--c, #00e8ff); min-height: 100vh; background: var(--void, #010508); color: #e8ecf0; padding-top: 24px; }
.kpi-strip { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
.kpi-card { background: rgba(12,15,22,.85); border: 1px solid rgba(255,255,255,.08); border-radius: 12px; padding: 1.25rem 1.5rem; }
.kpi-value { font-size: 2rem; font-weight: 700; color: var(--cyan); line-height: 1.1; }
.kpi-label { font-size: .8rem; text-transform: uppercase; letter-spacing: .8px; color: rgba(255,255,255,.5); margin-top: .35rem; }
.nav-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.25rem; margin-bottom: 2rem; }
.nav-card { display: block; background: rgba(12,15,22,.85); border: 1px solid rgba(255,255,255,.08); border-radius: 14px; padding: 1.75rem; text-decoration: none !important; color: #e8ecf0 !important; transition: border-color .2s, box-shadow .2s, transform .2s; }
.nav-card:hover { border-color: rgba(0,212,255,.35); box-shadow: 0 0 24px rgba(0,212,255,.12); transform: translateY(-3px); }
.nav-card-locked { opacity: 0.6; cursor: not-allowed; pointer-events: none; }
.nav-card .card-icon { font-size: 1.6rem; color: var(--cyan); margin-bottom: .75rem; }
.nav-card h3 { font-size: 1.1rem; font-weight: 600; margin-bottom: .4rem; }
.nav-card p { font-size: .85rem; color: rgba(255,255,255,.5); margin: 0; }
.sys-box { background: rgba(12,15,22,.85); border: 1px solid rgba(255,255,255,.08); border-radius: 12px; padding: 1.25rem 1.5rem; font-size: .85rem; }
.sys-box .label { color: rgba(255,255,255,.5); }
.sys-ok { color: #4ade80; }
.sys-fail { color: #f87171; }
.admin-topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem; }
.admin-topbar h1 { font-size: 1.75rem; font-weight: 700; margin: 0; }
</style>

<div class="admin-dash">
<div class="container">
    <div class="admin-topbar">
        <h1><i class="fas fa-terminal me-2" style="color:var(--cyan)"></i>KND Admin</h1>
    </div>

    <div class="kpi-strip">
        <div class="kpi-card">
            <div class="kpi-value"><?php echo $totalOrders; ?></div>
            <div class="kpi-label">Total Orders</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-value"><?php echo $pendingCount; ?></div>
            <div class="kpi-label">Pending</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-value"><?php echo $paidCount; ?></div>
            <div class="kpi-label">Paid / Completed</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-value" style="font-size:1.1rem;"><?php echo $lastTimestamp ? date('M j, H:i', strtotime($lastTimestamp)) : '—'; ?></div>
            <div class="kpi-label">Last Order</div>
        </div>
    </div>

    <div class="nav-cards">
        <?php foreach ($navCards as $card):
            if (isset($card['show']) && !$card['show']) continue;
            $canAccess = admin_has_perm($card['perm']);
            if (!$canAccess) {
                ?><div class="nav-card nav-card-locked" title="Insufficient permissions">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="card-icon"><i class="fas <?php echo $card['icon']; ?>"></i></div>
                        <span class="badge bg-secondary">Locked</span>
                    </div>
                    <h3><?php echo htmlspecialchars($card['title']); ?></h3>
                    <p class="text-muted">Insufficient permissions</p>
                </div><?php
                continue;
            }
        ?>
        <a href="<?php echo $card['href']; ?>" class="nav-card">
            <div class="d-flex justify-content-between align-items-start">
                <div class="card-icon"><i class="fas <?php echo $card['icon']; ?>"></i></div>
                <?php if (!empty($card['badge'])): ?>
                <span style="background:rgba(0,212,255,.15);color:var(--cyan);border:1px solid rgba(0,212,255,.3);border-radius:20px;padding:2px 10px;font-size:.75rem;font-weight:700;"><?php echo $card['badge']; ?></span>
                <?php endif; ?>
            </div>
            <h3><?php echo htmlspecialchars($card['title']); ?></h3>
            <p><?php echo htmlspecialchars($card['desc']); ?></p>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="sys-box">
        <strong style="color:var(--cyan)">System Status</strong>
        <div class="d-flex flex-wrap gap-4 mt-2">
            <div>
                <span class="label">Storage writable:</span>
                <span class="<?php echo $storageWritable ? 'sys-ok' : 'sys-fail'; ?>"><?php echo $storageWritable ? 'Yes' : 'No'; ?></span>
            </div>
            <div>
                <span class="label">Last log entry:</span>
                <span><?php echo $lastLogTime ?? '—'; ?></span>
            </div>
        </div>
    </div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
require_once __DIR__ . '/_guard.php';
admin_require_login();
admin_require_perm('system.storage_diag');

header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/../includes/config.php';

$secretsPath = __DIR__ . '/../config/admin_secrets.local.php';
$secretsExists = file_exists($secretsPath);
$secretsReadable = $secretsExists && is_readable($secretsPath);

// Admin Auth (DB)
$dbOk = false;
$adminUsersExists = false;
$totalAdmins = 0;
$activeAdmins = 0;
$ownerAdmins = 0;
$credsValid = false;
try {
    $pdo = getDBConnection();
    $dbOk = ($pdo !== null);
    if ($pdo) {
        $adminUsersExists = $pdo->query("SHOW TABLES LIKE 'admin_users'")->rowCount() > 0;
        if ($adminUsersExists) {
            $totalAdmins = (int) $pdo->query("SELECT COUNT(*) FROM admin_users")->fetchColumn();
            $activeAdmins = (int) $pdo->query("SELECT COUNT(*) FROM admin_users WHERE active = 1")->fetchColumn();
            $ownerAdmins = (int) $pdo->query("SELECT COUNT(*) FROM admin_users WHERE role = 'owner' AND active = 1")->fetchColumn();
            $credsValid = ($activeAdmins >= 1 && $ownerAdmins >= 1) || ($activeAdmins >= 1);
        }
    }
} catch (\Throwable $e) {
    $dbError = $e->getMessage();
}

$projectRoot = dirname(__DIR__);

$expectedFiles = [
    'admin/orders.php'             => __DIR__ . '/orders.php',
    'admin/test_order.php'         => __DIR__ . '/test_order.php',
    'admin/debug_storage.php'      => __DIR__ . '/debug_storage.php',
    'includes/storage.php'         => $projectRoot . '/includes/storage.php',
    'api/other/create_request.php' => $projectRoot . '/api/other/create_request.php',
    'config/admin_secrets.local.php' => $secretsPath,
];
?>
<!DOCTYPE html>
<html>
<head><title>Path Diagnostics — KND Admin</title>
<style>
    body { font-family: 'Courier New', monospace; background: #0a0a0a; color: #e0e0e0; padding: 2rem; }
    h1 { color: #00d4ff; margin-bottom: 1.5rem; }
    .section { background: #111; border: 1px solid #222; padding: 1rem; margin-bottom: 1rem; border-radius: 6px; }
    .label { color: #888; }
    .ok { color: #4ade80; }
    .warn { color: #facc15; }
    .fail { color: #f87171; }
    table { width: 100%; border-collapse: collapse; }
    td, th { text-align: left; padding: .4rem .6rem; border-bottom: 1px solid #222; }
    th { color: #888; font-weight: normal; }
    a { color: #00d4ff; }
    code { background: #1a1a1a; padding: .15rem .4rem; border-radius: 3px; }
</style>
</head>
<body>
<h1>Path Diagnostics</h1>
<p><a href="/admin/orders.php">&larr; Admin Panel</a> &nbsp;|&nbsp; <a href="/admin/debug_storage.php">Debug Storage</a></p>

<div class="section">
    <h3>Core Paths</h3>
    <table>
        <tr><th>Key</th><th>Value</th></tr>
        <tr><td class="label">__DIR__ (admin/)</td><td><code><?php echo htmlspecialchars(__DIR__); ?></code></td></tr>
        <tr><td class="label">Project root</td><td><code><?php echo htmlspecialchars($projectRoot); ?></code></td></tr>
    </table>
</div>

<div class="section">
    <h3>Legacy (optional)</h3>
    <p class="label" style="margin-bottom: .5rem;">Legacy secrets file is not used for login anymore. Admin auth is DB-backed (admin_users).</p>
    <table>
        <tr><th>Key</th><th>Value</th></tr>
        <tr><td class="label">secretsPath</td><td><code><?php echo htmlspecialchars($secretsPath); ?></code></td></tr>
        <tr>
            <td class="label">Secrets file exists</td>
            <td class="<?php echo $secretsExists ? 'ok' : 'warn'; ?>"><?php echo $secretsExists ? 'YES' : 'NO'; ?></td>
        </tr>
        <tr>
            <td class="label">Secrets file readable</td>
            <td class="<?php echo $secretsReadable ? 'ok' : 'warn'; ?>"><?php echo $secretsReadable ? 'YES' : 'NO'; ?></td>
        </tr>
    </table>
</div>

<div class="section">
    <h3>Admin Auth (DB)</h3>
    <table>
        <tr><th>Key</th><th>Value</th></tr>
        <tr>
            <td class="label">DB connection</td>
            <td class="<?php echo $dbOk ? 'ok' : 'fail'; ?>"><?php echo $dbOk ? 'OK' : (isset($dbError) ? htmlspecialchars($dbError) : 'FAIL'); ?></td>
        </tr>
        <tr>
            <td class="label">Table admin_users exists</td>
            <td class="<?php echo $adminUsersExists ? 'ok' : 'fail'; ?>"><?php echo $adminUsersExists ? 'YES' : 'NO'; ?></td>
        </tr>
        <?php if ($adminUsersExists): ?>
        <tr><td class="label">total_admins</td><td><?php echo (int) $totalAdmins; ?></td></tr>
        <tr><td class="label">active_admins</td><td><?php echo (int) $activeAdmins; ?></td></tr>
        <tr><td class="label">owner_admins (active)</td><td><?php echo (int) $ownerAdmins; ?></td></tr>
        <tr>
            <td class="label">Credentials valid</td>
            <td class="<?php echo $credsValid ? 'ok' : 'fail'; ?>"><?php echo $credsValid ? 'YES (active admins ≥ 1)' : 'NO (run php admin/seed_owner.php user pass)'; ?></td>
        </tr>
        <?php endif; ?>
    </table>
</div>

<?php if (!empty($_SESSION['admin_logged_in'])): ?>
<div class="section">
    <h3>Current session</h3>
    <table>
        <tr><th>Key</th><th>Value</th></tr>
        <tr><td class="label">admin_id</td><td><?php echo htmlspecialchars((string) ($_SESSION['admin_id'] ?? '—')); ?></td></tr>
        <tr><td class="label">admin_username</td><td><?php echo htmlspecialchars($_SESSION['admin_username'] ?? '—'); ?></td></tr>
        <tr><td class="label">admin_role</td><td><?php echo htmlspecialchars($_SESSION['admin_role'] ?? '—'); ?></td></tr>
        <tr>
            <td class="label">Role recognized by RBAC</td>
            <td class="<?php echo in_array($_SESSION['admin_role'] ?? '', ['owner','manager','support','viewer'], true) ? 'ok' : 'warn'; ?>">
                <?php echo in_array($_SESSION['admin_role'] ?? '', ['owner','manager','support','viewer'], true) ? 'YES' : 'NO (unknown role)'; ?>
            </td>
        </tr>
    </table>
</div>
<?php endif; ?>

<div class="section">
    <h3>Expected Files</h3>
    <table>
        <tr><th>Relative Path</th><th>Absolute Path</th><th>Exists</th></tr>
        <?php foreach ($expectedFiles as $rel => $abs): ?>
        <?php $exists = file_exists($abs); ?>
        <tr>
            <td><code><?php echo htmlspecialchars($rel); ?></code></td>
            <td><code><?php echo htmlspecialchars($abs); ?></code></td>
            <td class="<?php echo $exists ? 'ok' : 'fail'; ?>"><?php echo $exists ? 'YES' : 'MISSING'; ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>

<div class="section">
    <h3>PHP Environment</h3>
    <table>
        <tr><td class="label">PHP version</td><td><?php echo htmlspecialchars(PHP_VERSION); ?></td></tr>
        <tr><td class="label">SAPI</td><td><?php echo htmlspecialchars(php_sapi_name()); ?></td></tr>
        <tr><td class="label">DOCUMENT_ROOT</td><td><code><?php echo htmlspecialchars($_SERVER['DOCUMENT_ROOT'] ?? '-'); ?></code></td></tr>
        <tr><td class="label">SCRIPT_FILENAME</td><td><code><?php echo htmlspecialchars($_SERVER['SCRIPT_FILENAME'] ?? '-'); ?></code></td></tr>
    </table>
</div>
</body>
</html>

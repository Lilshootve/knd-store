<?php
/**
 * CLI helper to create the first owner account.
 * Run: php admin/seed_owner.php [username] [password]
 * Or from browser (one-time): /admin/seed_owner.php?user=X&pass=Y
 * REMOVE or protect this file in production after use.
 */
require_once __DIR__ . '/../includes/config.php';

$isCli = (php_sapi_name() === 'cli');
if ($isCli) {
    $username = $argv[1] ?? 'admin';
    $password = $argv[2] ?? '';
} else {
    $username = trim($_GET['user'] ?? $_POST['user'] ?? '');
    $password = trim($_GET['pass'] ?? $_POST['pass'] ?? '');
}

if ($password === '') {
    if ($isCli) {
        echo "Usage: php seed_owner.php [username] [password]\n";
        echo "Example: php seed_owner.php admin MySecurePass123\n";
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo '<h1>Seed Owner</h1><p>Usage: /admin/seed_owner.php?user=admin&pass=YourPassword</p>';
        echo '<p><strong>Delete this file after creating the owner.</strong></p>';
    }
    exit(1);
}

try {
    $pdo = getDBConnection();
    if (!$pdo) throw new \Exception('DB connection failed.');

    $tables = $pdo->query("SHOW TABLES LIKE 'admin_users'")->rowCount();
    if ($tables === 0) {
        $sql = file_get_contents(__DIR__ . '/../sql/admin_users.sql');
        $pdo->exec($sql);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO admin_users (username, password_hash, role, active) VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), role = VALUES(role)');
    $stmt->execute([$username, $hash, 'owner']);
    $msg = $stmt->rowCount() > 0 ? "Owner '$username' created/updated." : "Owner '$username' already exists (password updated).";
    if ($isCli) {
        echo $msg . "\n";
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo '<p>' . htmlspecialchars($msg) . '</p><p><a href="/admin/">Go to Admin</a></p>';
    }
} catch (\Throwable $e) {
    $msg = 'Error: ' . $e->getMessage();
    if ($isCli) echo $msg . "\n";
    else { header('Content-Type: text/html; charset=utf-8'); echo '<p>' . htmlspecialchars($msg) . '</p>'; }
    exit(1);
}

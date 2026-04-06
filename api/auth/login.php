<?php
require_once __DIR__ . '/../../config/bootstrap.php';
// KND Store - User login endpoint (Death Roll 1v1)

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/csrf.php';
require_once BASE_PATH . '/includes/rate_limit.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/deathroll_1v1.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('METHOD_NOT_ALLOWED', 'POST only.', 405);
}

csrf_guard();

$pdo = getDBConnection();
if (!$pdo) { json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500); }

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
rate_limit_guard($pdo, "login:{$ip}", 10, 300);

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    json_error('MISSING_FIELDS', 'Username and password are required.');
}

$stmt = $pdo->prepare('SELECT id, username, password_hash, email, email_verified FROM users WHERE username = ? LIMIT 1');
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || !password_verify($password, $user['password_hash'])) {
    json_error('INVALID_CREDENTIALS', 'Invalid username or password.');
}

auth_login((int) $user['id'], $user['username']);

$emailPending = !empty($user['email']) && isset($user['email_verified']) && (int) $user['email_verified'] === 0;

json_success([
    'user_id'       => (int) $user['id'],
    'username'      => $user['username'],
    'email_pending' => $emailPending,
]);

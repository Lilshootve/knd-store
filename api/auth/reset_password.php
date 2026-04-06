<?php
require_once __DIR__ . '/../../config/bootstrap.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
ini_set('display_errors', '0');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/csrf.php';
require_once BASE_PATH . '/includes/rate_limit.php';
require_once BASE_PATH . '/includes/json.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('METHOD_NOT_ALLOWED', 'POST only.', 405);
    }

    csrf_guard();

    $pdo = getDBConnection();
    if (!$pdo) json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500);

    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    rate_limit_guard($pdo, "reset_pw:{$ip}", 10, 300);

    $email    = strtolower(trim($_POST['email'] ?? ''));
    $code     = trim($_POST['code'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_error('INVALID_EMAIL', 'Please enter a valid email address.');
    }
    if ($code === '' || !preg_match('/^\d{6}$/', $code)) {
        json_error('INVALID_CODE', 'Please enter a valid 6-digit code.');
    }
    if (strlen($password) < 8) {
        json_error('WEAK_PASSWORD', 'Password must be at least 8 characters.');
    }

    $stmt = $pdo->prepare(
        'SELECT id, username, password_reset_code, password_reset_expires FROM users WHERE email = ? LIMIT 1'
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || $user['password_reset_code'] === null) {
        json_error('INVALID_REQUEST', 'No password reset request found. Please request a new code.');
    }

    $expiresTs = strtotime($user['password_reset_expires'] . ' UTC');
    if (time() > $expiresTs) {
        json_error('CODE_EXPIRED', 'Reset code has expired. Please request a new one.');
    }

    if (!hash_equals($user['password_reset_code'], $code)) {
        json_error('WRONG_CODE', 'Incorrect reset code.');
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $now = gmdate('Y-m-d H:i:s');

    $pdo->prepare(
        'UPDATE users SET password_hash = ?, password_reset_code = NULL, password_reset_expires = NULL, updated_at = ? WHERE id = ?'
    )->execute([$hash, $now, $user['id']]);

    json_success(['reset' => true]);
} catch (\Throwable $e) {
    error_log('reset_password error: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'An unexpected error occurred.', 500);
}

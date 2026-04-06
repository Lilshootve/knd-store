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
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/json.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('METHOD_NOT_ALLOWED', 'POST only.', 405);
    }

    csrf_guard();
    api_require_login();

    $pdo = getDBConnection();
    if (!$pdo) json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500);

    $userId = current_user_id();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    rate_limit_guard($pdo, "verify_email:{$userId}:{$ip}", 10, 300);

    $code = trim($_POST['code'] ?? '');
    if ($code === '' || !preg_match('/^\d{6}$/', $code)) {
        json_error('INVALID_CODE', 'Please enter a valid 6-digit code.');
    }

    $stmt = $pdo->prepare(
        'SELECT email_verified, email_verify_code, email_verify_expires FROM users WHERE id = ? LIMIT 1'
    );
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        json_error('USER_NOT_FOUND', 'User not found.', 404);
    }

    if ((int) $user['email_verified'] === 1) {
        json_success(['already_verified' => true]);
    }

    if ($user['email_verify_code'] === null) {
        json_error('NO_CODE', 'No verification code found. Please request a new one.');
    }

    $expiresTs = strtotime($user['email_verify_expires'] . ' UTC');
    if (time() > $expiresTs) {
        json_error('CODE_EXPIRED', 'Verification code has expired. Please request a new one.');
    }

    if (!hash_equals($user['email_verify_code'], $code)) {
        json_error('WRONG_CODE', 'Incorrect verification code.');
    }

    $now = gmdate('Y-m-d H:i:s');
    $pdo->prepare(
        'UPDATE users SET email_verified = 1, email_verify_code = NULL, email_verify_expires = NULL, updated_at = ? WHERE id = ?'
    )->execute([$now, $userId]);

    auth_refresh_session_tenant($pdo);

    json_success(['verified' => true]);
} catch (\Throwable $e) {
    error_log('verify_email error: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'An unexpected error occurred.', 500);
}

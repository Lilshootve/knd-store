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
require_once BASE_PATH . '/includes/mailer.php';

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
    rate_limit_guard($pdo, "resend_code:{$userId}:{$ip}", 3, 120);

    $stmt = $pdo->prepare('SELECT username, email, email_verified FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) json_error('USER_NOT_FOUND', 'User not found.', 404);

    if ((int) $user['email_verified'] === 1) {
        json_success(['already_verified' => true]);
    }

    if (!$user['email']) {
        json_error('NO_EMAIL', 'No email on file.');
    }

    $newCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $codeExpires = gmdate('Y-m-d H:i:s', strtotime('+15 minutes'));
    $now = gmdate('Y-m-d H:i:s');

    $pdo->prepare(
        'UPDATE users SET email_verify_code = ?, email_verify_expires = ?, updated_at = ? WHERE id = ?'
    )->execute([$newCode, $codeExpires, $now, $userId]);

    $subject = 'KND Store — New Verification Code';
    $body = "Hi {$user['username']},\n\nYour new verification code is: {$newCode}\n\nThis code expires in 15 minutes.\n\n— KND Store";
    $mailSent = knd_mail($user['email'], $subject, $body);

    json_success(['mail_sent' => $mailSent]);
} catch (\Throwable $e) {
    error_log('resend_code error: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'An unexpected error occurred.', 500);
}

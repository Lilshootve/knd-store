<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
ini_set('display_errors', '0');

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/rate_limit.php';
require_once __DIR__ . '/../../includes/json.php';
require_once __DIR__ . '/../../includes/mailer.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('METHOD_NOT_ALLOWED', 'POST only.', 405);
    }

    csrf_guard();

    $pdo = getDBConnection();
    if (!$pdo) json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500);

    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    rate_limit_guard($pdo, "forgot_user:{$ip}", 5, 300);

    $email = strtolower(trim($_POST['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_error('INVALID_EMAIL', 'Please enter a valid email address.');
    }

    $stmt = $pdo->prepare('SELECT username, email FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $subject = 'KND Store — Your Username';
        $body  = "Hi,\n\n";
        $body .= "Your username is: {$user['username']}\n\n";
        $body .= "If you didn't request this, ignore this email.\n\n";
        $body .= "— KND Store";
        $mailSent = knd_mail($user['email'], $subject, $body);

        if (!$mailSent) {
            error_log("Failed to send username reminder to {$email}");
        }
    }

    // Always return success to prevent email enumeration
    json_success(['sent' => true]);
} catch (\Throwable $e) {
    error_log('forgot_username error: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'An unexpected error occurred.', 500);
}

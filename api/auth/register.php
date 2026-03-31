<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
ini_set('display_errors', '0');

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/rate_limit.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/deathroll_1v1.php';
require_once __DIR__ . '/../../includes/mailer.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('METHOD_NOT_ALLOWED', 'POST only.', 405);
    }

    csrf_guard();

    $pdo = getDBConnection();
    if (!$pdo) json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500);

    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    rate_limit_guard($pdo, "register:{$ip}", 5, 300);

    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!validate_username($username)) {
        json_error('INVALID_USERNAME', 'Username must be 3-24 characters (letters, numbers, underscore).');
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_error('INVALID_EMAIL', 'A valid email address is required.');
    }
    if (strlen($email) > 255) {
        json_error('INVALID_EMAIL', 'Email is too long.');
    }

    if (strlen($password) < 8) {
        json_error('WEAK_PASSWORD', 'Password must be at least 8 characters.');
    }

    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        json_error('USERNAME_TAKEN', 'That username is already taken.');
    }

    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([strtolower($email)]);
    if ($stmt->fetch()) {
        json_error('EMAIL_TAKEN', 'That email is already registered.');
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $now = gmdate('Y-m-d H:i:s');
    $verifyCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $codeExpires = gmdate('Y-m-d H:i:s', strtotime('+15 minutes'));

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO users (username, email, password_hash, email_verified, email_verify_code, email_verify_expires, created_at, updated_at)
             VALUES (?, ?, ?, 0, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $username,
            strtolower($email),
            $hash,
            $verifyCode,
            $codeExpires,
            $now,
            $now,
        ]);
        $userId = (int) $pdo->lastInsertId();

        $expiresAt = gmdate('Y-m-d H:i:s', strtotime('+12 months'));
        $pdo->prepare(
            "INSERT INTO points_ledger (user_id, source_type, source_id, entry_type, status, points, available_at, expires_at, created_at)
             VALUES (?, 'adjustment', 0, 'earn', 'available', ?, ?, ?, ?)"
        )->execute([$userId, WELCOME_BONUS_KP, $now, $expiresAt, $now]);

        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    auth_login($userId, $username);

    $subject = 'KND Store — Email Verification Code';
    $body = "Hi {$username},\n\nYour verification code is: {$verifyCode}\n\nThis code expires in 15 minutes.\n\nIf you didn't register, ignore this email.\n\n— KND Store";
    $mailSent = knd_mail(strtolower($email), $subject, $body);

    if (!$mailSent) {
        error_log("Failed to send verification email to {$email} for user {$userId}");
    }

    json_success([
        'user_id'         => $userId,
        'username'        => $username,
        'email_pending'   => true,
        'mail_sent'       => $mailSent,
        'welcome_bonus_kp' => WELCOME_BONUS_KP,
    ]);
} catch (\Throwable $e) {
    error_log('register error: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'An unexpected error occurred.', 500);
}

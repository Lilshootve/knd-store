<?php
require_once __DIR__ . '/../../config/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
ini_set('display_errors', '0');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/csrf.php';
require_once BASE_PATH . '/includes/rate_limit.php';

define('DROP_MAX_PER_HOUR', 10);
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/json.php';
require_once BASE_PATH . '/includes/knd_drop.php';
require_once BASE_PATH . '/includes/knd_daily.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('METHOD_NOT_ALLOWED', 'POST only.', 405);
    }

    csrf_guard();
    api_require_verified_email();

    $pdo = getDBConnection();
    if (!$pdo) json_error('DB_FAIL', 'Database error.', 500);

    $userId = current_user_id();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // User rate limit: 10 drops/hour — return friendly error with resets_at
    $userStatus = rate_limit_status($pdo, "drop_user:{$userId}", DROP_MAX_PER_HOUR, 3600);
    if ($userStatus['remaining'] <= 0) {
        http_response_code(429);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'error' => [
                'code' => 'RATE_LIMITED',
                'message' => 'Hourly limit reached. Max ' . DROP_MAX_PER_HOUR . ' drops per hour.',
                'resets_at' => $userStatus['resets_at'],
                'max' => DROP_MAX_PER_HOUR,
            ],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    rate_limit_guard($pdo, "drop_user:{$userId}", DROP_MAX_PER_HOUR, 3600);
    rate_limit_guard($pdo, "drop_ip:{$ip}", 30, 3600);

    if (has_risk_flag($pdo, $userId)) {
        json_error('ACCOUNT_FLAGGED', 'Your account has been flagged.', 403);
    }

    $result = drop_play($pdo, $userId);

    if (isset($result['error'])) {
        json_error($result['error'], $result['message']);
    }

    try {
        mission_increment($pdo, $userId, 'make_drop_1');
    } catch (\Throwable $e) {
        error_log('mission_increment drop error: ' . $e->getMessage());
    }

    json_success($result);
} catch (\Throwable $e) {
    error_log('drop play error: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'An error occurred.', 500);
}

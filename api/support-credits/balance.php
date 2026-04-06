<?php
require_once __DIR__ . '/../../config/bootstrap.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
ini_set('display_errors', '0');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/json.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/support_credits.php';

try {
    api_require_login();

    $pdo = getDBConnection();
    if (!$pdo) json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500);

    $userId = current_user_id();

    release_available_points_if_due($pdo, $userId);
    expire_points_if_due($pdo, $userId);

    $balance = get_points_balance($pdo, $userId);

    json_success($balance);
} catch (\Throwable $e) {
    error_log('support-credits/balance error: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'An unexpected error occurred.', 500);
}

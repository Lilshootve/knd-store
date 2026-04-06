<?php
require_once __DIR__ . '/../../config/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/json.php';
require_once BASE_PATH . '/includes/mw_lobby.php';

try {
    api_require_login();
    $pdo = getDBConnection();
    if (!$pdo) {
        json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500);
    }
    $userId = (int) current_user_id();
    if ($userId < 1) {
        json_error('AUTH_REQUIRED', 'You must be logged in.', 401);
    }
    $payload = mw_build_lobby_data_payload($pdo, $userId);
    json_success($payload);
} catch (\Throwable $e) {
    error_log('mind-wars/get_lobby_data error: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'An unexpected error occurred.', 500);
}

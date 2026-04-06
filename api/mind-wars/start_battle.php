<?php
require_once __DIR__ . '/../../config/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/csrf.php';
require_once BASE_PATH . '/includes/json.php';
require_once BASE_PATH . '/includes/rate_limit.php';
require_once BASE_PATH . '/includes/mind_wars.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('METHOD_NOT_ALLOWED', 'POST only.', 405);
    }
    csrf_guard();
    api_require_login();

    $pdo = getDBConnection();
    if (!$pdo) {
        json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500);
    }

    $userId = (int) current_user_id();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    rate_limit_guard($pdo, "mw_start_user:{$userId}", 20, 60);
    rate_limit_guard($pdo, "mw_start_ip:{$ip}", 40, 60);

    $data = mw_start_pve_battle_for_user($pdo, $userId, $_POST);
    json_success($data);
} catch (InvalidArgumentException $e) {
    $msg = $e->getMessage();
    if (str_starts_with($msg, 'AVATAR_NOT_OWNED')) {
        json_error('AVATAR_NOT_OWNED', trim(substr($msg, strlen('AVATAR_NOT_OWNED:'))), 403);
    }
    if (str_starts_with($msg, 'INVALID_AVATAR')) {
        json_error('INVALID_AVATAR', trim(substr($msg, strlen('INVALID_AVATAR:'))), 400);
    }
    json_error('INVALID_REQUEST', $msg, 400);
} catch (\Throwable $e) {
    error_log('mind-wars/start_battle error: ' . $e->getMessage());
    if (stripos($e->getMessage(), 'AGUACATE') !== false) {
        json_error('AGUACATE', $e->getMessage(), 400);
    }
    json_error('INTERNAL_ERROR', $e->getMessage(), 500);
}

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
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/json.php';
require_once BASE_PATH . '/includes/knd_daily.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('METHOD_NOT_ALLOWED', 'POST only.', 405);
    }

    csrf_guard();

    if (empty($_SESSION['dr_user_id'])) {
        json_error('NOT_LOGGED_IN', 'Login required.', 401);
    }

    $pdo = getDBConnection();
    if (!$pdo) json_error('DB_FAIL', 'Database error.', 500);

    $userId = (int) $_SESSION['dr_user_id'];
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    rate_limit_guard($pdo, "mission_claim:{$userId}", 10, 60);

    $code = trim($_POST['code'] ?? '');
    if ($code === '' || !preg_match('/^[a-z0-9_]+$/', $code)) {
        json_error('INVALID_CODE', 'Invalid mission code.');
    }

    $result = mission_claim($pdo, $userId, $code);

    if (isset($result['error'])) {
        json_error($result['error'], $result['message']);
    }

    json_success($result);
} catch (\Throwable $e) {
    error_log('mission-claim error: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'An error occurred.', 500);
}

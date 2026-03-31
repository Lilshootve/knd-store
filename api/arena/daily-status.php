<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
ini_set('display_errors', '0');

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/json.php';
require_once __DIR__ . '/../../includes/knd_daily.php';

try {
    if (empty($_SESSION['dr_user_id'])) {
        json_error('NOT_LOGGED_IN', 'Login required.', 401);
    }

    $pdo = getDBConnection();
    if (!$pdo) json_error('DB_FAIL', 'Database error.', 500);

    $userId = (int) $_SESSION['dr_user_id'];
    $status = daily_get_status($pdo, $userId);

    json_success($status);
} catch (\Throwable $e) {
    error_log('daily-status error: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'An error occurred.', 500);
}

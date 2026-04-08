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
require_once BASE_PATH . '/includes/mind_wars_arena_bootstrap.php';

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
    rate_limit_guard($pdo, "mw_queue_enq_user:{$userId}", 30, 60);
    rate_limit_guard($pdo, "mw_queue_enq_ip:{$ip}", 60, 60);

    $avatarItemId = (int) ($_POST['avatar_item_id'] ?? 0);
    if ($avatarItemId < 1) {
        json_error('INVALID_AVATAR', 'Select an avatar to queue.');
    }

    $avatar = mw_validate_owned_avatar($pdo, $userId, $avatarItemId);
    if (!$avatar) {
        json_error('AVATAR_NOT_OWNED', 'You do not own this avatar.', 403);
    }

    $payload = mw_perform_ranked_queue_enqueue($pdo, $userId, $avatarItemId, $avatar);
    json_success($payload);
} catch (\Throwable $e) {
    error_log('mind-wars/queue_enqueue error: ' . $e->getMessage());
    if (stripos($e->getMessage(), 'AGUACATE') !== false) {
        json_error('AGUACATE', $e->getMessage(), 400);
    }
    json_error('INTERNAL_ERROR', 'An unexpected error occurred.', 500);
}


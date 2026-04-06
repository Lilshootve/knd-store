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
require_once BASE_PATH . '/includes/csrf.php';
require_once BASE_PATH . '/includes/rate_limit.php';
require_once BASE_PATH . '/includes/knd_avatar.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('METHOD_NOT_ALLOWED', 'POST required.', 405);
    }

    api_require_login();
    csrf_guard();

    $pdo = getDBConnection();
    if (!$pdo) json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500);

    $userId = current_user_id();
    rate_limit_guard($pdo, "avatar_buy:{$userId}", 10, 60);

    $itemId = (int) ($_POST['item_id'] ?? $_SERVER['HTTP_X_AVATAR_ITEM_ID'] ?? 0);
    if ($itemId <= 0) {
        json_error('INVALID_ITEM', 'Invalid item_id.', 400);
    }

    $result = avatar_buy_item($pdo, $userId, $itemId);

    if (isset($result['error'])) {
        $code = $result['error'];
        $msg = $code === 'INSUFFICIENT_KP'
            ? ('Not enough KP. You have ' . ($result['available'] ?? 0) . ', need ' . ($result['required'] ?? 0))
            : ($code === 'ALREADY_OWNED' ? 'You already own this item.' : 'Could not complete purchase.');
        json_error($code, $msg, 400);
    }

    if (isset($_SESSION['sc_badge_cache'])) {
        unset($_SESSION['sc_badge_cache']);
    }

    json_success(['available_after' => $result['available_after']]);
} catch (\Throwable $e) {
    error_log('avatar/buy error: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'An unexpected error occurred.', 500);
}

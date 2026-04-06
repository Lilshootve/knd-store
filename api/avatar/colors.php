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
    rate_limit_guard($pdo, "avatar_colors:{$userId}", 15, 60);

    $input = $_POST['colors'] ?? null;
    if (is_string($input)) {
        $colors = json_decode($input, true);
    } else {
        $colors = is_array($input) ? $input : [];
    }
    if (!is_array($colors)) {
        $colors = [];
    }

    avatar_set_colors($pdo, $userId, $colors);

    json_success(['colors' => $colors]);
} catch (\Throwable $e) {
    error_log('avatar/colors error: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'An unexpected error occurred.', 500);
}

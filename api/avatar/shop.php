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
require_once BASE_PATH . '/includes/knd_avatar.php';

try {
    api_require_login();

    $pdo = getDBConnection();
    if (!$pdo) json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500);

    $sql = "SELECT id, code, slot, name, rarity, price_kp, asset_path FROM knd_avatar_items WHERE is_active = 1 ORDER BY slot, rarity, price_kp";
    $items = avatar_get_shop_items($pdo);

    $data = ['items' => $items];
    $isLocal = in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1']);
    $isAdmin = !empty($_SESSION['admin_logged_in']);
    if ((!empty($_GET['debug']) || !empty($_REQUEST['debug'])) && ($isLocal || $isAdmin)) {
        $data['_debug'] = [
            'slot_requested' => $_GET['slot'] ?? null,
            'sql' => $sql,
            'row_count' => count($items),
            'sample' => array_slice(array_map(function ($r) {
                return ['id' => $r['id'], 'slot' => $r['slot'], 'asset_path' => $r['asset_path']];
            }, $items), 0, 3),
        ];
    }

    json_success($data);
} catch (\Throwable $e) {
    error_log('avatar/shop error: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'An unexpected error occurred.', 500);
}

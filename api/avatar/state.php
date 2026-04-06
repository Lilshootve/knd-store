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
require_once BASE_PATH . '/includes/knd_avatar.php';

try {
    api_require_login();

    $pdo = getDBConnection();
    if (!$pdo) json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500);

    $userId = current_user_id();

    release_available_points_if_due($pdo, $userId);
    expire_points_if_due($pdo, $userId);

    $loadout = avatar_get_user_loadout($pdo, $userId);
    $inventory = avatar_get_inventory($pdo, $userId);
    $kpBalance = get_available_points($pdo, $userId);

    $invIds = array_column($inventory, 'id');

    $data = [
        'loadout' => $loadout,
        'inventory' => $inventory,
        'inventory_ids' => $invIds,
        'kp_balance' => $kpBalance,
    ];
    $isLocal = in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1']);
    $isAdmin = !empty($_SESSION['admin_logged_in']);
    if ((!empty($_GET['debug']) || !empty($_REQUEST['debug'])) && ($isLocal || $isAdmin)) {
        $data['_debug'] = [
            'slot_requested' => $_GET['slot'] ?? null,
            'inventory_sql' => 'SELECT i.id, i.code, i.slot, i.name, i.rarity, i.asset_path, inv.acquired_at FROM knd_user_avatar_inventory inv JOIN knd_avatar_items i ON i.id = inv.item_id WHERE inv.user_id = ? ORDER BY i.slot, i.rarity, i.name',
            'inventory_row_count' => count($inventory),
            'sample' => array_slice(array_map(function ($r) {
                return ['id' => $r['id'], 'slot' => $r['slot'], 'asset_path' => $r['asset_path']];
            }, $inventory), 0, 3),
        ];
    }
    json_success($data);
} catch (\Throwable $e) {
    error_log('avatar/state error: ' . $e->getMessage());
    $msg = 'An unexpected error occurred.';
    if (!empty($_GET['debug']) || !empty($_REQUEST['debug'])) {
        $msg = $e->getMessage();
    }
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'ok' => false,
        'error' => ['code' => 'INTERNAL_ERROR', 'message' => $msg]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

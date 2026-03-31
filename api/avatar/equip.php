<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
ini_set('display_errors', '0');

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/json.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/rate_limit.php';
require_once __DIR__ . '/../../includes/knd_avatar.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('METHOD_NOT_ALLOWED', 'POST required.', 405);
    }

    api_require_login();
    csrf_guard();

    $pdo = getDBConnection();
    if (!$pdo) json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500);

    $userId = current_user_id();
    rate_limit_guard($pdo, "avatar_equip:{$userId}", 20, 60);

    $slot = trim((string) ($_POST['slot'] ?? ''));
    $itemIdRaw = $_POST['item_id'] ?? null;
    $itemId = $itemIdRaw === '' || $itemIdRaw === null ? null : (int) $itemIdRaw;

    $allowedSlots = ['hair', 'top', 'bottom', 'shoes', 'accessory1', 'bg', 'frame'];
    if (!in_array($slot, $allowedSlots)) {
        json_error('INVALID_SLOT', 'Invalid slot.', 400);
    }

    if ($itemId !== null && $itemId <= 0) {
        $itemId = null;
    }

    $ok = avatar_equip_item($pdo, $userId, $slot, $itemId);
    if (!$ok) {
        json_error('EQUIP_FAILED', 'Item not found or not owned.', 400);
    }

    json_success(['slot' => $slot, 'item_id' => $itemId]);
} catch (\Throwable $e) {
    error_log('avatar/equip error: ' . $e->getMessage());
    $msg = 'An unexpected error occurred.';
    if (!empty($_GET['debug']) || !empty($_POST['debug'])) {
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

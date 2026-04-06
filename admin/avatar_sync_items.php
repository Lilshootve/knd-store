<?php
/**
 * Avatar Sync - Recursively scans assets/avatars/** and syncs knd_avatar_items.
 * Admin only. Run via browser: /admin/avatar_sync_items.php
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/_guard.php';
require_once BASE_PATH . '/includes/knd_avatar.php';
admin_require_login();

$pdo = getDBConnection();
if (!$pdo) {
    die('DB connection failed.');
}

$forceNames = isset($_GET['force_names']) && (string) $_GET['force_names'] !== '0';
$result = avatar_sync_items_from_assets($pdo, [
    'force_name_refresh' => $forceNames,
]);

header('Content-Type: application/json');
echo json_encode([
    'ok' => true,
    'message' => 'Avatar sync completed. Name updates and rename relink strategy applied.',
    'force_names' => $forceNames,
    'scanned' => $result['scanned'] ?? 0,
    'inserted' => $result['inserted'] ?? 0,
    'updated' => $result['updated'] ?? 0,
    'deactivated' => $result['deactivated'] ?? 0,
    'relinked' => $result['relinked'] ?? 0,
    'names_refreshed' => $result['names_refreshed'] ?? 0,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

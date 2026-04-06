<?php
/**
 * GET /api/avatar/fragments.php
 * Returns user's fragment balance
 */
require_once __DIR__ . '/../../config/bootstrap.php';
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache');
ini_set('display_errors', '0');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/json.php';
require_once BASE_PATH . '/includes/knd_drop.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        json_error('METHOD_NOT_ALLOWED', 'GET only.', 405);
    }

    api_require_login();

    $pdo = getDBConnection();
    if (!$pdo) {
        json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500);
    }

    $userId = current_user_id();
    $fragments = get_user_fragments($pdo, $userId);
    $pityCounter = get_user_pity($pdo, $userId);
    $pityBoost = min($pityCounter * (defined('PITY_BOOST_PER_DROP') ? PITY_BOOST_PER_DROP : 2), defined('PITY_MAX_BOOST') ? PITY_MAX_BOOST : 30);

    json_success([
        'fragments' => $fragments,
        'pity_boost' => $pityBoost,
        'drops_since_rare' => $pityCounter,
    ]);
} catch (\Throwable $e) {
    error_log('api/avatar/fragments: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', $e->getMessage(), 500);
}

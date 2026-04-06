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

try {
    api_require_login();

    $pdo = getDBConnection();
    if (!$pdo) json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500);

    $stmt = $pdo->prepare(
        "SELECT id, title, description, category, points_cost, stock
         FROM rewards_catalog WHERE is_active = 1
         ORDER BY points_cost ASC"
    );
    $stmt->execute();
    $rewards = $stmt->fetchAll();

    foreach ($rewards as &$r) {
        $r['id'] = (int) $r['id'];
        $r['points_cost'] = (int) $r['points_cost'];
        $r['stock'] = $r['stock'] === null ? null : (int) $r['stock'];
    }

    json_success(['rewards' => $rewards]);
} catch (\Throwable $e) {
    error_log('support-credits/rewards error: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'An unexpected error occurred.', 500);
}

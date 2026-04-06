<?php
/**
 * GET /api/badges/user_badges.php
 * Returns user's unlocked badges and progress
 */
require_once __DIR__ . '/../../config/bootstrap.php';
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache');
ini_set('display_errors', '0');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/json.php';
require_once BASE_PATH . '/includes/knd_badges.php';

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

    // Get user's unlocked badges
    $unlockedBadges = badges_get_user_badges($pdo, $userId);
    
    // Get badge progress
    $progress = badges_get_user_progress($pdo, $userId);
    
    // Get milestone counts
    $milestones = badges_get_user_milestones($pdo, $userId);

    json_success([
        'unlocked_badges' => $unlockedBadges,
        'progress' => $progress,
        'milestones' => $milestones,
    ]);
} catch (\Throwable $e) {
    error_log('api/badges/user_badges: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', $e->getMessage(), 500);
}

<?php
/**
 * POST /api/labs/preference.php
 * Body: private=0|1
 * Sets labs_recent_private (0=public catalog, 1=only my jobs).
 */
require_once __DIR__ . '/../../config/bootstrap.php';
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/json.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('METHOD_NOT_ALLOWED', 'POST only.', 405);
    }
    api_require_login();

    $private = isset($_POST['private']) ? (int) $_POST['private'] : null;
    if ($private === null) {
        json_error('INVALID_INPUT', 'private is required (0 or 1).', 400);
    }
    $private = $private ? 1 : 0;

    $pdo = getDBConnection();
    if (!$pdo) json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500);

    $userId = current_user_id();
    try {
        $stmt = $pdo->prepare("UPDATE users SET labs_recent_private = ? WHERE id = ?");
        if (!$stmt || !$stmt->execute([$private, $userId])) {
            json_error('DB_ERROR', 'Could not update preference. Run sql/users_alter_labs_recent_private.sql if needed.', 500);
        }
    } catch (\Throwable $e) {
        json_error('DB_ERROR', 'Column labs_recent_private may not exist. Run sql/users_alter_labs_recent_private.sql', 500);
    }

    json_success(['private' => $private]);
} catch (\Throwable $e) {
    error_log('api/labs/preference: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'An error occurred.', 500);
}

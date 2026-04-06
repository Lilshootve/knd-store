<?php
require_once __DIR__ . '/../../config/bootstrap.php';
// KND Store - Game state endpoint (Death Roll 1v1)

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
ini_set('display_errors', '0');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/deathroll_1v1.php';
require_once BASE_PATH . '/includes/support_credits.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        json_error('METHOD_NOT_ALLOWED', 'GET only.', 405);
    }

    api_require_login();

    $code = strtoupper(trim($_GET['code'] ?? ''));
    if (!validate_room_code($code)) {
        json_error('INVALID_CODE', 'Invalid room code.');
    }

    $pdo = getDBConnection();
    if (!$pdo) { json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500); }
    $userId = current_user_id();

    $stmt = $pdo->prepare('SELECT * FROM deathroll_games_1v1 WHERE code = ? LIMIT 1');
    $stmt->execute([$code]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$game) {
        json_error('ROOM_NOT_FOUND', 'Room not found.', 404);
    }

    // Check turn timeout: if playing and turn_started_at is set
    if ($game['status'] === 'playing' && !empty($game['turn_started_at'])) {
        $game = check_turn_timeout($pdo, $game);
    }

    // Update last_activity_at for non-finished games
    $now = gmdate('Y-m-d H:i:s');
    $pdo->prepare(
        'UPDATE deathroll_games_1v1 SET last_activity_at = ? WHERE id = ? AND status != "finished"'
    )->execute([$now, $game['id']]);

    $state = build_game_state($pdo, $game, $userId);

    if ($game['status'] === 'finished') {
        $state['my_kp_balance'] = get_available_points($pdo, $userId);
    }

    json_success($state);
} catch (Throwable $e) {
    error_log('DR1V1_FATAL ' . basename(__FILE__) . ': ' . $e->getMessage());
    error_log($e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => ['code' => 'SERVER_ERROR', 'message' => 'Internal error']]);
    exit;
}

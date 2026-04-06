<?php
require_once __DIR__ . '/../../config/bootstrap.php';
// KND Store - Lobby state endpoint (Death Roll 1v1)

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
ini_set('display_errors', '0');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/deathroll_1v1.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        json_error('METHOD_NOT_ALLOWED', 'GET only.', 405);
    }

    api_require_login();

    $pdo = getDBConnection();
    if (!$pdo) { json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500); }

    deathroll_gc($pdo);

    $stmt = $pdo->prepare(
        'SELECT u.id, u.username
         FROM user_presence p
         JOIN users u ON u.id = p.user_id
         WHERE p.last_seen >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 60 SECOND)
         ORDER BY p.last_seen DESC
         LIMIT 30'
    );
    $stmt->execute();
    $onlineUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($onlineUsers as &$u) {
        $u['id'] = (int) $u['id'];
    }
    unset($u);

    $stmt = $pdo->prepare(
        'SELECT g.code, g.created_at, g.initial_max, g.entry_kp, u.username AS creator
         FROM deathroll_games_1v1 g
         JOIN users u ON u.id = g.created_by_user_id
         WHERE g.visibility = "public" AND g.status = "waiting"
           AND g.last_activity_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 MINUTE)
         ORDER BY g.updated_at DESC
         LIMIT 30'
    );
    $stmt->execute();
    $publicRooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) as cnt FROM deathroll_games_1v1
         WHERE status = "playing"
           AND last_activity_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 10 MINUTE)'
    );
    $stmt->execute();
    $activeGames = (int) $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];

    json_success([
        'online_users' => $onlineUsers,
        'public_rooms' => $publicRooms,
        'active_games' => $activeGames,
    ]);
} catch (Throwable $e) {
    error_log('DR1V1_FATAL ' . basename(__FILE__) . ': ' . $e->getMessage());
    error_log($e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => ['code' => 'SERVER_ERROR', 'message' => 'Internal error']]);
    exit;
}

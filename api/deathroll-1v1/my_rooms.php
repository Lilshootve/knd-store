<?php
require_once __DIR__ . '/../../config/bootstrap.php';
// KND Store - My rooms endpoint (Death Roll 1v1)

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
    $userId = current_user_id();

    $stmt = $pdo->prepare(
        'SELECT g.code, g.visibility, g.status, g.current_max, g.initial_max, g.finished_reason,
                g.created_at, g.last_activity_at,
                g.player1_user_id, g.player2_user_id,
                g.winner_user_id, g.loser_user_id,
                u1.username AS p1_username,
                u2.username AS p2_username
         FROM deathroll_games_1v1 g
         JOIN users u1 ON u1.id = g.player1_user_id
         LEFT JOIN users u2 ON u2.id = g.player2_user_id
         WHERE (g.player1_user_id = ? OR g.player2_user_id = ?)
           AND (g.status IN ("waiting", "playing")
                OR (g.status = "finished" AND g.updated_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 HOUR)))
         ORDER BY FIELD(g.status, "playing", "waiting", "finished"), g.updated_at DESC
         LIMIT 20'
    );
    $stmt->execute([$userId, $userId]);
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];
    foreach ($rooms as $r) {
        $opponent = null;
        if ((int) $r['player1_user_id'] === $userId) {
            $opponent = $r['p2_username'];
        } else {
            $opponent = $r['p1_username'];
        }

        $wonLost = null;
        if ($r['status'] === 'finished') {
            if ((int) $r['winner_user_id'] === $userId) {
                $wonLost = 'won';
            } elseif ((int) $r['loser_user_id'] === $userId) {
                $wonLost = 'lost';
            }
        }

        $result[] = [
            'code'            => $r['code'],
            'visibility'      => $r['visibility'],
            'status'          => $r['status'],
            'current_max'     => (int) $r['current_max'],
            'initial_max'     => (int) ($r['initial_max'] ?? 1000),
            'finished_reason' => $r['finished_reason'],
            'opponent'        => $opponent,
            'result'          => $wonLost,
            'created_at'      => $r['created_at'],
            'last_activity'   => $r['last_activity_at'],
        ];
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) as cnt FROM deathroll_games_1v1
         WHERE created_by_user_id = ? AND status IN ("waiting", "playing")'
    );
    $stmt->execute([$userId]);
    $activeCount = (int) $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];

    json_success([
        'rooms'        => $result,
        'active_count' => $activeCount,
        'max_rooms'    => 5,
    ]);
} catch (Throwable $e) {
    error_log('DR1V1_FATAL ' . basename(__FILE__) . ': ' . $e->getMessage());
    error_log($e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => ['code' => 'SERVER_ERROR', 'message' => 'Internal error']]);
    exit;
}

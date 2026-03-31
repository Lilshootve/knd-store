<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/json.php';
require_once __DIR__ . '/../../includes/knowledge_duel.php';

try {
    $pdo = getDBConnection();
    if (!$pdo) {
        json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500);
    }

    $season = kd_ensure_active_season($pdo);
    $seasonId = (int) $season['id'];
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 20;
    $limit = max(1, min(100, $limit));

    $stmt = $pdo->prepare(
        "SELECT r.user_id, r.rank_score, r.wins, r.losses, r.draws, r.updated_at, u.username
         FROM knd_season_rankings r
         JOIN users u ON u.id = r.user_id
         WHERE r.season_id = ?
         ORDER BY r.rank_score DESC, r.updated_at ASC
         LIMIT $limit"
    );
    $stmt->execute([$seasonId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $currentUserId = is_logged_in() ? (int) current_user_id() : 0;

    $top = [];
    foreach ($rows as $i => $row) {
        $entryUserId = (int) $row['user_id'];
        $top[] = [
            'position' => $i + 1,
            'user_id' => $entryUserId,
            'username' => (string) $row['username'],
            'rank_score' => (int) ($row['rank_score'] ?? 0),
            'wins' => (int) ($row['wins'] ?? 0),
            'losses' => (int) ($row['losses'] ?? 0),
            'draws' => (int) ($row['draws'] ?? 0),
            'is_current_user' => ($currentUserId > 0 && $entryUserId === $currentUserId),
        ];
    }

    $myRanking = null;
    if (is_logged_in()) {
        $myRanking = kd_get_user_ranking($pdo, (int) current_user_id(), $seasonId);
    }

    json_success([
        'season' => [
            'id' => $seasonId,
            'name' => (string) $season['name'],
            'starts_at' => (string) $season['starts_at'],
            'ends_at' => (string) $season['ends_at'],
            'status' => (string) $season['status'],
        ],
        'top' => $top,
        'my_ranking' => $myRanking,
    ]);
} catch (\Throwable $e) {
    error_log('knowledge-duel/leaderboard error: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'An unexpected error occurred.', 500);
}


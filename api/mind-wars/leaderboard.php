<?php
require_once __DIR__ . '/../../config/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/json.php';
require_once BASE_PATH . '/games/mindwars1v1/bootstrap.php';

try {
    $pdo = getDBConnection();
    if (!$pdo) {
        json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500);
    }

    $season = mw_ensure_season($pdo);
    $seasonId = (int) ($season['id'] ?? 0);
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 20;
    $limit = max(1, min(100, $limit));

    $stmt = $pdo->prepare(
        "SELECT r.user_id, r.rank_score, r.wins, r.losses, r.updated_at, u.username
         FROM knd_mind_wars_rankings r
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
        $entryUserId = (int) ($row['user_id'] ?? 0);
        $wins = (int) ($row['wins'] ?? 0);
        $losses = (int) ($row['losses'] ?? 0);
        $total = max(0, $wins + $losses);
        $top[] = [
            'position' => $i + 1,
            'user_id' => $entryUserId,
            'username' => (string) ($row['username'] ?? ''),
            'rank_score' => (int) ($row['rank_score'] ?? 0),
            'wins' => $wins,
            'losses' => $losses,
            'win_rate' => $total > 0 ? round(($wins / $total) * 100, 2) : 0.0,
            'is_current_user' => ($currentUserId > 0 && $entryUserId === $currentUserId),
        ];
    }

    $myRanking = null;
    if ($currentUserId > 0) {
        $myStmt = $pdo->prepare(
            "SELECT rank_score, wins, losses
             FROM knd_mind_wars_rankings
             WHERE user_id = ? AND season_id = ?
             LIMIT 1"
        );
        $myStmt->execute([$currentUserId, $seasonId]);
        $myRow = $myStmt->fetch(PDO::FETCH_ASSOC);
        if ($myRow) {
            $myScore = (int) ($myRow['rank_score'] ?? 0);
            $myWins = (int) ($myRow['wins'] ?? 0);
            $myLosses = (int) ($myRow['losses'] ?? 0);
            $myTotal = max(0, $myWins + $myLosses);
            $posStmt = $pdo->prepare(
                "SELECT 1 + COUNT(*) AS pos
                 FROM knd_mind_wars_rankings
                 WHERE season_id = ? AND rank_score > ?"
            );
            $posStmt->execute([$seasonId, $myScore]);
            $myRanking = [
                'rank_score' => $myScore,
                'wins' => $myWins,
                'losses' => $myLosses,
                'win_rate' => $myTotal > 0 ? round(($myWins / $myTotal) * 100, 2) : 0.0,
                'estimated_position' => (int) ($posStmt->fetchColumn() ?: 1),
            ];
        }
    }

    $seasonEndTs = strtotime((string) ($season['ends_at'] ?? 'now')) ?: time();
    $secondsRemaining = max(0, $seasonEndTs - time());

    json_success([
        'season' => [
            'id' => $seasonId,
            'name' => (string) ($season['name'] ?? 'Mind Wars Season'),
            'starts_at' => (string) ($season['starts_at'] ?? ''),
            'ends_at' => (string) ($season['ends_at'] ?? ''),
            'status' => (string) ($season['status'] ?? ''),
            'seconds_remaining' => $secondsRemaining,
        ],
        'top' => $top,
        'my_ranking' => $myRanking,
    ]);
} catch (\Throwable $e) {
    error_log('mind-wars/leaderboard error: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'An unexpected error occurred.', 500);
}


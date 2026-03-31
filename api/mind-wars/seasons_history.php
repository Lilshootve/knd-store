<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/json.php';

try {
    $pdo = getDBConnection();
    if (!$pdo) {
        json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500);
    }

    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 12;
    $limit = max(1, min(24, $limit));

    $seasonStmt = $pdo->prepare(
        "SELECT id, name, starts_at, ends_at, status
         FROM knd_mind_wars_seasons
         WHERE status = 'finished'
         ORDER BY ends_at DESC, id DESC
         LIMIT $limit"
    );
    $seasonStmt->execute();
    $seasons = $seasonStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $topStmt = $pdo->prepare(
        "SELECT r.user_id, r.rank_score, r.wins, r.losses, u.username
         FROM knd_mind_wars_rankings r
         JOIN users u ON u.id = r.user_id
         WHERE r.season_id = ?
         ORDER BY r.rank_score DESC, r.updated_at ASC
         LIMIT 10"
    );

    $out = [];
    foreach ($seasons as $season) {
        $seasonId = (int) ($season['id'] ?? 0);
        if ($seasonId <= 0) continue;
        $topStmt->execute([$seasonId]);
        $rows = $topStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $top = [];
        foreach ($rows as $i => $row) {
            $wins = (int) ($row['wins'] ?? 0);
            $losses = (int) ($row['losses'] ?? 0);
            $total = max(0, $wins + $losses);
            $top[] = [
                'position' => $i + 1,
                'user_id' => (int) ($row['user_id'] ?? 0),
                'username' => (string) ($row['username'] ?? ''),
                'rank_score' => (int) ($row['rank_score'] ?? 0),
                'wins' => $wins,
                'losses' => $losses,
                'win_rate' => $total > 0 ? round(($wins / $total) * 100, 2) : 0.0,
            ];
        }

        $out[] = [
            'season' => [
                'id' => $seasonId,
                'name' => (string) ($season['name'] ?? ''),
                'starts_at' => (string) ($season['starts_at'] ?? ''),
                'ends_at' => (string) ($season['ends_at'] ?? ''),
                'status' => (string) ($season['status'] ?? ''),
            ],
            'winner' => $top[0] ?? null,
            'top' => $top,
        ];
    }

    json_success(['seasons' => $out]);
} catch (\Throwable $e) {
    error_log('mind-wars/seasons_history error: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'An unexpected error occurred.', 500);
}


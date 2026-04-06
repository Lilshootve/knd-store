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
require_once BASE_PATH . '/includes/mind_wars.php';

try {
    api_require_login();
    $pdo = getDBConnection();
    if (!$pdo) {
        json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500);
    }

    $season = mw_ensure_season($pdo);
    $seasonId = (int) ($season['id'] ?? 0);
    $currentUserId = (int) current_user_id();

    $stmt = $pdo->prepare(
        "SELECT r.user_id, r.rank_score, r.wins, r.losses, u.username,
                ai.asset_path AS favorite_asset_path, ai.name AS favorite_item_name,
                mw.id AS favorite_mw_avatar_id, mw.image AS favorite_mw_image
         FROM knd_mind_wars_rankings r
         JOIN users u ON u.id = r.user_id
         LEFT JOIN knd_avatar_items ai ON ai.id = u.favorite_avatar_id AND ai.is_active = 1
         LEFT JOIN mw_avatars mw ON LOWER(TRIM(mw.name)) = LOWER(TRIM(ai.name))
         WHERE r.season_id = ?
         ORDER BY r.rank_score DESC, r.updated_at ASC
         LIMIT 5"
    );
    $stmt->execute([$seasonId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $top = [];
    foreach ($rows as $i => $row) {
        $entryUserId = (int) ($row['user_id'] ?? 0);
        $wins = (int) ($row['wins'] ?? 0);
        $losses = (int) ($row['losses'] ?? 0);
        $total = max(0, $wins + $losses);
        $name = (string) ($row['favorite_item_name'] ?? '');
        $asset = (string) ($row['favorite_asset_path'] ?? '');
        $mwLb = (int) ($row['favorite_mw_avatar_id'] ?? 0);
        $mwImgRow = trim((string) ($row['favorite_mw_image'] ?? ''));
        $avatarUrl = $name !== ''
            ? mw_resolve_avatar_image_for_inventory(
                $pdo,
                $mwLb > 0 ? $mwLb : null,
                $name,
                $asset,
                $mwImgRow !== '' ? $mwImgRow : null
            )
            : null;
        $top[] = [
            'position' => $i + 1,
            'user_id' => $entryUserId,
            'username' => (string) ($row['username'] ?? ''),
            'rank_score' => (int) ($row['rank_score'] ?? 0),
            'wins' => $wins,
            'losses' => $losses,
            'win_rate' => $total > 0 ? round(($wins / $total) * 100, 2) : 0.0,
            'is_current_user' => ($currentUserId > 0 && $entryUserId === $currentUserId),
            'avatar_url' => ($avatarUrl !== '' ? $avatarUrl : null),
        ];
    }

    json_success([
        'season' => [
            'id' => $seasonId,
            'name' => (string) ($season['name'] ?? ''),
        ],
        'top' => $top,
    ]);
} catch (\Throwable $e) {
    error_log('mind-wars/get_leaderboard_preview error: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'An unexpected error occurred.', 500);
}

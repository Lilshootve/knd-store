<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/json.php';
require_once __DIR__ . '/../../includes/mind_wars.php';

try {
    api_require_login();

    $pdo = getDBConnection();
    if (!$pdo) {
        json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500);
    }

    $season = mw_ensure_season($pdo);
    $seasonId = (int) ($season['id'] ?? 0);
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 24;
    $limit = max(1, min(48, $limit));

    $presenceFilter = mw_queue_supports_presence_columns($pdo)
        ? "(
            (q.last_seen_at IS NOT NULL AND q.last_seen_at >= DATE_SUB(NOW(), INTERVAL 120 SECOND))
            OR
            (q.last_seen_at IS NULL AND q.updated_at >= DATE_SUB(NOW(), INTERVAL 120 SECOND))
        )"
        : "q.updated_at >= DATE_SUB(NOW(), INTERVAL 120 SECOND)";

    $hasLevelSnapshot = false;
    try {
        $hasLevelSnapshot = mw_queue_supports_avatar_level_snapshot($pdo);
    } catch (\Throwable $e) {
        $hasLevelSnapshot = false;
    }
    $queueAvatarLevelExpr = $hasLevelSnapshot
        ? "q.avatar_level_snapshot AS avatar_level_snapshot"
        : "NULL AS avatar_level_snapshot";

    $baseSql = "
        SELECT
            q.user_id,
            q.status,
            {$queueAvatarLevelExpr},
            q.updated_at,
            u.username,
            ai.id AS avatar_item_id,
            ai.name AS avatar_name,
            ai.asset_path AS avatar_asset_path,
            COALESCE(r.rank_score, 0) AS rank_score,
            COALESCE(ux.level, 1) AS user_level
        FROM knd_mind_wars_matchmaking_queue q
        INNER JOIN (
            SELECT user_id, MAX(id) AS max_id
            FROM knd_mind_wars_matchmaking_queue
            WHERE season_id = ? AND status IN ('queued','matched')
            GROUP BY user_id
        ) latest ON latest.max_id = q.id
        JOIN users u ON u.id = q.user_id
        LEFT JOIN knd_avatar_items ai ON ai.id = q.avatar_item_id
        LEFT JOIN knd_mind_wars_rankings r ON r.user_id = q.user_id AND r.season_id = q.season_id
        LEFT JOIN knd_user_xp ux ON ux.user_id = q.user_id
        WHERE q.season_id = ?
          AND q.status IN ('queued','matched')
          AND {$presenceFilter}
        ORDER BY q.updated_at DESC
        LIMIT {$limit}
    ";

    $playersByUser = [];
    $sourceHits = ['queue' => 0, 'recent' => 0, 'leaderboard_fallback' => 0];
    $pushRow = static function (array $row) use (&$playersByUser): void {
        $userId = (int) ($row['user_id'] ?? 0);
        if ($userId <= 0) {
            return;
        }
        $assetPath = (string) ($row['avatar_asset_path'] ?? '');
        $isAbs = substr($assetPath, 0, 1) === '/';
        $normalized = [
            'user_id' => $userId,
            'username' => (string) ($row['username'] ?? ''),
            'user_level' => max(1, (int) ($row['user_level'] ?? 1)),
            'rank_score' => (int) ($row['rank_score'] ?? 0),
            'status' => (string) ($row['status'] ?? 'active'),
            'avatar_name' => (string) ($row['avatar_name'] ?? 'Avatar'),
            'avatar_item_id' => (int) ($row['avatar_item_id'] ?? 0),
            'avatar_level' => max(1, (int) ($row['avatar_level'] ?? ($row['avatar_level_snapshot'] ?? 1))),
            'avatar_asset' => $assetPath !== '' ? ($isAbs ? $assetPath : '/assets/avatars/' . ltrim($assetPath, '/')) : '/assets/avatars/_placeholder.svg',
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
        $currentTs = strtotime((string) ($playersByUser[$userId]['updated_at'] ?? '')) ?: 0;
        $incomingTs = strtotime((string) ($normalized['updated_at'] ?? '')) ?: 0;
        if (!isset($playersByUser[$userId]) || $incomingTs >= $currentTs) {
            $playersByUser[$userId] = $normalized;
        }
    };

    try {
        try {
            $stmt = $pdo->prepare($baseSql);
            $stmt->execute([$seasonId, $seasonId]);
        } catch (\Throwable $e) {
            // Fallback for environments where knd_user_xp is unavailable.
            $fallbackSql = str_replace("LEFT JOIN knd_user_xp ux ON ux.user_id = q.user_id", "", $baseSql);
            $fallbackSql = str_replace("COALESCE(ux.level, 1) AS user_level", "1 AS user_level", $fallbackSql);
            $stmt = $pdo->prepare($fallbackSql);
            $stmt->execute([$seasonId, $seasonId]);
        }
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $pushRow($row);
            $sourceHits['queue']++;
        }
    } catch (\Throwable $e) {
        // Queue source is optional: do not fail endpoint.
    }

    // Fallback source: users in active battles updated recently.
    if (count($playersByUser) < $limit) {
        $battleSql = "
            SELECT
                src.user_id,
                src.status,
                src.updated_at,
                u.username,
                ai.id AS avatar_item_id,
                ai.name AS avatar_name,
                ai.asset_path AS avatar_asset_path,
                COALESCE(inv.avatar_level, 1) AS avatar_level,
                COALESCE(r.rank_score, 0) AS rank_score,
                COALESCE(ux.level, 1) AS user_level
            FROM (
                SELECT b.user_id, b.avatar_item_id, 'in_battle' AS status, b.updated_at
                FROM knd_mind_wars_battles b
                WHERE b.result IS NULL
                  AND b.updated_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
                UNION ALL
                SELECT p.user_id, p.avatar_item_id, 'in_battle' AS status, b.updated_at
                FROM knd_mind_wars_battle_participants p
                JOIN knd_mind_wars_battles b ON b.id = p.battle_id
                WHERE b.result IS NULL
                  AND b.updated_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
            ) src
            JOIN users u ON u.id = src.user_id
            LEFT JOIN knd_avatar_items ai ON ai.id = src.avatar_item_id
            LEFT JOIN knd_user_avatar_inventory inv ON inv.user_id = src.user_id AND inv.item_id = src.avatar_item_id
            LEFT JOIN knd_mind_wars_rankings r ON r.user_id = src.user_id AND r.season_id = ?
            LEFT JOIN knd_user_xp ux ON ux.user_id = src.user_id
            ORDER BY src.updated_at DESC
            LIMIT {$limit}
        ";
        $battleFetched = false;
        try {
            $battleStmt = $pdo->prepare($battleSql);
            $battleStmt->execute([$seasonId]);
            while ($row = $battleStmt->fetch(PDO::FETCH_ASSOC)) {
                $pushRow($row);
                $sourceHits['recent']++;
            }
            $battleFetched = true;
        } catch (\Throwable $e) {
            // try with no knd_user_xp
            try {
                $battleSqlFallback = str_replace("LEFT JOIN knd_user_xp ux ON ux.user_id = src.user_id", "", $battleSql);
                $battleSqlFallback = str_replace("COALESCE(ux.level, 1) AS user_level", "1 AS user_level", $battleSqlFallback);
                $battleStmt = $pdo->prepare($battleSqlFallback);
                $battleStmt->execute([$seasonId]);
                while ($row = $battleStmt->fetch(PDO::FETCH_ASSOC)) {
                    $pushRow($row);
                    $sourceHits['recent']++;
                }
                $battleFetched = true;
            } catch (\Throwable $e2) {
                $battleFetched = false;
            }
        }

        // If participants table is unavailable, fallback to owner rows only.
        if (!$battleFetched) {
            $ownerOnlySql = "
                SELECT
                    b.user_id,
                    'in_battle' AS status,
                    b.updated_at,
                    u.username,
                    ai.id AS avatar_item_id,
                    ai.name AS avatar_name,
                    ai.asset_path AS avatar_asset_path,
                    1 AS avatar_level,
                    COALESCE(r.rank_score, 0) AS rank_score,
                    1 AS user_level
                FROM knd_mind_wars_battles b
                JOIN users u ON u.id = b.user_id
                LEFT JOIN knd_avatar_items ai ON ai.id = b.avatar_item_id
                LEFT JOIN knd_mind_wars_rankings r ON r.user_id = b.user_id AND r.season_id = ?
                WHERE b.result IS NULL
                  AND b.updated_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
                ORDER BY b.updated_at DESC
                LIMIT {$limit}
            ";
            try {
                $ownerStmt = $pdo->prepare($ownerOnlySql);
                $ownerStmt->execute([$seasonId]);
                while ($row = $ownerStmt->fetch(PDO::FETCH_ASSOC)) {
                    $pushRow($row);
                    $sourceHits['recent']++;
                }
            } catch (\Throwable $e3) {
                // swallow: endpoint should still return success with empty list
            }
        }
    }

    // Last fallback: ranked players in active season.
    if (count($playersByUser) < max(6, (int) floor($limit / 2))) {
        $leaderSql = "
            SELECT
                r.user_id,
                'leaderboard' AS status,
                r.updated_at,
                u.username,
                ai.id AS avatar_item_id,
                ai.name AS avatar_name,
                ai.asset_path AS avatar_asset_path,
                COALESCE(inv.avatar_level, 1) AS avatar_level,
                COALESCE(r.rank_score, 0) AS rank_score,
                COALESCE(ux.level, 1) AS user_level
            FROM knd_mind_wars_rankings r
            JOIN users u ON u.id = r.user_id
            LEFT JOIN knd_user_avatar_inventory inv ON inv.user_id = r.user_id
            LEFT JOIN knd_avatar_items ai ON ai.id = inv.item_id
            LEFT JOIN knd_user_xp ux ON ux.user_id = r.user_id
            WHERE r.season_id = ?
            ORDER BY r.rank_score DESC, r.updated_at DESC
            LIMIT {$limit}
        ";
        try {
            try {
                $leaderStmt = $pdo->prepare($leaderSql);
                $leaderStmt->execute([$seasonId]);
            } catch (\Throwable $e) {
                $leaderSqlFallback = str_replace("LEFT JOIN knd_user_xp ux ON ux.user_id = r.user_id", "", $leaderSql);
                $leaderSqlFallback = str_replace("COALESCE(ux.level, 1) AS user_level", "1 AS user_level", $leaderSqlFallback);
                $leaderStmt = $pdo->prepare($leaderSqlFallback);
                $leaderStmt->execute([$seasonId]);
            }
            while ($row = $leaderStmt->fetch(PDO::FETCH_ASSOC)) {
                $pushRow($row);
                $sourceHits['leaderboard_fallback']++;
            }
        } catch (\Throwable $e) {
            // Optional fallback source, keep endpoint successful.
        }
    }

    $players = array_values($playersByUser);
    usort($players, static function (array $a, array $b): int {
        $ta = strtotime((string) ($a['updated_at'] ?? '')) ?: 0;
        $tb = strtotime((string) ($b['updated_at'] ?? '')) ?: 0;
        if ($tb !== $ta) {
            return $tb <=> $ta;
        }
        return ((int) ($b['rank_score'] ?? 0)) <=> ((int) ($a['rank_score'] ?? 0));
    });
    if (count($players) > $limit) {
        $players = array_slice($players, 0, $limit);
    }

    $source = 'queue';
    if ($sourceHits['queue'] <= 0 && $sourceHits['recent'] > 0) {
        $source = 'recent';
    } elseif ($sourceHits['queue'] <= 0 && $sourceHits['recent'] <= 0 && $sourceHits['leaderboard_fallback'] > 0) {
        $source = 'leaderboard_fallback';
    }

    json_success([
        'season_id' => $seasonId,
        'source' => $source,
        'source_hits' => $sourceHits,
        'players' => $players,
    ]);
} catch (\Throwable $e) {
    error_log('mind-wars/online_players error: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'An unexpected error occurred.', 500);
}


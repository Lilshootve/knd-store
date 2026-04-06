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
require_once BASE_PATH . '/includes/rate_limit.php';
require_once BASE_PATH . '/includes/mind_wars.php';

try {
    api_require_login();

    $pdo = getDBConnection();
    if (!$pdo) {
        json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500);
    }

    $userId = (int) current_user_id();
    rate_limit_guard($pdo, "mw_queue_status_user:{$userId}", 120, 60);

    $pdo->beginTransaction();
    try {
        mw_cleanup_stale_queue($pdo);
        mw_cleanup_stale_queue_presence($pdo);
        $season = mw_ensure_season($pdo);
        $seasonId = (int) ($season['id'] ?? 0);
        mw_queue_touch_presence($pdo, $userId, $seasonId);
        $hasLevelSnapshot = mw_queue_supports_avatar_level_snapshot($pdo);

        if ($hasLevelSnapshot) {
            $stmt = $pdo->prepare(
                "SELECT id, queue_token, status, rank_score_snapshot, avatar_level_snapshot, matched_with_user_id, matched_at, created_at, updated_at
                 FROM knd_mind_wars_matchmaking_queue
                 WHERE user_id = ? AND season_id = ?
                 ORDER BY updated_at DESC, id DESC
                 LIMIT 1
                 FOR UPDATE"
            );
        } else {
            $stmt = $pdo->prepare(
                "SELECT id, queue_token, status, rank_score_snapshot, matched_with_user_id, matched_at, created_at, updated_at
                 FROM knd_mind_wars_matchmaking_queue
                 WHERE user_id = ? AND season_id = ?
                 ORDER BY updated_at DESC, id DESC
                 LIMIT 1
                 FOR UPDATE"
            );
        }
        $stmt->execute([$userId, $seasonId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $pdo->commit();
            json_success(['status' => 'idle']);
        }

        $status = (string) ($row['status'] ?? 'idle');
        if ($status === 'matched') {
            $oppUserId = (int) ($row['matched_with_user_id'] ?? 0);
            $validMatch = $oppUserId > 0;
            if ($validMatch) {
                $oppStmt = $pdo->prepare(
                    "SELECT matched_with_user_id
                     FROM knd_mind_wars_matchmaking_queue
                     WHERE user_id = ? AND season_id = ? AND status = 'matched'
                     ORDER BY updated_at DESC, id DESC
                     LIMIT 1
                     FOR UPDATE"
                );
                $oppStmt->execute([$oppUserId, $seasonId]);
                $opp = $oppStmt->fetch(PDO::FETCH_ASSOC);
                $validMatch = $opp && (int) ($opp['matched_with_user_id'] ?? 0) === $userId;
            }

            // Self-heal orphan/inconsistent matches by returning user to queued state.
            if (!$validMatch) {
                if (mw_queue_supports_presence_columns($pdo)) {
                    $repair = $pdo->prepare(
                        "UPDATE knd_mind_wars_matchmaking_queue
                         SET status = 'queued',
                             matched_with_user_id = NULL,
                             matched_at = NULL,
                             match_expires_at = NULL,
                             expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND),
                             last_seen_at = NOW(),
                             updated_at = NOW()
                         WHERE id = ?"
                    );
                    $repair->execute([MW_QUEUE_TIMEOUT_SECONDS, (int) ($row['id'] ?? 0)]);
                } else {
                    $repair = $pdo->prepare(
                        "UPDATE knd_mind_wars_matchmaking_queue
                         SET status = 'queued',
                             matched_with_user_id = NULL,
                             matched_at = NULL,
                             expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND),
                             updated_at = NOW()
                         WHERE id = ?"
                    );
                    $repair->execute([MW_QUEUE_TIMEOUT_SECONDS, (int) ($row['id'] ?? 0)]);
                }
                $status = 'queued';
                $row['matched_with_user_id'] = null;
                $row['matched_at'] = null;
                $row['created_at'] = gmdate('Y-m-d H:i:s');
            }
        }

        if ($status === 'queued') {
            $createdAt = strtotime((string) ($row['created_at'] ?? 'now')) ?: time();
            $queuedFor = max(0, time() - $createdAt);
            $countStmt = $pdo->prepare(
                "SELECT COUNT(*) FROM knd_mind_wars_matchmaking_queue
                 WHERE season_id = ? AND status = 'queued'"
            );
            $countStmt->execute([$seasonId]);
            $queueCount = (int) ($countStmt->fetchColumn() ?: 0);
            $pdo->commit();
            json_success([
                'status' => 'queued',
                'queue_token' => (string) ($row['queue_token'] ?? ''),
                'queued_for_seconds' => $queuedFor,
                'level_window' => mw_queue_level_window($queuedFor),
                'rank_window' => mw_queue_rank_window($queuedFor),
                'rank_score_snapshot' => (int) ($row['rank_score_snapshot'] ?? 0),
                'avatar_level_snapshot' => (int) ($row['avatar_level_snapshot'] ?? $row['rank_score_snapshot'] ?? 1),
                'queue_count' => max(0, $queueCount),
            ]);
        }

        if ($status === 'matched') {
            $pdo->commit();
            json_success([
                'status' => 'matched',
                'queue_token' => (string) ($row['queue_token'] ?? ''),
                'match' => [
                    'opponent_user_id' => (int) ($row['matched_with_user_id'] ?? 0),
                    'matched_at' => (string) ($row['matched_at'] ?? ''),
                ],
            ]);
        }

        $pdo->commit();
        json_success([
            'status' => in_array($status, ['cancelled', 'expired'], true) ? $status : 'idle',
            'queue_token' => (string) ($row['queue_token'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ]);
    } catch (\Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
} catch (\Throwable $e) {
    error_log('mind-wars/queue_status error: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'An unexpected error occurred.', 500);
}


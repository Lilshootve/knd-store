<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/json.php';
require_once __DIR__ . '/../../includes/rate_limit.php';
require_once __DIR__ . '/../../includes/mind_wars.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('METHOD_NOT_ALLOWED', 'POST only.', 405);
    }
    csrf_guard();
    api_require_login();

    $pdo = getDBConnection();
    if (!$pdo) {
        json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500);
    }

    $userId = (int) current_user_id();
    rate_limit_guard($pdo, "mw_queue_deq_user:{$userId}", 30, 60);

    $pdo->beginTransaction();
    try {
        mw_cleanup_stale_queue($pdo);
        mw_cleanup_stale_queue_presence($pdo);
        $season = mw_ensure_season($pdo);
        $seasonId = (int) ($season['id'] ?? 0);

        $stmt = $pdo->prepare(
            "SELECT id FROM knd_mind_wars_matchmaking_queue
             WHERE user_id = ? AND season_id = ? AND status = 'queued'
             ORDER BY created_at DESC
             LIMIT 1
             FOR UPDATE"
        );
        $stmt->execute([$userId, $seasonId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $pdo->commit();
            json_success(['status' => 'idle']);
        }

        $upd = $pdo->prepare("UPDATE knd_mind_wars_matchmaking_queue SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
        $upd->execute([(int) $row['id']]);
        $pdo->commit();
        json_success(['status' => 'cancelled']);
    } catch (\Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
} catch (\Throwable $e) {
    error_log('mind-wars/queue_dequeue error: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'An unexpected error occurred.', 500);
}


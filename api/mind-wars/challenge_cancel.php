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
require_once __DIR__ . '/../../includes/mind_wars.php';
require_once __DIR__ . '/../../includes/mind_wars_challenges.php';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        json_error('METHOD_NOT_ALLOWED', 'POST only.', 405);
    }
    csrf_guard();
    api_require_login();

    $pdo = getDBConnection();
    if (!$pdo) {
        json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500);
    }

    $userId = (int) current_user_id();
    $challengeToken = trim((string) ($_POST['challenge_token'] ?? ''));
    if ($challengeToken === '') {
        json_error('CHALLENGE_REQUIRED', 'Challenge token is required.', 422);
    }

    mw_challenges_ensure_table($pdo);
    mw_challenges_cleanup_expired($pdo);
    $season = mw_ensure_season($pdo);
    $seasonId = (int) ($season['id'] ?? 0);

    $stmt = $pdo->prepare(
        "UPDATE knd_mind_wars_challenges
         SET status = 'cancelled', updated_at = NOW()
         WHERE season_id = ?
           AND challenge_token = ?
           AND challenger_user_id = ?
           AND status = 'pending'"
    );
    $stmt->execute([$seasonId, $challengeToken, $userId]);

    json_success([
        'status' => ((int) $stmt->rowCount() > 0) ? 'cancelled' : 'idle',
        'challenge_token' => $challengeToken,
    ]);
} catch (\Throwable $e) {
    error_log('mind-wars/challenge_cancel error: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'An unexpected error occurred.', 500);
}


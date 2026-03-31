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
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    rate_limit_guard($pdo, "mw_challenge_create_user:{$userId}", 40, 60);
    rate_limit_guard($pdo, "mw_challenge_create_ip:{$ip}", 80, 60);

    $challengedUserId = (int) ($_POST['challenged_user_id'] ?? 0);
    $avatarItemId = (int) ($_POST['avatar_item_id'] ?? 0);
    if ($challengedUserId <= 0 || $challengedUserId === $userId) {
        json_error('INVALID_TARGET', 'Invalid challenge target.', 422);
    }

    $pdo->beginTransaction();
    try {
        mw_challenges_ensure_table($pdo);
        mw_challenges_cleanup_expired($pdo);
        $season = mw_ensure_season($pdo);
        $seasonId = (int) ($season['id'] ?? 0);

        $userCheck = $pdo->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
        $userCheck->execute([$challengedUserId]);
        if (!(int) $userCheck->fetchColumn()) {
            $pdo->rollBack();
            json_error('TARGET_NOT_FOUND', 'Target player not found.', 404);
        }

        $challengerAvatar = null;
        if ($avatarItemId > 0) {
            $challengerAvatar = mw_validate_owned_avatar($pdo, $userId, $avatarItemId);
        }
        if (!$challengerAvatar) {
            $challengerAvatar = mw_challenges_get_user_avatar($pdo, $userId);
        }
        if (!$challengerAvatar) {
            $pdo->rollBack();
            json_error('NO_AVATAR', 'You need at least one active avatar to challenge.', 422);
        }

        $pendingCheck = $pdo->prepare(
            "SELECT id, challenge_token
             FROM knd_mind_wars_challenges
             WHERE season_id = ?
               AND status = 'pending'
               AND (
                 (challenger_user_id = ? AND challenged_user_id = ?)
                 OR
                 (challenger_user_id = ? AND challenged_user_id = ?)
               )
             ORDER BY id DESC
             LIMIT 1
             FOR UPDATE"
        );
        $pendingCheck->execute([$seasonId, $userId, $challengedUserId, $challengedUserId, $userId]);
        $existing = $pendingCheck->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            $pdo->commit();
            json_success([
                'challenge_token' => (string) ($existing['challenge_token'] ?? ''),
                'status' => 'pending',
                'already_exists' => true,
            ]);
        }

        $busyCheck = $pdo->prepare(
            "SELECT id
             FROM knd_mind_wars_challenges
             WHERE season_id = ?
               AND status = 'pending'
               AND challenger_user_id = ?
             LIMIT 1
             FOR UPDATE"
        );
        $busyCheck->execute([$seasonId, $userId]);
        if ($busyCheck->fetch(PDO::FETCH_ASSOC)) {
            $pdo->rollBack();
            json_error('OUTGOING_PENDING_EXISTS', 'You already have a pending outgoing challenge.', 409);
        }

        $challengeToken = mw_challenges_generate_token();
        $ins = $pdo->prepare(
            "INSERT INTO knd_mind_wars_challenges
             (challenge_token, season_id, challenger_user_id, challenged_user_id, challenger_avatar_item_id, status, expires_at)
             VALUES (?, ?, ?, ?, ?, 'pending', DATE_ADD(NOW(), INTERVAL 120 SECOND))"
        );
        $ins->execute([
            $challengeToken,
            $seasonId,
            $userId,
            $challengedUserId,
            (int) ($challengerAvatar['item_id'] ?? 0),
        ]);

        $pdo->commit();
        json_success([
            'challenge_token' => $challengeToken,
            'status' => 'pending',
            'challenged_user_id' => $challengedUserId,
            'expires_in_seconds' => 120,
            'challenger_avatar_item_id' => (int) ($challengerAvatar['item_id'] ?? 0),
        ]);
    } catch (\Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
} catch (\Throwable $e) {
    error_log('mind-wars/challenge_create error: ' . $e->getMessage());
    if (stripos($e->getMessage(), 'AGUACATE') !== false) {
        json_error('AGUACATE', $e->getMessage(), 400);
    }
    json_error('INTERNAL_ERROR', 'An unexpected error occurred.', 500);
}


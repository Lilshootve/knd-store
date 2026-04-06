<?php
require_once __DIR__ . '/../../config/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/csrf.php';
require_once BASE_PATH . '/includes/json.php';
require_once BASE_PATH . '/includes/mind_wars.php';
require_once BASE_PATH . '/includes/mind_wars_challenges.php';

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

    $pdo->beginTransaction();
    try {
        mw_challenges_ensure_table($pdo);
        mw_challenges_cleanup_expired($pdo);
        $season = mw_ensure_season($pdo);
        $seasonId = (int) ($season['id'] ?? 0);

        $challengeStmt = $pdo->prepare(
            "SELECT *
             FROM knd_mind_wars_challenges
             WHERE season_id = ?
               AND challenge_token = ?
               AND challenged_user_id = ?
             LIMIT 1
             FOR UPDATE"
        );
        $challengeStmt->execute([$seasonId, $challengeToken, $userId]);
        $challenge = $challengeStmt->fetch(PDO::FETCH_ASSOC);
        if (!$challenge) {
            $pdo->rollBack();
            json_error('CHALLENGE_NOT_FOUND', 'Challenge not found.', 404);
        }
        if ((string) ($challenge['status'] ?? '') !== 'pending') {
            $pdo->rollBack();
            json_error('CHALLENGE_NOT_PENDING', 'Challenge is no longer pending.', 409);
        }
        if (strtotime((string) ($challenge['expires_at'] ?? '')) <= time()) {
            $exp = $pdo->prepare("UPDATE knd_mind_wars_challenges SET status = 'expired', updated_at = NOW() WHERE id = ?");
            $exp->execute([(int) $challenge['id']]);
            $pdo->commit();
            json_error('CHALLENGE_EXPIRED', 'Challenge has expired.', 409);
        }

        $challengerUserId = (int) ($challenge['challenger_user_id'] ?? 0);
        $challengerAvatarId = (int) ($challenge['challenger_avatar_item_id'] ?? 0);
        $challengerAvatar = $challengerAvatarId > 0 ? mw_validate_owned_avatar($pdo, $challengerUserId, $challengerAvatarId) : null;
        if (!$challengerAvatar) {
            $challengerAvatar = mw_challenges_get_user_avatar($pdo, $challengerUserId);
        }
        $challengedAvatar = mw_challenges_get_user_avatar($pdo, $userId);
        if (!$challengerAvatar || !$challengedAvatar) {
            $pdo->rollBack();
            json_error('AVATAR_REQUIRED', 'Both players need a valid avatar.', 422);
        }

        $battleId = (int) ($challenge['battle_id'] ?? 0);
        $battleToken = (string) ($challenge['battle_token'] ?? '');
        $state = null;

        if ($battleId > 0 && $battleToken !== '') {
            $stateStmt = $pdo->prepare("SELECT id, battle_token, state_json FROM knd_mind_wars_battles WHERE id = ? LIMIT 1");
            $stateStmt->execute([$battleId]);
            $battleRow = $stateStmt->fetch(PDO::FETCH_ASSOC);
            if ($battleRow) {
                $battleId = (int) ($battleRow['id'] ?? 0);
                $battleToken = (string) ($battleRow['battle_token'] ?? '');
                $state = json_decode((string) ($battleRow['state_json'] ?? '{}'), true);
            }
        }

        if (!$state || $battleId <= 0 || $battleToken === '') {
            $active = mw_challenges_find_active_battle_between_users($pdo, $challengerUserId, $userId);
            if ($active) {
                $battleId = (int) ($active['id'] ?? 0);
                $battleToken = (string) ($active['battle_token'] ?? '');
                $stateStmt = $pdo->prepare("SELECT state_json FROM knd_mind_wars_battles WHERE id = ? LIMIT 1");
                $stateStmt->execute([$battleId]);
                $state = json_decode((string) ($stateStmt->fetchColumn() ?: '{}'), true);
            } else {
                $created = mw_challenges_create_battle($pdo, $challengerUserId, $userId, $challengerAvatar, $challengedAvatar);
                $battleId = (int) ($created['battle_id'] ?? 0);
                $battleToken = (string) ($created['battle_token'] ?? '');
                $state = $created['state'] ?? null;
            }
        }

        if (!$state || $battleId <= 0 || $battleToken === '') {
            $pdo->rollBack();
            json_error('BATTLE_CREATE_FAILED', 'Unable to initialize battle.', 500);
        }

        $sideStmt = $pdo->prepare(
            "SELECT side
             FROM knd_mind_wars_battle_participants
             WHERE battle_id = ? AND user_id = ?
             LIMIT 1"
        );
        $sideStmt->execute([$battleId, $userId]);
        $viewerSide = (string) ($sideStmt->fetchColumn() ?: 'player');
        if ($viewerSide !== 'enemy') {
            $viewerSide = 'player';
        }

        $nameStmt = $pdo->prepare("SELECT id, username FROM users WHERE id IN (?, ?)");
        $nameStmt->execute([$challengerUserId, $userId]);
        $users = [];
        while ($u = $nameStmt->fetch(PDO::FETCH_ASSOC)) {
            $users[(int) ($u['id'] ?? 0)] = (string) ($u['username'] ?? '');
        }

        $upd = $pdo->prepare(
            "UPDATE knd_mind_wars_challenges
             SET status = 'accepted',
                 accepted_by_user_id = ?,
                 challenged_avatar_item_id = ?,
                 battle_id = ?,
                 battle_token = ?,
                 updated_at = NOW()
             WHERE id = ?"
        );
        $upd->execute([
            $userId,
            (int) ($challengedAvatar['item_id'] ?? 0),
            $battleId,
            $battleToken,
            (int) ($challenge['id'] ?? 0),
        ]);

        $pdo->commit();
        json_success([
            'challenge_token' => $challengeToken,
            'status' => 'accepted',
            'battle_token' => $battleToken,
            'state' => mw_normalize_battle_state(is_array($state) ? $state : []),
            'viewer_side' => $viewerSide,
            'viewer_user' => $users[$userId] ?? 'You',
            'opponent_user' => $users[$challengerUserId] ?? 'Opponent',
        ]);
    } catch (\Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
} catch (\Throwable $e) {
    error_log('mind-wars/challenge_accept error: ' . $e->getMessage());
    if (stripos($e->getMessage(), 'AGUACATE') !== false) {
        json_error('AGUACATE', $e->getMessage(), 400);
    }
    json_error('INTERNAL_ERROR', 'An unexpected error occurred.', 500);
}


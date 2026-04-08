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
require_once BASE_PATH . '/includes/rate_limit.php';
require_once BASE_PATH . '/includes/mind_wars_arena_bootstrap.php';

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
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    rate_limit_guard($pdo, "mw_pvp_join_user:{$userId}", 40, 60);
    rate_limit_guard($pdo, "mw_pvp_join_ip:{$ip}", 80, 60);

    $pdo->beginTransaction();
    try {
        mw_cleanup_stale_queue($pdo);
        mw_cleanup_stale_queue_presence($pdo);
        $season = mw_ensure_season($pdo);
        $seasonId = (int) ($season['id'] ?? 0);

        $selfStmt = $pdo->prepare(
            "SELECT id, user_id, avatar_item_id, matched_with_user_id, created_at
             FROM knd_mind_wars_matchmaking_queue
             WHERE user_id = ? AND season_id = ? AND status = 'matched'
             ORDER BY matched_at DESC, id DESC
             LIMIT 1
             FOR UPDATE"
        );
        $selfStmt->execute([$userId, $seasonId]);
        $self = $selfStmt->fetch(PDO::FETCH_ASSOC);
        if (!$self) {
            $pdo->rollBack();
            json_error('NOT_MATCHED', 'No matched PvP queue entry found.', 409);
        }

        $opponentUserId = (int) ($self['matched_with_user_id'] ?? 0);
        if ($opponentUserId <= 0 || $opponentUserId === $userId) {
            $pdo->rollBack();
            json_error('MATCH_INVALID', 'Invalid matched opponent.', 409);
        }

        $oppStmt = $pdo->prepare(
            "SELECT id, user_id, avatar_item_id, matched_with_user_id, created_at
             FROM knd_mind_wars_matchmaking_queue
             WHERE user_id = ? AND season_id = ? AND status = 'matched'
             ORDER BY matched_at DESC, id DESC
             LIMIT 1
             FOR UPDATE"
        );
        $oppStmt->execute([$opponentUserId, $seasonId]);
        $opp = $oppStmt->fetch(PDO::FETCH_ASSOC);
        if (!$opp || (int) ($opp['matched_with_user_id'] ?? 0) !== $userId) {
            $pdo->rollBack();
            json_error('MATCH_INVALID', 'Match counterpart not ready.', 409);
        }

        $usernamesStmt = $pdo->prepare(
            "SELECT id, username FROM users WHERE id IN (?, ?) LIMIT 2"
        );
        $usernamesStmt->execute([$userId, $opponentUserId]);
        $usernames = [];
        while ($u = $usernamesStmt->fetch(PDO::FETCH_ASSOC)) {
            $usernames[(int) ($u['id'] ?? 0)] = (string) ($u['username'] ?? '');
        }

        $existing = $pdo->prepare(
            "SELECT b.id, b.battle_token, p.side
             FROM knd_mind_wars_battles b
             JOIN knd_mind_wars_battle_participants p ON p.battle_id = b.id
             JOIN knd_mind_wars_battle_participants p2 ON p2.battle_id = b.id
             WHERE b.mode = 'pvp_ranked'
               AND b.result IS NULL
               AND p.user_id = ?
               AND p2.user_id = ?
             ORDER BY b.id DESC
             LIMIT 1
             FOR UPDATE"
        );
        $existing->execute([$userId, $opponentUserId]);
        $battle = $existing->fetch(PDO::FETCH_ASSOC);
        if ($battle) {
            $stateStmt = $pdo->prepare("SELECT state_json FROM knd_mind_wars_battles WHERE id = ? LIMIT 1");
            $stateStmt->execute([(int) $battle['id']]);
            $state = json_decode((string) $stateStmt->fetchColumn(), true);
            $state = mw_normalize_battle_state(is_array($state) ? $state : []);
            $viewerUser = [
                'id' => $userId,
                'username' => (string) ($usernames[$userId] ?? ('user_' . $userId)),
            ];
            $opponentUser = [
                'id' => $opponentUserId,
                'username' => (string) ($usernames[$opponentUserId] ?? ('user_' . $opponentUserId)),
            ];
            $pdo->commit();
            json_success([
                'battle_token' => (string) $battle['battle_token'],
                'viewer_side' => (string) $battle['side'],
                'viewer_user' => $viewerUser,
                'opponent_user' => $opponentUser,
                'state' => $state,
            ]);
        }

        $selfAvatar = mw_validate_owned_avatar($pdo, $userId, (int) ($self['avatar_item_id'] ?? 0));
        $oppAvatar = mw_validate_owned_avatar($pdo, $opponentUserId, (int) ($opp['avatar_item_id'] ?? 0));
        if (!$selfAvatar || !$oppAvatar) {
            $pdo->rollBack();
            json_error('MATCH_INVALID', 'One of the matched avatars is no longer available.', 409);
        }

        $selfCreated = strtotime((string) ($self['created_at'] ?? 'now')) ?: time();
        $oppCreated = strtotime((string) ($opp['created_at'] ?? 'now')) ?: time();
        $selfIsPlayer = $selfCreated <= $oppCreated;

        $playerUserId = $selfIsPlayer ? $userId : $opponentUserId;
        $enemyUserId = $selfIsPlayer ? $opponentUserId : $userId;
        $playerAvatar = $selfIsPlayer ? $selfAvatar : $oppAvatar;
        $enemyAvatar = $selfIsPlayer ? $oppAvatar : $selfAvatar;

        $playerFighter = mw_build_fighter($playerAvatar, false);
        $enemyFighter = mw_build_fighter($enemyAvatar, false);
        $playerFirst = mw_roll_initiative($playerFighter, $enemyFighter);
        $battleToken = bin2hex(random_bytes(32));
        $state = mw_normalize_battle_state([
            'turn' => 1,
            'max_turns' => MW_MAX_TURNS,
            'player_first' => $playerFirst,
            'player' => $playerFighter,
            'enemy' => $enemyFighter,
            'log' => [[
                'type' => 'info',
                'msg' => 'PvP ranked battle initialized. Both players connected to same battle token.',
            ]],
            'next_actor' => $playerFirst ? 'player' : 'enemy',
            'player_next_attack_crit' => false,
            'meta' => [
                'mode' => 'pvp_ranked',
                'difficulty' => 'normal',
            ],
        ]);

        $insBattle = $pdo->prepare(
            "INSERT INTO knd_mind_wars_battles
             (battle_token, user_id, avatar_item_id, enemy_avatar_id, mode, state_json, turns_played)
             VALUES (?, ?, ?, ?, 'pvp_ranked', ?, 0)"
        );
        $insBattle->execute([
            $battleToken,
            $playerUserId,
            (int) ($playerAvatar['item_id'] ?? 0),
            (int) ($enemyAvatar['item_id'] ?? 0),
            json_encode($state, JSON_UNESCAPED_UNICODE),
        ]);
        $battleId = (int) $pdo->lastInsertId();

        $insPart = $pdo->prepare(
            "INSERT INTO knd_mind_wars_battle_participants (battle_id, user_id, avatar_item_id, side)
             VALUES (?, ?, ?, ?)"
        );
        $insPart->execute([$battleId, $playerUserId, (int) ($playerAvatar['item_id'] ?? 0), 'player']);
        $insPart->execute([$battleId, $enemyUserId, (int) ($enemyAvatar['item_id'] ?? 0), 'enemy']);

        $viewerSide = $userId === $playerUserId ? 'player' : 'enemy';
        $viewerUser = [
            'id' => $userId,
            'username' => (string) ($usernames[$userId] ?? ('user_' . $userId)),
        ];
        $opponentUser = [
            'id' => $opponentUserId,
            'username' => (string) ($usernames[$opponentUserId] ?? ('user_' . $opponentUserId)),
        ];
        $pdo->commit();
        json_success([
            'battle_token' => $battleToken,
            'viewer_side' => $viewerSide,
            'viewer_user' => $viewerUser,
            'opponent_user' => $opponentUser,
            'state' => $state,
        ]);
    } catch (\Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
} catch (\Throwable $e) {
    error_log('mind-wars/pvp_join_matched error: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'An unexpected error occurred.', 500);
}


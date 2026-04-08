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
require_once BASE_PATH . '/includes/knd_badges.php';
require_once BASE_PATH . '/includes/knowledge_duel.php';
require_once BASE_PATH . '/includes/mind_wars_rewards.php';

function mw_viewer_result(?string $baseResult, ?string $viewerSide): ?string {
    $baseResult = is_string($baseResult) ? strtolower($baseResult) : null;
    $viewerSide = is_string($viewerSide) ? strtolower($viewerSide) : null;
    if ($baseResult === null || $viewerSide !== 'enemy') {
        return $baseResult;
    }
    if ($baseResult === 'win') return 'lose';
    if ($baseResult === 'lose') return 'win';
    return $baseResult;
}

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
    rate_limit_guard($pdo, "mw_forfeit_user:{$userId}", 20, 60);

    $battleToken = trim((string) ($_POST['battle_token'] ?? ''));
    if (strlen($battleToken) < 32) {
        json_error('INVALID_REQUEST', 'Missing or invalid battle_token.');
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "SELECT id, user_id, avatar_item_id, mode, state_json, result, turns_played
             FROM knd_mind_wars_battles
             WHERE battle_token = ?
             LIMIT 1
             FOR UPDATE"
        );
        $stmt->execute([$battleToken]);
        $battle = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$battle) {
            $pdo->rollBack();
            json_error('BATTLE_NOT_FOUND', 'Battle not found.', 404);
        }

        $battleId = (int) ($battle['id'] ?? 0);
        $mode = mw_normalize_mode((string) ($battle['mode'] ?? 'pve'));
        $battleUserId = (int) ($battle['user_id'] ?? 0);

        if ($mode === 'pve') {
            if ($battleUserId !== $userId) {
                $pdo->rollBack();
                json_error('BATTLE_NOT_FOUND', 'Battle not found.', 404);
            }
            $existingResult = $battle['result'] !== null ? (string) $battle['result'] : null;
            if ($existingResult !== null) {
                $state = mw_normalize_battle_state(json_decode((string) $battle['state_json'], true) ?: []);
                $pdo->commit();
                json_success([
                    'state' => $state,
                    'battle_over' => true,
                    'result' => 'lose',
                    'rewards' => null,
                ]);
            }
            $state = mw_normalize_battle_state(json_decode((string) $battle['state_json'], true) ?: []);
            $state['log'][] = [
                'type' => 'status',
                'actor' => 'player',
                'turn' => (int) ($state['turn'] ?? 1),
                'action_type' => 'forfeit',
                'msg' => 'You surrendered the battle.',
            ];
            $state = mw_normalize_battle_state($state);
            $rewards = mw_rewards_for_result_in_mode('lose', $mode);
            $avatarItemId = (int) ($battle['avatar_item_id'] ?? 0);
            mw_apply_rewards_to_user($pdo, $userId, $avatarItemId, $rewards, 'lose');
            $upd = $pdo->prepare(
                "UPDATE knd_mind_wars_battles
                 SET result = 'lose', turns_played = ?, user_hp_final = ?, enemy_hp_final = ?, xp_gained = ?, knowledge_energy_gained = ?, rank_gained = ?, battle_log_json = ?, state_json = ?, updated_at = NOW()
                 WHERE id = ?"
            );
            $upd->execute([
                (int) ($battle['turns_played'] ?? 0),
                (int) ($state['player']['hp'] ?? 0),
                (int) ($state['enemy']['hp'] ?? 0),
                (int) ($rewards['xp'] ?? 0),
                (int) ($rewards['knowledge_energy'] ?? 0),
                (int) ($rewards['rank'] ?? 0),
                json_encode($state['log'], JSON_UNESCAPED_UNICODE),
                json_encode($state, JSON_UNESCAPED_UNICODE),
                $battleId,
            ]);
            $pdo->commit();
            json_success([
                'state' => $state,
                'battle_over' => true,
                'result' => 'lose',
                'rewards' => $rewards,
            ]);
        }

        if ($mode !== 'pvp_ranked') {
            $pdo->rollBack();
            json_error('INVALID_REQUEST', 'Forfeit is available only for PvP battles.');
        }

        $part = $pdo->prepare(
            "SELECT side FROM knd_mind_wars_battle_participants
             WHERE battle_id = ? AND user_id = ?
             LIMIT 1"
        );
        $part->execute([$battleId, $userId]);
        $viewerSide = (string) ($part->fetchColumn() ?: '');
        if (!in_array($viewerSide, ['player', 'enemy'], true)) {
            $pdo->rollBack();
            json_error('BATTLE_NOT_FOUND', 'Battle not found.', 404);
        }

        $existingResult = $battle['result'] !== null ? (string) $battle['result'] : null;
        if ($existingResult !== null) {
            $state = mw_normalize_battle_state(json_decode((string) $battle['state_json'], true) ?: []);
            $pdo->commit();
            json_success([
                'state' => $state,
                'battle_over' => true,
                'result' => mw_viewer_result($existingResult, $viewerSide),
                'rewards' => null,
            ]);
        }

        $state = mw_normalize_battle_state(json_decode((string) $battle['state_json'], true) ?: []);
        $baseResult = ($viewerSide === 'player') ? 'lose' : 'win';
        $viewerResult = mw_viewer_result($baseResult, $viewerSide);
        $turn = (int) ($state['turn'] ?? 1);
        $state['log'][] = [
            'type' => 'status',
            'actor' => $viewerSide,
            'turn' => $turn,
            'action_type' => 'forfeit',
            'msg' => 'Match ended by forfeit.',
        ];
        $state = mw_normalize_battle_state($state);

        $upd = $pdo->prepare(
            "UPDATE knd_mind_wars_battles
             SET result = ?, turns_played = ?, user_hp_final = ?, enemy_hp_final = ?, battle_log_json = ?, state_json = ?, updated_at = NOW()
             WHERE id = ?"
        );
        $upd->execute([
            $baseResult,
            (int) ($battle['turns_played'] ?? 0),
            (int) ($state['player']['hp'] ?? 0),
            (int) ($state['enemy']['hp'] ?? 0),
            json_encode($state['log'], JSON_UNESCAPED_UNICODE),
            json_encode($state, JSON_UNESCAPED_UNICODE),
            $battleId,
        ]);

        // Cancel any stale matched queue entries from this pair (schema-compatible).
        if (mw_queue_supports_presence_columns($pdo)) {
            $cancelQ = $pdo->prepare(
                "UPDATE knd_mind_wars_matchmaking_queue
                 SET status = 'cancelled', matched_with_user_id = NULL, matched_at = NULL, match_expires_at = NULL, updated_at = NOW()
                 WHERE status = 'matched'
                   AND user_id IN (
                     SELECT user_id FROM knd_mind_wars_battle_participants WHERE battle_id = ?
                   )"
            );
        } else {
            $cancelQ = $pdo->prepare(
                "UPDATE knd_mind_wars_matchmaking_queue
                 SET status = 'cancelled', matched_with_user_id = NULL, matched_at = NULL, updated_at = NOW()
                 WHERE status = 'matched'
                   AND user_id IN (
                     SELECT user_id FROM knd_mind_wars_battle_participants WHERE battle_id = ?
                   )"
            );
        }
        $cancelQ->execute([$battleId]);

        $partsStmt = $pdo->prepare("SELECT user_id FROM knd_mind_wars_battle_participants WHERE battle_id = ?");
        $partsStmt->execute([$battleId]);
        $mwTypes = ['mind_wars_wins', 'mind_wars_streak', 'mind_wars_special', 'mind_wars_legendary'];
        while ($pr = $partsStmt->fetch(PDO::FETCH_ASSOC)) {
            $partUserId = (int) ($pr['user_id'] ?? 0);
            if ($partUserId > 0) {
                foreach ($mwTypes as $t) {
                    badges_check_and_grant($pdo, $partUserId, $t);
                }
            }
        }

        $pdo->commit();
        json_success([
            'state' => $state,
            'battle_over' => true,
            'result' => $viewerResult,
            'rewards' => null,
        ]);
    } catch (\Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
} catch (\Throwable $e) {
    error_log('mind-wars/forfeit error: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'An unexpected error occurred.', 500);
}


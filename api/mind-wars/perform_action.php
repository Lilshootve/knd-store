<?php
require_once __DIR__ . '/../../config/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        error_log(sprintf('mind-wars/perform_action FATAL: %s in %s:%d', $err['message'] ?? 'unknown', $err['file'] ?? '?', $err['line'] ?? 0));
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo '{"ok":false,"error":{"code":"FATAL","message":"Server error. Please try again."}}';
    }
});

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/csrf.php';
require_once BASE_PATH . '/includes/json.php';
require_once BASE_PATH . '/includes/rate_limit.php';
require_once BASE_PATH . '/includes/mind_wars_arena_bootstrap.php';
require_once BASE_PATH . '/games/mindwars3v3/engine/PveKnockoutResolver.php';
require_once BASE_PATH . '/includes/knd_badges.php';
require_once BASE_PATH . '/includes/knowledge_duel.php';
require_once BASE_PATH . '/includes/mind_wars_rewards.php';
require_once BASE_PATH . '/includes/mind_wars_combo.php';

function mw_extract_cached_replay_payload(array $state): ?array {
    $meta = (array) ($state['meta'] ?? []);
    $cached = $meta['last_response'] ?? null;
    if (!is_array($cached)) {
        return null;
    }
    $cachedState = isset($cached['state']) && is_array($cached['state']) ? $cached['state'] : [];
    $cached['state'] = mw_normalize_battle_state($cachedState);
    $cached['battle_over'] = !empty($cached['battle_over']);
    $cached['result'] = isset($cached['result']) ? $cached['result'] : null;
    $cached['rewards'] = isset($cached['rewards']) && is_array($cached['rewards']) ? $cached['rewards'] : null;
    return [
        'state' => $cached['state'],
        'battle_over' => $cached['battle_over'],
        'result' => $cached['result'],
        'rewards' => $cached['rewards'],
    ];
}

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

function mw_get_pvp_participant_side(PDO $pdo, int $battleId, int $userId): ?string {
    $stmt = $pdo->prepare(
        "SELECT side FROM knd_mind_wars_battle_participants
         WHERE battle_id = ? AND user_id = ?
         LIMIT 1"
    );
    $stmt->execute([$battleId, $userId]);
    $side = $stmt->fetchColumn();
    if (!is_string($side)) {
        return null;
    }
    return in_array($side, ['player', 'enemy'], true) ? $side : null;
}

function mw_pvp_swap_state(array $state): array {
    $swapped = $state;
    $swapped['player'] = $state['enemy'];
    $swapped['enemy'] = $state['player'];
    $next = (string) ($state['next_actor'] ?? 'player');
    $swapped['next_actor'] = $next === 'player' ? 'enemy' : 'player';

    $enemyCrit = !empty($state['meta']['pvp_enemy_next_attack_crit']);
    $playerCrit = !empty($state['player_next_attack_crit']);
    $swapped['player_next_attack_crit'] = $enemyCrit;
    if (!isset($swapped['meta']) || !is_array($swapped['meta'])) {
        $swapped['meta'] = [];
    }
    $swapped['meta']['pvp_enemy_next_attack_crit'] = $playerCrit;
    return $swapped;
}

function mw_pvp_run_action(array $state, string $side, string $action): array {
    if ($side === 'player') {
        return mw_process_player_action($action, $state);
    }
    $preLogCount = is_array($state['log'] ?? null) ? count($state['log']) : 0;
    $swapped = mw_pvp_swap_state($state);
    $swapped = mw_process_player_action($action, $swapped);
    $result = mw_pvp_swap_state($swapped);

    // Remap only newly appended log actors back to canonical state orientation.
    if (!isset($result['log']) || !is_array($result['log'])) {
        return $result;
    }
    $total = count($result['log']);
    for ($i = max(0, $preLogCount); $i < $total; $i++) {
        if (!isset($result['log'][$i]) || !is_array($result['log'][$i])) {
            continue;
        }
        $actor = (string) ($result['log'][$i]['actor'] ?? '');
        if ($actor === 'player') {
            $result['log'][$i]['actor'] = 'enemy';
        } elseif ($actor === 'enemy') {
            $result['log'][$i]['actor'] = 'player';
        }
    }
    return $result;
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
    rate_limit_guard($pdo, "mw_action_user:{$userId}", 60, 60);

    $battleToken = trim((string) ($_POST['battle_token'] ?? ''));
    $actionId = trim((string) ($_POST['action_id'] ?? ''));
    
    // Check if this is a combo request
    $isComboRequest = mw_is_combo_request($_POST);
    $comboActions = $isComboRequest ? mw_parse_combo_actions($_POST) : null;
    $action = !$isComboRequest ? trim((string) ($_POST['action'] ?? '')) : '';
    
    // Validate request
    if (strlen($battleToken) < 32) {
        json_error('INVALID_REQUEST', 'Missing or invalid battle_token.');
    }
    
    if (!$isComboRequest && !in_array($action, ['attack', 'defend', 'ability', 'special', 'heal', 'advance'], true)) {
        json_error('INVALID_REQUEST', 'Invalid action.');
    }
    
    if ($isComboRequest && $comboActions === null) {
        json_error('INVALID_REQUEST', 'Invalid combo_actions format.');
    }
    
    if ($actionId !== '' && strlen($actionId) > 120) {
        json_error('INVALID_REQUEST', 'Invalid action_id.');
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "SELECT id, user_id, avatar_item_id, mode, state_json, result, turns_played
             FROM knd_mind_wars_battles
             WHERE battle_token = ?
             LIMIT 1 FOR UPDATE"
        );
        $stmt->execute([$battleToken]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $pdo->rollBack();
            json_error('BATTLE_NOT_FOUND', 'Battle not found.', 404);
        }
        $battleId = (int) ($row['id'] ?? 0);
        $mode = mw_normalize_mode((string) ($row['mode'] ?? 'pve'));
        $pvpSide = null;
        if ($mode === 'pvp_ranked') {
            $pvpSide = mw_get_pvp_participant_side($pdo, $battleId, $userId);
            if (!$pvpSide) {
                $pdo->rollBack();
                json_error('BATTLE_NOT_FOUND', 'Battle not found.', 404);
            }
        } elseif ((int) ($row['user_id'] ?? 0) !== $userId) {
            $pdo->rollBack();
            json_error('BATTLE_NOT_FOUND', 'Battle not found.', 404);
        }
        if ($row['result'] !== null) {
            $pdo->rollBack();
            json_error('BATTLE_ENDED', 'This battle has already ended.', 409);
        }

    $rawState = json_decode($row['state_json'], true);
    if (!is_array($rawState)) {
        $rawState = [
            'player' => [],
            'enemy' => [],
            'log' => [['type' => 'status', 'msg' => 'Recovered malformed battle state.']],
            'turn' => 1,
            'next_actor' => 'player',
        ];
    }
        $rawNextActor = (string) ($rawState['next_actor'] ?? 'player');
        if (!in_array($rawNextActor, ['player', 'enemy'], true)) {
            $pdo->rollBack();
            json_error('INVALID_BATTLE_STATE', 'Invalid battle actor state.', 409);
        }
        if (isset($rawState['turn']) && (int) $rawState['turn'] < 1) {
            $pdo->rollBack();
            json_error('INVALID_BATTLE_STATE', 'Invalid battle turn state.', 409);
        }
        $state = mw_normalize_battle_state($rawState);
        $difficulty = mw_normalize_difficulty((string) ($state['meta']['difficulty'] ?? 'normal'));
        $state['meta']['mode'] = $mode;
        $state['meta']['difficulty'] = $difficulty;
        if (!isset($state['meta']['pvp_enemy_next_attack_crit'])) {
            $state['meta']['pvp_enemy_next_attack_crit'] = false;
        }

        if ($actionId !== '' && (string) ($state['meta']['last_action_id'] ?? '') === $actionId) {
            $cached = mw_extract_cached_replay_payload($state);
            if ($cached) {
                $pdo->commit();
                json_success($cached);
            }
        }

        $isAdvance = ($action === 'advance');
        if ($mode === 'pvp_ranked') {
            if ($isAdvance) {
                $pdo->rollBack();
                json_error('INVALID_REQUEST', 'Advance is not available in PvP.');
            }
            if (($state['next_actor'] ?? '') !== $pvpSide) {
                $pdo->rollBack();
                json_error('NOT_YOUR_TURN', 'It is not your turn.');
            }
            $actor = $pvpSide === 'player' ? (array) ($state['player'] ?? []) : (array) ($state['enemy'] ?? []);
            if ($action === 'attack' && ((int) ($actor['energy'] ?? 0) < MW_ENERGY_ATTACK_COST)) {
                $pdo->rollBack();
                json_error('NOT_ENOUGH_ENERGY', 'Attack requires 1 Energy.');
            }
            if ($action === 'ability') {
                if ((int) ($actor['energy'] ?? 0) < MW_ENERGY_ABILITY_COST) {
                    $pdo->rollBack();
                    json_error('NOT_ENOUGH_ENERGY', 'Ability requires 2 Energy.');
                }
                if ((int) ($actor['ability_cooldown'] ?? 0) > 0) {
                    $pdo->rollBack();
                    json_error('ABILITY_ON_COOLDOWN', 'Ability is on cooldown.');
                }
            }
            if ($action === 'special' && ((int) ($actor['energy'] ?? 0) < MW_MAX_ENERGY)) {
                $pdo->rollBack();
                json_error('NOT_ENOUGH_ENERGY', 'Need 5 Energy to use Special.');
            }

            $state = mw_pvp_run_action($state, (string) $pvpSide, $action);
            $turnNow = (int) ($state['turn'] ?? 0);
            if ($pvpSide === 'player') {
                $playerEndEvents = mw_end_turn_phase($state['player'], $turnNow);
                mw_log_effect_events($state, 'player', $turnNow, $playerEndEvents, $action, null);
                if (!mw_has_effect($state['player'], 'next_attack_crit')) {
                    $state['player_next_attack_crit'] = false;
                    if (isset($state['player']['battle_bonus']['next_crit'])) {
                        unset($state['player']['battle_bonus']['next_crit']);
                    }
                }
            } else {
                $enemyEndEvents = mw_end_turn_phase($state['enemy'], $turnNow);
                mw_log_effect_events($state, 'enemy', $turnNow, $enemyEndEvents, $action, null);
                if (!mw_has_effect($state['enemy'], 'next_attack_crit')) {
                    $state['meta']['pvp_enemy_next_attack_crit'] = false;
                    if (isset($state['enemy']['battle_bonus']['next_crit'])) {
                        unset($state['enemy']['battle_bonus']['next_crit']);
                    }
                }
            }
        } else {
            if (!$isAdvance && ($state['next_actor'] ?? '') !== 'player') {
                $pdo->rollBack();
                json_error('NOT_YOUR_TURN', 'It is not your turn.');
            }
            if ($isAdvance && ($state['next_actor'] ?? '') !== 'enemy') {
                $pdo->rollBack();
                json_error('NOT_BOT_TURN', 'It is not the bot turn.');
            }

            if (!$isAdvance && $action === 'ability' && ($state['player']['energy'] ?? 0) < MW_ENERGY_ABILITY_COST) {
                $pdo->rollBack();
                json_error('NOT_ENOUGH_ENERGY', 'Ability requires 2 Energy.');
            }
            if (!$isAdvance && $action === 'ability' && ($state['player']['ability_cooldown'] ?? 0) > 0) {
                $pdo->rollBack();
                json_error('ABILITY_ON_COOLDOWN', 'Ability is on cooldown.');
            }
            if (!$isAdvance && $action === 'attack' && ($state['player']['energy'] ?? 0) < MW_ENERGY_ATTACK_COST) {
                $pdo->rollBack();
                json_error('NOT_ENOUGH_ENERGY', 'Attack requires 1 Energy.');
            }
            if (!$isAdvance && $action === 'special' && ($state['player']['energy'] ?? 0) < MW_MAX_ENERGY) {
                $pdo->rollBack();
                json_error('NOT_ENOUGH_ENERGY', 'Need 5 Energy to use Special.');
            }
            if (!$isAdvance && $action === 'heal') {
                if (empty(trim((string) ($state['player']['heal_code'] ?? '')))) {
                    $pdo->rollBack();
                    json_error('NO_HEAL_SKILL', 'This avatar has no heal skill.');
                }
                if (($state['player']['energy'] ?? 0) < 2) {
                    $pdo->rollBack();
                    json_error('NOT_ENOUGH_ENERGY', 'Heal requires 2 Energy.');
                }
            }

            $actionsPerformed = 1;
            
            if ($isAdvance) {
                $state = mw_process_bot_turn($state, $difficulty);
                $enemyEndEvents = mw_end_turn_phase($state['enemy'], (int) ($state['turn'] ?? 0));
                mw_log_effect_events($state, 'enemy', (int) ($state['turn'] ?? 0), $enemyEndEvents, 'advance', null);
                $state['next_actor'] = 'player';
                $state = mw_tick_cooldowns($state);
            } elseif ($isComboRequest && $comboActions !== null) {
                // Execute combo actions
                $validation = mw_validate_combo_actions($comboActions, $state);
                if (!$validation['valid']) {
                    $pdo->rollBack();
                    json_error('INVALID_COMBO', $validation['error']);
                }
                
                $state = mw_execute_combo_actions($comboActions, $state, $difficulty);
                $actionsPerformed = (int) ($state['meta']['last_combo_actions'] ?? count($comboActions));
            } else {
                $state = mw_process_player_action($action, $state);
                $playerEndEvents = mw_end_turn_phase($state['player'], (int) ($state['turn'] ?? 0));
                mw_log_effect_events($state, 'player', (int) ($state['turn'] ?? 0), $playerEndEvents, $action, null);
            }
            if (!mw_has_effect($state['player'], 'next_attack_crit')) {
                $state['player_next_attack_crit'] = false;
                if (isset($state['player']['battle_bonus']['next_crit'])) {
                    unset($state['player']['battle_bonus']['next_crit']);
                }
            }
        }

        $battleOver = false;
        $result = null;
        [$state, $battleOver, $result] = knd_mw3v3_resolve_pve_knockouts($pdo, $state, $difficulty, $battleOver, $result);

        if ($mode === 'pvp_ranked') {
            if (!$battleOver) {
                $state['turn']++;
                $state['next_actor'] = ($pvpSide === 'player') ? 'enemy' : 'player';
                $state = mw_tick_cooldowns($state);
            }
        } else {
            $ranPveCounterBot = false;
            if (!$battleOver && !$isAdvance && ($state['enemy']['hp'] ?? 0) > 0) {
                $state['next_actor'] = 'enemy';
                $state = mw_process_bot_turn($state, $difficulty);
                $enemyEndEvents = mw_end_turn_phase($state['enemy'], (int) ($state['turn'] ?? 0));
                mw_log_effect_events($state, 'enemy', (int) ($state['turn'] ?? 0), $enemyEndEvents, 'advance', null);
                $ranPveCounterBot = true;
            }
            if ($ranPveCounterBot) {
                [$state, $battleOver, $result] = knd_mw3v3_resolve_pve_knockouts($pdo, $state, $difficulty, $battleOver, $result);
            }

            if (!$battleOver && !$isAdvance) {
                $state['turn']++;
                $state['next_actor'] = 'player';
                $state = mw_tick_cooldowns($state);
            }
        }

        $turnsPlayed = (int) $row['turns_played'] + 1;
        $rewards = null;
        $viewerResult = $result;

        if (!$battleOver) {
            if (($state['next_actor'] ?? '') === 'player' && ($state['player']['energy'] ?? 0) < MW_ENERGY_ATTACK_COST) {
                $state['player']['energy'] = min(MW_MAX_ENERGY, (int) ($state['player']['energy'] ?? 0) + MW_ENERGY_STUCK_GAIN);
            }
            if (($state['next_actor'] ?? '') === 'enemy' && ($state['enemy']['energy'] ?? 0) < MW_ENERGY_ATTACK_COST) {
                $state['enemy']['energy'] = min(MW_MAX_ENERGY, (int) ($state['enemy']['energy'] ?? 0) + MW_ENERGY_STUCK_GAIN);
            }
        }
        if ($mode === 'pvp_ranked') {
            $viewerResult = mw_viewer_result($result, $pvpSide);
        }

        if ($battleOver) {
            if ($mode === 'pvp_ranked') {
                $playerResult = (string) $result;
                $enemyResult = mw_viewer_result($playerResult, 'enemy') ?? 'draw';
                $playerRewards = mw_rewards_for_result_in_mode($playerResult, $mode);
                $enemyRewards = mw_rewards_for_result_in_mode($enemyResult, $mode);

                $partsStmt = $pdo->prepare(
                    "SELECT user_id, avatar_item_id, side
                     FROM knd_mind_wars_battle_participants
                     WHERE battle_id = ?
                     LIMIT 2"
                );
                $partsStmt->execute([$battleId]);
                $parts = $partsStmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($parts as $pr) {
                    $partSide = (string) ($pr['side'] ?? '');
                    $partUserId = (int) ($pr['user_id'] ?? 0);
                    $partAvatarId = (int) ($pr['avatar_item_id'] ?? 0);
                    if ($partUserId <= 0 || ($partSide !== 'player' && $partSide !== 'enemy')) {
                        continue;
                    }
                    $partResult = $partSide === 'player' ? $playerResult : $enemyResult;
                    $partRewards = $partSide === 'player' ? $playerRewards : $enemyRewards;
                    mw_apply_rewards_to_user($pdo, $partUserId, $partAvatarId, $partRewards, $partResult);
                }

                $state['meta']['pvp_rewards'] = [
                    'player' => $playerRewards,
                    'enemy' => $enemyRewards,
                ];
                $rewards = $pvpSide === 'player' ? $playerRewards : $enemyRewards;
            } else {
                $rewards = mw_rewards_for_result_in_mode($result, $mode);
                $avatarItemId = (int) $row['avatar_item_id'];
                mw_apply_rewards_to_user($pdo, $userId, $avatarItemId, $rewards, (string) $result);
            }

            $state = mw_normalize_battle_state($state);
            $responsePayload = mw_build_cached_response_payload($state, true, $viewerResult, $rewards);
            if ($actionId !== '') {
                $state['meta']['last_action_id'] = $actionId;
                $state['meta']['last_action_at'] = gmdate('Y-m-d H:i:s');
                $state['meta']['last_actor'] = $mode === 'pvp_ranked' ? (string) $pvpSide : ($isAdvance ? 'enemy' : 'player');
                $state['meta']['last_response'] = $responsePayload;
            }
            $state = mw_normalize_battle_state($state);

            $upd = $pdo->prepare(
                "UPDATE knd_mind_wars_battles SET result = ?, turns_played = ?, user_hp_final = ?, enemy_hp_final = ?, xp_gained = ?, knowledge_energy_gained = ?, rank_gained = ?, battle_log_json = ?, state_json = ?, updated_at = NOW() WHERE id = ?"
            );
            $upd->execute([
                $result,
                $turnsPlayed,
                $state['player']['hp'],
                $state['enemy']['hp'],
                $rewards['xp'],
                $rewards['knowledge_energy'],
                $rewards['rank'],
                json_encode($state['log'], JSON_UNESCAPED_UNICODE),
                json_encode($state, JSON_UNESCAPED_UNICODE),
                $row['id'],
            ]);

            $mwTypes = ['mind_wars_wins', 'mind_wars_streak', 'mind_wars_special', 'mind_wars_legendary'];
            if ($mode === 'pvp_ranked') {
                foreach ($parts as $pr) {
                    $partUserId = (int) ($pr['user_id'] ?? 0);
                    if ($partUserId > 0) {
                        foreach ($mwTypes as $t) {
                            badges_check_and_grant($pdo, $partUserId, $t);
                        }
                    }
                }
            } else {
                foreach ($mwTypes as $t) {
                    badges_check_and_grant($pdo, $userId, $t);
                }
            }
        } else {
            $state = mw_normalize_battle_state($state);
            $responsePayload = mw_build_cached_response_payload($state, false, null, null);
            if ($actionId !== '') {
                $state['meta']['last_action_id'] = $actionId;
                $state['meta']['last_action_at'] = gmdate('Y-m-d H:i:s');
                $state['meta']['last_actor'] = $mode === 'pvp_ranked' ? (string) $pvpSide : ($isAdvance ? 'enemy' : 'player');
                $state['meta']['last_response'] = $responsePayload;
            }
            $state = mw_normalize_battle_state($state);
            $upd = $pdo->prepare("UPDATE knd_mind_wars_battles SET state_json = ?, turns_played = ?, updated_at = NOW() WHERE id = ?");
            $upd->execute([json_encode($state, JSON_UNESCAPED_UNICODE), $turnsPlayed, $row['id']]);
        }

        $pdo->commit();

        json_success([
            'state' => $state,
            'battle_over' => $battleOver,
            'result' => $viewerResult,
            'rewards' => $rewards,
        ]);
    } catch (\Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
} catch (\Throwable $e) {
    error_log('mind-wars/perform_action error: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', $e->getMessage(), 500);
}

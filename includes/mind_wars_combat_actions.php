<?php
/**
 * Mind Wars combat engine - extracted for CLI simulation and API use.
 * Requires mind_wars.php to be loaded first.
 */

if (!function_exists('mw_init_effects_container')) {
    require_once __DIR__ . '/mind_wars.php';
}

function mw_log_enrich(array $entry, string $actor, int $turn, ?string $action_type = null, ?string $skill_code = null, ?string $actor_name = null, ?string $target_name = null): array {
    $out = array_merge($entry, ['actor' => $actor, 'turn' => $turn, 'action_type' => $action_type, 'skill_code' => $skill_code]);
    if ($actor_name !== null) {
        $out['actor_name'] = $actor_name;
    }
    if ($target_name !== null) {
        $out['target'] = $target_name;
    }
    if (isset($entry['damage'])) {
        $out['value'] = (int) ($entry['damage'] ?? 0);
    }
    return $out;
}

/** Build log message for damage application - handles 0 damage with reason */
function mw_build_damage_log_msg(array $result, string $attackerName, string $defenderName, int $finalDmg, string $actionType, ?string $skillCode): array {
    $evaded = !empty($result['evaded']);
    if ($evaded) {
        $healAmt = (int) ($result['heal_attacker'] ?? 0);
        if ($healAmt > 0) {
            return ['type' => 'evade', 'msg' => $defenderName . ' evaded the attack! ' . $attackerName . ' healed ' . $healAmt . ' HP.', 'reason' => 'evaded', 'value' => 0];
        }
        return ['type' => 'evade', 'msg' => $defenderName . ' evaded!', 'reason' => 'evaded', 'value' => 0];
    }
    if ($finalDmg === 0) {
        $skill = mw_skill_display_name($skillCode);
        $special = (array) ($result['special_effects'] ?? []);
        if (!empty($result['stun_applied']) || !empty($special['petrify']) || !empty($special['stun']) || !empty($special['freeze'])) {
            return ['type' => 'ability_no_damage', 'msg' => $attackerName . ' used ' . $skill . ' - target petrified/stunned (no damage)!', 'reason' => 'petrify_stun', 'value' => 0];
        }
        if ($actionType === 'special') {
            return ['type' => 'ability_no_damage', 'msg' => $attackerName . ' used Special - no damage dealt.', 'reason' => 'ability_no_damage', 'value' => 0];
        }
        return ['type' => 'ability_no_damage', 'msg' => $attackerName . ' used ' . $skill . ' - no damage (effect only).', 'reason' => 'ability_no_damage', 'value' => 0];
    }
    $isCrit = !empty($result['crit']);
    if ($isCrit) {
        return ['type' => 'crit', 'msg' => 'Critical hit! ' . $finalDmg . ' damage', 'reason' => null, 'value' => $finalDmg];
    }
    return ['type' => 'damage', 'msg' => $finalDmg . ' damage', 'reason' => null, 'value' => $finalDmg];
}

/** Build log message for ability/special with 0 damage - explains the effect */
function mw_build_no_damage_msg(string $actorName, string $targetName, array $result, string $skillCode, string $actionType): string {
    $skill = mw_skill_display_name($skillCode);
    if (!empty($result['next_attack_crit'])) {
        return $actorName . ' used ' . $skill . ' - next attack will crit!';
    }
    if (!empty($result['shield_attacker'])) {
        return $actorName . ' used ' . $skill . ' - gained shield!';
    }
    if (!empty($result['heal_attacker'])) {
        return $actorName . ' used ' . $skill . ' - healed ' . (int) $result['heal_attacker'] . ' HP!';
    }
    if (!empty($result['enemy_lose_energy'])) {
        return $actorName . ' used ' . $skill . ' - ' . $targetName . ' loses ' . (int) $result['enemy_lose_energy'] . ' energy.';
    }
    if (!empty($result['stun_applied'])) {
        return $actorName . ' used ' . $skill . ' - target petrified (no damage)!';
    }
    return $actorName . ' used ' . $skill . ' - no damage (effect only).';
}

function mw_log_effect_events(array &$state, string $actor, int $turn, array $events, ?string $action_type = null, ?string $skill_code = null, ?string $attackerName = null, ?string $targetName = null): void {
    $skill = $skill_code ? mw_skill_display_name($skill_code) : null;
    $ccTypes = ['stun' => 1, 'freeze' => 1, 'petrify' => 1];
    foreach ($events as $event) {
        if (is_string($event)) {
            $msg = ucfirst($event) . ' applied.';
            $logType = 'status';
            $logValue = 0;
            if (isset($ccTypes[$event])) {
                $logType = 'cc';
                $logValue = 1;
            }
            if ($attackerName && $skill) {
                if ($event === 'shield') {
                    $msg = $attackerName . ' gained shield from ' . $skill . '!';
                } elseif ($targetName && $event === 'stun') {
                    $msg = $targetName . ' was stunned by ' . $attackerName . '\'s ' . $skill . '!';
                } elseif ($event === 'freeze') {
                    $msg = $targetName . ' was frozen by ' . $attackerName . '\'s ' . $skill . '!';
                } elseif ($event === 'petrify') {
                    $msg = $targetName . ' was petrified by ' . $attackerName . '\'s ' . $skill . '!';
                } elseif ($event === 'chill') {
                    $msg = $targetName . ' was chilled by ' . $attackerName . '\'s ' . $skill . '!';
                } elseif ($event === 'focus_down') {
                    $msg = $targetName . '\'s focus was reduced by ' . $attackerName . '\'s ' . $skill . '!';
                } elseif ($event === 'next_attack_crit') {
                    $msg = $attackerName . ' - next attack will crit!';
                }
            }
            $entry = ['type' => $logType, 'msg' => $msg, 'value' => $logValue];
            if ($attackerName !== null) $entry['actor_name'] = $attackerName;
            if ($targetName !== null) $entry['target'] = $targetName;
            $state['log'][] = mw_log_enrich($entry, $actor, $turn, $action_type, $skill_code);
            continue;
        }
        if (!is_array($event)) {
            continue;
        }
        if (($event['type'] ?? '') === 'shock') {
            $dmg = (int) ($event['damage'] ?? 0);
            $entry = ['type' => 'status', 'msg' => 'Shock deals ' . $dmg . ' damage.', 'value' => $dmg];
            if ($attackerName !== null) $entry['actor_name'] = $attackerName;
            if ($targetName !== null) $entry['target'] = $targetName;
            $state['log'][] = mw_log_enrich($entry, $actor, $turn, $action_type, $skill_code);
            continue;
        }
        if (($event['type'] ?? '') === 'turn_blocked') {
            $effect = (string) ($event['effect'] ?? 'control');
            $entry = ['type' => 'cc', 'msg' => 'Action blocked by ' . $effect . '.', 'value' => 1];
            if ($attackerName !== null) $entry['actor_name'] = $attackerName;
            if ($targetName !== null) $entry['target'] = $targetName;
            $state['log'][] = mw_log_enrich($entry, $actor, $turn, $action_type, $skill_code);
            continue;
        }
    }
}

function mw_apply_damage(array $state, string $target, int $dmg, array $logEntry = [], array $context = []): array {
    $key = $target === 'player' ? 'player' : 'enemy';
    $attackerKey = $target === 'player' ? 'enemy' : 'player';
    $targetName = (string) ($state[$key]['name'] ?? ($key === 'player' ? 'Player' : 'Enemy'));
    $attackerName = (string) ($state[$attackerKey]['name'] ?? ($attackerKey === 'player' ? 'Player' : 'Enemy'));

    mw_init_effects_container($state[$key]);
    $defenderWasDefending = !empty($state[$key]['defending']);
    $shield = (array) ($state[$key]['effects']['shield'] ?? []);
    $shieldAbsorbed = 0;
    $rawDmg = $dmg;
    if (!empty($shield) && $dmg > 0) {
        $absorb = max(0, (int) round((float) ($shield['potency'] ?? 0)));
        if ($absorb > 0) {
            $blocked = min($dmg, $absorb);
            $shieldAbsorbed = $blocked;
            $dmg -= $blocked;
            $remaining = $absorb - $blocked;
            if ($remaining <= 0) {
                mw_remove_effect($state[$key], 'shield');
                if (isset($state[$key]['states']['shield'])) {
                    unset($state[$key]['states']['shield']);
                }
            } else {
                $state[$key]['effects']['shield']['potency'] = $remaining;
            }
        }
    }
    $state[$key]['hp'] = max(0, $state[$key]['hp'] - $dmg);
    $state[$key]['defending'] = false;
    if (!empty($logEntry)) {
        $logEntry['damage'] = $dmg;
        $logEntry['damage_raw'] = $rawDmg;
        $logEntry['shield_absorbed'] = $shieldAbsorbed;
        $logEntry['value'] = $dmg;
        $logEntry['target'] = $targetName;
        $logEntry['actor_name'] = $attackerName;
        if ($shieldAbsorbed > 0) {
            $shieldEntry = array_merge($logEntry, [
                'type' => 'shield_block',
                'msg' => $targetName . '\'s shield absorbed ' . $shieldAbsorbed . ' damage.',
                'shield_absorbed' => $shieldAbsorbed,
                'value' => 0,
                'target' => $targetName,
                'actor_name' => $attackerName,
            ]);
            $state['log'][] = $shieldEntry;
        }
        if (isset($logEntry['msg']) && is_string($logEntry['msg'])) {
            if ($dmg === 0 && $shieldAbsorbed > 0) {
                $logEntry['reason'] = 'shield_full';
                $logEntry['msg'] = 'Shield blocked all damage. 0 dealt.';
            } elseif ($dmg > 0 && $shieldAbsorbed > 0) {
                $logEntry['msg'] = 'Shield absorbed ' . $shieldAbsorbed . '. ' . $dmg . ' damage dealt.';
            } elseif ($dmg > 0 && $defenderWasDefending) {
                $logEntry['reason'] = 'defending';
                $logEntry['msg'] = 'Defending reduced damage. ' . $dmg . ' dealt.';
            } else {
                $logEntry['msg'] = preg_replace('/\d+\s+damage/i', $dmg . ' damage', $logEntry['msg']) ?? $logEntry['msg'];
            }
        }
        $state['log'][] = $logEntry;
    }
    return $state;
}

function mw_process_player_action(string $action, array $state): array {
    $player = &$state['player'];
    $enemy = &$state['enemy'];
    $turn = (int) ($state['turn'] ?? 0);
    $playerName = (string) ($player['name'] ?? 'Player');
    $enemyName = (string) ($enemy['name'] ?? 'Enemy');
    mw_init_effects_container($player);
    mw_init_effects_container($enemy);
    if (!isset($player['states']) || !is_array($player['states'])) $player['states'] = [];
    if (!isset($enemy['states']) || !is_array($enemy['states'])) $enemy['states'] = [];

    if (($player['energy'] ?? 0) < MW_ENERGY_ATTACK_COST) {
        $player['energy'] = min(MW_MAX_ENERGY, (int) ($player['energy'] ?? 0) + MW_ENERGY_STUCK_GAIN);
    }

    $phase = mw_start_turn_phase($player, $turn);
    if (empty($phase['can_act'])) {
        $blockedBy = (string) ($phase['blocked_by'] ?? 'control');
        $state['log'][] = mw_log_enrich(['type' => 'status', 'msg' => $playerName . ' is affected by ' . $blockedBy . ' and misses the turn.', 'value' => 0], 'player', $turn, $action, null, $playerName, $enemyName);
        return $state;
    }

    $ctx = ['state' => &$state, 'turn' => $turn, 'actor' => 'player', 'action' => $action, 'attacker' => &$player, 'defender' => &$enemy];
    mw_execute_skill($player['passive_code'] ?? null, $ctx);

    if ($action === 'defend') {
        $player['defending'] = true;
        $state['log'][] = mw_log_enrich(['type' => 'info', 'msg' => $playerName . ' defends!', 'value' => 0], 'player', $turn, 'defend', null, $playerName, null);
        return $state;
    }

    if ($action === 'heal') {
        $code = $player['heal_code'] ?? null;
        if (!$code || $player['energy'] < 2) {
            return $state;
        }
        $player['energy'] = max(0, $player['energy'] - 2);
        $ctx = ['state' => &$state, 'turn' => $turn, 'actor' => 'player', 'action' => 'heal', 'attacker' => &$player, 'defender' => &$enemy];
        mw_execute_skill($code, $ctx);
        $result = mw_execute_ability($code, $player, $enemy);
        if ($result === null) {
            $result = ['heal_attacker' => mw_calculate_heal($player, 12), 'energy_gain_attacker' => MW_ENERGY_ATTACK, 'log' => null];
        }
        if (!empty($result['heal_attacker'])) {
            $player['hp'] = min($player['hp_max'], $player['hp'] + $result['heal_attacker']);
            $healMsg = !empty($result['log']) && is_string($result['log'])
                ? $result['log']
                : $playerName . ' healed ' . $result['heal_attacker'] . ' HP!';
            $state['log'][] = mw_log_enrich(['type' => 'heal', 'msg' => $healMsg, 'value' => 0], 'player', $turn, 'heal', $code, $playerName, null);
        }
        $player['energy'] = min(MW_MAX_ENERGY, $player['energy'] + ($result['energy_gain_attacker'] ?? MW_ENERGY_ATTACK));
        return $state;
    }

    if ($action === 'attack') {
        if (($player['energy'] ?? 0) < MW_ENERGY_ATTACK_COST) {
            return $state;
        }
        $player['energy'] = max(0, (int) ($player['energy'] ?? 0) - MW_ENERGY_ATTACK_COST);
        $guaranteedCrit = !empty($state['player_next_attack_crit']) || !empty($player['battle_bonus']['next_crit']) || mw_has_effect($player, 'next_attack_crit');
        $result = mw_resolve_attack($player, $enemy, $guaranteedCrit);
        $state['player_next_attack_crit'] = false;
        if (isset($player['battle_bonus']['next_crit'])) unset($player['battle_bonus']['next_crit']);
        mw_consume_attack_one_shots($player);
        $state = mw_apply_damage($state, 'enemy', $result['damage'], mw_log_enrich([
            'type' => $result['evaded'] ? 'evade' : ($result['crit'] ? 'crit' : 'damage'),
            'msg' => $result['evaded'] ? $enemyName . ' evaded!' : ($result['crit'] ? 'Critical hit! ' . $result['damage'] . ' damage' : $result['damage'] . ' damage'),
            'damage' => $result['damage'],
        ], 'player', $turn, 'attack', null, $playerName, $enemyName));
        $player['energy'] = min(MW_MAX_ENERGY, $player['energy'] + ($result['energy_gain_attacker'] ?? MW_ENERGY_ATTACK));
        if ($result['damage'] > 0) {
            $enemy['energy'] = min(MW_MAX_ENERGY, $enemy['energy'] + ($result['energy_gain_defender'] ?? MW_ENERGY_DAMAGE));
        }
        if (!empty($result['heal_attacker'])) {
            $player['hp'] = min($player['hp_max'], $player['hp'] + $result['heal_attacker']);
            $state['log'][] = mw_log_enrich(['type' => 'heal', 'msg' => $playerName . ' healed ' . $result['heal_attacker'] . ' HP', 'value' => 0], 'player', $turn, 'attack', null, $playerName, null);
        }
        if (($player['passive_code'] ?? '') === 'spark_of_genius' && !empty($result['crit'])) {
            $player['energy'] = min(MW_MAX_ENERGY, $player['energy'] + 1);
        }
        mw_remove_effect($player, 'damage_up_once');
        return $state;
    }

    if ($action === 'ability') {
        if (($player['ability_cooldown'] ?? 0) > 0) {
            return $state;
        }
        if (($player['energy'] ?? 0) < MW_ENERGY_ABILITY_COST) {
            return $state;
        }
        $code = $player['ability_code'] ?? null;
        if (!$code) {
            return $state;
        }
        $player['energy'] = max(0, (int) ($player['energy'] ?? 0) - MW_ENERGY_ABILITY_COST);
        $ctx = ['state' => &$state, 'turn' => $turn, 'actor' => 'player', 'action' => 'ability', 'attacker' => &$player, 'defender' => &$enemy];
        mw_execute_skill($code, $ctx);
        $result = mw_execute_ability($code, $player, $enemy);
        if ($result === null) {
            return $state;
        }
        if (!empty($result['next_attack_crit'])) {
            $state['player_next_attack_crit'] = true;
            $state['log'][] = mw_log_enrich(['type' => 'info', 'msg' => $playerName . ' used ' . mw_skill_display_name($player['ability_code'] ?? null) . ' - next attack will crit!', 'value' => 0], 'player', $turn, 'ability', $player['ability_code'] ?? null, $playerName, $enemyName);
        }
        if (isset($result['damage']) && $result['damage'] > 0) {
            $dmgMsg = !empty($result['log']) && is_string($result['log'])
                ? $result['log']
                : $playerName . ' used ability! ' . $result['damage'] . ' damage';
            $state = mw_apply_damage($state, 'enemy', $result['damage'], mw_log_enrich([
                'type' => !empty($result['crit']) ? 'crit' : 'damage',
                'msg' => $dmgMsg,
                'damage' => $result['damage'],
            ], 'player', $turn, 'ability', $player['ability_code'] ?? null, $playerName, $enemyName));
            $player['energy'] = min(MW_MAX_ENERGY, $player['energy'] + ($result['energy_gain_attacker'] ?? MW_ENERGY_ATTACK));
            if ($result['damage'] > 0) {
                $enemy['energy'] = min(MW_MAX_ENERGY, $enemy['energy'] + ($result['energy_gain_defender'] ?? MW_ENERGY_DAMAGE));
            }
            if (!empty($result['heal_attacker'])) {
                $player['hp'] = min($player['hp_max'], $player['hp'] + $result['heal_attacker']);
                $state['log'][] = mw_log_enrich(['type' => 'heal', 'msg' => $playerName . ' healed ' . $result['heal_attacker'] . ' HP', 'value' => 0], 'player', $turn, 'ability', $player['ability_code'] ?? null, $playerName, null);
            }
        } elseif (!empty($result['heal_attacker'])) {
            $player['hp'] = min($player['hp_max'], $player['hp'] + $result['heal_attacker']);
            $healMsg = !empty($result['log']) && is_string($result['log'])
                ? $result['log']
                : $playerName . ' used ' . mw_skill_display_name($player['ability_code'] ?? null) . ' - healed ' . $result['heal_attacker'] . ' HP!';
            $state['log'][] = mw_log_enrich(['type' => 'heal', 'msg' => $healMsg, 'value' => 0], 'player', $turn, 'ability', $player['ability_code'] ?? null, $playerName, null);
        } elseif ((int) ($result['damage'] ?? 0) === 0 && (!empty($result['shield_attacker']) || !empty($result['enemy_lose_energy']) || !empty($result['stun_applied']))) {
            $msg = mw_build_no_damage_msg($playerName, $enemyName, $result, (string) ($player['ability_code'] ?? 'ability'), 'ability');
            $state['log'][] = mw_log_enrich(['type' => 'status', 'msg' => $msg, 'value' => 0], 'player', $turn, 'ability', $player['ability_code'] ?? null, $playerName, $enemyName);
        }
        if (!empty($result['focus_up_once'])) {
            $player['battle_bonus']['focus_up_once'] = $result['focus_up_once'];
        }
        if (!empty($result['crit_damage_up_temp'])) {
            $player['battle_bonus']['crit_damage_up_temp'] = $result['crit_damage_up_temp'];
        }
        if (!empty($result['energy_bonus'])) {
            $player['energy'] = min(MW_MAX_ENERGY, $player['energy'] + (int) $result['energy_bonus']);
        }
        if (!empty($result['enemy_lose_energy'])) {
            $enemy['energy'] = max(0, (int) $enemy['energy'] - (int) $result['enemy_lose_energy']);
            $state['log'][] = mw_log_enrich(['type' => 'status', 'msg' => $enemyName . ' loses ' . (int) $result['enemy_lose_energy'] . ' energy.', 'value' => 0], 'player', $turn, 'ability', $player['ability_code'] ?? null, $playerName, $enemyName);
        }
        $effectEvents = mw_apply_effect_payload($player, $enemy, $result, (string) $code, $turn);
        mw_log_effect_events($state, 'player', $turn, $effectEvents, 'ability', $player['ability_code'] ?? null, $playerName, $enemyName);
        $player['ability_cooldown'] = 3;
        return $state;
    }

    if ($action === 'special') {
        if ($player['energy'] < MW_MAX_ENERGY) {
            return $state;
        }
        $code = $player['special_code'] ?? null;
        if (!$code) {
            return $state;
        }
        $ctx = ['state' => &$state, 'turn' => $turn, 'actor' => 'player', 'action' => 'special', 'attacker' => &$player, 'defender' => &$enemy];
        mw_execute_skill($code, $ctx);
        $result = mw_execute_special($code, $player, $enemy);
        if ($result === null) {
            $state['log'][] = mw_log_enrich(['type' => 'status', 'msg' => 'Action failed: no handler for special ' . $code, 'value' => 0], 'player', $turn, 'special', $code, $playerName, $enemyName);
            return $state;
        }
        $dmg = (int) ($result['damage'] ?? 0);
        $built = mw_build_damage_log_msg($result, $playerName, $enemyName, $dmg, 'special', $player['special_code'] ?? null);
        if (!empty($result['log']) && is_string($result['log'])) {
            $built['msg'] = $result['log'];
        }
        $state = mw_apply_damage($state, 'enemy', $dmg, mw_log_enrich(array_merge($built, ['damage' => $dmg]), 'player', $turn, 'special', $player['special_code'] ?? null, $playerName, $enemyName));
        $player['energy'] = 0;
        if (($result['damage'] ?? 0) > 0) {
            $enemy['energy'] = min(MW_MAX_ENERGY, $enemy['energy'] + ($result['energy_gain_defender'] ?? MW_ENERGY_DAMAGE));
        }
        if (!empty($result['heal_attacker'])) {
            $player['hp'] = min($player['hp_max'], $player['hp'] + $result['heal_attacker']);
            if (empty($result['evaded'])) {
                $state['log'][] = mw_log_enrich(['type' => 'heal', 'msg' => $playerName . ' healed ' . $result['heal_attacker'] . ' HP', 'value' => 0], 'player', $turn, 'special', $player['special_code'] ?? null, $playerName, null);
            }
        }
        $effectEvents = mw_apply_effect_payload($player, $enemy, $result, (string) $code, $turn);
        mw_log_effect_events($state, 'player', $turn, $effectEvents, 'special', $player['special_code'] ?? null, $playerName, $enemyName);
        return $state;
    }

    return $state;
}

function mw_process_bot_turn(array $state, string $difficulty = 'normal'): array {
    $bot = &$state['enemy'];
    $player = &$state['player'];
    $turn = (int) ($state['turn'] ?? 0);
    $botName = (string) ($bot['name'] ?? 'Enemy');
    $playerName = (string) ($player['name'] ?? 'Player');
    mw_init_effects_container($bot);
    mw_init_effects_container($player);
    if (!isset($bot['states']) || !is_array($bot['states'])) $bot['states'] = [];
    if (!isset($player['states']) || !is_array($player['states'])) $player['states'] = [];

    if (($bot['energy'] ?? 0) < MW_ENERGY_ATTACK_COST) {
        $bot['energy'] = min(MW_MAX_ENERGY, (int) ($bot['energy'] ?? 0) + MW_ENERGY_STUCK_GAIN);
    }

    $phase = mw_start_turn_phase($bot, $turn);
    if (empty($phase['can_act'])) {
        $blockedBy = (string) ($phase['blocked_by'] ?? 'control');
        $state['log'][] = mw_log_enrich(['type' => 'status', 'msg' => $botName . ' is affected by ' . $blockedBy . ' and misses the turn.', 'value' => 0], 'enemy', $turn, 'advance', null, $botName, $playerName);
        return $state;
    }
    $action = mw_bot_choose_action_with_difficulty($bot, $player, $difficulty);

    $ctx = ['state' => &$state, 'turn' => $turn, 'actor' => 'enemy', 'action' => $action, 'attacker' => &$bot, 'defender' => &$player];
    mw_execute_skill($bot['passive_code'] ?? null, $ctx);

    if ($action === 'defend') {
        $bot['defending'] = true;
        $state['log'][] = mw_log_enrich(['type' => 'info', 'msg' => $botName . ' defends!', 'value' => 0], 'enemy', $turn, 'defend', null, $botName, null);
        return $state;
    }

    if ($action === 'attack') {
        if (($bot['energy'] ?? 0) < MW_ENERGY_ATTACK_COST) {
            $bot['defending'] = true;
            $state['log'][] = mw_log_enrich(['type' => 'info', 'msg' => $botName . ' defends (no energy to attack).', 'value' => 0], 'enemy', $turn, 'defend', null, $botName, null);
            return $state;
        }
        $bot['energy'] = max(0, (int) ($bot['energy'] ?? 0) - MW_ENERGY_ATTACK_COST);
        $result = mw_resolve_attack($bot, $player);
        $state = mw_apply_damage($state, 'player', $result['damage'], mw_log_enrich([
            'type' => $result['evaded'] ? 'evade' : ($result['crit'] ? 'crit' : 'damage'),
            'msg' => $result['evaded'] ? $playerName . ' evaded!' : $botName . ' attacks! ' . $result['damage'] . ' damage',
            'damage' => $result['damage'],
        ], 'enemy', $turn, 'attack', null, $botName, $playerName));
        $bot['energy'] = min(MW_MAX_ENERGY, $bot['energy'] + ($result['energy_gain_attacker'] ?? MW_ENERGY_ATTACK));
        if ($result['damage'] > 0) {
            $player['energy'] = min(MW_MAX_ENERGY, $player['energy'] + MW_ENERGY_DAMAGE);
        }
        if (!empty($result['heal_attacker'])) {
            $bot['hp'] = min($bot['hp_max'], $bot['hp'] + $result['heal_attacker']);
            $state['log'][] = mw_log_enrich(['type' => 'heal', 'msg' => $botName . ' healed ' . $result['heal_attacker'] . ' HP', 'value' => 0], 'enemy', $turn, 'attack', null, $botName, null);
        }
        mw_consume_attack_one_shots($bot);
        return $state;
    }

    if ($action === 'ability' && ($bot['ability_cooldown'] ?? 0) <= 0 && ($bot['ability_code'] ?? null) && ($bot['energy'] ?? 0) >= MW_ENERGY_ABILITY_COST) {
        $bot['energy'] = max(0, (int) ($bot['energy'] ?? 0) - MW_ENERGY_ABILITY_COST);
        $ctx = ['state' => &$state, 'turn' => $turn, 'actor' => 'enemy', 'action' => 'ability', 'attacker' => &$bot, 'defender' => &$player];
        mw_execute_skill($bot['ability_code'], $ctx);
        $result = mw_execute_ability($bot['ability_code'], $bot, $player);
        if ($result && isset($result['damage']) && $result['damage'] > 0) {
            $dmgMsg = !empty($result['log']) && is_string($result['log'])
                ? $result['log']
                : $botName . ' used ability! ' . $result['damage'] . ' damage';
            $state = mw_apply_damage($state, 'player', $result['damage'], mw_log_enrich([
                'type' => 'damage',
                'msg' => $dmgMsg,
                'damage' => $result['damage'],
            ], 'enemy', $turn, 'ability', $bot['ability_code'], $botName, $playerName));
            $bot['energy'] = min(MW_MAX_ENERGY, $bot['energy'] + ($result['energy_gain_attacker'] ?? MW_ENERGY_ATTACK));
            $player['energy'] = min(MW_MAX_ENERGY, $player['energy'] + ($result['energy_gain_defender'] ?? 0));
        } elseif ($result && (int) ($result['damage'] ?? 0) === 0) {
            $msg = !empty($result['log']) && is_string($result['log'])
                ? $result['log']
                : mw_build_no_damage_msg($botName, $playerName, $result, (string) ($bot['ability_code'] ?? 'ability'), 'ability');
            $logType = !empty($result['heal_attacker']) ? 'heal' : 'status';
            $state['log'][] = mw_log_enrich(['type' => $logType, 'msg' => $msg, 'value' => 0], 'enemy', $turn, 'ability', $bot['ability_code'], $botName, $playerName);
        }
        if ($result && !empty($result['heal_attacker']) && (int) ($result['damage'] ?? 0) > 0) {
            $bot['hp'] = min($bot['hp_max'], $bot['hp'] + $result['heal_attacker']);
            $healMsg = !empty($result['log']) && is_string($result['log'])
                ? $result['log']
                : $botName . ' healed ' . $result['heal_attacker'] . ' HP';
            $state['log'][] = mw_log_enrich(['type' => 'heal', 'msg' => $healMsg, 'value' => 0], 'enemy', $turn, 'ability', $bot['ability_code'], $botName, null);
        } elseif ($result && !empty($result['heal_attacker'])) {
            $bot['hp'] = min($bot['hp_max'], $bot['hp'] + $result['heal_attacker']);
        }
        if ($result && !empty($result['enemy_lose_energy'])) {
            $player['energy'] = max(0, (int) $player['energy'] - (int) $result['enemy_lose_energy']);
            if ((int) ($result['damage'] ?? 0) > 0) {
                $state['log'][] = mw_log_enrich(['type' => 'status', 'msg' => $playerName . ' loses ' . (int) $result['enemy_lose_energy'] . ' energy.', 'value' => 0], 'enemy', $turn, 'ability', $bot['ability_code'], $botName, $playerName);
            }
        }
        $effectEvents = $result ? mw_apply_effect_payload($bot, $player, $result, (string) ($bot['ability_code'] ?? 'ability'), $turn) : [];
        mw_log_effect_events($state, 'enemy', $turn, $effectEvents, 'ability', $bot['ability_code'] ?? null, $botName, $playerName);
        $bot['ability_cooldown'] = 3;
        return $state;
    }

    if ($action === 'special' && $bot['energy'] >= MW_MAX_ENERGY && ($bot['special_code'] ?? null)) {
        $ctx = ['state' => &$state, 'turn' => $turn, 'actor' => 'enemy', 'action' => 'special', 'attacker' => &$bot, 'defender' => &$player];
        mw_execute_skill($bot['special_code'], $ctx);
        $result = mw_execute_special($bot['special_code'], $bot, $player);
        if ($result === null) {
            $state['log'][] = mw_log_enrich(['type' => 'status', 'msg' => 'Action failed: no handler for special ' . ($bot['special_code'] ?? 'unknown'), 'value' => 0], 'enemy', $turn, 'special', $bot['special_code'] ?? null, $botName, $playerName);
            $bot['energy'] = 0;
            return $state;
        }
        $dmg = (int) ($result['damage'] ?? 0);
        $built = mw_build_damage_log_msg($result, $botName, $playerName, $dmg, 'special', $bot['special_code']);
        if (!empty($result['log']) && is_string($result['log'])) {
            $built['msg'] = $result['log'];
        }
        $state = mw_apply_damage($state, 'player', $dmg, mw_log_enrich(array_merge($built, ['damage' => $dmg]), 'enemy', $turn, 'special', $bot['special_code'], $botName, $playerName));
        $bot['energy'] = 0;
        if (($result['damage'] ?? 0) > 0) {
            $player['energy'] = min(MW_MAX_ENERGY, $player['energy'] + MW_ENERGY_DAMAGE);
        }
        if (!empty($result['heal_attacker'])) {
            $bot['hp'] = min($bot['hp_max'], $bot['hp'] + $result['heal_attacker']);
            if (empty($result['evaded'])) {
                $state['log'][] = mw_log_enrich(['type' => 'heal', 'msg' => $botName . ' healed ' . $result['heal_attacker'] . ' HP', 'value' => 0], 'enemy', $turn, 'special', $bot['special_code'] ?? null, $botName, null);
            }
        }
        $effectEvents = mw_apply_effect_payload($bot, $player, $result, (string) ($bot['special_code'] ?? 'special'), $turn);
        mw_log_effect_events($state, 'enemy', $turn, $effectEvents, 'special', $bot['special_code'] ?? null, $botName, $playerName);
        return $state;
    }

    if (($bot['energy'] ?? 0) < MW_ENERGY_ATTACK_COST) {
        $bot['defending'] = true;
        $state['log'][] = mw_log_enrich(['type' => 'info', 'msg' => $botName . ' defends (no energy to attack).', 'value' => 0], 'enemy', $turn, 'defend', null, $botName, null);
        return $state;
    }
    $bot['energy'] = max(0, (int) ($bot['energy'] ?? 0) - MW_ENERGY_ATTACK_COST);
    $result = mw_resolve_attack($bot, $player);
    $state = mw_apply_damage($state, 'player', $result['damage'], mw_log_enrich([
        'type' => $result['evaded'] ? 'evade' : 'damage',
        'msg' => $result['evaded'] ? $playerName . ' evaded!' : $botName . ' attacks! ' . $result['damage'] . ' damage',
        'damage' => $result['damage'],
    ], 'enemy', $turn, 'attack', null, $botName, $playerName));
    $bot['energy'] = min(MW_MAX_ENERGY, $bot['energy'] + ($result['energy_gain_attacker'] ?? MW_ENERGY_ATTACK));
    if ($result['damage'] > 0) {
        $player['energy'] = min(MW_MAX_ENERGY, $player['energy'] + MW_ENERGY_DAMAGE);
    }
    if (!empty($result['heal_attacker'])) {
        $bot['hp'] = min($bot['hp_max'], $bot['hp'] + $result['heal_attacker']);
        $state['log'][] = mw_log_enrich(['type' => 'heal', 'msg' => $botName . ' healed ' . $result['heal_attacker'] . ' HP', 'value' => 0], 'enemy', $turn, 'attack', null, $botName, null);
    }
    mw_consume_attack_one_shots($bot);
    return $state;
}

function mw_tick_cooldowns(array $state): array {
    if (($state['player']['ability_cooldown'] ?? 0) > 0) {
        $state['player']['ability_cooldown']--;
    }
    if (($state['enemy']['ability_cooldown'] ?? 0) > 0) {
        $state['enemy']['ability_cooldown']--;
    }
    return $state;
}

/**
 * Execute one combat action (attacker vs defender). Uses the same engine as 1v1.
 * Chooses action via bot logic, executes via mw_process_player_action.
 *
 * @param array $attacker Fighter acting (frontline of attacking team)
 * @param array $defender Fighter being targeted
 * @param array $context ['difficulty' => 'normal', 'turn' => 1]
 * @return array ['attacker' => updated, 'defender' => updated, 'log' => array]
 */
function mw_execute_combat_action(array $attacker, array $defender, array $context = []): array {
    $turn = (int) ($context['turn'] ?? 1);
    $difficulty = (string) ($context['difficulty'] ?? 'normal');

    $state = [
        'turn' => $turn,
        'max_turns' => 999,
        'player' => $attacker,
        'enemy' => $defender,
        'log' => [],
        'player_next_attack_crit' => false,
    ];

    $action = mw_bot_choose_action_with_difficulty($attacker, $defender, $difficulty);
    $state = mw_process_player_action($action, $state);

    $events = mw_end_turn_phase($state['player'], $turn);
    mw_log_effect_events($state, 'player', $turn, $events, $action, null);

    return [
        'attacker' => $state['player'],
        'defender' => $state['enemy'],
        'log' => $state['log'],
    ];
}

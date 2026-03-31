<?php
/**
 * Mind Wars - Combo Action System
 * Handles validation and execution of 3-card ability combos
 */

if (!defined('MW_ENERGY_ATTACK_COST')) {
    require_once __DIR__ . '/mind_wars.php';
}
if (!function_exists('mw_process_player_action')) {
    require_once __DIR__ . '/mind_wars_combat_actions.php';
}

/**
 * Energy costs for each action type
 */
function mw_get_action_energy_cost(string $action): int {
    switch ($action) {
        case 'attack':
            return MW_ENERGY_ATTACK_COST; // 1
        case 'ability':
        case 'heal':
            return MW_ENERGY_ABILITY_COST; // 2
        case 'special':
            return MW_MAX_ENERGY; // 5
        case 'defend':
            return 0;
        default:
            return 0;
    }
}

/**
 * Validate combo actions array
 * 
 * @param array $comboActions Array of action objects
 * @param array $state Current battle state
 * @return array ['valid' => bool, 'error' => string|null, 'total_energy' => int]
 */
function mw_validate_combo_actions(array $comboActions, array $state): array {
    if (empty($comboActions)) {
        return ['valid' => false, 'error' => 'Combo actions array is empty', 'total_energy' => 0];
    }

    if (count($comboActions) > 3) {
        return ['valid' => false, 'error' => 'Maximum 3 actions per combo', 'total_energy' => 0];
    }

    $player = $state['player'] ?? [];
    $playerEnergy = (int) ($player['energy'] ?? 0);
    $totalEnergyCost = 0;
    $actionsUsed = [];
    $abilitiesUsed = [];

    foreach ($comboActions as $idx => $actionObj) {
        if (!is_array($actionObj)) {
            return ['valid' => false, 'error' => "Invalid action object at index {$idx}", 'total_energy' => 0];
        }

        $action = trim((string) ($actionObj['action'] ?? ''));
        if (!in_array($action, ['attack', 'defend', 'ability', 'special', 'heal'], true)) {
            return ['valid' => false, 'error' => "Invalid action type '{$action}' at index {$idx}", 'total_energy' => 0];
        }

        $energyCost = mw_get_action_energy_cost($action);
        $totalEnergyCost += $energyCost;

        // Track action usage
        $actionsUsed[] = $action;

        // Special validation
        if ($action === 'ability') {
            $abilityCooldown = (int) ($player['ability_cooldown'] ?? 0);
            if ($abilityCooldown > 0 && in_array('ability', $abilitiesUsed)) {
                return ['valid' => false, 'error' => 'Ability is on cooldown or already used in this combo', 'total_energy' => $totalEnergyCost];
            }
            $abilitiesUsed[] = 'ability';
        }

        if ($action === 'heal') {
            $healCode = trim((string) ($player['heal_code'] ?? ''));
            if (empty($healCode)) {
                return ['valid' => false, 'error' => 'This avatar has no heal skill', 'total_energy' => $totalEnergyCost];
            }
        }

        if ($action === 'special') {
            // Special must be last action in combo (resets energy to 0)
            if ($idx < count($comboActions) - 1) {
                return ['valid' => false, 'error' => 'Special must be the last action in combo', 'total_energy' => $totalEnergyCost];
            }
        }
    }

    // Check total energy
    if ($totalEnergyCost > $playerEnergy) {
        return [
            'valid' => false,
            'error' => "Not enough energy. Need {$totalEnergyCost}, have {$playerEnergy}",
            'total_energy' => $totalEnergyCost
        ];
    }

    if ($totalEnergyCost > MW_MAX_ENERGY) {
        return [
            'valid' => false,
            'error' => "Combo cost ({$totalEnergyCost}) exceeds maximum energy (" . MW_MAX_ENERGY . ")",
            'total_energy' => $totalEnergyCost
        ];
    }

    return ['valid' => true, 'error' => null, 'total_energy' => $totalEnergyCost];
}

/**
 * Execute a combo sequence of actions
 * 
 * @param array $comboActions Array of action objects
 * @param array $state Current battle state
 * @param string $difficulty PvE difficulty
 * @return array Updated state with all actions applied
 */
function mw_execute_combo_actions(array $comboActions, array $state, string $difficulty = 'normal'): array {
    $actionsPerformed = 0;
    $battleEndedMidCombo = false;

    // Add combo start marker to log
    if (count($comboActions) > 1) {
        $state['log'][] = [
            'type' => 'info',
            'msg' => '=== COMBO START: ' . count($comboActions) . ' actions ===',
            'actor' => 'player',
            'turn' => (int) ($state['turn'] ?? 1)
        ];
    }

    foreach ($comboActions as $idx => $actionObj) {
        $action = trim((string) ($actionObj['action'] ?? ''));
        
        // Check if battle ended
        if (($state['player']['hp'] ?? 0) <= 0 || ($state['enemy']['hp'] ?? 0) <= 0) {
            $battleEndedMidCombo = true;
            $state['log'][] = [
                'type' => 'info',
                'msg' => 'Combo interrupted - battle ended',
                'actor' => 'player',
                'turn' => (int) ($state['turn'] ?? 1)
            ];
            break;
        }

        // Execute action
        $state = mw_process_player_action($action, $state);
        $actionsPerformed++;

        // End turn phase for player after each action
        $playerEndEvents = mw_end_turn_phase($state['player'], (int) ($state['turn'] ?? 1));
        mw_log_effect_events($state, 'player', (int) ($state['turn'] ?? 1), $playerEndEvents, $action, null);

        // Check for enemy KO after each action
        if (($state['enemy']['hp'] ?? 0) <= 0) {
            // Handle 3v3 wave logic if needed
            $format = (string) ($state['meta']['format'] ?? '1v1');
            $enemyWaveIndex = (int) ($state['meta']['enemy_wave_index'] ?? 0);
            
            if ($format === '3v3' && $enemyWaveIndex < 2) {
                // Enemy defeated but wave continues - next enemy will spawn
                // This will be handled by mw_resolve_pve_knockouts later
            } else {
                // Battle over - stop combo execution
                $battleEndedMidCombo = true;
                break;
            }
        }
    }

    // Add combo end marker to log
    if (count($comboActions) > 1 && !$battleEndedMidCombo) {
        $state['log'][] = [
            'type' => 'info',
            'msg' => '=== COMBO END: ' . $actionsPerformed . ' actions executed ===',
            'actor' => 'player',
            'turn' => (int) ($state['turn'] ?? 1)
        ];
    }

    // Store combo metadata
    $state['meta']['last_combo_actions'] = $actionsPerformed;
    $state['meta']['combo_interrupted'] = $battleEndedMidCombo;

    return $state;
}

/**
 * Build cached response payload for combo execution
 * 
 * @param array $state Battle state
 * @param bool $battleOver Whether battle ended
 * @param string|null $result Battle result
 * @param array|null $rewards Rewards earned
 * @param int $actionsPerformed Number of combo actions executed
 * @return array Response payload
 */
function mw_build_combo_response_payload(array $state, bool $battleOver, ?string $result, ?array $rewards, int $actionsPerformed): array {
    $state = mw_normalize_battle_state($state);
    
    return [
        'state' => $state,
        'battle_over' => $battleOver,
        'result' => $result,
        'rewards' => $rewards,
        'combo_executed' => true,
        'actions_performed' => $actionsPerformed
    ];
}

/**
 * Check if request contains combo actions
 * 
 * @param array $post POST data
 * @return bool
 */
function mw_is_combo_request(array $post): bool {
    return isset($post['combo_actions']) && is_array($post['combo_actions']) && !empty($post['combo_actions']);
}

/**
 * Parse combo actions from POST data
 * 
 * @param array $post POST data
 * @return array|null Parsed combo actions or null if invalid
 */
function mw_parse_combo_actions(array $post): ?array {
    if (!isset($post['combo_actions'])) {
        return null;
    }

    $raw = $post['combo_actions'];
    
    // Handle JSON string
    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }
        $raw = $decoded;
    }

    if (!is_array($raw) || empty($raw)) {
        return null;
    }

    return $raw;
}

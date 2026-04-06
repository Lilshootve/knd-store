<?php
/**
 * Mind Wars Squad 3v3 orchestration.
 *
 * Uses core 1v1 engine functions from includes/mind_wars.php:
 * - mw_calc_damage()
 * - mw_apply_effect_payload()
 * - mw_skill_* handlers (resolved through dispatcher)
 */

require_once __DIR__ . '/../../../config/bootstrap.php';
declare(strict_types=1);

// ── Constants (mirrors 1v1 values — change only here if 1v1 changes) ──
if (!defined('MW_SQUAD_ENERGY_MAX'))          define('MW_SQUAD_ENERGY_MAX',          5);
if (!defined('MW_SQUAD_ENERGY_ATTACK'))       define('MW_SQUAD_ENERGY_ATTACK',       1);
if (!defined('MW_SQUAD_ENERGY_DAMAGE'))       define('MW_SQUAD_ENERGY_DAMAGE',       1);
if (!defined('MW_SQUAD_ENERGY_ABILITY_COST')) define('MW_SQUAD_ENERGY_ABILITY_COST', 2);
if (!defined('MW_SQUAD_MAX_TURNS'))           define('MW_SQUAD_MAX_TURNS',           18);

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 1 — STATE BUILDER
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Build a fresh 3v3 state from two arrays of three fighter arrays.
 * Each fighter must already be in the same shape as $state['player'] in 1v1
 * (i.e. produced by mw_build_fighter + mw_normalize_fighter_state).
 *
 * @param array $player_units  3 fighter arrays for the player side
 * @param array $enemy_units   3 fighter arrays for the enemy side
 * @param array $meta_extra    Additional meta fields (mode, difficulty, etc.)
 * @return array               Full state_json-compatible structure
 */
function mw_squad_build_state(array $player_units, array $enemy_units, array $meta_extra = []): array
{
    $p_squad = mw_squad_build_side($player_units);
    $e_squad = mw_squad_build_side($enemy_units);

    $state = [
        'turn'                   => 1,
        'max_turns'              => MW_SQUAD_MAX_TURNS,
        'player_first'           => true,
        'next_actor'             => 'player',
        // Keep null so any 1v1 code that reads state['player'] gets null
        // and fails gracefully rather than silently using wrong data.
        'player'                 => null,
        'enemy'                  => null,
        'squads'                 => [
            'player' => $p_squad,
            'enemy'  => $e_squad,
        ],
        'turn_order'             => [],
        'turn_order_index'       => 0,
        'player_next_attack_crit'=> false,
        'log'                    => [],
        'meta'                   => array_merge([
            'mode'              => 'pve_3v3',
            'difficulty'        => 'normal',
            'format'            => '3v3_squad',
            'enemy_wave_index'  => 0,
            'player_queue'      => null,
            'player_queue_index'=> 0,
            'last_action_id'    => null,
            'last_action_at'    => null,
            'last_actor'        => null,
            'last_response'     => null,
            'next_actor_slot'   => 0,
            'battle_over'       => false,
            'winner'            => null,
        ], $meta_extra),
    ];

    // Build first turn order
    $state['turn_order'] = mw_squad_build_turn_order($state);

    // UX rule: a fresh battle always starts with a player unit.
    // If speed roll put enemy first, promote the first player entry to index 0.
    if (!empty($state['turn_order']) && ($state['turn_order'][0]['side'] ?? '') !== 'player') {
        foreach ($state['turn_order'] as $idx => $entry) {
            if (($entry['side'] ?? '') === 'player') {
                $tmp = $state['turn_order'][0];
                $state['turn_order'][0] = $state['turn_order'][$idx];
                $state['turn_order'][$idx] = $tmp;
                break;
            }
        }
    }

    // Sync next_actor from first entry
    if (!empty($state['turn_order'])) {
        $first = $state['turn_order'][0];
        $state['next_actor'] = (string) ($first['side'] ?? 'player');
        $state['meta']['next_actor_slot'] = (int) ($first['slot'] ?? 0);
    }

    return $state;
}

/**
 * Build one side's squad array from raw fighter arrays.
 */
function mw_squad_build_side(array $units): array
{
    $built = [];
    $positions = ['front', 'mid', 'back'];
    foreach ($units as $i => $unit) {
        $slot = (int)$i;
        $u = $unit; // copy
        $u['slot']            = $slot;
        $u['position']        = $positions[$slot] ?? 'back';
        $u['is_dead']         = false;
        $u['acted_this_turn'] = false;
        // Ensure required energy fields exist
        if (!isset($u['energy']))           $u['energy']           = 1;
        if (!isset($u['ability_cooldown'])) $u['ability_cooldown'] = 0;
        if (!isset($u['defending']))        $u['defending']        = false;
        if (!isset($u['states']))           $u['states']           = [];
        if (!isset($u['effects']))          $u['effects']          = [];
        if (!isset($u['battle_bonus']))     $u['battle_bonus']     = [];
        $built[$slot] = $u;
    }
    return [
        'units'       => $built,
        'units_alive' => count($built),
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 2 — TURN ORDER
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Build a speed-sorted turn order for the current turn.
 * Returns array of ['side', 'slot', 'speed_roll'].
 */
function mw_squad_build_turn_order(array $state): array
{
    $entries = [];
    foreach (['player', 'enemy'] as $side) {
        foreach ($state['squads'][$side]['units'] as $unit) {
            if ($unit['is_dead']) continue;
            $roll = (int)($unit['speed'] ?? 10) + random_int(1, 6);
            $entries[] = [
                'side'        => $side,
                'slot'        => (int)$unit['slot'],
                'speed_roll'  => $roll,
            ];
        }
    }
    usort($entries, static fn($a, $b) => $b['speed_roll'] <=> $a['speed_roll']);
    return array_values($entries);
}

/**
 * Advance turn_order_index, skip dead units.
 * If the index reaches the end, start a new turn.
 */
function mw_squad_advance_turn(array &$state): void
{
    $state['turn_order_index']++;

    // Skip any dead units that were alive when the order was built
    while (
        $state['turn_order_index'] < count($state['turn_order']) &&
        mw_squad_unit_is_dead($state, $state['turn_order'][$state['turn_order_index']])
    ) {
        $state['turn_order_index']++;
    }

    // End of round → new turn
    if ($state['turn_order_index'] >= count($state['turn_order'])) {
        $state['turn']++;
        // Reset per-turn fields
        foreach (['player', 'enemy'] as $side) {
            foreach ($state['squads'][$side]['units'] as &$u) {
                $u['acted_this_turn'] = false;
                $u['defending']       = false;
                // Tick cooldowns
                if ($u['ability_cooldown'] > 0) {
                    $u['ability_cooldown']--;
                }
                // Tick effects (duration-based)
                mw_squad_tick_effects($u);
            }
            unset($u);
        }
        // Rebuild for next turn
        $state['turn_order']       = mw_squad_build_turn_order($state);
        $state['turn_order_index'] = 0;
    }

    // Sync next_actor
    if (!empty($state['turn_order']) && isset($state['turn_order'][$state['turn_order_index']])) {
        $next = $state['turn_order'][$state['turn_order_index']];
        $state['next_actor']              = $next['side'];
        $state['meta']['next_actor_slot'] = $next['slot'];
    }
}

/**
 * Tick effect durations on a unit. Effects with duration <= 0 are removed.
 */
function mw_squad_tick_effects(array &$unit): void
{
    if (empty($unit['effects'])) return;
    foreach ($unit['effects'] as $key => &$effect) {
        if (isset($effect['duration'])) {
            $effect['duration']--;
            if ($effect['duration'] <= 0) {
                unset($unit['effects'][$key]);
            }
        }
    }
    unset($effect);
}

/**
 * Check if a turn_order entry points to a dead unit.
 */
function mw_squad_unit_is_dead(array $state, array $entry): bool
{
    return (bool)($state['squads'][$entry['side']]['units'][$entry['slot']]['is_dead'] ?? true);
}

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 3 — TARGETING
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Resolve a target slot on the given side.
 * Returns the resolved slot index, or -1 if no alive units.
 *
 * Priority when wanted_slot is dead:
 *  1. Front (slot 0) if alive
 *  2. Lowest HP alive unit
 */
function mw_squad_resolve_target(array $units, int $wanted_slot): int
{
    // Wanted slot is valid and alive → use it
    if (isset($units[$wanted_slot]) && !$units[$wanted_slot]['is_dead']) {
        return $wanted_slot;
    }

    // Fallback: front first
    if (isset($units[0]) && !$units[0]['is_dead']) return 0;

    // Fallback: lowest HP alive
    $best_slot = -1;
    $best_hp   = PHP_INT_MAX;
    foreach ($units as $u) {
        if ($u['is_dead']) continue;
        if ((int)$u['hp'] < $best_hp) {
            $best_hp   = (int)$u['hp'];
            $best_slot = (int)$u['slot'];
        }
    }
    return $best_slot;
}

/**
 * Check whether a side has any living units.
 */
function mw_squad_side_has_alive(array $state, string $side): bool
{
    foreach ($state['squads'][$side]['units'] as $u) {
        if (!$u['is_dead']) return true;
    }
    return false;
}

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 4 — POSITION MODIFIER
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Return a defense_bonus multiplier for the target based on their position
 * and whether their front/mid is still alive.
 * The bonus is applied by temporarily inflating target['focus'] before
 * calling the handler, then restored afterward — so the handler itself
 * never knows about it.
 */
function mw_squad_position_defense_bonus(array $state, string $target_side, int $target_slot): float
{
    $units = $state['squads'][$target_side]['units'];
    $pos   = $units[$target_slot]['position'] ?? 'front';

    if ($pos === 'front') return 0.0;

    if ($pos === 'mid') {
        // Only gets bonus if front is alive
        $front_alive = isset($units[0]) && !$units[0]['is_dead'];
        return $front_alive ? 0.10 : 0.0;
    }

    if ($pos === 'back') {
        $front_alive = isset($units[0]) && !$units[0]['is_dead'];
        $mid_alive   = isset($units[1]) && !$units[1]['is_dead'];
        return ($front_alive || $mid_alive) ? 0.20 : 0.0;
    }

    return 0.0;
}

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 5 — ACTION DISPATCH
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Dispatch an action using the existing 1v1 skill handlers.
 *
 * This function:
 *  1. Applies position defense bonus (by inflating target focus)
 *  2. Calls the correct 1v1 handler (mw_skill_*, mw_calc_damage, etc.)
 *  3. Restores target focus
 *  4. Returns the handler result array unchanged
 *
 * @param array  $attacker   Fighter array (by value — handlers don't mutate it)
 * @param array  $target     Fighter array (by reference only for focus temp mod)
 * @param string $action     'attack' | 'ability' | 'special' | 'defend'
 * @param float  $def_bonus  Position defense bonus (0.0 – 0.20)
 * @param array  $context    Extra context passed to handlers
 * @return array             Result from handler (damage, energy, special_effects, …)
 */
function mw_squad_dispatch_action(
    array  $attacker,
    array  $target,
    string $action,
    float  $def_bonus,
    array  $context = []
): array {
    // Apply position defense bonus by temporarily inflating focus
    $original_focus = $target['focus'];
    if ($def_bonus > 0.0) {
        $target['focus'] = (int)round($original_focus * (1.0 + $def_bonus));
    }

    $result = [];

    switch ($action) {
        case 'attack':
            // Standard attack — reuse mw_calc_damage exactly as 1v1 does
            $result = mw_calc_damage($attacker, $target, 1.0);
            $result['energy_gain_attacker'] = MW_SQUAD_ENERGY_ATTACK;
            $result['energy_gain_defender'] = $result['damage'] > 0 ? MW_SQUAD_ENERGY_DAMAGE : 0;
            $result['special_effects']      = [];
            $result['log']                  = null;
            break;

        case 'ability':
            $fn = 'mw_skill_' . ($attacker['ability_code'] ?? '');
            if (function_exists($fn)) {
                $result = $fn($attacker, $target, $context);
            } else {
                // Graceful fallback: plain attack
                $result = mw_calc_damage($attacker, $target, 1.0);
                $result['energy_gain_attacker'] = MW_SQUAD_ENERGY_ATTACK;
                $result['energy_gain_defender'] = $result['damage'] > 0 ? MW_SQUAD_ENERGY_DAMAGE : 0;
                $result['special_effects']      = [];
                $result['log']                  = null;
            }
            break;

        case 'special':
            $fn = 'mw_skill_' . ($attacker['special_code'] ?? '');
            if (function_exists($fn)) {
                $result = $fn($attacker, $target, $context);
            } else {
                $result = mw_calc_damage($attacker, $target, 1.5);
                $result['energy_gain_attacker'] = 0;
                $result['energy_gain_defender'] = $result['damage'] > 0 ? MW_SQUAD_ENERGY_DAMAGE : 0;
                $result['special_effects']      = [];
                $result['log']                  = null;
            }
            // Special always empties attacker energy (enforced in process_action)
            $result['energy_gain_attacker'] = 0;
            break;

        case 'defend':
            $result = [
                'damage'                 => 0,
                'crit'                   => false,
                'evaded'                 => false,
                'energy_gain_attacker'   => MW_SQUAD_ENERGY_ATTACK, // gain 1 for defend
                'energy_gain_defender'   => 0,
                'special_effects'        => [],
                'log'                    => null,
            ];
            break;

        default:
            $result = [
                'damage'               => 0,
                'crit'                 => false,
                'evaded'               => false,
                'energy_gain_attacker' => 0,
                'energy_gain_defender' => 0,
                'special_effects'      => [],
                'log'                  => null,
            ];
    }

    // Restore original focus (we passed target by value so no need,
    // but being explicit for safety in case PHP semantics surprise us)
    unset($target); // local copy, no effect on caller

    return $result;
}

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 6 — MAIN ACTION PROCESSOR
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Process one action in a 3v3 battle.
 *
 * @param array $state   Full state_json (passed by reference, mutated in place)
 * @param array $input   [
 *                         'side'        => 'player'|'enemy',
 *                         'actor_slot'  => 0|1|2,
 *                         'action'      => 'attack'|'ability'|'special'|'defend',
 *                         'target_slot' => 0|1|2  (ignored for defend)
 *                       ]
 * @return array         ['ok'=>bool, 'error'=>string|null, 'action_result'=>array]
 */
function mw_squad_process_action(array &$state, array $input): array
{
    $side        = $input['side']        ?? 'player';
    $actor_slot  = (int)($input['actor_slot']  ?? 0);
    $action      = $input['action']      ?? 'attack';
    $target_slot = (int)($input['target_slot'] ?? 0);
    $opp_side    = ($side === 'player') ? 'enemy' : 'player';

    // ── 1. Validate it's this unit's turn ───────────────────────────────────
    if (empty($state['turn_order'])) {
        return ['ok' => false, 'error' => 'Turn order is empty.', 'action_result' => []];
    }
    $expected = $state['turn_order'][$state['turn_order_index']] ?? null;
    if (!$expected || $expected['side'] !== $side || (int)$expected['slot'] !== $actor_slot) {
        return ['ok' => false, 'error' => 'It is not this unit\'s turn.', 'action_result' => []];
    }

    // ── 2. Get references to actor and target ───────────────────────────────
    $actor  = &$state['squads'][$side]['units'][$actor_slot];
    if ($actor['is_dead']) {
        return ['ok' => false, 'error' => 'Actor is dead.', 'action_result' => []];
    }

    // ── 3. Validate action-specific requirements ────────────────────────────
    if ($action === 'ability') {
        if ((int)$actor['energy'] < MW_SQUAD_ENERGY_ABILITY_COST) {
            return ['ok' => false, 'error' => 'Not enough energy for ability.', 'action_result' => []];
        }
        if ((int)$actor['ability_cooldown'] > 0) {
            return ['ok' => false, 'error' => 'Ability is on cooldown.', 'action_result' => []];
        }
        $actor['energy'] -= MW_SQUAD_ENERGY_ABILITY_COST;
    }
    if ($action === 'special') {
        if ((int)$actor['energy'] < MW_SQUAD_ENERGY_MAX) {
            return ['ok' => false, 'error' => 'Special requires full energy.', 'action_result' => []];
        }
    }

    // ── 4. Resolve target ───────────────────────────────────────────────────
    $action_result = [];
    $target_died   = false;

    if ($action !== 'defend') {
        $resolved_target = mw_squad_resolve_target(
            $state['squads'][$opp_side]['units'],
            $target_slot
        );
        if ($resolved_target === -1) {
            // No alive targets → battle over
            $state = mw_squad_end_battle($state, $side);
            return ['ok' => true, 'action_result' => [], 'battle_over' => true, 'winner' => $side];
        }
        $target = &$state['squads'][$opp_side]['units'][$resolved_target];
        $target_slot = $resolved_target;

        // ── 5. Position defense bonus ───────────────────────────────────────
        $def_bonus = mw_squad_position_defense_bonus($state, $opp_side, $target_slot);

        // ── 6. Dispatch to 1v1 handler ──────────────────────────────────────
        $context = [
            'turn'   => (int)$state['turn'],
            'format' => '3v3_squad',
            'mode'   => $state['meta']['mode'] ?? 'pve',
        ];
        $result = mw_squad_dispatch_action(
            $actor,      // by value — handler won't mutate the state copy
            $target,     // by value
            $action,
            $def_bonus,
            $context
        );

        // ── 7. Apply damage ─────────────────────────────────────────────────
        $damage = (int)max(0, $result['damage'] ?? 0);
        $target['hp'] = max(0, (int)$target['hp'] - $damage);

        // ── 8. Apply effects via existing function ──────────────────────────
        // mw_apply_effect_payload expects references to the two fighters.
        // We pass references to the actual squad slots.
        if (function_exists('mw_apply_effect_payload')) {
            $skill_source = ($action === 'ability')
                ? ($actor['ability_code'] ?? $action)
                : (($action === 'special') ? ($actor['special_code'] ?? $action) : $action);
            mw_apply_effect_payload($actor, $target, $result, $skill_source, (int)$state['turn']);
        }

        // ── 9. Energy ───────────────────────────────────────────────────────
        $actor['energy'] = min(
            MW_SQUAD_ENERGY_MAX,
            (int)$actor['energy'] + (int)($result['energy_gain_attacker'] ?? 0)
        );
        $target['energy'] = min(
            MW_SQUAD_ENERGY_MAX,
            (int)$target['energy'] + (int)($result['energy_gain_defender'] ?? 0)
        );
        // Crit bonus energy
        if (!empty($result['crit'])) {
            $actor['energy'] = min(MW_SQUAD_ENERGY_MAX, (int)$actor['energy'] + 2);
        }
        // Special resets energy to 0 after gain
        if ($action === 'special') {
            $actor['energy'] = 0;
        }

        // ── 10. Cooldown ────────────────────────────────────────────────────
        if ($action === 'ability') {
            $actor['ability_cooldown'] = 1;
        }

        // ── 11. Death check ─────────────────────────────────────────────────
        if ($target['hp'] <= 0) {
            $target['hp']     = 0;
            $target['is_dead'] = true;
            $state['squads'][$opp_side]['units_alive'] = max(
                0,
                (int)$state['squads'][$opp_side]['units_alive'] - 1
            );
            $target_died = true;
        }

        // ── 12. Build action_result for response ────────────────────────────
        $action_result = [
            'actor'       => ['side' => $side,     'slot' => $actor_slot,  'name' => $actor['name']  ?? ''],
            'target'      => ['side' => $opp_side,  'slot' => $target_slot, 'name' => $target['name'] ?? ''],
            'action'      => $action,
            'damage'      => $damage,
            'crit'        => !empty($result['crit']),
            'evaded'      => !empty($result['evaded']),
            'target_died' => $target_died,
            'energy_after'=> (int)$actor['energy'],
            'def_bonus'   => $def_bonus,
        ];

        // ── 13. Log entry ───────────────────────────────────────────────────
        $log_msg = sprintf(
            '%s → %s [%s] %d dmg%s%s',
            $actor['name']  ?? 'Unit',
            $target['name'] ?? 'Unit',
            strtoupper($action),
            $damage,
            !empty($result['crit'])   ? ' CRIT!'   : '',
            !empty($result['evaded']) ? ' EVADED'  : ''
        );
        if ($target_died) $log_msg .= ' — ' . ($target['name'] ?? 'Unit') . ' defeated!';
        $state['log'][] = [
            'type'   => $damage > 0 ? 'damage' : 'info',
            'msg'    => $log_msg,
            'actor'  => $side,
            'turn'   => (int)$state['turn'],
            'value'  => $damage,
        ];

    } else {
        // Defend
        $actor['defending'] = true;
        $actor['energy'] = min(MW_SQUAD_ENERGY_MAX, (int)$actor['energy'] + MW_SQUAD_ENERGY_ATTACK);
        $action_result = [
            'actor'        => ['side' => $side, 'slot' => $actor_slot, 'name' => $actor['name'] ?? ''],
            'target'       => null,
            'action'       => 'defend',
            'damage'       => 0,
            'crit'         => false,
            'evaded'       => false,
            'target_died'  => false,
            'energy_after' => (int)$actor['energy'],
            'def_bonus'    => 0.0,
        ];
        $state['log'][] = [
            'type'  => 'info',
            'msg'   => ($actor['name'] ?? 'Unit') . ' takes a defensive stance.',
            'actor' => $side,
            'turn'  => (int)$state['turn'],
            'value' => 0,
        ];
    }

    // ── 14. Mark actor as acted ─────────────────────────────────────────────
    $actor['acted_this_turn'] = true;

    // ── 15. Check win condition ─────────────────────────────────────────────
    if ($target_died && !mw_squad_side_has_alive($state, $opp_side)) {
        $state = mw_squad_end_battle($state, $side);
        return [
            'ok'           => true,
            'action_result'=> $action_result,
            'battle_over'  => true,
            'winner'       => $side,
        ];
    }

    // Check max turns
    if ((int)$state['turn'] > (int)$state['max_turns']) {
        $winner = mw_squad_winner_by_hp($state);
        $state  = mw_squad_end_battle($state, $winner);
        return [
            'ok'           => true,
            'action_result'=> $action_result,
            'battle_over'  => true,
            'winner'       => $winner,
        ];
    }

    // ── 16. Advance turn order ──────────────────────────────────────────────
    mw_squad_advance_turn($state);

    return [
        'ok'           => true,
        'action_result'=> $action_result,
        'battle_over'  => false,
        'winner'       => null,
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 7 — END BATTLE
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Mark the battle as over and set winner in meta.
 * Returns the updated state.
 */
function mw_squad_end_battle(array $state, ?string $winner_side): array
{
    $state['meta']['battle_over'] = true;
    $state['meta']['winner']      = $winner_side;
    $result_label = match($winner_side) {
        'player' => 'win',
        'enemy'  => 'lose',
        default  => 'draw',
    };
    $state['meta']['result'] = $result_label;
    $state['log'][] = [
        'type'  => 'result',
        'msg'   => $winner_side === 'player'
            ? 'Victory! Your squad prevailed.'
            : ($winner_side === 'enemy' ? 'Defeat. The enemy squad won.' : 'Draw — time limit reached.'),
        'actor' => $winner_side ?? 'none',
        'turn'  => (int)$state['turn'],
        'value' => 0,
    ];
    return $state;
}

/**
 * Determine winner by total HP remaining.
 */
function mw_squad_winner_by_hp(array $state): string
{
    $player_hp = 0;
    $enemy_hp  = 0;
    foreach ($state['squads']['player']['units'] as $u) {
        if (!$u['is_dead']) $player_hp += (int)$u['hp'];
    }
    foreach ($state['squads']['enemy']['units'] as $u) {
        if (!$u['is_dead']) $enemy_hp += (int)$u['hp'];
    }
    if ($player_hp > $enemy_hp) return 'player';
    if ($enemy_hp > $player_hp) return 'enemy';
    return 'draw';
}

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 8 — ENEMY AI
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Simple deterministic AI for the enemy side.
 *
 * Priority:
 *  1. Special if energy == MW_SQUAD_ENERGY_MAX
 *  2. Ability if energy >= cost and no cooldown
 *  3. Attack otherwise
 *
 * Target:
 *  1. Front (slot 0) if alive
 *  2. Lowest HP alive unit
 *
 * @return array  ['actor_slot', 'action', 'target_slot']
 */
function mw_squad_ai_decide(array $state): array
{
    // Find the current acting enemy unit
    $current = $state['turn_order'][$state['turn_order_index']] ?? null;
    if (!$current || $current['side'] !== 'enemy') {
        // Shouldn't happen, but safe fallback
        return ['actor_slot' => 0, 'action' => 'attack', 'target_slot' => 0];
    }
    $actor_slot = (int)$current['slot'];
    $actor      = $state['squads']['enemy']['units'][$actor_slot];

    // Choose action
    $energy   = (int)$actor['energy'];
    $cooldown = (int)$actor['ability_cooldown'];

    if ($energy >= MW_SQUAD_ENERGY_MAX && !empty($actor['special_code'])) {
        $action = 'special';
    } elseif ($energy >= MW_SQUAD_ENERGY_ABILITY_COST && $cooldown === 0 && !empty($actor['ability_code'])) {
        $action = 'ability';
    } else {
        $action = 'attack';
    }

    // Choose target: front first, then lowest HP
    $player_units = $state['squads']['player']['units'];
    $target_slot  = mw_squad_resolve_target($player_units, 0);

    return [
        'actor_slot'  => $actor_slot,
        'action'      => $action,
        'target_slot' => $target_slot,
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 9 — STATE PERSISTENCE HELPERS
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Sum current HP for all living units on one side (for battle row analytics).
 */
function mw_squad_sum_side_hp(array $state, string $side): int
{
    $sum = 0;
    foreach ($state['squads'][$side]['units'] ?? [] as $u) {
        if (!empty($u['is_dead'])) {
            continue;
        }
        $sum += (int) ($u['hp'] ?? 0);
    }
    return $sum;
}

/**
 * Fetch squad battle row fields needed for rewards / persistence.
 *
 * @return array{id:int, avatar_item_id:int}|null
 */
function mw_squad_get_battle_row(PDO $pdo, string $token, int $user_id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, avatar_item_id
         FROM knd_mind_wars_battles
         WHERE battle_token = :token
           AND user_id = :uid
           AND mode = "pve_3v3"
         LIMIT 1'
    );
    $stmt->execute([':token' => $token, ':uid' => $user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    return [
        'id'              => (int) $row['id'],
        'avatar_item_id'  => (int) $row['avatar_item_id'],
    ];
}

/**
 * Build three enemy fighters, preferring distinct mw_avatars.id when the pool allows.
 *
 * @return array{0: list<array>, 1: int} Tuple: enemy fighter arrays, first enemy knd item id for FK
 */
function mw_squad_build_enemy_units_for_battle(PDO $pdo, int $avgLevel, string $difficulty): array
{
    $exclude       = [];
    $enemyUnits    = [];
    $enemyKndItemId = 0;

    for ($i = 0; $i < 3; $i++) {
        $tries        = 0;
        $enemyAvatar  = null;
        $mwId         = 0;
        do {
            $enemyAvatar = mw_pick_enemy_avatar($pdo, max(1, $avgLevel), $difficulty);
            $mwId        = (int) ($enemyAvatar['id'] ?? 0);
            $tries++;
        } while ($mwId > 0 && in_array($mwId, $exclude, true) && $tries < 28);

        if ($mwId > 0) {
            $exclude[] = $mwId;
        }
        if ($enemyKndItemId < 1) {
            $enemyKndItemId = mw_resolve_enemy_to_knd_item_id($pdo, $enemyAvatar);
        }
        $enemyUnits[] = mw_build_fighter($enemyAvatar, true);
    }

    return [$enemyUnits, max(1, $enemyKndItemId)];
}

/**
 * Load a battle state from the DB (active rows only: result IS NULL).
 *
 * @return array|null   Decoded state or null if not found
 */
function mw_squad_load_state(PDO $pdo, string $token, int $user_id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT state_json
         FROM knd_mind_wars_battles
         WHERE battle_token = :token
           AND user_id = :uid
           AND mode = "pve_3v3"
           AND result IS NULL
         LIMIT 1'
    );
    $stmt->execute([':token' => $token, ':uid' => $user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    $decoded = json_decode($row['state_json'], true);
    return is_array($decoded) ? $decoded : null;
}

/**
 * Save battle state. When the battle is over and $granted_rewards is set, also writes
 * reward columns and combat log (parity with 1v1 perform_action).
 *
 * @param array|null $granted_rewards Same shape as mw_squad_calculate_rewards() return
 */
function mw_squad_save_state(PDO $pdo, string $token, int $user_id, array $state, ?array $granted_rewards = null): bool
{
    $json        = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $turnsPlayed = max(0, (int) ($state['turn'] ?? 1) - 1);
    $battleOver  = !empty($state['meta']['battle_over']);
    $result      = $battleOver ? (string) ($state['meta']['result'] ?? 'draw') : null;

    if ($battleOver && $granted_rewards !== null) {
        $stmt = $pdo->prepare(
            'UPDATE knd_mind_wars_battles
             SET state_json = :json,
                 turns_played = :turns,
                 result = :result,
                 user_hp_final = :uhp,
                 enemy_hp_final = :ehp,
                 xp_gained = :xp,
                 knowledge_energy_gained = :ke,
                 rank_gained = :rk,
                 battle_log_json = :blog,
                 updated_at = NOW()
             WHERE battle_token = :token
               AND user_id = :uid
               AND mode = "pve_3v3"'
        );

        return $stmt->execute([
            ':json'   => $json,
            ':turns'  => $turnsPlayed,
            ':result' => $result,
            ':uhp'    => mw_squad_sum_side_hp($state, 'player'),
            ':ehp'    => mw_squad_sum_side_hp($state, 'enemy'),
            ':xp'     => (int) ($granted_rewards['xp'] ?? 0),
            ':ke'     => (int) ($granted_rewards['knowledge_energy'] ?? 0),
            ':rk'     => (int) ($granted_rewards['rank'] ?? 0),
            ':blog'   => json_encode($state['log'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':token'  => $token,
            ':uid'    => $user_id,
        ]);
    }

    $stmt = $pdo->prepare(
        'UPDATE knd_mind_wars_battles
         SET state_json = :json,
             turns_played = :turns,
             result = :result,
             updated_at = NOW()
         WHERE battle_token = :token
           AND user_id = :uid
           AND mode = "pve_3v3"'
    );

    return $stmt->execute([
        ':json'   => $json,
        ':turns'  => $turnsPlayed,
        ':result' => $result,
        ':token'  => $token,
        ':uid'    => $user_id,
    ]);
}

/**
 * Apply rewards to the user, persist battle row with xp/KE/rank/log (call inside a DB transaction).
 *
 * @return array Rewards granted (same as mw_squad_calculate_rewards)
 */
function mw_squad_finalize_battle(PDO $pdo, string $token, int $user_id, array $state): array
{
    if (!function_exists('kd_avatar_progress')) {
        require_once BASE_PATH . '/includes/knowledge_duel.php';
    }
    if (!function_exists('mw_apply_rewards_to_user')) {
        require_once BASE_PATH . '/includes/mind_wars_rewards.php';
    }

    $rewards = mw_squad_calculate_rewards($state, $user_id);
    $row     = mw_squad_get_battle_row($pdo, $token, $user_id);
    if (!$row) {
        throw new RuntimeException('Squad battle row not found for finalize.');
    }

    $resultLabel = (string) ($state['meta']['result'] ?? 'draw');
    mw_apply_rewards_to_user(
        $pdo,
        $user_id,
        (int) $row['avatar_item_id'],
        [
            'xp'               => (int) ($rewards['xp'] ?? 0),
            'knowledge_energy' => (int) ($rewards['knowledge_energy'] ?? 0),
            'rank'             => (int) ($rewards['rank'] ?? 0),
        ],
        $resultLabel
    );

    if (!mw_squad_save_state($pdo, $token, $user_id, $state, $rewards)) {
        throw new RuntimeException('Failed to persist squad battle.');
    }

    return $rewards;
}

/**
 * Create a new battle row.
 */
function mw_squad_create_battle(PDO $pdo, int $user_id, string $token, array $state, int $avatarItemId, int $enemyAvatarId): bool
{
    $json = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $stmt = $pdo->prepare(
        'INSERT INTO knd_mind_wars_battles
            (battle_token, user_id, avatar_item_id, enemy_avatar_id, mode, state_json, turns_played)
         VALUES
            (:token, :uid, :avatar_item_id, :enemy_avatar_id, "pve_3v3", :json, 0)'
    );
    return $stmt->execute([
        ':uid' => $user_id,
        ':token' => $token,
        ':avatar_item_id' => $avatarItemId,
        ':enemy_avatar_id' => $enemyAvatarId,
        ':json' => $json,
    ]);
}

/**
 * Generate a secure battle token.
 */
function mw_squad_generate_token(): string
{
    return bin2hex(random_bytes(24));
}

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 10 — REWARD CALCULATION
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Calculate rewards after a 3v3 battle.
 * Uses the same logic as 1v1 (or a simplified version if 1v1 helper
 * is not accessible). Override this if mw_calculate_rewards() exists.
 */
function mw_squad_calculate_rewards(array $state, int $user_id): array
{
    $result  = $state['meta']['result'] ?? 'draw';
    $diff    = $state['meta']['difficulty'] ?? 'normal';

    $xp_map = [
        'easy'   => ['win' => 80,  'lose' => 20,  'draw' => 40],
        'normal' => ['win' => 150, 'lose' => 35,  'draw' => 70],
        'hard'   => ['win' => 250, 'lose' => 60,  'draw' => 110],
    ];
    $ke_map = [
        'easy'   => ['win' => 50,  'lose' => 10,  'draw' => 25],
        'normal' => ['win' => 100, 'lose' => 20,  'draw' => 50],
        'hard'   => ['win' => 180, 'lose' => 40,  'draw' => 90],
    ];

    return [
        'xp'               => $xp_map[$diff][$result] ?? 0,
        'knowledge_energy' => $ke_map[$diff][$result] ?? 0,
        'rank'             => ($result === 'win') ? 15 : (($result === 'draw') ? 5 : 0),
        'result'           => $result,
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 11 — PVE ENEMY SQUAD BUILDER
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Build a PvE enemy squad from the DB.
 * Fetches 3 random avatars at approximately the player's avg level.
 *
 * @param PDO   $pdo
 * @param int   $avg_level   Average level of player squad
 * @param array $exclude_ids Avatar IDs to exclude (player's own)
 * @return array             3 fighter arrays ready for mw_squad_build_side()
 */
function mw_squad_build_pve_enemy(PDO $pdo, int $avg_level, array $exclude_ids = []): array
{
    $placeholders = implode(',', array_fill(0, max(1, count($exclude_ids)), '?'));
    $stmt = $pdo->prepare(
        "SELECT a.id, a.name, a.rarity, a.class AS combat_class, a.image AS asset_path,
                s.mind, s.focus, s.speed, s.luck,
                sk.passive_code, sk.ability_code, sk.special_code, sk.heal_code
         FROM mw_avatars a
         LEFT JOIN mw_avatar_stats s  ON s.avatar_id = a.id
         LEFT JOIN mw_avatar_skills sk ON sk.avatar_id = a.id
         WHERE a.id NOT IN ($placeholders)
         ORDER BY RAND()
         LIMIT 3"
    );
    $stmt->execute($exclude_ids ?: [0]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If fewer than 3 enemies found, pad with the first one repeated
    while (count($rows) < 3) {
        $rows[] = $rows[0] ?? [
            'id' => 0, 'name' => 'Shadow', 'rarity' => 'common',
            'combat_class' => 'striker', 'asset_path' => '',
            'mind' => 20, 'focus' => 20, 'speed' => 20, 'luck' => 20,
            'passive_code' => null, 'ability_code' => null,
            'special_code' => null, 'heal_code' => null,
        ];
    }

    $units = [];
    foreach (array_slice($rows, 0, 3) as $row) {
        $mind  = (int)($row['mind']  ?? 20);
        $focus = (int)($row['focus'] ?? 20);
        $level = max(1, min(10, $avg_level + random_int(-1, 1)));
        $hp    = 800 + ($mind * 4) + ($focus * 3) + ($level * 30);

        $units[] = [
            'id'               => (int)$row['id'],
            'name'             => $row['name'] ?? 'Enemy',
            'asset_path'       => $row['asset_path'] ?? '',
            'level'            => $level,
            'hp'               => $hp,
            'hp_max'           => $hp,
            'mind'             => $mind,
            'focus'            => $focus,
            'speed'            => (int)($row['speed'] ?? 20),
            'luck'             => (int)($row['luck']  ?? 20),
            'passive_code'     => $row['passive_code']  ?? null,
            'ability_code'     => $row['ability_code']  ?? null,
            'special_code'     => $row['special_code']  ?? null,
            'heal_code'        => $row['heal_code']     ?? null,
            'rarity'           => $row['rarity']        ?? 'common',
            'combat_class'     => $row['combat_class']  ?? 'striker',
            'energy'           => 1,
            'ability_cooldown' => 0,
            'defending'        => false,
            'states'           => [],
            'effects'          => [],
            'battle_bonus'     => [],
        ];
    }
    return $units;
}

/**
 * Build a player unit from their inventory entry + avatar data.
 * Expects a joined row from knd_user_avatar_inventory + mw_avatars + mw_avatar_stats.
 */
function mw_squad_build_player_unit(array $row): array
{
    $mind  = (int)($row['mind']  ?? 20);
    $focus = (int)($row['focus'] ?? 20);
    $level = max(1, min(10, (int)($row['avatar_level'] ?? 1)));
    $hp    = 800 + ($mind * 4) + ($focus * 3) + ($level * 30);

    return [
        'id'               => (int)($row['id'] ?? $row['avatar_id'] ?? 0),
        'name'             => $row['name'] ?? 'Avatar',
        'asset_path'       => $row['image'] ?? $row['asset_path'] ?? '',
        'level'            => $level,
        'hp'               => $hp,
        'hp_max'           => $hp,
        'mind'             => $mind,
        'focus'            => $focus,
        'speed'            => (int)($row['speed'] ?? 20),
        'luck'             => (int)($row['luck']  ?? 20),
        'passive_code'     => $row['passive_code']  ?? null,
        'ability_code'     => $row['ability_code']  ?? null,
        'special_code'     => $row['special_code']  ?? null,
        'heal_code'        => $row['heal_code']     ?? null,
        'rarity'           => $row['rarity']        ?? 'common',
        'combat_class'     => $row['class']         ?? $row['combat_class'] ?? 'striker',
        'energy'           => 1,
        'ability_cooldown' => 0,
        'defending'        => false,
        'states'           => [],
        'effects'          => [],
        'battle_bonus'     => [],
        'knowledge_energy' => (int)($row['knowledge_energy'] ?? 0),
    ];
}

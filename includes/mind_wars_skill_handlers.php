<?php
/**
 * Mind Wars skill handlers.
 * Each handler returns the standard result structure for ability/special,
 * or mutates $attacker in-place for passive.
 * Requires mind_wars.php (mw_calc_damage, mw_calculate_heal, MW_ENERGY_*, etc.).
 * Loaded by mind_wars.php after mw_calc_damage is defined.
 */

// --- Base class passives (STRIKER, TANK, CONTROLLER, STRATEGIST) ---

function mw_skill_crit_boost(array &$attacker, array &$defender, array $context = []): void {
    if (function_exists('mw_log')) mw_log('USING HANDLER: crit_boost');
    if (!isset($attacker['battle_bonus']) || !is_array($attacker['battle_bonus'])) $attacker['battle_bonus'] = [];
    $attacker['battle_bonus']['class_crit_chance_up'] = (float) ($attacker['battle_bonus']['class_crit_chance_up'] ?? 0) + 0.05;
}

function mw_skill_damage_reduction(array &$attacker, array &$defender, array $context = []): void {
    if (function_exists('mw_log')) mw_log('USING HANDLER: damage_reduction');
    if (!isset($attacker['battle_bonus']) || !is_array($attacker['battle_bonus'])) $attacker['battle_bonus'] = [];
    $attacker['battle_bonus']['damage_taken_down'] = (float) ($attacker['battle_bonus']['damage_taken_down'] ?? 0) + 0.05;
}

function mw_skill_control_boost(array &$attacker, array &$defender, array $context = []): void {
    if (function_exists('mw_log')) mw_log('USING HANDLER: control_boost');
    if (!isset($attacker['battle_bonus']) || !is_array($attacker['battle_bonus'])) $attacker['battle_bonus'] = [];
    $attacker['battle_bonus']['class_status_chance_up'] = (float) ($attacker['battle_bonus']['class_status_chance_up'] ?? 0) + 0.05;
}

function mw_skill_tactical_edge(array &$attacker, array &$defender, array $context = []): void {
    if (function_exists('mw_log')) mw_log('USING HANDLER: tactical_edge');
    if (!isset($attacker['battle_bonus']) || !is_array($attacker['battle_bonus'])) $attacker['battle_bonus'] = [];
    $attacker['battle_bonus']['class_crit_chance_up'] = (float) ($attacker['battle_bonus']['class_crit_chance_up'] ?? 0) + 0.05;
}

// --- Legacy class passives ---

function mw_skill_deductive_precision(array &$attacker, array &$defender, array $context = []): void {
    if (function_exists('mw_log')) mw_log('USING HANDLER: deductive_precision');
    if (!isset($attacker['battle_bonus']) || !is_array($attacker['battle_bonus'])) $attacker['battle_bonus'] = [];
    $attacker['battle_bonus']['class_crit_chance_up'] = (float) ($attacker['battle_bonus']['class_crit_chance_up'] ?? 0) + 0.03;
}

function mw_skill_trickster_instinct(array &$attacker, array &$defender, array $context = []): void {
    if (function_exists('mw_log')) mw_log('USING HANDLER: trickster_instinct');
    if (!isset($attacker['battle_bonus']) || !is_array($attacker['battle_bonus'])) $attacker['battle_bonus'] = [];
    $attacker['battle_bonus']['class_dodge_chance_up'] = (float) ($attacker['battle_bonus']['class_dodge_chance_up'] ?? 0) + 0.03;
}

function mw_skill_divine_pressure(array &$attacker, array &$defender, array $context = []): void {
    if (function_exists('mw_log')) mw_log('USING HANDLER: divine_pressure');
    if (!isset($attacker['battle_bonus']) || !is_array($attacker['battle_bonus'])) $attacker['battle_bonus'] = [];
    $attacker['battle_bonus']['damage_up'] = (float) ($attacker['battle_bonus']['damage_up'] ?? 0) + 0.04;
}

function mw_skill_cursed_presence(array &$attacker, array &$defender, array $context = []): void {
    if (function_exists('mw_log')) mw_log('USING HANDLER: cursed_presence');
    if (!isset($attacker['states']) || !is_array($attacker['states'])) $attacker['states'] = [];
    $attacker['states']['cursed_presence'] = true;
    mw_apply_effect($attacker, 'cursed_presence', ['type' => 'debuff', 'duration' => 999, 'tick_phase' => 'end_turn', 'source' => 'cursed_presence']);
}

function mw_skill_deep_armor(array &$attacker, array &$defender, array $context = []): void {
    if (function_exists('mw_log')) mw_log('USING HANDLER: deep_armor');
    if (!isset($attacker['battle_bonus']) || !is_array($attacker['battle_bonus'])) $attacker['battle_bonus'] = [];
    $attacker['battle_bonus']['damage_taken_down'] = (float) ($attacker['battle_bonus']['damage_taken_down'] ?? 0) + 0.08;
}

function mw_skill_frozen_calm(array &$attacker, array &$defender, array $context = []): void {
    if (function_exists('mw_log')) mw_log('USING HANDLER: frozen_calm');
    if (!isset($attacker['battle_bonus']) || !is_array($attacker['battle_bonus'])) $attacker['battle_bonus'] = [];
    $attacker['battle_bonus']['status_resist_up'] = (float) ($attacker['battle_bonus']['status_resist_up'] ?? 0) + 0.10;
}

// --- Base class abilities ---

function mw_skill_heavy_strike(array $attacker, array $defender, array $context = []): array {
    if (function_exists('mw_log')) mw_log('USING HANDLER: heavy_strike');
    $r = mw_calc_damage($attacker, $defender, 1.15);
    $r['heal_attacker'] = 0;
    $r['heal_defender'] = 0;
    $r['energy_gain_attacker'] = MW_ENERGY_ATTACK;
    $r['energy_gain_defender'] = $r['damage'] > 0 ? MW_ENERGY_DAMAGE : 0;
    $r['special_effects'] = [];
    $r['log'] = null;
    return $r;
}

function mw_skill_shield_bash(array $attacker, array $defender, array $context = []): array {
    if (function_exists('mw_log')) mw_log('USING HANDLER: shield_bash');
    $r = mw_calc_damage($attacker, $defender, 1.20);
    $r['heal_attacker'] = 0;
    $r['heal_defender'] = 0;
    $r['energy_gain_attacker'] = MW_ENERGY_ATTACK;
    $r['energy_gain_defender'] = $r['damage'] > 0 ? MW_ENERGY_DAMAGE : 0;
    $r['special_effects'] = [];
    $r['log'] = null;
    return $r;
}

function mw_skill_mind_disrupt(array $attacker, array $defender, array $context = []): array {
    if (function_exists('mw_log')) mw_log('USING HANDLER: mind_disrupt');
    $r = mw_calc_damage($attacker, $defender, 1.15);
    $r['stun_chance'] = mw_adjust_status_chance(25, $attacker);
    $r['heal_attacker'] = 0;
    $r['heal_defender'] = 0;
    $r['energy_gain_attacker'] = MW_ENERGY_ATTACK;
    $r['energy_gain_defender'] = $r['damage'] > 0 ? MW_ENERGY_DAMAGE : 0;
    $r['special_effects'] = [];
    $r['log'] = null;
    return $r;
}

function mw_skill_expose_weakness(array $attacker, array $defender, array $context = []): array {
    if (function_exists('mw_log')) mw_log('USING HANDLER: expose_weakness');
    $r = mw_calc_damage($attacker, $defender, 1.25);
    $r['heal_attacker'] = 0;
    $r['heal_defender'] = 0;
    $r['energy_gain_attacker'] = MW_ENERGY_ATTACK;
    $r['energy_gain_defender'] = $r['damage'] > 0 ? MW_ENERGY_DAMAGE : 0;
    $r['special_effects'] = ['focus_down' => true];
    $r['log'] = null;
    return $r;
}

function mw_skill_healing_aura(array $attacker, array $defender, array $context = []): array {
    if (function_exists('mw_log')) mw_log('USING HANDLER: healing_aura');
    return [
        'damage' => 0,
        'heal_attacker' => mw_calculate_heal($attacker, 10, 1.0),
        'heal_defender' => 0,
        'energy_gain_attacker' => MW_ENERGY_ATTACK,
        'energy_gain_defender' => 0,
        'special_effects' => [],
        'log' => null,
    ];
}

function mw_skill_restore(array $attacker, array $defender, array $context = []): array {
    if (function_exists('mw_log')) mw_log('USING HANDLER: restore');
    return [
        'damage' => 0,
        'heal_attacker' => mw_calculate_heal($attacker, 14, 1.15),
        'heal_defender' => 0,
        'energy_gain_attacker' => MW_ENERGY_ATTACK,
        'energy_gain_defender' => 0,
        'special_effects' => [],
        'log' => null,
    ];
}

function mw_skill_mass_heal(array $attacker, array $defender, array $context = []): array {
    if (function_exists('mw_log')) mw_log('USING HANDLER: mass_heal');
    return [
        'damage' => 0,
        'heal_attacker' => mw_calculate_heal($attacker, 18, 1.25),
        'heal_defender' => 0,
        'energy_gain_attacker' => MW_ENERGY_ATTACK,
        'energy_gain_defender' => 0,
        'special_effects' => [],
        'log' => null,
    ];
}

// --- Generic fallback abilities ---

function mw_skill_generic_strike(array $attacker, array $defender, array $context = []): array {
    if (function_exists('mw_log')) mw_log('USING HANDLER: generic_strike');
    $r = mw_calc_damage($attacker, $defender, 1.10);
    $r['heal_attacker'] = 0;
    $r['heal_defender'] = 0;
    $r['energy_gain_attacker'] = MW_ENERGY_ATTACK;
    $r['energy_gain_defender'] = $r['damage'] > 0 ? MW_ENERGY_DAMAGE : 0;
    $r['special_effects'] = [];
    $r['log'] = null;
    return $r;
}

function mw_skill_generic_focus(array $attacker, array $defender, array $context = []): array {
    if (function_exists('mw_log')) mw_log('USING HANDLER: generic_focus');
    $r = mw_calc_damage($attacker, $defender, 1.20);
    $r['focus_up_once'] = 0.05;
    $r['heal_attacker'] = 0;
    $r['heal_defender'] = 0;
    $r['energy_gain_attacker'] = MW_ENERGY_ATTACK;
    $r['energy_gain_defender'] = $r['damage'] > 0 ? MW_ENERGY_DAMAGE : 0;
    $r['special_effects'] = [];
    $r['log'] = null;
    return $r;
}

function mw_skill_generic_burst(array $attacker, array $defender, array $context = []): array {
    if (function_exists('mw_log')) mw_log('USING HANDLER: generic_burst');
    $r = mw_calc_damage($attacker, $defender, 1.30);
    $r['next_attack_crit'] = true;
    $r['heal_attacker'] = 0;
    $r['heal_defender'] = 0;
    $r['energy_gain_attacker'] = MW_ENERGY_ATTACK;
    $r['energy_gain_defender'] = $r['damage'] > 0 ? MW_ENERGY_DAMAGE : 0;
    $r['special_effects'] = [];
    $r['log'] = null;
    return $r;
}

function mw_skill_generic_legendary_strike(array $attacker, array $defender, array $context = []): array {
    if (function_exists('mw_log')) mw_log('USING HANDLER: generic_legendary_strike');
    $r = mw_calc_damage($attacker, $defender, 1.45);
    $r['heal_attacker'] = 0;
    $r['heal_defender'] = 0;
    $r['energy_gain_attacker'] = MW_ENERGY_ATTACK;
    $r['energy_gain_defender'] = $r['damage'] > 0 ? MW_ENERGY_DAMAGE : 0;
    $r['special_effects'] = [];
    $r['log'] = null;
    if (random_int(1, 2) === 1) {
        $r['energy_bonus'] = 1;
    } else {
        $r['crit_damage_up_temp'] = 0.05;
    }
    return $r;
}

// --- Base class specials ---

function mw_skill_execution_burst(array $attacker, array $defender, array $context = []): array {
    if (function_exists('mw_log')) mw_log('USING HANDLER: execution_burst');
    $r = mw_calc_damage($attacker, $defender, 1.50);
    $r['heal_attacker'] = 0;
    $r['heal_defender'] = 0;
    $r['energy_gain_attacker'] = 0;
    $r['energy_gain_defender'] = $r['damage'] > 0 ? MW_ENERGY_DAMAGE : 0;
    $r['special_effects'] = [];
    $r['log'] = null;
    return $r;
}

function mw_skill_iron_wall(array $attacker, array $defender, array $context = []): array {
    if (function_exists('mw_log')) mw_log('USING HANDLER: iron_wall');
    $r = mw_calc_damage($attacker, $defender, 1.40);
    $r['heal_attacker'] = 0;
    $r['heal_defender'] = 0;
    $r['energy_gain_attacker'] = 0;
    $r['energy_gain_defender'] = $r['damage'] > 0 ? MW_ENERGY_DAMAGE : 0;
    $r['special_effects'] = ['focus_down' => true];
    $r['log'] = null;
    return $r;
}

function mw_skill_chaos_field(array $attacker, array $defender, array $context = []): array {
    if (function_exists('mw_log')) mw_log('USING HANDLER: chaos_field');
    $r = mw_calc_damage($attacker, $defender, 1.60);
    $options = ['stun', 'heal', 'extra_hit', 'shield'];
    $r['heal_attacker'] = 0;
    $r['heal_defender'] = 0;
    $r['energy_gain_attacker'] = 0;
    $r['energy_gain_defender'] = $r['damage'] > 0 ? MW_ENERGY_DAMAGE : 0;
    $r['special_effects'] = ['random' => $options[array_rand($options)]];
    $r['log'] = null;
    return $r;
}

function mw_skill_mastermind_plan(array $attacker, array $defender, array $context = []): array {
    if (function_exists('mw_log')) mw_log('USING HANDLER: mastermind_plan');
    $r = mw_calc_damage($attacker, $defender, 1.55);
    $r['next_attack_crit'] = true;
    $r['heal_attacker'] = 0;
    $r['heal_defender'] = 0;
    $r['energy_gain_attacker'] = 0;
    $r['energy_gain_defender'] = $r['damage'] > 0 ? MW_ENERGY_DAMAGE : 0;
    $r['special_effects'] = [];
    $r['log'] = null;
    return $r;
}

// --- Generic fallback specials ---

function mw_skill_generic_finisher(array $attacker, array $defender, array $context = []): array {
    if (function_exists('mw_log')) mw_log('USING HANDLER: generic_finisher');
    $r = mw_calc_damage($attacker, $defender, 1.50);
    $r['heal_attacker'] = 0;
    $r['heal_defender'] = 0;
    $r['energy_gain_attacker'] = 0;
    $r['energy_gain_defender'] = $r['damage'] > 0 ? MW_ENERGY_DAMAGE : 0;
    $r['special_effects'] = [];
    $r['log'] = null;
    return $r;
}

function mw_skill_generic_legendary_finisher(array $attacker, array $defender, array $context = []): array {
    if (function_exists('mw_log')) mw_log('USING HANDLER: generic_legendary_finisher');
    $r = mw_calc_damage($attacker, $defender, 1.80);
    $r['heal_attacker'] = 0;
    $r['heal_defender'] = 0;
    $r['energy_gain_attacker'] = 0;
    $r['energy_gain_defender'] = $r['damage'] > 0 ? MW_ENERGY_DAMAGE : 0;
    $r['special_effects'] = [];
    $r['log'] = null;
    return $r;
}

// --- Legacy character abilities ---

function mw_skill_predictive_strike(array $attacker, array $defender, array $context = []): array {
    if (function_exists('mw_log')) mw_log('USING HANDLER: predictive_strike');
    return [
        'damage' => 0,
        'heal_attacker' => 0,
        'heal_defender' => 0,
        'next_attack_crit' => true,
        'ability_buff' => true,
        'energy_gain_attacker' => MW_ENERGY_ATTACK,
        'energy_gain_defender' => 0,
        'special_effects' => [],
        'log' => null,
    ];
}

function mw_skill_clone_assault(array $attacker, array $defender, array $context = []): array {
    if (function_exists('mw_log')) mw_log('USING HANDLER: clone_assault');
    $r1 = mw_calc_damage($attacker, $defender, 0.70);
    $r2 = mw_calc_damage($attacker, $defender, 0.70);
    return [
        'damage' => $r1['damage'] + $r2['damage'],
        'crit' => $r1['crit'] || $r2['crit'],
        'evaded' => false,
        'heal_attacker' => 0,
        'heal_defender' => 0,
        'energy_gain_attacker' => MW_ENERGY_ATTACK,
        'energy_gain_defender' => ($r1['damage'] + $r2['damage']) > 0 ? MW_ENERGY_DAMAGE : 0,
        'special_effects' => [],
        'log' => null,
    ];
}

function mw_skill_thunder_judgment(array $attacker, array $defender, array $context = []): array {
    if (function_exists('mw_log')) mw_log('USING HANDLER: thunder_judgment');
    $r = mw_calc_damage($attacker, $defender, 1.50);
    $r['stun_chance'] = mw_adjust_status_chance(20, $attacker);
    $r['heal_attacker'] = 0;
    $r['heal_defender'] = 0;
    $r['energy_gain_attacker'] = MW_ENERGY_ATTACK;
    $r['energy_gain_defender'] = $r['damage'] > 0 ? MW_ENERGY_DAMAGE : 0;
    $r['special_effects'] = [];
    $r['log'] = null;
    return $r;
}

function mw_skill_petrifying_gaze(array $attacker, array $defender, array $context = []): array {
    if (function_exists('mw_log')) mw_log('USING HANDLER: petrifying_gaze');
    $stun = mw_roll_status_chance(25, $attacker);
    $r = mw_calc_damage($attacker, $defender, $stun ? 0 : 1.10);
    $r['stun_applied'] = $stun;
    $r['heal_attacker'] = 0;
    $r['heal_defender'] = 0;
    $r['energy_gain_attacker'] = MW_ENERGY_ATTACK;
    $r['energy_gain_defender'] = $r['damage'] > 0 ? MW_ENERGY_DAMAGE : 0;
    $r['special_effects'] = [];
    $r['log'] = null;
    return $r;
}

function mw_skill_abyssal_grip(array $attacker, array $defender, array $context = []): array {
    if (function_exists('mw_log')) mw_log('USING HANDLER: abyssal_grip');
    $r = mw_calc_damage($attacker, $defender, 1.20);
    $r['heal_attacker'] = (int) round($r['damage'] * 0.12);
    $r['heal_defender'] = 0;
    $r['energy_gain_attacker'] = MW_ENERGY_ATTACK;
    $r['energy_gain_defender'] = $r['damage'] > 0 ? MW_ENERGY_DAMAGE : 0;
    $r['special_effects'] = [];
    $r['log'] = null;
    return $r;
}

function mw_skill_frostbite_pulse(array $attacker, array $defender, array $context = []): array {
    if (function_exists('mw_log')) mw_log('USING HANDLER: frostbite_pulse');
    $r = mw_calc_damage($attacker, $defender, 1.25);
    $r['chill_applied'] = true;
    $r['heal_attacker'] = 0;
    $r['heal_defender'] = 0;
    $r['energy_gain_attacker'] = MW_ENERGY_ATTACK;
    $r['energy_gain_defender'] = $r['damage'] > 0 ? MW_ENERGY_DAMAGE : 0;
    $r['special_effects'] = [];
    $r['log'] = null;
    return $r;
}

function mw_skill_chaos_tea(array $attacker, array $defender, array $context = []): array {
    if (function_exists('mw_log')) mw_log('USING HANDLER: chaos_tea');
    $opt = random_int(1, 4);
    if ($opt === 1) {
        $r = mw_calc_damage($attacker, $defender, 1.30);
        $r['heal_attacker'] = 0;
        $r['heal_defender'] = 0;
        $r['energy_gain_attacker'] = MW_ENERGY_ATTACK;
        $r['energy_gain_defender'] = $r['damage'] > 0 ? MW_ENERGY_DAMAGE : 0;
        $r['special_effects'] = [];
        $r['log'] = null;
        return $r;
    }
    if ($opt === 2) {
        return [
            'damage' => 0,
            'heal_attacker' => mw_calculate_heal($attacker, 12),
            'heal_defender' => 0,
            'energy_gain_attacker' => MW_ENERGY_ATTACK,
            'energy_gain_defender' => 0,
            'special_effects' => [],
            'log' => null,
        ];
    }
    if ($opt === 3) {
        return [
            'damage' => 0,
            'heal_attacker' => 0,
            'heal_defender' => 0,
            'enemy_lose_energy' => 1,
            'energy_gain_attacker' => MW_ENERGY_ATTACK,
            'energy_gain_defender' => 0,
            'special_effects' => [],
            'log' => null,
        ];
    }
    return [
        'damage' => 0,
        'heal_attacker' => 0,
        'heal_defender' => 0,
        'shield_attacker' => true,
        'energy_gain_attacker' => MW_ENERGY_ATTACK,
        'energy_gain_defender' => 0,
        'special_effects' => [],
        'log' => null,
    ];
}

function mw_skill_relativity_collapse(array $attacker, array $defender, array $context = []): array {
    if (function_exists('mw_log')) mw_log('USING HANDLER: relativity_collapse');
    $mult = 1.60;
    if ($defender['hp'] > $defender['hp_max'] * 0.70) $mult += 0.10;
    $r = mw_calc_damage($attacker, $defender, $mult);
    $r['heal_attacker'] = 0;
    $r['heal_defender'] = 0;
    $r['energy_gain_attacker'] = MW_ENERGY_ATTACK;
    $r['energy_gain_defender'] = $r['damage'] > 0 ? MW_ENERGY_DAMAGE : 0;
    $r['special_effects'] = [];
    $r['log'] = null;
    return $r;
}

function mw_skill_lightning_conductor(array $attacker, array $defender, array $context = []): array {
    if (function_exists('mw_log')) mw_log('USING HANDLER: lightning_conductor');
    $r = mw_calc_damage($attacker, $defender, 1.30);
    if (random_int(1, 100) <= 25) {
        $r2 = mw_calc_damage($attacker, $defender, 0.50);
        $r['damage'] += $r2['damage'];
    }
    $r['heal_attacker'] = 0;
    $r['heal_defender'] = 0;
    $r['energy_gain_attacker'] = MW_ENERGY_ATTACK;
    $r['energy_gain_defender'] = $r['damage'] > 0 ? MW_ENERGY_DAMAGE : 0;
    $r['special_effects'] = [];
    $r['log'] = null;
    return $r;
}

// --- Legacy character specials ---

function mw_skill_mental_singularity(array $attacker, array $defender, array $context = []): array {
    if (function_exists('mw_log')) mw_log('USING HANDLER: mental_singularity');
    $r = mw_calc_damage($attacker, $defender, 2.30, false, true);
    $r['heal_attacker'] = 0;
    $r['heal_defender'] = 0;
    $r['energy_gain_attacker'] = 0;
    $r['energy_gain_defender'] = $r['damage'] > 0 ? MW_ENERGY_DAMAGE : 0;
    $r['special_effects'] = [];
    $r['log'] = null;
    return $r;
}

function mw_skill_storm_protocol(array $attacker, array $defender, array $context = []): array {
    if (function_exists('mw_log')) mw_log('USING HANDLER: storm_protocol');
    $r = mw_calc_damage($attacker, $defender, 1.80);
    $r['heal_attacker'] = 0;
    $r['heal_defender'] = 0;
    $r['energy_gain_attacker'] = 0;
    $r['energy_gain_defender'] = $r['damage'] > 0 ? MW_ENERGY_DAMAGE : 0;
    $r['special_effects'] = ['shock' => 8];
    $r['log'] = null;
    return $r;
}

function mw_skill_final_deduction(array $attacker, array $defender, array $context = []): array {
    if (function_exists('mw_log')) mw_log('USING HANDLER: final_deduction');
    $r = mw_calc_damage($attacker, $defender, 1.70);
    $r['heal_attacker'] = 0;
    $r['heal_defender'] = 0;
    $r['energy_gain_attacker'] = 0;
    $r['energy_gain_defender'] = $r['damage'] > 0 ? MW_ENERGY_DAMAGE : 0;
    $r['special_effects'] = ['focus_down' => true];
    $r['log'] = null;
    return $r;
}

function mw_skill_celestial_rampage(array $attacker, array $defender, array $context = []): array {
    if (function_exists('mw_log')) mw_log('USING HANDLER: celestial_rampage');
    $r1 = mw_calc_damage($attacker, $defender, 0.50);
    $r2 = mw_calc_damage($attacker, $defender, 0.70);
    $r3 = mw_calc_damage($attacker, $defender, 1.00);
    $total = $r1['damage'] + $r2['damage'] + $r3['damage'];
    return [
        'damage' => $total,
        'crit' => $r1['crit'] || $r2['crit'] || $r3['crit'],
        'heal_attacker' => 0,
        'heal_defender' => 0,
        'energy_gain_attacker' => 0,
        'energy_gain_defender' => $total > 0 ? MW_ENERGY_DAMAGE : 0,
        'special_effects' => [],
        'log' => null,
    ];
}

function mw_skill_wrath_of_olympus(array $attacker, array $defender, array $context = []): array {
    if (function_exists('mw_log')) mw_log('USING HANDLER: wrath_of_olympus');
    $stunGuaranteed = $defender['hp'] < $defender['hp_max'] * 0.40;
    $r = mw_calc_damage($attacker, $defender, 2.20);
    $r['heal_attacker'] = 0;
    $r['heal_defender'] = 0;
    $r['energy_gain_attacker'] = 0;
    $r['energy_gain_defender'] = $r['damage'] > 0 ? MW_ENERGY_DAMAGE : 0;
    $r['special_effects'] = $stunGuaranteed ? ['stun' => true] : [];
    $r['log'] = null;
    return $r;
}

function mw_skill_stone_eternity(array $attacker, array $defender, array $context = []): array {
    if (function_exists('mw_log')) mw_log('USING HANDLER: stone_eternity');
    $r = mw_calc_damage($attacker, $defender, 1.60);
    $r['heal_attacker'] = 0;
    $r['heal_defender'] = 0;
    $r['energy_gain_attacker'] = 0;
    $r['energy_gain_defender'] = $r['damage'] > 0 ? MW_ENERGY_DAMAGE : 0;
    $r['special_effects'] = ['petrify' => true];
    $r['log'] = null;
    return $r;
}

function mw_skill_leviathan_crush(array $attacker, array $defender, array $context = []): array {
    if (function_exists('mw_log')) mw_log('USING HANDLER: leviathan_crush');
    $r = mw_calc_damage($attacker, $defender, 2.00);
    $r['heal_attacker'] = ($attacker['hp'] < $attacker['hp_max'] * 0.50) ? mw_calculate_heal($attacker, 15) : 0;
    $r['heal_defender'] = 0;
    $r['energy_gain_attacker'] = 0;
    $r['energy_gain_defender'] = $r['damage'] > 0 ? MW_ENERGY_DAMAGE : 0;
    $r['special_effects'] = [];
    $r['log'] = null;
    return $r;
}

function mw_skill_absolute_zero(array $attacker, array $defender, array $context = []): array {
    if (function_exists('mw_log')) mw_log('USING HANDLER: absolute_zero');
    $r = mw_calc_damage($attacker, $defender, 1.70);
    $r['heal_attacker'] = 0;
    $r['heal_defender'] = 0;
    $r['energy_gain_attacker'] = 0;
    $r['energy_gain_defender'] = $r['damage'] > 0 ? MW_ENERGY_DAMAGE : 0;
    $r['special_effects'] = ['freeze' => true];
    $r['log'] = null;
    return $r;
}

// --- Character passives (existing) ---

/**
 * Passive: Vampiric Lifesteal - grants damage bonus at battle start.
 * Mutates $attacker in-place.
 */
function mw_skill_vamp_lifesteal(array &$attacker, array &$defender, array $context = []): void {
    if (!isset($attacker['battle_bonus']) || !is_array($attacker['battle_bonus'])) {
        $attacker['battle_bonus'] = [];
    }
    $attacker['battle_bonus']['damage_up'] = (float) ($attacker['battle_bonus']['damage_up'] ?? 0) + 0.05;
}

/**
 * Ability: Blood Bite - 1.25x damage, 15% lifesteal.
 */
function mw_skill_blood_bite(array $attacker, array $defender, array $context = []): array {
    $r = mw_calc_damage($attacker, $defender, 1.25);
    $r['heal_attacker'] = (int) round($r['damage'] * 0.15);
    $r['energy_gain_attacker'] = MW_ENERGY_ATTACK;
    $r['energy_gain_defender'] = $r['damage'] > 0 ? MW_ENERGY_DAMAGE : 0;
    $r['special_effects'] = [];
    $r['log'] = null;
    return $r;
}

/**
 * Special: Night Domination - 1.90x damage, 20% lifesteal.
 */
function mw_skill_night_domination(array $attacker, array $defender, array $context = []): array {
    $r = mw_calc_damage($attacker, $defender, 1.90);
    $r['heal_attacker'] = (int) round($r['damage'] * 0.20);
    $r['energy_gain_attacker'] = 0;
    $r['energy_gain_defender'] = $r['damage'] > 0 ? MW_ENERGY_DAMAGE : 0;
    $r['special_effects'] = [];
    $r['log'] = null;
    return $r;
}

// --- Albert Einstein ---

/**
 * Passive: Relativity Field - +5% crit chance, +5% damage reduction at battle start.
 */
function mw_skill_relativity_field(array &$attacker, array &$defender, array $context = []): void {
    if (function_exists('mw_log')) {
        mw_log('SKILL: relativity_field by ' . ($attacker['name'] ?? 'unknown'));
    }
    if (!isset($attacker['battle_bonus']) || !is_array($attacker['battle_bonus'])) {
        $attacker['battle_bonus'] = [];
    }
    $attacker['battle_bonus']['class_crit_chance_up'] = (float) ($attacker['battle_bonus']['class_crit_chance_up'] ?? 0) + 0.05;
    $attacker['battle_bonus']['damage_taken_down'] = (float) ($attacker['battle_bonus']['damage_taken_down'] ?? 0) + 0.05;
}

/**
 * Ability: Time Shift - 1.20x damage, focus_down on defender.
 */
function mw_skill_time_shift(array $attacker, array $defender, array $context = []): array {
    if (function_exists('mw_log')) {
        mw_log('SKILL: time_shift by ' . ($attacker['name'] ?? 'unknown'));
    }
    $r = mw_calc_damage($attacker, $defender, 1.20);
    $r['heal_attacker'] = 0;
    $r['heal_defender'] = 0;
    $r['energy_gain_attacker'] = MW_ENERGY_ATTACK;
    $r['energy_gain_defender'] = $r['damage'] > 0 ? MW_ENERGY_DAMAGE : 0;
    $r['special_effects'] = ['focus_down' => true];
    $r['log'] = 'Einstein bends time.';
    return $r;
}

/**
 * Special: Space Collapse - 1.85x damage, ignore focus, focus_down on defender.
 */
function mw_skill_space_collapse(array $attacker, array $defender, array $context = []): array {
    if (function_exists('mw_log')) {
        mw_log('SKILL: space_collapse by ' . ($attacker['name'] ?? 'unknown'));
    }
    $r = mw_calc_damage($attacker, $defender, 1.85, false, true);
    $r['heal_attacker'] = 0;
    $r['heal_defender'] = 0;
    $r['energy_gain_attacker'] = 0;
    $r['energy_gain_defender'] = $r['damage'] > 0 ? MW_ENERGY_DAMAGE : 0;
    $r['special_effects'] = ['focus_down' => true];
    $r['log'] = 'Einstein collapses space.';
    return $r;
}

// --- Alice ---

/**
 * Passive: Chaos Mind - +7% dodge chance at battle start.
 */
function mw_skill_chaos_mind(array &$attacker, array &$defender, array $context = []): void {
    if (function_exists('mw_log')) {
        mw_log('SKILL: chaos_mind by ' . ($attacker['name'] ?? 'unknown'));
    }
    if (!isset($attacker['battle_bonus']) || !is_array($attacker['battle_bonus'])) {
        $attacker['battle_bonus'] = [];
    }
    $attacker['battle_bonus']['class_dodge_chance_up'] = (float) ($attacker['battle_bonus']['class_dodge_chance_up'] ?? 0) + 0.07;
}

/**
 * Ability: Reality Glitch - 1.15x damage, 35% chance to stun.
 */
function mw_skill_reality_glitch(array $attacker, array $defender, array $context = []): array {
    if (function_exists('mw_log')) {
        mw_log('SKILL: reality_glitch by ' . ($attacker['name'] ?? 'unknown'));
    }
    $r = mw_calc_damage($attacker, $defender, 1.15);
    $r['heal_attacker'] = 0;
    $r['heal_defender'] = 0;
    $r['stun_applied'] = random_int(1, 100) <= 35;
    $r['energy_gain_attacker'] = MW_ENERGY_ATTACK;
    $r['energy_gain_defender'] = $r['damage'] > 0 ? MW_ENERGY_DAMAGE : 0;
    $r['special_effects'] = [];
    $r['log'] = 'Alice distorts reality.';
    return $r;
}

/**
 * Special: Madness Loop - 1.70x damage, random effect (stun/heal/extra_hit/shield).
 */
function mw_skill_madness_loop(array $attacker, array $defender, array $context = []): array {
    if (function_exists('mw_log')) {
        mw_log('SKILL: madness_loop by ' . ($attacker['name'] ?? 'unknown'));
    }
    $r = mw_calc_damage($attacker, $defender, 1.70);
    $options = ['stun', 'heal', 'extra_hit', 'shield'];
    $r['heal_attacker'] = 0;
    $r['heal_defender'] = 0;
    $r['energy_gain_attacker'] = 0;
    $r['energy_gain_defender'] = $r['damage'] > 0 ? MW_ENERGY_DAMAGE : 0;
    $r['special_effects'] = ['random' => $options[array_rand($options)]];
    $r['log'] = 'Alice traps the enemy in madness.';
    return $r;
}

// --- Benjamin Franklin ---

/**
 * Passive: Electric Flow - +1 energy at battle start.
 */
function mw_skill_electric_flow(array &$attacker, array &$defender, array $context = []): void {
    if (function_exists('mw_log')) {
        mw_log('SKILL: electric_flow by ' . ($attacker['name'] ?? 'unknown'));
    }
    $attacker['energy'] = min(MW_MAX_ENERGY, (int) ($attacker['energy'] ?? 0) + 1);
}

/**
 * Ability: Charged Heal - heal self, no damage.
 */
function mw_skill_charged_heal(array $attacker, array $defender, array $context = []): array {
    if (function_exists('mw_log')) {
        mw_log('SKILL: charged_heal by ' . ($attacker['name'] ?? 'unknown'));
    }
    return [
        'damage' => 0,
        'heal_attacker' => mw_calculate_heal($attacker, 14, 1.20),
        'heal_defender' => 0,
        'energy_gain_attacker' => MW_ENERGY_ATTACK,
        'energy_gain_defender' => 0,
        'special_effects' => [],
        'log' => 'Franklin channels healing electricity.',
    ];
}

/**
 * Special: Storm Revival - 1.35x damage, heal self, shock on defender.
 */
function mw_skill_storm_revival(array $attacker, array $defender, array $context = []): array {
    if (function_exists('mw_log')) {
        mw_log('SKILL: storm_revival by ' . ($attacker['name'] ?? 'unknown'));
    }
    $r = mw_calc_damage($attacker, $defender, 1.35);
    $r['heal_attacker'] = mw_calculate_heal($attacker, 20, 1.30);
    $r['heal_defender'] = 0;
    $r['energy_gain_attacker'] = 0;
    $r['energy_gain_defender'] = $r['damage'] > 0 ? MW_ENERGY_DAMAGE : 0;
    $r['special_effects'] = ['shock' => 8];
    $r['log'] = 'Franklin unleashes a healing storm.';
    return $r;
}

// --- Odin ---

/**
 * Passive: Allfather Wisdom - +5% focus bonus, +5% damage at battle start.
 */
function mw_skill_allfather_wisdom(array &$attacker, array &$defender, array $context = []): void {
    if (function_exists('mw_log')) {
        mw_log('SKILL: allfather_wisdom by ' . ($attacker['name'] ?? 'unknown'));
    }
    if (!isset($attacker['battle_bonus']) || !is_array($attacker['battle_bonus'])) {
        $attacker['battle_bonus'] = [];
    }
    $attacker['battle_bonus']['focus_up'] = (float) ($attacker['battle_bonus']['focus_up'] ?? 0) + 0.05;
    $attacker['battle_bonus']['damage_up'] = (float) ($attacker['battle_bonus']['damage_up'] ?? 0) + 0.05;
}

/**
 * Ability: Rune Strike - 1.20x damage, focus_down on defender.
 */
function mw_skill_rune_strike(array $attacker, array $defender, array $context = []): array {
    if (function_exists('mw_log')) {
        mw_log('SKILL: rune_strike by ' . ($attacker['name'] ?? 'unknown'));
    }
    $r = mw_calc_damage($attacker, $defender, 1.20);
    $r['heal_attacker'] = 0;
    $r['heal_defender'] = 0;
    $r['energy_gain_attacker'] = MW_ENERGY_ATTACK;
    $r['energy_gain_defender'] = $r['damage'] > 0 ? MW_ENERGY_DAMAGE : 0;
    $r['special_effects'] = ['focus_down' => true];
    $r['log'] = 'Odin strikes with runic power.';
    return $r;
}

/**
 * Special: Valhalla Command - 1.70x damage, damage_up buff, no energy gain.
 */
function mw_skill_valhalla_command(array $attacker, array $defender, array $context = []): array {
    if (function_exists('mw_log')) {
        mw_log('SKILL: valhalla_command by ' . ($attacker['name'] ?? 'unknown'));
    }
    $r = mw_calc_damage($attacker, $defender, 1.70);
    $r['heal_attacker'] = 0;
    $r['heal_defender'] = 0;
    $r['energy_gain_attacker'] = 0;
    $r['energy_gain_defender'] = $r['damage'] > 0 ? MW_ENERGY_DAMAGE : 0;
    $r['special_effects'] = ['damage_up' => true];
    $r['log'] = 'Odin commands Valhalla.';
    return $r;
}

// --- Wendigo ---

/**
 * Passive: Endless Hunger - +8% damage (applied at battle start).
 */
function mw_skill_endless_hunger(array &$attacker, array &$defender, array $context = []): void {
    if (function_exists('mw_log')) {
        mw_log('SKILL: endless_hunger by ' . ($attacker['name'] ?? 'unknown'));
    }
    if (!isset($attacker['battle_bonus']) || !is_array($attacker['battle_bonus'])) {
        $attacker['battle_bonus'] = [];
    }
    $attacker['battle_bonus']['damage_up'] = (float) ($attacker['battle_bonus']['damage_up'] ?? 0) + 0.08;
}

/**
 * Ability: Devour Claw - 1.25x damage, 15% lifesteal.
 */
function mw_skill_devour_claw(array $attacker, array $defender, array $context = []): array {
    if (function_exists('mw_log')) {
        mw_log('SKILL: devour_claw by ' . ($attacker['name'] ?? 'unknown'));
    }
    $r = mw_calc_damage($attacker, $defender, 1.25);
    $r['heal_attacker'] = (int) round($r['damage'] * 0.15);
    $r['heal_defender'] = 0;
    $r['energy_gain_attacker'] = MW_ENERGY_ATTACK;
    $r['energy_gain_defender'] = $r['damage'] > 0 ? MW_ENERGY_DAMAGE : 0;
    $r['special_effects'] = [];
    $r['log'] = 'Wendigo devours flesh.';
    return $r;
}

/**
 * Special: Cannibal Frenzy - 1.80x damage, 25% lifesteal.
 */
function mw_skill_cannibal_frenzy(array $attacker, array $defender, array $context = []): array {
    if (function_exists('mw_log')) {
        mw_log('SKILL: cannibal_frenzy by ' . ($attacker['name'] ?? 'unknown'));
    }
    $r = mw_calc_damage($attacker, $defender, 1.80);
    $r['heal_attacker'] = (int) round($r['damage'] * 0.25);
    $r['heal_defender'] = 0;
    $r['energy_gain_attacker'] = 0;
    $r['energy_gain_defender'] = $r['damage'] > 0 ? MW_ENERGY_DAMAGE : 0;
    $r['special_effects'] = [];
    $r['log'] = 'Wendigo enters frenzy.';
    return $r;
}

// --- Pinocchio ---

/**
 * Passive: Liar Instinct - +5% crit chance, +5% evade at battle start.
 */
function mw_skill_liar_instinct(array &$attacker, array &$defender, array $context = []): void {
    if (function_exists('mw_log')) {
        mw_log('SKILL: liar_instinct by ' . ($attacker['name'] ?? 'unknown'));
    }
    if (!isset($attacker['battle_bonus']) || !is_array($attacker['battle_bonus'])) {
        $attacker['battle_bonus'] = [];
    }
    $attacker['battle_bonus']['class_crit_chance_up'] = (float) ($attacker['battle_bonus']['class_crit_chance_up'] ?? 0) + 0.05;
    $attacker['battle_bonus']['class_dodge_chance_up'] = (float) ($attacker['battle_bonus']['class_dodge_chance_up'] ?? 0) + 0.05;
}

/**
 * Ability: False Strike - 1.15x damage, 30% chance to confuse.
 */
function mw_skill_false_strike(array $attacker, array $defender, array $context = []): array {
    if (function_exists('mw_log')) {
        mw_log('SKILL: false_strike by ' . ($attacker['name'] ?? 'unknown'));
    }
    $r = mw_calc_damage($attacker, $defender, 1.15);
    $r['heal_attacker'] = 0;
    $r['heal_defender'] = 0;
    $r['energy_gain_attacker'] = MW_ENERGY_ATTACK;
    $r['energy_gain_defender'] = $r['damage'] > 0 ? MW_ENERGY_DAMAGE : 0;
    $r['special_effects'] = [];
    if (random_int(1, 100) <= 30) {
        $r['special_effects']['confuse'] = true;
    }
    $r['log'] = 'Pinocchio deceives the enemy.';
    return $r;
}

/**
 * Special: Puppet Master - 1.60x damage, control effect.
 */
function mw_skill_puppet_master(array $attacker, array $defender, array $context = []): array {
    if (function_exists('mw_log')) {
        mw_log('SKILL: puppet_master by ' . ($attacker['name'] ?? 'unknown'));
    }
    $r = mw_calc_damage($attacker, $defender, 1.60);
    $r['heal_attacker'] = 0;
    $r['heal_defender'] = 0;
    $r['energy_gain_attacker'] = 0;
    $r['energy_gain_defender'] = $r['damage'] > 0 ? MW_ENERGY_DAMAGE : 0;
    $r['special_effects'] = ['control' => true];
    $r['log'] = 'Pinocchio controls the battlefield.';
    return $r;
}

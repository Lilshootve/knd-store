<?php
/**
 * Mind Wars skill registries.
 * Three separate registries: passive, ability, special.
 * Each maps code => handler function name.
 * Loaded via mw_get_passive_registry(), mw_get_ability_registry(), mw_get_special_registry().
 */

$MW_SKILLS_DATA = null;

function mw_load_skills_data(): array {
    global $MW_SKILLS_DATA;
    if ($MW_SKILLS_DATA !== null) {
        return $MW_SKILLS_DATA;
    }
    $MW_SKILLS_DATA = [
        'passive' => [
            // Base class passives
            'crit_boost' => 'mw_skill_crit_boost',
            'damage_reduction' => 'mw_skill_damage_reduction',
            'control_boost' => 'mw_skill_control_boost',
            'tactical_edge' => 'mw_skill_tactical_edge',
            // Legacy class passives (from mw_apply_passive_start_hooks switch)
            'mind_expansion' => 'mw_skill_relativity_field',
            'deductive_precision' => 'mw_skill_deductive_precision',
            'trickster_instinct' => 'mw_skill_trickster_instinct',
            'divine_pressure' => 'mw_skill_divine_pressure',
            'cursed_presence' => 'mw_skill_cursed_presence',
            'deep_armor' => 'mw_skill_deep_armor',
            'frozen_calm' => 'mw_skill_frozen_calm',
            // Character passives
            'vamp_lifesteal' => 'mw_skill_vamp_lifesteal',
            'relativity_field' => 'mw_skill_relativity_field',
            'chaos_mind' => 'mw_skill_chaos_mind',
            'electric_flow' => 'mw_skill_electric_flow',
            'allfather_wisdom' => 'mw_skill_allfather_wisdom',
            'endless_hunger' => 'mw_skill_endless_hunger',
            'liar_instinct' => 'mw_skill_liar_instinct',
            // Legacy aliases
            'wonderland_shift' => 'mw_skill_chaos_mind',
            'spark_of_genius' => 'mw_skill_electric_flow',
        ],
        'ability' => [
            // Base class abilities
            'heavy_strike' => 'mw_skill_heavy_strike',
            'shield_bash' => 'mw_skill_shield_bash',
            'mind_disrupt' => 'mw_skill_mind_disrupt',
            'expose_weakness' => 'mw_skill_expose_weakness',
            'healing_aura' => 'mw_skill_healing_aura',
            'restore' => 'mw_skill_restore',
            'mass_heal' => 'mw_skill_mass_heal',
            // Generic fallbacks
            'generic_strike' => 'mw_skill_generic_strike',
            'generic_focus' => 'mw_skill_generic_focus',
            'generic_burst' => 'mw_skill_generic_burst',
            'generic_legendary_strike' => 'mw_skill_generic_legendary_strike',
            // Character abilities
            'blood_bite' => 'mw_skill_blood_bite',
            'time_shift' => 'mw_skill_time_shift',
            'reality_glitch' => 'mw_skill_reality_glitch',
            'charged_heal' => 'mw_skill_charged_heal',
            'rune_strike' => 'mw_skill_rune_strike',
            'devour_claw' => 'mw_skill_devour_claw',
            'false_strike' => 'mw_skill_false_strike',
            // Legacy character abilities
            'predictive_strike' => 'mw_skill_predictive_strike',
            'clone_assault' => 'mw_skill_clone_assault',
            'thunder_judgment' => 'mw_skill_thunder_judgment',
            'petrifying_gaze' => 'mw_skill_petrifying_gaze',
            'abyssal_grip' => 'mw_skill_abyssal_grip',
            'frostbite_pulse' => 'mw_skill_frostbite_pulse',
            'chaos_tea' => 'mw_skill_chaos_tea',
            'relativity_collapse' => 'mw_skill_relativity_collapse',
            'lightning_conductor' => 'mw_skill_lightning_conductor',
            // Legacy aliases
            'vamp_bite' => 'mw_skill_blood_bite',
        ],
        'special' => [
            // Base class specials
            'execution_burst' => 'mw_skill_execution_burst',
            'iron_wall' => 'mw_skill_iron_wall',
            'chaos_field' => 'mw_skill_chaos_field',
            'mastermind_plan' => 'mw_skill_mastermind_plan',
            // Generic fallbacks
            'generic_finisher' => 'mw_skill_generic_finisher',
            'generic_legendary_finisher' => 'mw_skill_generic_legendary_finisher',
            // Character specials
            'night_domination' => 'mw_skill_night_domination',
            'space_collapse' => 'mw_skill_space_collapse',
            'madness_loop' => 'mw_skill_madness_loop',
            'storm_revival' => 'mw_skill_storm_revival',
            'valhalla_command' => 'mw_skill_valhalla_command',
            'cannibal_frenzy' => 'mw_skill_cannibal_frenzy',
            'puppet_master' => 'mw_skill_puppet_master',
            // Legacy character specials
            'mental_singularity' => 'mw_skill_mental_singularity',
            'storm_protocol' => 'mw_skill_storm_protocol',
            'final_deduction' => 'mw_skill_final_deduction',
            'celestial_rampage' => 'mw_skill_celestial_rampage',
            'wrath_of_olympus' => 'mw_skill_wrath_of_olympus',
            'stone_eternity' => 'mw_skill_stone_eternity',
            'leviathan_crush' => 'mw_skill_leviathan_crush',
            'absolute_zero' => 'mw_skill_absolute_zero',
            // Legacy aliases
            'mirror_madness' => 'mw_skill_madness_loop',
            'vamp_curse_bleed' => 'mw_skill_night_domination',
        ],
    ];
    return $MW_SKILLS_DATA;
}

function mw_get_passive_registry(): array {
    $data = mw_load_skills_data();
    return $data['passive'] ?? [];
}

function mw_get_ability_registry(): array {
    $data = mw_load_skills_data();
    return $data['ability'] ?? [];
}

function mw_get_special_registry(): array {
    $data = mw_load_skills_data();
    return $data['special'] ?? [];
}

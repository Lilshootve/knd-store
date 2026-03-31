<?php
// Mind Wars - Turn-based combat game

require_once __DIR__ . '/config.php';

/** Temporary debug helper - writes to mw_debug.log in document root */
function mw_log($msg) {
    $path = $_SERVER['DOCUMENT_ROOT'] . '/mw_debug.log';
    $line = '[' . date('H:i:s') . '] ' . $msg . PHP_EOL;
    file_put_contents($path, $line, FILE_APPEND);
}
require_once __DIR__ . '/knd_avatar.php';
require_once __DIR__ . '/mind_wars_skill_handlers.php';
require_once __DIR__ . '/mind_wars_skills.php';

const MW_MAX_TURNS = 12;
const MW_HP_BASE = 1000;
const MW_HP_PER_LEVEL = 12;
const MW_MAX_ENERGY = 5;
const MW_ENERGY_START = 1;
const MW_ENERGY_ATTACK_COST = 1;
const MW_ENERGY_ABILITY_COST = 2;
const MW_ENERGY_STUCK_GAIN = 1;
const MW_ENERGY_ATTACK = 1;
const MW_ENERGY_DAMAGE = 1;
const MW_ENERGY_CRIT = 2;
const MW_EFFECT_NEXT_ATTACK_CRIT_TTL = 2;

const MW_XP_WIN = 20;
const MW_XP_LOSE = 8;
const MW_XP_DRAW = 12;
const MW_KE_WIN = 10;
const MW_KE_LOSE = 4;
const MW_KE_DRAW = 6;
const MW_RANK_WIN = 20;
const MW_RANK_LOSE = 5;
const MW_RANK_DRAW = 10;
const MW_QUEUE_TIMEOUT_SECONDS = 90;
const MW_QUEUE_HEARTBEAT_STALE_SECONDS = 20;
const MW_QUEUE_MATCH_GRACE_SECONDS = 25;
const MW_QUEUE_LEVEL_BASE_WINDOW = 1;
const MW_QUEUE_LEVEL_WINDOW_STEP = 0;
const MW_QUEUE_LEVEL_WINDOW_STEP_SECONDS = 20;
const MW_QUEUE_LEVEL_MAX_WINDOW = 1;
const MW_QUEUE_BASE_WINDOW = 100;
const MW_QUEUE_WINDOW_STEP = 50;
const MW_QUEUE_WINDOW_STEP_SECONDS = 10;
const MW_QUEUE_MAX_WINDOW = 600;
const MW_SEASON_DEFAULT_DAYS = 90;
const MW_SEASON_REWARD_XP_TOP500 = 300;
const MW_SEASON_REWARD_KE_PARTICIPATION = 80;

defined('MW_CRIT_MULT') || define('MW_CRIT_MULT', 1.5);
defined('MW_DEFEND_MULT') || define('MW_DEFEND_MULT', 0.5);
defined('MW_FOCUS_REDUCTION') || define('MW_FOCUS_REDUCTION', 0.8);
defined('MW_CRIT_CHANCE_CAP') || define('MW_CRIT_CHANCE_CAP', 0.75);
defined('MW_EVADE_CHANCE_CAP') || define('MW_EVADE_CHANCE_CAP', 0.35);
defined('MW_EVADE_SPEED_RATIO') || define('MW_EVADE_SPEED_RATIO', 0.25);

/** Legendary combat profiles: name => [mind, focus, speed, luck, passive_code, ability_code, special_code] */
const MW_LEGENDARY_PROFILES = [
    'Alice' => ['mind' => 60, 'focus' => 45, 'speed' => 35, 'luck' => 40, 'passive' => 'wonderland_shift', 'ability' => 'chaos_tea', 'special' => 'mirror_madness'],
    'Benjamin Franklin' => ['mind' => 55, 'focus' => 50, 'speed' => 35, 'luck' => 40, 'passive' => 'spark_of_genius', 'ability' => 'lightning_conductor', 'special' => 'storm_protocol'],
    'Zeus' => ['mind' => 50, 'focus' => 40, 'speed' => 50, 'luck' => 40, 'passive' => 'divine_pressure', 'ability' => 'thunder_judgment', 'special' => 'wrath_of_olympus'],
    'Wukong' => ['mind' => 45, 'focus' => 35, 'speed' => 60, 'luck' => 40, 'passive' => 'trickster_instinct', 'ability' => 'clone_assault', 'special' => 'celestial_rampage'],
    'Kraken' => ['mind' => 55, 'focus' => 60, 'speed' => 30, 'luck' => 35, 'passive' => 'deep_armor', 'ability' => 'abyssal_grip', 'special' => 'leviathan_crush'],
    'Medusa' => ['mind' => 50, 'focus' => 55, 'speed' => 35, 'luck' => 40, 'passive' => 'cursed_presence', 'ability' => 'petrifying_gaze', 'special' => 'stone_eternity'],
    'Sherlock Holmes' => ['mind' => 60, 'focus' => 50, 'speed' => 35, 'luck' => 35, 'passive' => 'deductive_precision', 'ability' => 'predictive_strike', 'special' => 'final_deduction'],
    'Albert Einstein' => ['mind' => 65, 'focus' => 45, 'speed' => 30, 'luck' => 40, 'passive' => 'mind_expansion', 'ability' => 'relativity_collapse', 'special' => 'mental_singularity'],
    'Jack Frost' => ['mind' => 45, 'focus' => 40, 'speed' => 50, 'luck' => 45, 'passive' => 'frozen_calm', 'ability' => 'frostbite_pulse', 'special' => 'absolute_zero'],
];

const MW_FALLBACK_STATS = [
    'common'    => ['mind' => 22, 'focus' => 24, 'speed' => 22, 'luck' => 22, 'per_level' => 2],
    'special'   => ['mind' => 28, 'focus' => 30, 'speed' => 26, 'luck' => 26, 'per_level' => 3],
    'rare'      => ['mind' => 28, 'focus' => 30, 'speed' => 26, 'luck' => 26, 'per_level' => 3],
    'epic'      => ['mind' => 36, 'focus' => 38, 'speed' => 34, 'luck' => 32, 'per_level' => 4],
    'legendary' => ['mind' => 45, 'focus' => 45, 'speed' => 45, 'luck' => 45, 'per_level' => 5],
];

const MW_FALLBACK_ABILITY = [
    'common'    => 'generic_strike',
    'special'   => 'generic_focus',
    'rare'      => 'generic_focus',
    'epic'      => 'generic_burst',
    'legendary' => 'generic_legendary_strike',
];

const MW_FALLBACK_SPECIAL = [
    'common'    => 'generic_finisher',
    'special'   => 'generic_finisher',
    'rare'      => 'generic_finisher',
    'epic'      => 'generic_finisher',
    'legendary' => 'generic_legendary_finisher',
];

const MW_FALLBACK_HEAL = 'healing_aura';

const MW_CLASS_STRIKER = 'striker';
const MW_CLASS_TANK = 'tank';
const MW_CLASS_CONTROLLER = 'controller';
const MW_CLASS_STRATEGIST = 'strategist';
const MW_CLASS_TRICKSTER = 'trickster';
const MW_CLASS_SUPPORT = 'support';

const MW_COMBAT_CLASS_LABELS = [
    MW_CLASS_STRIKER => 'Striker',
    MW_CLASS_TANK => 'Tank',
    MW_CLASS_CONTROLLER => 'Controller',
    MW_CLASS_STRATEGIST => 'Strategist',
    MW_CLASS_TRICKSTER => 'Trickster',
    MW_CLASS_SUPPORT => 'Support',
];

const MW_COMBAT_CLASS_TOOLTIPS = [
    MW_CLASS_STRIKER => 'High damage output.',
    MW_CLASS_TANK => 'High survivability and damage resistance.',
    MW_CLASS_CONTROLLER => 'Applies status effects like stun or freeze.',
    MW_CLASS_STRATEGIST => 'Higher critical chance and tactical advantage.',
    MW_CLASS_TRICKSTER => 'Unpredictable abilities and higher dodge potential.',
    MW_CLASS_SUPPORT => 'Healing and team support.',
];

const MW_LEGENDARY_CLASS_MAP = [
    'albert einstein' => MW_CLASS_STRATEGIST,
    'benjamin franklin' => MW_CLASS_STRATEGIST,
    'sherlock holmes' => MW_CLASS_STRATEGIST,
    'wukong' => MW_CLASS_TRICKSTER,
    'zeus' => MW_CLASS_STRIKER,
    'medusa' => MW_CLASS_CONTROLLER,
    'kraken' => MW_CLASS_TANK,
    'jack frost' => MW_CLASS_CONTROLLER,
    'alice' => MW_CLASS_TRICKSTER,
];

const MW_EPIC_CLASS_MAP = [
    'thor' => MW_CLASS_STRIKER,
    'hercules' => MW_CLASS_TANK,
    'fenrir' => MW_CLASS_STRIKER,
    'hydra' => MW_CLASS_TANK,
    'dracula' => MW_CLASS_TRICKSTER,
    'frankenstein' => MW_CLASS_TANK,
    'anubis' => MW_CLASS_CONTROLLER,
    'odin' => MW_CLASS_STRATEGIST,
    'corrupted odin' => MW_CLASS_CONTROLLER,
    'loki' => MW_CLASS_TRICKSTER,
    'corrupted loki' => MW_CLASS_TRICKSTER,
    'krampus' => MW_CLASS_CONTROLLER,
    'sandman' => MW_CLASS_CONTROLLER,
    'headless horseman' => MW_CLASS_STRIKER,
    'wendigo' => MW_CLASS_STRIKER,
    'genghis khan' => MW_CLASS_STRATEGIST,
    'napoleon' => MW_CLASS_STRATEGIST,
    'julio cesar' => MW_CLASS_STRATEGIST,
    'george washington' => MW_CLASS_STRATEGIST,
    'simon bolivar' => MW_CLASS_STRATEGIST,
    'isaac newton' => MW_CLASS_STRATEGIST,
    'abraham lincoln' => MW_CLASS_STRATEGIST,
    'arthur king' => MW_CLASS_TANK,
    'morgana' => MW_CLASS_CONTROLLER,
    'queen grimhilde' => MW_CLASS_CONTROLLER,
    'mad hatter' => MW_CLASS_TRICKSTER,
    'aladdin' => MW_CLASS_TRICKSTER,
    'rapunzel' => MW_CLASS_STRATEGIST,
    'pinocchio' => MW_CLASS_TRICKSTER,
    'little red riding hood' => MW_CLASS_STRIKER,
    'puss in boots' => MW_CLASS_TRICKSTER,
    'dorian gray' => MW_CLASS_TRICKSTER,
    'ichabod crane' => MW_CLASS_STRATEGIST,
    'long john silver' => MW_CLASS_STRATEGIST,
];

const MW_CLASS_NAME_ALIASES = [
    'pussy in boots' => 'puss in boots',
    'napoleon bonaparte' => 'napoleon',
];

const MW_RARE_SPECIAL_ROTATION = [
    'alpha' => MW_CLASS_STRIKER,
    'beta' => MW_CLASS_TANK,
    'gamma' => MW_CLASS_CONTROLLER,
    'delta' => MW_CLASS_STRATEGIST,
    'epsilon' => MW_CLASS_TRICKSTER,
    'zeta' => MW_CLASS_STRIKER,
    'eta' => MW_CLASS_TANK,
    'theta' => MW_CLASS_CONTROLLER,
    'iota' => MW_CLASS_STRATEGIST,
    'kappa' => MW_CLASS_TRICKSTER,
    'lambda' => MW_CLASS_STRIKER,
    'mu' => MW_CLASS_TANK,
    'nu' => MW_CLASS_CONTROLLER,
    'xi' => MW_CLASS_STRATEGIST,
    'omicron' => MW_CLASS_TRICKSTER,
    'sigma' => MW_CLASS_STRIKER,
    'tau' => MW_CLASS_TANK,
    'upsilon' => MW_CLASS_CONTROLLER,
    'phi' => MW_CLASS_STRATEGIST,
    'chi' => MW_CLASS_TRICKSTER,
    'psi' => MW_CLASS_STRIKER,
    'omega' => MW_CLASS_TANK,
    'prime' => MW_CLASS_CONTROLLER,
    'nova' => MW_CLASS_STRATEGIST,
];

const MW_GREEK_ROTATION_ORDER = [
    'alpha', 'beta', 'gamma', 'delta', 'epsilon', 'zeta', 'eta', 'theta',
    'iota', 'kappa', 'lambda', 'mu', 'nu', 'xi', 'omicron', 'sigma',
    'tau', 'upsilon', 'phi', 'chi', 'psi', 'omega', 'prime', 'nova',
];

const MW_CADET_ROTATION_CLASSES = [MW_CLASS_STRIKER, MW_CLASS_TANK, MW_CLASS_CONTROLLER];

const MW_COMBAT_CLASS_RARITY_FALLBACK = [
    'common' => MW_CLASS_STRIKER,
    'special' => MW_CLASS_STRIKER,
    'rare' => MW_CLASS_STRIKER,
    'epic' => MW_CLASS_STRATEGIST,
    'legendary' => MW_CLASS_STRATEGIST,
];

function mw_normalize_name_key(string $value): string {
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (function_exists('iconv')) {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($ascii) && $ascii !== '') {
            $value = $ascii;
        }
    }
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    return MW_CLASS_NAME_ALIASES[$value] ?? $value;
}

function mw_extract_group_token(string $normalizedName, string $prefix): ?string {
    if (strpos($normalizedName, $prefix) !== 0) {
        return null;
    }
    $suffix = trim(substr($normalizedName, strlen($prefix)));
    if ($suffix === '') {
        return null;
    }
    $parts = preg_split('/\s+/', $suffix) ?: [];
    return isset($parts[0]) ? (string) $parts[0] : null;
}

function mw_cadet_rotation_class(?string $token): ?string {
    if (!$token) {
        return null;
    }
    $idx = array_search($token, MW_GREEK_ROTATION_ORDER, true);
    if ($idx === false) {
        return null;
    }
    return MW_CADET_ROTATION_CLASSES[$idx % count(MW_CADET_ROTATION_CLASSES)] ?? null;
}

function mw_combat_class_label(string $combatClass): string {
    return MW_COMBAT_CLASS_LABELS[$combatClass] ?? 'Fighter';
}

function mw_combat_class_tooltip(string $combatClass): string {
    return MW_COMBAT_CLASS_TOOLTIPS[$combatClass] ?? 'Balanced fighter.';
}

function mw_resolve_combat_class(array $avatar): string {
    $rawName = (string) ($avatar['name'] ?? '');
    $assetBase = (string) pathinfo((string) ($avatar['asset_path'] ?? ''), PATHINFO_FILENAME);
    $nameKey = mw_normalize_name_key($rawName);
    $assetKey = mw_normalize_name_key($assetBase);
    $rarity = strtolower((string) ($avatar['rarity'] ?? 'common'));
    if (!isset(MW_COMBAT_CLASS_RARITY_FALLBACK[$rarity])) {
        $rarity = 'common';
    }

    foreach ([$nameKey, $assetKey] as $key) {
        if ($key === '') {
            continue;
        }
        if (isset(MW_LEGENDARY_CLASS_MAP[$key])) {
            return MW_LEGENDARY_CLASS_MAP[$key];
        }
        if (isset(MW_EPIC_CLASS_MAP[$key])) {
            return MW_EPIC_CLASS_MAP[$key];
        }
    }

    foreach ([$nameKey, $assetKey] as $key) {
        if ($key === '') {
            continue;
        }
        $token = mw_extract_group_token($key, 'knd vanguard ');
        if ($token && isset(MW_RARE_SPECIAL_ROTATION[$token])) {
            return MW_RARE_SPECIAL_ROTATION[$token];
        }
        $token = mw_extract_group_token($key, 'knd specialist ');
        if ($token && isset(MW_RARE_SPECIAL_ROTATION[$token])) {
            return MW_RARE_SPECIAL_ROTATION[$token];
        }
        $token = mw_extract_group_token($key, 'knd cadet ');
        $cadetClass = mw_cadet_rotation_class($token);
        if ($cadetClass) {
            return $cadetClass;
        }
    }

    return MW_COMBAT_CLASS_RARITY_FALLBACK[$rarity] ?? MW_CLASS_STRIKER;
}

function mw_apply_combat_class_profile_bonuses(array $profile): array {
    $combatClass = (string) ($profile['combat_class'] ?? MW_CLASS_STRIKER);
    $profile['hp_flat_bonus'] = (int) ($profile['hp_flat_bonus'] ?? 0);
    if ($combatClass === MW_CLASS_TANK) {
        $profile['focus'] = (int) round(((int) ($profile['focus'] ?? 0)) * 1.10);
        $profile['hp_flat_bonus'] += 10;
    }
    return $profile;
}

function mw_get_class_battle_bonus(string $combatClass): array {
    switch ($combatClass) {
        case MW_CLASS_STRIKER:
            return ['class_damage_up' => 0.10];
        case MW_CLASS_CONTROLLER:
            return ['class_status_chance_up' => 0.05];
        case MW_CLASS_STRATEGIST:
            return ['class_crit_chance_up' => 0.05];
        case MW_CLASS_TRICKSTER:
            return ['class_dodge_chance_up' => 0.05];
        default:
            return [];
    }
}

function mw_merge_battle_bonus(array $base, array $extra): array {
    $merged = $base;
    foreach ($extra as $k => $v) {
        if (isset($merged[$k]) && is_numeric($merged[$k]) && is_numeric($v)) {
            $merged[$k] += $v;
        } else {
            $merged[$k] = $v;
        }
    }
    return $merged;
}

function mw_calculate_heal(array $caster, int $base = 10, float $mult = 1.0): int {
    $mind = (int) ($caster['mind'] ?? 0);
    $focus = (int) ($caster['focus'] ?? 0);
    $scaled = $base * (1 + ($mind * 0.01) + ($focus * 0.005));
    $heal = max(1, (int) round($scaled * $mult));
    if (defined('MW_DEBUG') && MW_DEBUG) {
        error_log(sprintf('MW HEAL: caster=%s base=%d mind=%d focus=%d mult=%.2f result=%d', $caster['name'] ?? '?', $base, $mind, $focus, $mult, $heal));
    }
    return $heal;
}

function mw_get_multiplier(string $type): float {
    return match ($type) {
        'low' => 0.8,
        'medium' => 1.0,
        'high' => 1.3,
        'massive' => 1.8,
        default => 1.0,
    };
}

function mw_init_effects_container(array &$fighter): void {
    if (!isset($fighter['effects']) || !is_array($fighter['effects'])) {
        $fighter['effects'] = [];
    }
}

function mw_has_effect(array $fighter, string $effect): bool {
    if (!isset($fighter['effects']) || !is_array($fighter['effects'])) {
        return false;
    }
    if (!isset($fighter['effects'][$effect]) || !is_array($fighter['effects'][$effect])) {
        return false;
    }
    $entry = $fighter['effects'][$effect];
    return (int) ($entry['duration'] ?? 0) > 0 && (int) ($entry['stacks'] ?? 1) > 0;
}

function mw_apply_effect(array &$fighter, string $effect, array $payload): void {
    mw_init_effects_container($fighter);
    $defaults = [
        'type' => 'debuff',
        'stacks' => 1,
        'duration' => 1,
        'potency' => 0,
        'tick_phase' => 'end_turn',
        'source' => 'unknown',
        'applied_turn' => 0,
        'max_stacks' => 1,
        'stack_mode' => 'refresh',
    ];
    $next = array_merge($defaults, $payload);
    $next['stacks'] = max(1, (int) $next['stacks']);
    $next['duration'] = max(0, (int) $next['duration']);
    $next['max_stacks'] = max(1, (int) $next['max_stacks']);

    if (!isset($fighter['effects'][$effect]) || !is_array($fighter['effects'][$effect])) {
        $fighter['effects'][$effect] = $next;
        return;
    }

    $curr = $fighter['effects'][$effect];
    $mode = (string) ($next['stack_mode'] ?? 'refresh');
    if ($mode === 'stack') {
        $curr['stacks'] = min((int) $next['max_stacks'], max(1, (int) ($curr['stacks'] ?? 1) + (int) $next['stacks']));
        $curr['duration'] = max((int) ($curr['duration'] ?? 0), (int) $next['duration']);
        $curr['potency'] = (float) ($curr['potency'] ?? 0) + (float) ($next['potency'] ?? 0);
    } elseif ($mode === 'replace_if_stronger') {
        $currPotency = (float) ($curr['potency'] ?? 0);
        $nextPotency = (float) ($next['potency'] ?? 0);
        if ($nextPotency >= $currPotency) {
            $curr = array_merge($curr, $next);
        } else {
            $curr['duration'] = max((int) ($curr['duration'] ?? 0), (int) $next['duration']);
        }
    } else {
        $curr['duration'] = max((int) ($curr['duration'] ?? 0), (int) $next['duration']);
        $curr['potency'] = max((float) ($curr['potency'] ?? 0), (float) ($next['potency'] ?? 0));
        $curr['type'] = $next['type'];
        $curr['tick_phase'] = $next['tick_phase'];
        $curr['source'] = $next['source'];
        $curr['applied_turn'] = $next['applied_turn'];
        $curr['max_stacks'] = $next['max_stacks'];
    }

    $fighter['effects'][$effect] = $curr;
}

function mw_remove_effect(array &$fighter, string $effect): void {
    if (isset($fighter['effects'][$effect])) {
        unset($fighter['effects'][$effect]);
    }
}

function mw_start_turn_phase(array &$fighter, int $turn): array {
    mw_init_effects_container($fighter);
    $priority = ['petrify', 'freeze', 'stun'];
    foreach ($priority as $effect) {
        if (!mw_has_effect($fighter, $effect)) {
            continue;
        }
        $fighter['effects'][$effect]['duration'] = max(0, (int) ($fighter['effects'][$effect]['duration'] ?? 0) - 1);
        if ((int) ($fighter['effects'][$effect]['duration'] ?? 0) <= 0) {
            mw_remove_effect($fighter, $effect);
            if (isset($fighter['states'][$effect])) {
                unset($fighter['states'][$effect]);
            }
        }
        return ['can_act' => false, 'blocked_by' => $effect];
    }
    return ['can_act' => true, 'blocked_by' => null];
}

function mw_end_turn_phase(array &$fighter, int $turn): array {
    mw_init_effects_container($fighter);
    $events = [];
    foreach (array_keys($fighter['effects']) as $effect) {
        $entry = $fighter['effects'][$effect] ?? null;
        if (!is_array($entry)) {
            unset($fighter['effects'][$effect]);
            continue;
        }
        $phase = (string) ($entry['tick_phase'] ?? 'end_turn');
        if ($phase !== 'end_turn') {
            continue;
        }
        if ($effect === 'shock') {
            $potency = max(0, (int) round((float) ($entry['potency'] ?? 0)));
            $stacks = max(1, (int) ($entry['stacks'] ?? 1));
            $damage = $potency * $stacks;
            if ($damage > 0) {
                $fighter['hp'] = max(0, (int) ($fighter['hp'] ?? 0) - $damage);
                $events[] = ['type' => 'shock', 'damage' => $damage];
            }
        }
        $entry['duration'] = max(0, (int) ($entry['duration'] ?? 0) - 1);
        if ($entry['duration'] <= 0) {
            unset($fighter['effects'][$effect]);
            if (isset($fighter['states'][$effect])) {
                unset($fighter['states'][$effect]);
            }
        } else {
            $fighter['effects'][$effect] = $entry;
        }
    }
    return $events;
}

function mw_apply_effect_payload(array &$actor, array &$target, array $result, string $sourceCode, int $turn): array {
    if (!isset($actor['states']) || !is_array($actor['states'])) $actor['states'] = [];
    if (!isset($target['states']) || !is_array($target['states'])) $target['states'] = [];
    mw_init_effects_container($actor);
    mw_init_effects_container($target);
    $events = [];

    if (!empty($result['stun_applied'])) {
        mw_apply_effect($target, 'stun', ['type' => 'crowd_control', 'duration' => 1, 'tick_phase' => 'start_turn', 'source' => $sourceCode, 'applied_turn' => $turn]);
        $target['states']['stun'] = true;
        $events[] = 'stun';
    }
    if (!empty($result['stun_chance'])) {
        $chance = max(0, min(100, (int) $result['stun_chance']));
        if (random_int(1, 100) <= $chance) {
            mw_apply_effect($target, 'stun', ['type' => 'crowd_control', 'duration' => 1, 'tick_phase' => 'start_turn', 'source' => $sourceCode, 'applied_turn' => $turn]);
            $target['states']['stun'] = true;
            $events[] = 'stun';
        }
    }
    if (!empty($result['chill_applied'])) {
        mw_apply_effect($target, 'chill', ['type' => 'debuff', 'duration' => 2, 'potency' => 0.20, 'tick_phase' => 'end_turn', 'source' => $sourceCode, 'applied_turn' => $turn]);
        $target['states']['chill'] = true;
        $events[] = 'chill';
    }

    $special = (array) ($result['special_effects'] ?? []);
    if (!empty($special['stun'])) {
        mw_apply_effect($target, 'stun', ['type' => 'crowd_control', 'duration' => 1, 'tick_phase' => 'start_turn', 'source' => $sourceCode, 'applied_turn' => $turn]);
        $target['states']['stun'] = true;
        $events[] = 'stun';
    }
    if (!empty($special['freeze'])) {
        mw_apply_effect($target, 'freeze', ['type' => 'crowd_control', 'duration' => 1, 'tick_phase' => 'start_turn', 'source' => $sourceCode, 'applied_turn' => $turn]);
        $target['states']['freeze'] = true;
        $events[] = 'freeze';
    }
    if (!empty($special['petrify'])) {
        mw_apply_effect($target, 'petrify', ['type' => 'crowd_control', 'duration' => 1, 'tick_phase' => 'start_turn', 'source' => $sourceCode, 'applied_turn' => $turn]);
        $target['states']['petrify'] = true;
        $events[] = 'petrify';
    }
    if (!empty($special['focus_down'])) {
        mw_apply_effect($target, 'focus_down', ['type' => 'debuff', 'duration' => 2, 'potency' => 0.20, 'tick_phase' => 'end_turn', 'source' => $sourceCode, 'applied_turn' => $turn]);
        $target['states']['focus_down'] = true;
        $events[] = 'focus_down';
    }
    if (!empty($special['shock'])) {
        mw_apply_effect($target, 'shock', [
            'type' => 'debuff',
            'duration' => 2,
            'potency' => (int) $special['shock'],
            'tick_phase' => 'end_turn',
            'source' => $sourceCode,
            'applied_turn' => $turn,
            'max_stacks' => 3,
            'stack_mode' => 'stack',
        ]);
        $target['states']['shock'] = true;
        $events[] = 'shock';
    }
    if (!empty($special['random'])) {
        $random = (string) $special['random'];
        if ($random === 'stun') {
            mw_apply_effect($target, 'stun', ['type' => 'crowd_control', 'duration' => 1, 'tick_phase' => 'start_turn', 'source' => $sourceCode, 'applied_turn' => $turn]);
            $target['states']['stun'] = true;
            $events[] = 'stun';
        } elseif ($random === 'heal') {
            $heal = mw_calculate_heal($actor, 12);
            $actor['hp'] = min((int) ($actor['hp_max'] ?? 0), (int) ($actor['hp'] ?? 0) + $heal);
            $events[] = 'heal';
        } elseif ($random === 'extra_hit') {
            mw_apply_effect($actor, 'damage_up_once', ['type' => 'buff', 'duration' => 1, 'potency' => 0.15, 'tick_phase' => 'on_action', 'source' => $sourceCode, 'applied_turn' => $turn]);
            $events[] = 'extra_hit';
        } elseif ($random === 'shield') {
            mw_apply_effect($actor, 'shield', ['type' => 'utility', 'duration' => 2, 'potency' => 18, 'tick_phase' => 'end_turn', 'source' => $sourceCode, 'applied_turn' => $turn, 'stack_mode' => 'replace_if_stronger']);
            $actor['states']['shield'] = true;
            $events[] = 'shield';
        }
    }

    if (!empty($result['shield_attacker'])) {
        mw_apply_effect($actor, 'shield', ['type' => 'utility', 'duration' => 2, 'potency' => 18, 'tick_phase' => 'end_turn', 'source' => $sourceCode, 'applied_turn' => $turn, 'stack_mode' => 'replace_if_stronger']);
        $actor['states']['shield'] = true;
        $events[] = 'shield';
    }
    if (!empty($result['next_attack_crit'])) {
        mw_apply_effect($actor, 'next_attack_crit', ['type' => 'buff', 'duration' => MW_EFFECT_NEXT_ATTACK_CRIT_TTL, 'tick_phase' => 'end_turn', 'source' => $sourceCode, 'applied_turn' => $turn]);
        $events[] = 'next_attack_crit';
    }
    return $events;
}

function mw_consume_attack_one_shots(array &$attacker): void {
    if (isset($attacker['battle_bonus']['focus_up_once'])) {
        unset($attacker['battle_bonus']['focus_up_once']);
    }
    if (isset($attacker['battle_bonus']['crit_damage_up_temp'])) {
        unset($attacker['battle_bonus']['crit_damage_up_temp']);
    }
    mw_remove_effect($attacker, 'next_attack_crit');
}

function mw_adjust_status_chance(int $baseChance, array $attacker): int {
    $bonus = (float) ($attacker['battle_bonus']['class_status_chance_up'] ?? 0);
    $chance = $baseChance + (int) round($bonus * 100);
    return max(0, min(100, $chance));
}

function mw_roll_status_chance(int $baseChance, array $attacker): bool {
    return random_int(1, 100) <= mw_adjust_status_chance($baseChance, $attacker);
}

/**
 * Get avatar skills from mw_avatar_skills by mw_avatar_id (mw_avatars.id).
 * Uses ONLY avatar_id - no name lookups.
 * Returns ['passive_code'=>?, 'ability_code'=>?, 'special_code'=>?, 'heal'=>?] or null.
 */
function mw_get_avatar_skills(PDO $pdo, int $mwAvatarId): ?array {
    mw_log('=== ENTER SKILLS FUNCTION ===');
    mw_log('INPUT ID: ' . $mwAvatarId);
    if (!$pdo || $mwAvatarId <= 0) {
        mw_log('ERROR: INVALID mwAvatarId');
        return null;
    }
    try {
        $stmt = $pdo->prepare(
            "SELECT passive_code, ability_code, special_code, heal
             FROM mw_avatar_skills
             WHERE avatar_id = ?
             LIMIT 1"
        );
        $stmt->execute([$mwAvatarId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        mw_log('DB RESULT: ' . json_encode($row));
    } catch (Throwable $e) {
        $row = null;
        try {
            $stmt = $pdo->prepare(
                "SELECT passive AS passive_code, ability AS ability_code, special AS special_code, heal
                 FROM mw_avatar_skills
                 WHERE avatar_id = ?
                 LIMIT 1"
            );
            $stmt->execute([$mwAvatarId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            mw_log('DB RESULT: ' . json_encode($row));
        } catch (Throwable $e2) {
            return null;
        }
    }
    if (!$row) {
        return null;
    }
    $pc = trim((string) ($row['passive_code'] ?? ''));
    $ac = trim((string) ($row['ability_code'] ?? ''));
    $sc = trim((string) ($row['special_code'] ?? ''));
    if ($pc === '' && $ac === '' && $sc === '') {
        return null;
    }
    return [
        'passive_code' => $pc !== '' ? $pc : null,
        'ability_code' => $ac !== '' ? $ac : null,
        'special_code' => $sc !== '' ? $sc : null,
        'heal' => isset($row['heal']) ? trim((string) $row['heal']) : null,
    ];
}

/** Map DB class enum (Striker, Tank, etc.) to lowercase combat class constant */
function mw_db_class_to_combat_class(?string $dbClass): string {
    $c = strtolower(trim((string) ($dbClass ?? '')));
    $valid = [MW_CLASS_STRIKER, MW_CLASS_TANK, MW_CLASS_CONTROLLER, MW_CLASS_STRATEGIST, MW_CLASS_TRICKSTER, MW_CLASS_SUPPORT];
    return in_array($c, $valid, true) ? $c : MW_CLASS_STRIKER;
}

/**
 * Fetch combat profile from DB only. Throws RuntimeException with "AGUACATE" if avatar/stats/skills missing.
 * Uses mw_avatars + mw_avatar_stats + mw_avatar_skills (INNER JOIN).
 */
function mw_get_combat_profile_from_db(PDO $pdo, int $mwAvatarId): array {
    $stmt = $pdo->prepare(
        "SELECT a.id, a.name, a.rarity, a.class,
                s.mind, s.focus, s.speed, s.luck,
                sk.passive_code, sk.ability_code, sk.special_code, sk.heal
         FROM mw_avatars a
         INNER JOIN mw_avatar_stats s ON s.avatar_id = a.id
         INNER JOIN mw_avatar_skills sk ON sk.avatar_id = a.id
         WHERE a.id = ? LIMIT 1"
    );
    $stmt->execute([$mwAvatarId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new RuntimeException("AGUACATE: mw_avatar_id {$mwAvatarId} not found in mw_avatars+mw_avatar_stats+mw_avatar_skills");
    }
    $mind = max(1, (int) ($row['mind'] ?? 0));
    $focus = max(1, (int) ($row['focus'] ?? 0));
    $speed = max(1, (int) ($row['speed'] ?? 0));
    $luck = max(1, (int) ($row['luck'] ?? 0));
    if ($mind < 1 && $focus < 1 && $speed < 1 && $luck < 1) {
        throw new RuntimeException("AGUACATE: mw_avatar_id {$mwAvatarId} has invalid stats (all zero)");
    }
    $pc = trim((string) ($row['passive_code'] ?? ''));
    $ac = trim((string) ($row['ability_code'] ?? ''));
    $sc = trim((string) ($row['special_code'] ?? ''));
    $rawHeal = trim((string) ($row['heal'] ?? ''));
    $hc = ($rawHeal !== '' && $rawHeal !== '0') ? $rawHeal : null;
    if ($pc === '' || $ac === '' || $sc === '') {
        throw new RuntimeException("AGUACATE: mw_avatar_id {$mwAvatarId} missing passive/ability/special in mw_avatar_skills");
    }
    $rarity = strtolower((string) ($row['rarity'] ?? 'common'));
    $combatClass = mw_db_class_to_combat_class((string) ($row['class'] ?? ''));
    $profile = [
        'mind' => $mind,
        'focus' => $focus,
        'speed' => $speed,
        'luck' => $luck,
        'passive_code' => $pc,
        'ability_code' => $ac,
        'special_code' => $sc,
        'heal_code' => $hc,
        'is_legendary' => $rarity === 'legendary',
        'combat_kit_label' => $rarity === 'legendary' ? 'Legendary' : ucfirst($rarity),
        'combat_class' => $combatClass,
        'combat_class_label' => mw_combat_class_label($combatClass),
        'combat_class_tooltip' => mw_combat_class_tooltip($combatClass),
    ];
    return mw_apply_combat_class_profile_bonuses($profile);
}

/**
 * Build combat profile from avatar array. DB-only: mw_avatars + mw_avatar_stats + mw_avatar_skills.
 * Throws RuntimeException with "AGUACATE" if avatar not found or missing stats/skills.
 */
function mw_get_combat_profile(array $avatar): array {
    $pdo = function_exists('getDBConnection') ? getDBConnection() : null;
    if (!$pdo) {
        throw new RuntimeException('AGUACATE: Database connection required for combat profile');
    }
    $mwAvatarId = (int) ($avatar['mw_avatar_id'] ?? 0);
    if ($mwAvatarId <= 0 && !isset($avatar['item_id'])) {
        $mwAvatarId = (int) ($avatar['id'] ?? 0);
    }
    if ($mwAvatarId <= 0) {
        $name = trim((string) ($avatar['name'] ?? ''));
        if ($name !== '') {
            $stmt = $pdo->prepare('SELECT id FROM mw_avatars WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) LIMIT 1');
            $stmt->execute([$name]);
            $mwAvatarId = (int) ($stmt->fetchColumn() ?: 0);
        }
    }
    if ($mwAvatarId <= 0) {
        throw new RuntimeException('AGUACATE: Cannot resolve mw_avatar_id for avatar: ' . json_encode($avatar));
    }
    return mw_get_combat_profile_from_db($pdo, $mwAvatarId);
}

function mw_calc_hp(int $level): int {
    return MW_HP_BASE + (max(1, $level) * MW_HP_PER_LEVEL);
}

function mw_build_fighter(array $avatar, bool $isEnemy = false): array {
    $level = max(1, (int) ($avatar['avatar_level'] ?? 1));
    $profile = mw_get_combat_profile($avatar);
    $hp = mw_calc_hp($level) + (int) ($profile['hp_flat_bonus'] ?? 0);
    $classBattleBonus = mw_get_class_battle_bonus((string) ($profile['combat_class'] ?? MW_CLASS_STRIKER));
    $battleBonus = $classBattleBonus;
    if (!$isEnemy) {
        $battleBonus = mw_merge_battle_bonus($battleBonus, mw_alice_start_bonus($profile));
    }

    $rawImg = (string) ($avatar['mw_image'] ?? $avatar['image'] ?? $avatar['asset_path'] ?? '');
    $assetPath = $rawImg !== '' ? (strpos($rawImg, '/') === 0 || strpos($rawImg, 'http') === 0 ? $rawImg : '/assets/avatars/' . ltrim($rawImg, '/')) : '';

    $fighter = [
        'id' => (int) ($avatar['item_id'] ?? $avatar['id'] ?? 0),
        'name' => (string) ($avatar['name'] ?? 'Enemy'),
        'asset_path' => $assetPath,
        'level' => $level,
        'hp' => $hp,
        'hp_max' => $hp,
        'mind' => $profile['mind'],
        'focus' => $profile['focus'],
        'speed' => $profile['speed'],
        'luck' => $profile['luck'],
        'passive_code' => $profile['passive_code'] ?? null,
        'ability_code' => $profile['ability_code'] ?? null,
        'special_code' => $profile['special_code'] ?? null,
        'heal_code' => $profile['heal_code'] ?? null,
        'is_legendary' => !empty($profile['is_legendary']),
        'rarity' => (string) ($avatar['rarity'] ?? 'common'),
        'combat_class' => (string) ($profile['combat_class'] ?? MW_CLASS_STRIKER),
        'combat_class_label' => (string) ($profile['combat_class_label'] ?? 'Fighter'),
        'combat_class_tooltip' => (string) ($profile['combat_class_tooltip'] ?? 'Balanced fighter.'),
        'combat_kit_label' => (string) ($profile['combat_kit_label'] ?? ''),
        'energy' => MW_ENERGY_START,
        'defending' => false,
        'ability_cooldown' => 0,
        'states' => [],
        'effects' => [],
        'battle_bonus' => $battleBonus,
    ];
    if (!empty($fighter['battle_bonus']['energy_bonus'])) {
        $fighter['energy'] = min(MW_MAX_ENERGY, (int) $fighter['energy'] + (int) $fighter['battle_bonus']['energy_bonus']);
        unset($fighter['battle_bonus']['energy_bonus']);
    }
    mw_apply_passive_start_hooks($fighter);
    return $fighter;
}

function mw_apply_passive_start_hooks(array &$fighter): void {
    $passive = (string) ($fighter['passive_code'] ?? '');
    if (!isset($fighter['states']) || !is_array($fighter['states'])) {
        $fighter['states'] = [];
    }
    if (!isset($fighter['battle_bonus']) || !is_array($fighter['battle_bonus'])) {
        $fighter['battle_bonus'] = [];
    }
    mw_init_effects_container($fighter);

    $dummy = [];
    if (mw_execute_skill_code($passive, 'passive', $fighter, $dummy, []) === true) {
        return;
    }

    switch ($passive) {
        case 'mind_expansion':
            $fighter['battle_bonus']['damage_up'] = (float) ($fighter['battle_bonus']['damage_up'] ?? 0) + 0.05;
            break;
        case 'deductive_precision':
            $fighter['battle_bonus']['class_crit_chance_up'] = (float) ($fighter['battle_bonus']['class_crit_chance_up'] ?? 0) + 0.03;
            break;
        case 'trickster_instinct':
            $fighter['battle_bonus']['class_dodge_chance_up'] = (float) ($fighter['battle_bonus']['class_dodge_chance_up'] ?? 0) + 0.03;
            break;
        case 'divine_pressure':
            $fighter['battle_bonus']['damage_up'] = (float) ($fighter['battle_bonus']['damage_up'] ?? 0) + 0.04;
            break;
        case 'cursed_presence':
            $fighter['states']['cursed_presence'] = true;
            mw_apply_effect($fighter, 'cursed_presence', [
                'type' => 'debuff',
                'duration' => 999,
                'tick_phase' => 'end_turn',
                'source' => $passive,
            ]);
            break;
        case 'deep_armor':
            $fighter['battle_bonus']['damage_taken_down'] = (float) ($fighter['battle_bonus']['damage_taken_down'] ?? 0) + 0.08;
            break;
        case 'frozen_calm':
            $fighter['battle_bonus']['status_resist_up'] = (float) ($fighter['battle_bonus']['status_resist_up'] ?? 0) + 0.10;
            break;
    }
}

function mw_alice_start_bonus(array $profile): array {
    if (($profile['passive_code'] ?? '') !== 'wonderland_shift') {
        return [];
    }
    $r = random_int(1, 3);
    if ($r === 1) return ['damage_up' => 0.10];
    if ($r === 2) return ['evade_up' => 0.10];
    return ['energy_bonus' => 1];
}

function mw_roll_initiative(array $player, array $enemy): bool {
    $pScore = $player['speed'] + random_int(1, 6);
    $eScore = $enemy['speed'] + random_int(1, 6);
    if ($pScore === $eScore) {
        return random_int(0, 1) === 1;
    }
    return $pScore > $eScore;
}

function mw_calc_damage(array $attacker, array $defender, float $mult = 1.0, bool $guaranteedCrit = false, bool $ignoreFocus = false): array {
    $defenderEffects = (array) ($defender['effects'] ?? []);
    $defenderFocus = (int) ($defender['focus'] ?? 0);
    if (!empty($defenderEffects['focus_down']) && is_array($defenderEffects['focus_down'])) {
        $potency = (float) ($defenderEffects['focus_down']['potency'] ?? 0.20);
        $defenderFocus = max(0, (int) round($defenderFocus * (1 - $potency)));
    }
    $defenderSpeed = (int) ($defender['speed'] ?? 0);
    if (!empty($defenderEffects['chill']) && is_array($defenderEffects['chill'])) {
        $potency = (float) ($defenderEffects['chill']['potency'] ?? 0.20);
        $defenderSpeed = max(1, (int) round($defenderSpeed * (1 - $potency)));
    }

    $base = ($attacker['mind'] * 2) + random_int(4, 8);
    $reduction = $ignoreFocus ? 0 : ($defenderFocus * MW_FOCUS_REDUCTION);
    $dmg = max(1, (int) round(($base - $reduction) * $mult));

    $critChance = ($attacker['luck'] * 2) / 100;
    $critChance += (float) ($attacker['battle_bonus']['class_crit_chance_up'] ?? 0);
    $critChance = min($critChance, MW_CRIT_CHANCE_CAP);
    $isCrit = $guaranteedCrit || (random_int(1, 100) / 100 <= $critChance);
    if ($isCrit) {
        $dmg = (int) round($dmg * MW_CRIT_MULT);
        $critUp = ($attacker['battle_bonus']['crit_damage_up'] ?? 0) + ($attacker['battle_bonus']['crit_damage_up_temp'] ?? 0);
        if ($critUp > 0) {
            $dmg = (int) round($dmg * (1 + $critUp));
        }
    }

    $evadeChance = ($defenderSpeed * MW_EVADE_SPEED_RATIO) / 100;
    $evadeChance += (float) ($defender['battle_bonus']['class_dodge_chance_up'] ?? 0);
    if (($defender['battle_bonus']['evade_up'] ?? 0) > 0) {
        $evadeChance += 0.10;
    }
    $evadeChance = min($evadeChance, MW_EVADE_CHANCE_CAP);
    $evaded = (random_int(1, 100) / 100 <= $evadeChance);
    if ($evaded) {
        $dmg = 0;
    }

    if ($defender['defending']) {
        $dmg = (int) round($dmg * MW_DEFEND_MULT);
    }

    if (($defender['states']['cursed_presence'] ?? false) && $defender['hp'] > $defender['hp_max'] * 0.5) {
        $dmg = (int) round($dmg * 0.95);
    }
    $damageTakenDown = (float) ($defender['battle_bonus']['damage_taken_down'] ?? 0);
    if ($damageTakenDown > 0) {
        $dmg = (int) round($dmg * (1 - max(0, min(0.90, $damageTakenDown))));
    }

    $dmgUp = ($attacker['battle_bonus']['damage_up'] ?? 0)
        + ($attacker['battle_bonus']['focus_up_once'] ?? 0)
        + ($attacker['battle_bonus']['class_damage_up'] ?? 0)
        + (mw_has_effect($attacker, 'damage_up_once') ? (float) ($attacker['effects']['damage_up_once']['potency'] ?? 0) : 0);
    if ($dmgUp > 0) {
        $dmg = (int) round($dmg * (1 + $dmgUp));
    }

    if (defined('MW_DEBUG') && MW_DEBUG) {
        error_log(sprintf(
            'MW DAMAGE: attacker=%s defender=%s base=%d mult=%.2f crit=%s evaded=%s final=%d',
            $attacker['name'] ?? '?',
            $defender['name'] ?? '?',
            (int) (($attacker['mind'] ?? 0) * 2) + 4,
            $mult,
            $isCrit ? 'yes' : 'no',
            $evaded ? 'yes' : 'no',
            max(0, $dmg)
        ));
    }

    return ['damage' => max(0, $dmg), 'crit' => $isCrit, 'evaded' => $evaded];
}

function mw_resolve_attack(array $attacker, array $defender, bool $guaranteedCrit = false): array {
    $result = mw_calc_damage($attacker, $defender, 1.0, $guaranteedCrit);
    $energyGain = MW_ENERGY_ATTACK;
    if ($result['crit']) $energyGain += MW_ENERGY_CRIT;
    $result['energy_gain_attacker'] = $energyGain;
    $result['energy_gain_defender'] = $result['damage'] > 0 ? MW_ENERGY_DAMAGE : 0;
    $passive = (string) ($attacker['passive_code'] ?? '');
    if ($result['damage'] > 0 && $passive === 'vamp_lifesteal') {
        $result['heal_attacker'] = (int) round($result['damage'] * 0.12);
    }
    return $result;
}

function mw_get_passive_handler(string $code): ?string {
    $reg = mw_get_passive_registry();
    $fn = $reg[$code] ?? null;
    return ($fn && function_exists($fn)) ? $fn : null;
}

function mw_get_ability_handler(string $code): ?string {
    $reg = mw_get_ability_registry();
    $fn = $reg[$code] ?? null;
    return ($fn && function_exists($fn)) ? $fn : null;
}

function mw_get_special_handler(string $code): ?string {
    $reg = mw_get_special_registry();
    $fn = $reg[$code] ?? null;
    return ($fn && function_exists($fn)) ? $fn : null;
}

function mw_execute_skill_code(string $code, string $phase, array &$attacker, array &$defender, array $context = []) {
    $code = trim($code);
    if ($code === '') {
        return null;
    }
    if ($phase === 'passive') {
        $handler = mw_get_passive_handler($code);
        if ($handler === null) {
            return null;
        }
        $handler($attacker, $defender, $context);
        return true;
    }
    if ($phase === 'ability') {
        $handler = mw_get_ability_handler($code);
        if ($handler === null) {
            return null;
        }
        $result = $handler($attacker, $defender, $context);
        return is_array($result) ? $result : null;
    }
    if ($phase === 'special') {
        $handler = mw_get_special_handler($code);
        if ($handler === null) {
            return null;
        }
        $result = $handler($attacker, $defender, $context);
        return is_array($result) ? $result : null;
    }
    return null;
}

function mw_execute_skill($code, $context = []) {
    if (!$code) return;

    mw_log('EXECUTING SKILL: ' . $code);

    switch ($code) {

        case 'vamp_lifesteal':
            mw_log('Lifesteal passive triggered');
            break;

        case 'vamp_curse_bleed':
            mw_log('Bleed special triggered');
            break;

        default:
            mw_log('Unknown skill: ' . $code);
    }
}

function mw_execute_ability(string $code, array $attacker, array $defender): ?array {
    $abilityCode = trim((string) ($code !== '' ? $code : ($attacker['ability_code'] ?? '')));
    mw_log('ABILITY CODE: ' . $abilityCode);
    if ($abilityCode === '') {
        return null;
    }
    $handler = mw_get_ability_handler($abilityCode);
    if ($handler === null) {
        mw_log('ABILITY HANDLER NOT FOUND: ' . $abilityCode);
        return null;
    }
    mw_log('USING HANDLER: ' . $abilityCode);
    $result = $handler($attacker, $defender, []);
    return is_array($result) ? $result : null;
}

function mw_execute_special(string $code, array $attacker, array $defender): ?array {
    $specialCode = trim((string) ($code !== '' ? $code : ($attacker['special_code'] ?? '')));
    mw_log('SPECIAL CODE: ' . $specialCode);
    if ($specialCode === '') {
        return null;
    }
    $handler = mw_get_special_handler($specialCode);
    if ($handler === null) {
        mw_log('SPECIAL HANDLER NOT FOUND: ' . $specialCode);
        return null;
    }
    mw_log('USING HANDLER: ' . $specialCode);
    $result = $handler($attacker, $defender, []);
    return is_array($result) ? $result : null;
}

function mw_bot_choose_action(array $bot, array $player): string {
    return mw_bot_choose_action_with_difficulty($bot, $player, 'normal');
}

function mw_normalize_mode(string $mode): string {
    $mode = strtolower(trim($mode));
    if ($mode === 'training') {
        return 'pve';
    }
    return in_array($mode, ['pve', 'pvp_ranked'], true) ? $mode : 'pve';
}

function mw_normalize_difficulty(string $difficulty): string {
    $difficulty = strtolower(trim($difficulty));
    return in_array($difficulty, ['easy', 'normal', 'hard'], true) ? $difficulty : 'normal';
}

function mw_enemy_level_band_for_difficulty(string $difficulty): array {
    $difficulty = mw_normalize_difficulty($difficulty);
    if ($difficulty === 'easy') {
        return ['min_offset' => -2, 'max_offset' => 0];
    }
    if ($difficulty === 'hard') {
        return ['min_offset' => 0, 'max_offset' => 2];
    }
    return ['min_offset' => -1, 'max_offset' => 1];
}

function mw_bot_difficulty_profile(string $difficulty): array {
    $difficulty = mw_normalize_difficulty($difficulty);
    if ($difficulty === 'easy') {
        return [
            'ability_chance' => 35,
            'defend_chance_low_hp' => 30,
            'force_special_when_full_energy' => false,
            'special_when_full_energy_chance' => 65,
        ];
    }
    if ($difficulty === 'hard') {
        return [
            'ability_chance' => 75,
            'defend_chance_low_hp' => 12,
            'force_special_when_full_energy' => true,
            'special_when_full_energy_chance' => 100,
        ];
    }
    return [
        'ability_chance' => 60,
        'defend_chance_low_hp' => 20,
        'force_special_when_full_energy' => true,
        'special_when_full_energy_chance' => 100,
    ];
}

function mw_bot_choose_action_with_difficulty(array $bot, array $player, string $difficulty = 'normal'): string {
    $profile = mw_bot_difficulty_profile($difficulty);
    if ($bot['energy'] >= MW_MAX_ENERGY && ($bot['special_code'] ?? null)) {
        if (!empty($profile['force_special_when_full_energy']) || random_int(1, 100) <= (int) ($profile['special_when_full_energy_chance'] ?? 100)) {
            return 'special';
        }
    }
    if (($bot['ability_cooldown'] ?? 0) <= 0 && ($bot['energy'] ?? 0) >= MW_ENERGY_ABILITY_COST) {
        $sustain = in_array($bot['ability_code'] ?? '', ['abyssal_grip']);
        if ($bot['hp'] < $bot['hp_max'] * 0.35 && $sustain) {
            return 'ability';
        }
        if (random_int(1, 100) <= (int) ($profile['ability_chance'] ?? 60)) {
            return 'ability';
        }
    }
    if ($bot['hp'] < $bot['hp_max'] * 0.40 && random_int(1, 100) <= (int) ($profile['defend_chance_low_hp'] ?? 20)) {
        return 'defend';
    }
    if (($bot['energy'] ?? 0) >= MW_ENERGY_ATTACK_COST) {
        return 'attack';
    }
    return 'defend';
}

function mw_get_user_avatars(PDO $pdo, int $userId): array {
    if (!function_exists('avatar_sync_items_from_assets')) {
        require_once __DIR__ . '/knd_avatar.php';
    }
    avatar_sync_items_from_assets($pdo);

    $favId = 0;
    try {
        $fav = $pdo->prepare('SELECT favorite_avatar_id FROM users WHERE id = ? LIMIT 1');
        $fav->execute([$userId]);
        $favId = (int) ($fav->fetchColumn() ?: 0);
    } catch (\Throwable $e) {}

    try {
        $stmt = $pdo->prepare(
            "SELECT ai.id AS item_id, ai.name, ai.rarity AS ai_rarity, ai.asset_path, inv.knowledge_energy, inv.avatar_level,
                    mw.id AS mw_avatar_id, mw.image AS mw_image, mw.rarity AS mw_rarity
             FROM knd_user_avatar_inventory inv
             JOIN knd_avatar_items ai ON ai.id = inv.item_id
             INNER JOIN mw_avatars mw ON LOWER(TRIM(mw.name)) = LOWER(TRIM(ai.name))
             WHERE inv.user_id = ? AND ai.is_active = 1
             ORDER BY inv.acquired_at DESC"
        );
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {
        $stmt = $pdo->prepare(
            "SELECT ai.id AS item_id, ai.name, ai.rarity AS ai_rarity, ai.asset_path, inv.knowledge_energy, inv.avatar_level
             FROM knd_user_avatar_inventory inv
             JOIN knd_avatar_items ai ON ai.id = inv.item_id
             WHERE inv.user_id = ? AND ai.is_active = 1
             ORDER BY inv.acquired_at DESC"
        );
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $i => $r) { $rows[$i]['mw_avatar_id'] = null; $rows[$i]['mw_image'] = null; $rows[$i]['mw_rarity'] = null; }
    }

    $avatars = [];
    $seenItem = [];
    foreach ($rows as $row) {
        $iid = (int) ($row['item_id'] ?? 0);
        if ($iid < 1 || isset($seenItem[$iid])) {
            continue;
        }
        $seenItem[$iid] = true;
        $mwId = (int) ($row['mw_avatar_id'] ?? 0);
        if ($mwId < 1) {
            continue;
        }
        $ke = max(0, (int) ($row['knowledge_energy'] ?? 0));
        $lvl = max(1, (int) ($row['avatar_level'] ?? 1));
        $req = (int) ceil(80 * pow($lvl, 1.3));
        $into = $req > 0 ? ($ke % $req) : 0;
        $mwImage = isset($row['mw_image']) ? trim((string) $row['mw_image']) : '';
        $rarity = ($mwId > 0 && trim((string) ($row['mw_rarity'] ?? '')) !== '')
            ? (string) $row['mw_rarity']
            : (string) ($row['ai_rarity'] ?? 'common');
        $avatars[] = [
            'mw_avatar_id' => $mwId > 0 ? $mwId : null,
            'mw_image' => $mwImage !== '' ? $mwImage : null,
            'item_id' => (int) $row['item_id'],
            'name' => (string) ($row['name'] ?? 'Avatar'),
            'rarity' => $rarity,
            'asset_path' => (string) ($row['asset_path'] ?? ''),
            'avatar_level' => $lvl,
            'knowledge_energy' => $ke,
            'knowledge_energy_into_level' => $into,
            'knowledge_energy_required_current' => $req,
            'knowledge_energy_to_next_level' => max(0, $req - $into),
            'is_favorite' => ((int) $row['item_id'] === $favId),
        ];
    }
    return $avatars;
}

function mw_avatar_asset_url(string $assetPath): string {
    $assetPath = trim($assetPath);
    if ($assetPath === '') {
        return '/assets/avatars/_placeholder.svg';
    }
    if (strpos($assetPath, '/') === 0) {
        return $assetPath;
    }
    return '/assets/avatars/' . ltrim($assetPath, '/');
}

/**
 * Normalize value stored in mw_avatars.image (e.g. /assets/avatars/thumbs/foo.png or thumbs/foo.png).
 */
function mw_normalize_mw_image_url(string $raw): string {
    $raw = trim($raw);
    if ($raw === '') {
        return '/assets/avatars/_placeholder.svg';
    }
    if (preg_match('#^https?://#i', $raw)) {
        return $raw;
    }
    if (strlen($raw) > 0 && $raw[0] === '/') {
        return $raw;
    }
    if (stripos($raw, 'assets/') === 0) {
        return '/' . ltrim($raw, '/');
    }
    return '/assets/avatars/' . ltrim($raw, '/');
}

/**
 * Public URL from mw_avatars.image only, by row id.
 */
function mw_mw_avatar_image_url_by_id(PDO $pdo, int $mwAvatarId): ?string {
    if ($mwAvatarId < 1) {
        return null;
    }
    try {
        $stmt = $pdo->prepare('SELECT image FROM mw_avatars WHERE id = ? LIMIT 1');
        $stmt->execute([$mwAvatarId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $img = trim((string) ($row['image'] ?? ''));
        if ($img === '') {
            return null;
        }
        return mw_normalize_mw_image_url($img);
    } catch (\Throwable $e) {
        return null;
    }
}

/**
 * Public URL from mw_avatars.image only, by avatar name match.
 */
function mw_mw_avatar_image_url_by_name(PDO $pdo, string $name): ?string {
    $name = trim($name);
    if ($name === '') {
        return null;
    }
    try {
        $stmt = $pdo->prepare('SELECT image FROM mw_avatars WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) LIMIT 1');
        $stmt->execute([$name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $img = trim((string) ($row['image'] ?? ''));
        if ($img === '') {
            return null;
        }
        return mw_normalize_mw_image_url($img);
    } catch (\Throwable $e) {
        return null;
    }
}

/**
 * Resolve avatar image URL: mw_avatars.image by name match, else KND inventory asset_path.
 */
function mw_resolve_avatar_image(PDO $pdo, string $name, string $fallbackAssetPath): string {
    $name = trim($name);
    if ($name === '') {
        return mw_avatar_asset_url($fallbackAssetPath);
    }
    $fromImage = mw_mw_avatar_image_url_by_name($pdo, $name);
    if ($fromImage !== null) {
        return $fromImage;
    }
    return mw_avatar_asset_url($fallbackAssetPath);
}

/**
 * Lobby/inventory: solo mw_avatars.image (por id, valor del JOIN, o nombre). Sin fallback a knd_avatar_items.asset_path.
 *
 * @param string $kndAssetPath ignorado (compatibilidad de firma)
 * @param string|null $mwImageFromJoin mw_avatars.image del JOIN
 */
function mw_resolve_avatar_image_for_inventory(PDO $pdo, ?int $mwAvatarId, string $name, string $kndAssetPath, ?string $mwImageFromJoin = null): string {
    $mwAvatarId = (int) $mwAvatarId;
    if ($mwAvatarId > 0) {
        $byId = mw_mw_avatar_image_url_by_id($pdo, $mwAvatarId);
        if ($byId !== null) {
            return $byId;
        }
    }
    $join = $mwImageFromJoin !== null ? trim($mwImageFromJoin) : '';
    if ($join !== '') {
        return mw_normalize_mw_image_url($join);
    }
    $byName = mw_mw_avatar_image_url_by_name($pdo, $name);
    if ($byName !== null) {
        return $byName;
    }

    return '';
}

/**
 * @param array<int> $mwAvatarIds
 * @return array<int, array{mnd:int,fcs:int,spd:int,lck:int}>
 */
function mw_batch_avatar_stats_by_ids(PDO $pdo, array $mwAvatarIds): array {
    $mwAvatarIds = array_values(array_unique(array_filter(array_map('intval', $mwAvatarIds), static function ($v) {
        return $v > 0;
    })));
    if ($mwAvatarIds === []) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($mwAvatarIds), '?'));
    try {
        $stmt = $pdo->prepare(
            "SELECT avatar_id, mind, focus, speed, luck FROM mw_avatar_stats WHERE avatar_id IN ($placeholders)"
        );
        $stmt->execute($mwAvatarIds);
    } catch (\Throwable $e) {
        return [];
    }
    $out = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $aid = (int) ($row['avatar_id'] ?? 0);
        if ($aid < 1) {
            continue;
        }
        $out[$aid] = [
            'mnd' => max(0, (int) ($row['mind'] ?? 0)),
            'fcs' => max(0, (int) ($row['focus'] ?? 0)),
            'spd' => max(0, (int) ($row['speed'] ?? 0)),
            'lck' => max(0, (int) ($row['luck'] ?? 0)),
        ];
    }
    return $out;
}

function mw_avatar_slug_from_name(string $name): string {
    $key = mw_normalize_name_key($name);
    if ($key === '') {
        return 'avatar';
    }
    return preg_replace('/[^a-z0-9]+/', '-', $key) ?: 'avatar';
}

function mw_skill_display_name(?string $code): string {
    if ($code === null || trim($code ?? '') === '') {
        return 'Ability';
    }
    static $map = [
        'generic_strike' => 'Power Strike',
        'generic_focus' => 'Focus Break',
        'generic_burst' => 'Burst Protocol',
        'generic_finisher' => 'Final Blow',
        'generic_legendary_strike' => 'Legend Pulse',
        'generic_legendary_finisher' => 'Mythic End',
        'relativity_collapse' => 'Relativity Collapse',
        'mental_singularity' => 'Mental Singularity',
        'lightning_conductor' => 'Lightning Conductor',
        'storm_protocol' => 'Storm Protocol',
        'predictive_strike' => 'Predictive Strike',
        'final_deduction' => 'Final Deduction',
        'clone_assault' => 'Clone Assault',
        'celestial_rampage' => 'Celestial Rampage',
        'thunder_judgment' => 'Thunder Judgment',
        'wrath_of_olympus' => 'Wrath of Olympus',
        'petrifying_gaze' => 'Petrifying Gaze',
        'stone_eternity' => 'Stone Eternity',
        'abyssal_grip' => 'Abyssal Grip',
        'leviathan_crush' => 'Leviathan Crush',
        'frostbite_pulse' => 'Frostbite Pulse',
        'absolute_zero' => 'Absolute Zero',
        'chaos_tea' => 'Chaos Tea',
        'mirror_madness' => 'Mirror Madness',
        'mind_expansion' => 'Mind Expansion',
        'spark_of_genius' => 'Spark of Genius',
        'deductive_precision' => 'Deductive Precision',
        'trickster_instinct' => 'Trickster Instinct',
        'divine_pressure' => 'Divine Pressure',
        'cursed_presence' => 'Cursed Presence',
        'deep_armor' => 'Deep Armor',
        'frozen_calm' => 'Frozen Calm',
        'wonderland_shift' => 'Wonderland Shift',
    ];
    $v = trim($code);
    if ($v === '') {
        return 'None';
    }
    return $map[$v] ?? ucwords(str_replace('_', ' ', $v));
}

function mw_skill_short_description(string $code, string $type = 'ability'): string {
    static $map = [
        'generic_strike' => 'Deal heavy damage to one target.',
        'generic_focus' => 'Strike while disrupting enemy defense.',
        'generic_burst' => 'Deal burst damage and pressure the enemy.',
        'generic_finisher' => 'Deliver a high-damage finishing hit.',
        'generic_legendary_strike' => 'Legendary strike with boosted output.',
        'generic_legendary_finisher' => 'Mythic finishing move with huge impact.',
        'relativity_collapse' => 'Distort space to overwhelm the enemy.',
        'mental_singularity' => 'Collapse the enemy mind with concentrated force.',
        'lightning_conductor' => 'Chain lightning damage through your strike.',
        'storm_protocol' => 'Call a storm surge for amplified damage.',
        'predictive_strike' => 'Read the enemy and strike with precision.',
        'final_deduction' => 'Exploit a weakness for decisive damage.',
        'clone_assault' => 'Swarm the target with rapid clone attacks.',
        'celestial_rampage' => 'Unleash divine fury in one massive hit.',
        'thunder_judgment' => 'Strike with thunder and punishing power.',
        'wrath_of_olympus' => 'Channel Olympus power for a devastating finisher.',
        'petrifying_gaze' => 'Paralyze momentum and hit with force.',
        'stone_eternity' => 'Seal the target under crushing pressure.',
        'abyssal_grip' => 'Drag the target into abyssal pressure.',
        'leviathan_crush' => 'Overwhelm the enemy with leviathan force.',
        'frostbite_pulse' => 'Inflict chilling damage with control pressure.',
        'absolute_zero' => 'Freeze the battlefield with lethal cold.',
        'chaos_tea' => 'Chaos-infused strike with unpredictable pressure.',
        'mirror_madness' => 'Confuse and punish with mirror tricks.',
        'mind_expansion' => 'Expand combat cognition for stronger attacks.',
        'spark_of_genius' => 'Precision strike powered by insight.',
        'deductive_precision' => 'Exploit tiny openings with exact force.',
        'trickster_instinct' => 'Outplay timing and punish with style.',
        'divine_pressure' => 'Apply holy pressure that breaks resistance.',
        'cursed_presence' => 'Oppressive cursed aura weakens enemy resolve.',
        'deep_armor' => 'Dense guard profile that reduces damage taken.',
        'frozen_calm' => 'Calm, controlled defense under pressure.',
        'wonderland_shift' => 'Reality shift that creates tactical advantage.',
    ];
    $v = trim($code);
    if ($v === '') {
        return $type === 'passive' ? 'No passive skill assigned.' : 'No skill assigned.';
    }
    return $map[$v] ?? 'Specialized combat technique.';
}

function mw_get_avatar_lore_dataset(): array {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = [];
    $file = __DIR__ . '/mind_wars_lore.php';
    if (is_file($file)) {
        require_once $file;
        if (function_exists('mw_avatar_lore_dataset')) {
            $data = mw_avatar_lore_dataset();
            if (is_array($data)) {
                $cache = $data;
            }
        }
    }
    return $cache;
}

/**
 * Get avatar catalog from mw_avatars directly. DB-only: INNER JOIN mw_avatar_stats + mw_avatar_skills.
 * Throws AGUACATE if any avatar is missing stats or skills.
 */
function mw_get_mw_avatars_catalog(PDO $pdo): array {
    $stmt = $pdo->query(
        "SELECT a.id, a.name, a.rarity, a.class, a.image,
                s.mind, s.focus, s.speed, s.luck,
                sk.passive_code, sk.ability_code, sk.special_code
         FROM mw_avatars a
         INNER JOIN mw_avatar_stats s ON s.avatar_id = a.id
         INNER JOIN mw_avatar_skills sk ON sk.avatar_id = a.id
         ORDER BY FIELD(a.rarity, 'legendary','epic','rare','special','common'), a.name ASC"
    );
    $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    $lore = mw_get_avatar_lore_dataset();

    $out = [];
    $used = [];
    foreach ($rows as $row) {
        $mwId = (int) ($row['id'] ?? 0);
        $name = (string) ($row['name'] ?? 'Avatar');
        $rarity = (string) ($row['rarity'] ?? 'common');

        $mind = max(1, (int) ($row['mind'] ?? 0));
        $focus = max(1, (int) ($row['focus'] ?? 0));
        $speed = max(1, (int) ($row['speed'] ?? 0));
        $luck = max(1, (int) ($row['luck'] ?? 0));
        if ($mind < 1 && $focus < 1 && $speed < 1 && $luck < 1) {
            throw new RuntimeException("AGUACATE: mw_avatar_id {$mwId} ({$name}) has invalid stats (all zero)");
        }
        $stats = ['mind' => $mind, 'focus' => $focus, 'speed' => $speed, 'luck' => $luck];

        $passiveCode = trim((string) ($row['passive_code'] ?? ''));
        $abilityCode = trim((string) ($row['ability_code'] ?? ''));
        $specialCode = trim((string) ($row['special_code'] ?? ''));
        if ($passiveCode === '' || $abilityCode === '' || $specialCode === '') {
            throw new RuntimeException("AGUACATE: mw_avatar_id {$mwId} ({$name}) missing passive/ability/special in mw_avatar_skills");
        }

        $slug = mw_avatar_slug_from_name($name);
        if (isset($used[$slug])) {
            $slug .= '-' . $mwId;
        }
        $used[$slug] = true;
        $loreItem = is_array($lore[$slug] ?? null) ? $lore[$slug] : [];

        $classLabel = trim((string) ($loreItem['class_label'] ?? ''));
        if ($classLabel === '') {
            $classLabel = (string) ($row['class'] ?? 'Fighter');
        }
        $roleDescription = trim((string) ($loreItem['role_description'] ?? ''));
        if ($roleDescription === '') {
            $roleDescription = trim((string) ($loreItem['role'] ?? ''));
        }
        if ($roleDescription === '') {
            $roleDescription = 'Balanced fighter.';
        }
        $cultureLabel = trim((string) ($loreItem['culture'] ?? ''));
        $culturalDescription = trim((string) ($loreItem['cultural'] ?? ''));
        if ($culturalDescription === '' && $cultureLabel !== '') {
            $culturalDescription = 'Culture: ' . $cultureLabel;
        }
        if ($culturalDescription === '') {
            $culturalDescription = 'Description coming soon.';
        }
        $historicalDescription = trim((string) ($loreItem['historical'] ?? ''));
        if ($historicalDescription === '') {
            $historicalDescription = trim((string) ($loreItem['description'] ?? ''));
        }
        if ($historicalDescription === '') {
            $historicalDescription = 'Description coming soon.';
        }
        $shortLore = trim((string) ($loreItem['short_lore'] ?? ''));
        if ($shortLore === '') {
            $shortLore = $historicalDescription;
        }

        $img = trim((string) ($row['image'] ?? ''));
        $assetUrl = $img !== '' ? mw_avatar_asset_url($img) : mw_avatar_asset_url('');

        $out[] = [
            'slug' => $slug,
            'item_id' => $mwId,
            'name' => $name,
            'rarity' => $rarity,
            'asset_url' => $assetUrl,
            'thumbnail_path' => $assetUrl,
            'stats' => $stats,
            'combat_class' => (string) ($row['class'] ?? MW_CLASS_STRIKER),
            'combat_class_label' => $classLabel,
            'combat_class_tooltip' => 'Balanced fighter.',
            'passive_code' => $passiveCode,
            'ability_code' => $abilityCode,
            'special_code' => $specialCode,
            'passive_name' => mw_skill_display_name($passiveCode),
            'ability_name' => mw_skill_display_name($abilityCode),
            'special_name' => mw_skill_display_name($specialCode),
            'passive_description' => mw_skill_short_description($passiveCode, 'passive'),
            'ability_description' => mw_skill_short_description($abilityCode, 'ability'),
            'special_description' => mw_skill_short_description($specialCode, 'special'),
            'role_description' => $roleDescription,
            'culture_label' => $cultureLabel,
            'short_lore' => $shortLore,
            'cultural_description' => $culturalDescription,
            'historical_description' => $historicalDescription,
        ];
    }
    return $out;
}

function mw_get_avatar_collection_catalog(PDO $pdo): array {
    $stmt = $pdo->query(
        "SELECT ai.id AS item_id, ai.name, ai.rarity, ai.asset_path, mw.id AS mw_avatar_id
         FROM knd_avatar_items ai
         LEFT JOIN mw_avatars mw ON LOWER(TRIM(mw.name)) = LOWER(TRIM(ai.name))
         WHERE ai.is_active = 1
         ORDER BY FIELD(ai.rarity, 'legendary','epic','rare','special','common'), ai.name ASC"
    );
    $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    $lore = mw_get_avatar_lore_dataset();

    $out = [];
    $used = [];
    foreach ($rows as $row) {
        $mwId = (int) ($row['mw_avatar_id'] ?? 0);
        $avatar = [
            'item_id' => (int) ($row['item_id'] ?? 0),
            'mw_avatar_id' => $mwId > 0 ? $mwId : null,
            'name' => (string) ($row['name'] ?? 'Avatar'),
            'rarity' => (string) ($row['rarity'] ?? 'common'),
            'asset_path' => (string) ($row['asset_path'] ?? ''),
            'avatar_level' => 1,
            'knowledge_energy' => 0,
        ];
        $profile = mw_get_combat_profile($avatar);
        $slug = mw_avatar_slug_from_name((string) $avatar['name']);
        if (isset($used[$slug])) {
            $slug .= '-' . (int) $avatar['item_id'];
        }
        $used[$slug] = true;
        $loreItem = is_array($lore[$slug] ?? null) ? $lore[$slug] : [];

        $classLabel = trim((string) ($loreItem['class_label'] ?? ''));
        if ($classLabel === '') {
            $classLabel = (string) ($profile['combat_class_label'] ?? 'Fighter');
        }
        $roleDescription = trim((string) ($loreItem['role_description'] ?? ''));
        if ($roleDescription === '') {
            $roleDescription = trim((string) ($loreItem['role'] ?? ''));
        }
        if ($roleDescription === '') {
            $roleDescription = (string) ($profile['combat_class_tooltip'] ?? 'Balanced fighter.');
        }
        $cultureLabel = trim((string) ($loreItem['culture'] ?? ''));
        $culturalDescription = trim((string) ($loreItem['cultural'] ?? ''));
        if ($culturalDescription === '' && $cultureLabel !== '') {
            $culturalDescription = 'Culture: ' . $cultureLabel;
        }
        if ($culturalDescription === '') {
            $culturalDescription = 'Description coming soon.';
        }
        $historicalDescription = trim((string) ($loreItem['historical'] ?? ''));
        if ($historicalDescription === '') {
            $historicalDescription = trim((string) ($loreItem['description'] ?? ''));
        }
        if ($historicalDescription === '') {
            $historicalDescription = 'Description coming soon.';
        }
        $shortLore = trim((string) ($loreItem['short_lore'] ?? ''));
        if ($shortLore === '') {
            $shortLore = $historicalDescription;
        }

        $out[] = [
            'slug' => $slug,
            'item_id' => (int) $avatar['item_id'],
            'name' => (string) $avatar['name'],
            'rarity' => (string) $avatar['rarity'],
            'asset_url' => mw_avatar_asset_url((string) $avatar['asset_path']),
            'stats' => [
                'mind' => (int) ($profile['mind'] ?? 0),
                'focus' => (int) ($profile['focus'] ?? 0),
                'speed' => (int) ($profile['speed'] ?? 0),
                'luck' => (int) ($profile['luck'] ?? 0),
            ],
            'combat_class' => (string) ($profile['combat_class'] ?? MW_CLASS_STRIKER),
            'combat_class_label' => $classLabel,
            'combat_class_tooltip' => (string) ($profile['combat_class_tooltip'] ?? 'Balanced fighter.'),
            'passive_code' => (string) ($profile['passive_code'] ?? ''),
            'ability_code' => (string) ($profile['ability_code'] ?? ''),
            'special_code' => (string) ($profile['special_code'] ?? ''),
            'passive_name' => mw_skill_display_name((string) ($profile['passive_code'] ?? '')),
            'ability_name' => mw_skill_display_name((string) ($profile['ability_code'] ?? '')),
            'special_name' => mw_skill_display_name((string) ($profile['special_code'] ?? '')),
            'passive_description' => mw_skill_short_description((string) ($profile['passive_code'] ?? ''), 'passive'),
            'ability_description' => mw_skill_short_description((string) ($profile['ability_code'] ?? ''), 'ability'),
            'special_description' => mw_skill_short_description((string) ($profile['special_code'] ?? ''), 'special'),
            'role_description' => $roleDescription,
            'culture_label' => $cultureLabel,
            'short_lore' => $shortLore,
            'cultural_description' => $culturalDescription,
            'historical_description' => $historicalDescription,
        ];
    }
    return $out;
}

function mw_get_avatar_collection_item(PDO $pdo, string $slug): ?array {
    $slug = trim(strtolower($slug));
    if ($slug === '') {
        return null;
    }
    $catalog = mw_get_avatar_collection_catalog($pdo);
    foreach ($catalog as $item) {
        if ((string) ($item['slug'] ?? '') === $slug) {
            return $item;
        }
    }
    return null;
}

function mw_pick_enemy_avatar(PDO $pdo, int $playerLevel, string $difficulty = 'normal'): array {
    $baseLevel = max(1, $playerLevel);
    $band = mw_enemy_level_band_for_difficulty($difficulty);
    $minLevel = max(1, $baseLevel + (int) ($band['min_offset'] ?? -1));
    $maxLevel = max($minLevel, $baseLevel + (int) ($band['max_offset'] ?? 1));

    $stmt = $pdo->query(
        "SELECT a.id, a.name, a.rarity, a.class, a.image,
                s.mind, s.focus, s.speed, s.luck,
                sk.passive_code, sk.ability_code, sk.special_code, sk.heal
         FROM mw_avatars a
         INNER JOIN mw_avatar_stats s ON s.avatar_id = a.id
         INNER JOIN mw_avatar_skills sk ON sk.avatar_id = a.id
         WHERE a.rarity IN ('rare','epic','legendary')
         ORDER BY RAND() LIMIT 1"
    );
    $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
    if (!$row) {
        $stmt = $pdo->query(
            "SELECT a.id, a.name, a.rarity, a.class, a.image,
                    s.mind, s.focus, s.speed, s.luck,
                    sk.passive_code, sk.ability_code, sk.special_code, sk.heal
             FROM mw_avatars a
             INNER JOIN mw_avatar_stats s ON s.avatar_id = a.id
             INNER JOIN mw_avatar_skills sk ON sk.avatar_id = a.id
             ORDER BY RAND() LIMIT 1"
        );
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
    }
    if (!$row) {
        throw new RuntimeException('AGUACATE: No enemy avatar found in mw_avatars+mw_avatar_stats+mw_avatar_skills');
    }
    $row['avatar_level'] = random_int($minLevel, $maxLevel);
    return $row;
}

/**
 * Resolve enemy avatar (from mw_pick_enemy_avatar) to knd_avatar_items.id for FK.
 * mw_avatars.id != knd_avatar_items.id; we match by name or fallback to any frame.
 */
function mw_resolve_enemy_to_knd_item_id(PDO $pdo, array $enemyAvatar): int {
    $name = trim((string) ($enemyAvatar['name'] ?? ''));
    if ($name !== '') {
        $stmt = $pdo->prepare(
            "SELECT id FROM knd_avatar_items
             WHERE slot = 'frame' AND is_active = 1
               AND LOWER(TRIM(name)) = LOWER(?)
             LIMIT 1"
        );
        $stmt->execute([$name]);
        $id = $stmt->fetchColumn();
        if ($id !== false && (int) $id > 0) {
            return (int) $id;
        }
    }
    $stmt = $pdo->query(
        "SELECT id FROM knd_avatar_items
         WHERE slot = 'frame' AND is_active = 1
         ORDER BY RAND()
         LIMIT 1"
    );
    $id = $stmt ? $stmt->fetchColumn() : false;
    if ($id !== false && (int) $id > 0) {
        return (int) $id;
    }
    $stmt = $pdo->query("SELECT id FROM knd_avatar_items WHERE is_active = 1 LIMIT 1");
    $id = $stmt ? $stmt->fetchColumn() : false;
    return ($id !== false && (int) $id > 0) ? (int) $id : 1;
}

function mw_get_active_season(PDO $pdo): ?array {
    $pdo->exec("UPDATE knd_mind_wars_seasons SET status = 'finished' WHERE status = 'active' AND ends_at <= NOW()");
    $stmt = $pdo->query(
        "SELECT id, name, starts_at, ends_at, status
         FROM knd_mind_wars_seasons
         WHERE status = 'active' AND starts_at <= NOW() AND ends_at > NOW()
         ORDER BY id DESC
         LIMIT 1"
    );
    $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
    return $row ?: null;
}

function mw_create_next_season(PDO $pdo, ?array $afterSeason = null): array {
    $active = mw_get_active_season($pdo);
    if ($active) {
        return $active;
    }

    if (!$afterSeason) {
        $stmt = $pdo->query("SELECT id, name, starts_at, ends_at, status FROM knd_mind_wars_seasons ORDER BY id DESC LIMIT 1");
        $afterSeason = $stmt ? ($stmt->fetch(PDO::FETCH_ASSOC) ?: null) : null;
    }

    $nextNumber = 1;
    if (is_array($afterSeason) && !empty($afterSeason['name'])) {
        if (preg_match('/season\s+(\d+)/i', (string) $afterSeason['name'], $m)) {
            $nextNumber = max(1, (int) $m[1] + 1);
        } else {
            $nextNumber = max(1, (int) ($afterSeason['id'] ?? 0) + 1);
        }
    }
    $nextName = 'Mind Wars Season ' . $nextNumber;

    if (is_array($afterSeason) && !empty($afterSeason['ends_at'])) {
        $startTs = strtotime((string) $afterSeason['ends_at']) ?: time();
    } else {
        $startTs = time();
    }
    if ($startTs < time()) {
        $startTs = time();
    }
    $startsAt = date('Y-m-d H:i:s', $startTs);
    $endsAt = date('Y-m-d H:i:s', strtotime($startsAt . ' +' . MW_SEASON_DEFAULT_DAYS . ' days'));

    $ins = $pdo->prepare(
        "INSERT INTO knd_mind_wars_seasons (name, starts_at, ends_at, status)
         VALUES (?, ?, ?, 'active')"
    );
    $ins->execute([$nextName, $startsAt, $endsAt]);

    $stmt = $pdo->prepare("SELECT id, name, starts_at, ends_at, status FROM knd_mind_wars_seasons WHERE id = ? LIMIT 1");
    $stmt->execute([(int) $pdo->lastInsertId()]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: [
        'id' => (int) $pdo->lastInsertId(),
        'name' => $nextName,
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
        'status' => 'active',
    ];
}

function mw_finish_season(PDO $pdo, int $seasonId): ?array {
    if ($seasonId <= 0) {
        return null;
    }
    $upd = $pdo->prepare("UPDATE knd_mind_wars_seasons SET status = 'finished' WHERE id = ? AND status <> 'finished'");
    $upd->execute([$seasonId]);
    $stmt = $pdo->prepare("SELECT id, name, starts_at, ends_at, status FROM knd_mind_wars_seasons WHERE id = ? LIMIT 1");
    $stmt->execute([$seasonId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function mw_ensure_season_reward_grants_table(PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS knd_mind_wars_season_reward_grants (
            id BIGINT NOT NULL AUTO_INCREMENT,
            season_id BIGINT NOT NULL,
            user_id BIGINT NOT NULL,
            reward_tier VARCHAR(32) NOT NULL,
            payload_json JSON NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_season_user_tier (season_id, user_id, reward_tier),
            KEY idx_season_created (season_id, created_at),
            KEY idx_user_created (user_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function mw_get_reward_tier_for_rank(int $rank): string {
    if ($rank > 0 && $rank <= 10) return 'top10_legendary';
    if ($rank <= 100) return 'top100_epic';
    if ($rank <= 500) return 'top500_xp';
    return 'participation_ke';
}

function mw_grant_random_avatar_by_rarity(PDO $pdo, int $userId, string $rarity): int {
    if ($userId <= 0) return 0;
    $rarity = strtolower(trim($rarity));
    if (!in_array($rarity, ['epic', 'legendary'], true)) return 0;

    $pick = $pdo->prepare(
        "SELECT ai.id
         FROM knd_avatar_items ai
         LEFT JOIN knd_user_avatar_inventory inv
           ON inv.item_id = ai.id AND inv.user_id = ?
         WHERE ai.is_active = 1
           AND ai.rarity = ?
           AND inv.item_id IS NULL
         ORDER BY RAND()
         LIMIT 1"
    );
    $pick->execute([$userId, $rarity]);
    $itemId = (int) ($pick->fetchColumn() ?: 0);

    if ($itemId <= 0) {
        $fallback = $pdo->prepare(
            "SELECT id
             FROM knd_avatar_items
             WHERE is_active = 1 AND rarity = ?
             ORDER BY RAND()
             LIMIT 1"
        );
        $fallback->execute([$rarity]);
        $itemId = (int) ($fallback->fetchColumn() ?: 0);
    }
    if ($itemId <= 0) return 0;

    $ins = $pdo->prepare(
        "INSERT IGNORE INTO knd_user_avatar_inventory (user_id, item_id, acquired_at)
         VALUES (?, ?, NOW())"
    );
    $ins->execute([$userId, $itemId]);
    return $itemId;
}

function mw_grant_user_xp_bonus(PDO $pdo, int $userId, int $xp): int {
    $xp = max(0, $xp);
    if ($userId <= 0 || $xp <= 0) return 0;
    $stmt = $pdo->prepare(
        "INSERT INTO knd_user_xp (user_id, xp, level)
         VALUES (?, ?, 1)
         ON DUPLICATE KEY UPDATE xp = xp + VALUES(xp), level = GREATEST(1, level)"
    );
    $stmt->execute([$userId, $xp]);
    return $xp;
}

function mw_grant_user_ke_bonus(PDO $pdo, int $userId, int $ke): int {
    $ke = max(0, $ke);
    if ($userId <= 0 || $ke <= 0) return 0;

    $favStmt = $pdo->prepare("SELECT favorite_avatar_id FROM users WHERE id = ? LIMIT 1");
    $favStmt->execute([$userId]);
    $favId = (int) ($favStmt->fetchColumn() ?: 0);

    $targetItemId = 0;
    if ($favId > 0) {
        $chk = $pdo->prepare("SELECT item_id FROM knd_user_avatar_inventory WHERE user_id = ? AND item_id = ? LIMIT 1");
        $chk->execute([$userId, $favId]);
        $targetItemId = (int) ($chk->fetchColumn() ?: 0);
    }
    if ($targetItemId <= 0) {
        $fallback = $pdo->prepare("SELECT item_id FROM knd_user_avatar_inventory WHERE user_id = ? ORDER BY acquired_at DESC LIMIT 1");
        $fallback->execute([$userId]);
        $targetItemId = (int) ($fallback->fetchColumn() ?: 0);
    }
    if ($targetItemId <= 0) return 0;

    $upd = $pdo->prepare(
        "UPDATE knd_user_avatar_inventory
         SET knowledge_energy = knowledge_energy + ?
         WHERE user_id = ? AND item_id = ?"
    );
    $upd->execute([$ke, $userId, $targetItemId]);
    return $ke;
}

function mw_apply_season_rewards(PDO $pdo, int $seasonId): array {
    $summary = ['total_grants' => 0, 'legendary' => 0, 'epic' => 0, 'xp' => 0, 'ke' => 0];
    if ($seasonId <= 0) return $summary;
    mw_ensure_season_reward_grants_table($pdo);

    $stmt = $pdo->prepare(
        "SELECT user_id, rank_score, wins, losses
         FROM knd_mind_wars_rankings
         WHERE season_id = ?
         ORDER BY rank_score DESC, updated_at ASC"
    );
    $stmt->execute([$seasonId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) return $summary;

    $claimStmt = $pdo->prepare(
        "INSERT IGNORE INTO knd_mind_wars_season_reward_grants (season_id, user_id, reward_tier, payload_json, created_at)
         VALUES (?, ?, ?, ?, NOW())"
    );
    $payloadUpdStmt = $pdo->prepare(
        "UPDATE knd_mind_wars_season_reward_grants
         SET payload_json = ?
         WHERE season_id = ? AND user_id = ? AND reward_tier = ?
         LIMIT 1"
    );

    $rank = 0;
    foreach ($rows as $row) {
        $rank++;
        $userId = (int) ($row['user_id'] ?? 0);
        if ($userId <= 0) continue;
        $tier = mw_get_reward_tier_for_rank($rank);
        $payload = ['rank' => $rank, 'season_id' => $seasonId];

        $claimStmt->execute([$seasonId, $userId, $tier, '{}']);
        if ((int) $claimStmt->rowCount() === 0) {
            continue;
        }

        if ($tier === 'top10_legendary') {
            $itemId = mw_grant_random_avatar_by_rarity($pdo, $userId, 'legendary');
            $payload['avatar_item_id'] = $itemId;
            $payload['reward'] = 'legendary_avatar';
        } elseif ($tier === 'top100_epic') {
            $itemId = mw_grant_random_avatar_by_rarity($pdo, $userId, 'epic');
            $payload['avatar_item_id'] = $itemId;
            $payload['reward'] = 'epic_avatar';
        } elseif ($tier === 'top500_xp') {
            $xp = mw_grant_user_xp_bonus($pdo, $userId, MW_SEASON_REWARD_XP_TOP500);
            $payload['xp'] = $xp;
            $payload['reward'] = 'xp_bonus';
        } else {
            $ke = mw_grant_user_ke_bonus($pdo, $userId, MW_SEASON_REWARD_KE_PARTICIPATION);
            $payload['knowledge_energy'] = $ke;
            $payload['reward'] = 'knowledge_energy';
        }

        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $payloadUpdStmt->execute([$jsonPayload ?: '{}', $seasonId, $userId, $tier]);
        $summary['total_grants']++;
        if ($tier === 'top10_legendary') $summary['legendary']++;
        elseif ($tier === 'top100_epic') $summary['epic']++;
        elseif ($tier === 'top500_xp') $summary['xp']++;
        else $summary['ke']++;
    }

    return $summary;
}

function mw_rollover_season(PDO $pdo, bool $force = false): array {
    $pdo->beginTransaction();
    try {
        $active = mw_get_active_season($pdo);
        if (!$active) {
            $created = mw_create_next_season($pdo, null);
            $pdo->commit();
            return [
                'rolled_over' => false,
                'reason' => 'no_active_season',
                'finished_season' => null,
                'new_season' => $created,
                'rewards' => ['total_grants' => 0, 'legendary' => 0, 'epic' => 0, 'xp' => 0, 'ke' => 0],
            ];
        }

        $endTs = strtotime((string) ($active['ends_at'] ?? 'now')) ?: time();
        if (!$force && $endTs > time()) {
            $pdo->commit();
            return [
                'rolled_over' => false,
                'reason' => 'season_not_ended',
                'finished_season' => $active,
                'new_season' => $active,
                'rewards' => ['total_grants' => 0, 'legendary' => 0, 'epic' => 0, 'xp' => 0, 'ke' => 0],
            ];
        }

        $finished = mw_finish_season($pdo, (int) ($active['id'] ?? 0));
        $rewardSummary = mw_apply_season_rewards($pdo, (int) ($active['id'] ?? 0));
        $next = mw_create_next_season($pdo, $finished ?: $active);

        $pdo->commit();
        return [
            'rolled_over' => true,
            'reason' => 'ok',
            'finished_season' => $finished ?: $active,
            'new_season' => $next,
            'rewards' => $rewardSummary,
        ];
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function mw_ensure_season(PDO $pdo): array {
    $active = mw_get_active_season($pdo);
    if ($active) return $active;
    $created = mw_create_next_season($pdo, null);
    if ($created) return $created;
    return [
        'id' => 1,
        'name' => 'Mind Wars Season 1',
        'starts_at' => date('Y-m-d H:i:s'),
        'ends_at' => date('Y-m-d H:i:s', strtotime('+' . MW_SEASON_DEFAULT_DAYS . ' days')),
        'status' => 'active',
    ];
}

function mw_validate_owned_avatar(PDO $pdo, int $userId, int $itemId): ?array {
    $stmt = $pdo->prepare(
        "SELECT ai.id AS item_id, ai.name, ai.rarity AS ai_rarity, ai.asset_path, inv.knowledge_energy, inv.avatar_level,
                mw.id AS mw_avatar_id, mw.image AS mw_image, mw.rarity AS mw_rarity,
                s.mind, s.focus, s.speed, s.luck
         FROM knd_user_avatar_inventory inv
         JOIN knd_avatar_items ai ON ai.id = inv.item_id
         LEFT JOIN mw_avatars mw ON LOWER(TRIM(mw.name)) = LOWER(TRIM(ai.name))
         LEFT JOIN mw_avatar_stats s ON s.avatar_id = mw.id
         WHERE inv.user_id = ? AND inv.item_id = ? AND ai.is_active = 1 LIMIT 1"
    );
    $stmt->execute([$userId, $itemId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    $row['rarity'] = ((int) ($row['mw_avatar_id'] ?? 0) > 0 && trim((string) ($row['mw_rarity'] ?? '')) !== '')
        ? (string) $row['mw_rarity']
        : (string) ($row['ai_rarity'] ?? 'common');
    unset($row['ai_rarity'], $row['mw_rarity']);
    return $row;
}

function mw_rewards_for_result(string $result): array {
    $r = $result === 'win' ? 'win' : ($result === 'lose' ? 'lose' : 'draw');
    return [
        'xp' => $r === 'win' ? MW_XP_WIN : ($r === 'lose' ? MW_XP_LOSE : MW_XP_DRAW),
        'knowledge_energy' => $r === 'win' ? MW_KE_WIN : ($r === 'lose' ? MW_KE_LOSE : MW_KE_DRAW),
        'rank' => $r === 'win' ? MW_RANK_WIN : ($r === 'lose' ? MW_RANK_LOSE : MW_RANK_DRAW),
    ];
}

function mw_rewards_for_result_in_mode(string $result, string $mode): array {
    $base = mw_rewards_for_result($result);
    $mode = mw_normalize_mode($mode);
    if ($mode === 'training') {
        $base['rank'] = 0;
        $base['knowledge_energy'] = max(0, (int) floor(((int) $base['knowledge_energy']) * 0.25));
    }
    return $base;
}

function mw_normalize_fighter_state(array $fighter): array {
    $defaults = [
        'hp' => 0,
        'hp_max' => 0,
        'energy' => 0,
        'defending' => false,
        'ability_cooldown' => 0,
        'states' => [],
        'effects' => [],
        'battle_bonus' => [],
    ];
    $fighter = array_merge($defaults, $fighter);
    $fighter['hp'] = max(0, (int) $fighter['hp']);
    $fighter['hp_max'] = max(1, (int) $fighter['hp_max']);
    $fighter['energy'] = max(0, min(MW_MAX_ENERGY, (int) $fighter['energy']));
    $fighter['defending'] = !empty($fighter['defending']);
    $fighter['ability_cooldown'] = max(0, (int) $fighter['ability_cooldown']);
    if (!is_array($fighter['states'])) $fighter['states'] = [];
    if (!is_array($fighter['effects'])) $fighter['effects'] = [];
    if (!is_array($fighter['battle_bonus'])) $fighter['battle_bonus'] = [];
    mw_init_effects_container($fighter);
    return $fighter;
}

function mw_sanitize_cached_response_state(array $state): array {
    if (isset($state['meta']) && is_array($state['meta'])) {
        unset($state['meta']['last_response']);
    }
    return $state;
}

function mw_build_cached_response_payload(array $state, bool $battleOver, ?string $result, ?array $rewards): array {
    return [
        'state' => mw_sanitize_cached_response_state($state),
        'battle_over' => $battleOver,
        'result' => $result,
        'rewards' => $rewards,
    ];
}

function mw_normalize_battle_state(array $state): array {
    $defaults = [
        'turn' => 1,
        'max_turns' => MW_MAX_TURNS,
        'player_first' => true,
        'player' => [],
        'enemy' => [],
        'log' => [],
        'next_actor' => 'player',
        'player_next_attack_crit' => false,
        'meta' => [],
    ];
    $state = array_merge($defaults, $state);
    $state['turn'] = max(1, (int) $state['turn']);
    $state['max_turns'] = max(1, (int) ($state['max_turns'] ?? MW_MAX_TURNS));
    $state['player_first'] = !empty($state['player_first']);
    $state['next_actor'] = (($state['next_actor'] ?? 'player') === 'enemy') ? 'enemy' : 'player';
    $state['player_next_attack_crit'] = !empty($state['player_next_attack_crit']);
    if (!is_array($state['log'])) $state['log'] = [];

    $state['player'] = mw_normalize_fighter_state(is_array($state['player']) ? $state['player'] : []);
    $state['enemy'] = mw_normalize_fighter_state(is_array($state['enemy']) ? $state['enemy'] : []);

    $meta = is_array($state['meta']) ? $state['meta'] : [];
    $metaDefaults = [
        'mode' => 'pve',
        'difficulty' => 'normal',
        'last_action_id' => null,
        'last_action_at' => null,
        'last_actor' => null,
        'last_response' => null,
    ];
    $meta = array_merge($metaDefaults, $meta);
    $meta['mode'] = mw_normalize_mode((string) ($meta['mode'] ?? 'pve'));
    $meta['difficulty'] = mw_normalize_difficulty((string) ($meta['difficulty'] ?? 'normal'));
    if (!is_string($meta['last_action_id']) || $meta['last_action_id'] === '') $meta['last_action_id'] = null;
    if (!is_string($meta['last_action_at']) || $meta['last_action_at'] === '') $meta['last_action_at'] = null;
    if (!in_array($meta['last_actor'], ['player', 'enemy'], true)) $meta['last_actor'] = null;
    if (!is_array($meta['last_response'])) $meta['last_response'] = null;
    $state['meta'] = $meta;

    return $state;
}

function mw_queue_generate_token(): string {
    return hash('sha256', bin2hex(random_bytes(32)) . '|' . microtime(true));
}

function mw_queue_level_window(int $queuedSeconds): int {
    // Strict queue window: only same level or +/-1.
    return 1;
}

function mw_queue_rank_window(int $queuedSeconds): int {
    // Backward-compatible alias while queue matching migrated to avatar-level windows.
    return mw_queue_level_window($queuedSeconds);
}

function mw_cleanup_stale_queue(PDO $pdo): int {
    $stmt = $pdo->prepare(
        "UPDATE knd_mind_wars_matchmaking_queue
         SET status = 'expired', updated_at = NOW()
         WHERE status = 'queued' AND expires_at <= NOW()"
    );
    $stmt->execute();
    return (int) $stmt->rowCount();
}

function mw_get_rank_score_snapshot(PDO $pdo, int $userId, int $seasonId): int {
    $stmt = $pdo->prepare(
        "SELECT rank_score FROM knd_mind_wars_rankings
         WHERE user_id = ? AND season_id = ? LIMIT 1"
    );
    $stmt->execute([$userId, $seasonId]);
    return (int) ($stmt->fetchColumn() ?: 0);
}

function mw_queue_supports_avatar_level_snapshot(PDO $pdo): bool {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    try {
        $stmt = $pdo->prepare(
            "SELECT 1
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'knd_mind_wars_matchmaking_queue'
               AND COLUMN_NAME = 'avatar_level_snapshot'
             LIMIT 1"
        );
        $stmt->execute();
        $cache = (bool) $stmt->fetchColumn();
        return $cache;
    } catch (\Throwable $e) {
        $cache = false;
        return false;
    }
}

function mw_queue_supports_presence_columns(PDO $pdo): bool {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) AS c
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'knd_mind_wars_matchmaking_queue'
               AND COLUMN_NAME IN ('last_seen_at', 'match_expires_at')"
        );
        $stmt->execute();
        $cache = ((int) $stmt->fetchColumn()) >= 2;
        return $cache;
    } catch (\Throwable $e) {
        $cache = false;
        return false;
    }
}

function mw_queue_touch_presence(PDO $pdo, int $userId, int $seasonId): int {
    if (!mw_queue_supports_presence_columns($pdo)) {
        return 0;
    }
    $stmt = $pdo->prepare(
        "UPDATE knd_mind_wars_matchmaking_queue
         SET last_seen_at = NOW(), updated_at = NOW()
         WHERE user_id = ? AND season_id = ? AND status IN ('queued', 'matched')"
    );
    $stmt->execute([$userId, $seasonId]);
    return (int) $stmt->rowCount();
}

function mw_cleanup_stale_queue_presence(PDO $pdo): int {
    if (!mw_queue_supports_presence_columns($pdo)) {
        return 0;
    }
    $affected = 0;

    // Expire queued entries with stale heartbeat.
    $expireQueued = $pdo->prepare(
        "UPDATE knd_mind_wars_matchmaking_queue
         SET status = 'expired', updated_at = NOW()
         WHERE status = 'queued'
           AND (
             (last_seen_at IS NOT NULL AND last_seen_at <= DATE_SUB(NOW(), INTERVAL ? SECOND))
             OR
             (last_seen_at IS NULL AND updated_at <= DATE_SUB(NOW(), INTERVAL ? SECOND))
           )"
    );
    $expireQueued->execute([MW_QUEUE_HEARTBEAT_STALE_SECONDS, MW_QUEUE_HEARTBEAT_STALE_SECONDS]);
    $affected += (int) $expireQueued->rowCount();

    // Resolve matched entries that never joined battle in time.
    $staleMatched = $pdo->prepare(
        "SELECT id, user_id, season_id, matched_with_user_id
         FROM knd_mind_wars_matchmaking_queue
         WHERE status = 'matched'
           AND match_expires_at IS NOT NULL
           AND match_expires_at <= NOW()
         ORDER BY id ASC
         LIMIT 50
         FOR UPDATE"
    );
    $staleMatched->execute();
    $rows = $staleMatched->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $selfId = (int) ($row['id'] ?? 0);
        $selfUserId = (int) ($row['user_id'] ?? 0);
        $seasonId = (int) ($row['season_id'] ?? 0);
        $opponentUserId = (int) ($row['matched_with_user_id'] ?? 0);
        if ($selfId <= 0 || $selfUserId <= 0 || $seasonId <= 0 || $opponentUserId <= 0) {
            continue;
        }

        // If already consumed by battle orchestration, skip.
        $selfCheck = $pdo->prepare("SELECT status FROM knd_mind_wars_matchmaking_queue WHERE id = ? LIMIT 1 FOR UPDATE");
        $selfCheck->execute([$selfId]);
        $selfStatus = (string) ($selfCheck->fetchColumn() ?: '');
        if ($selfStatus !== 'matched') {
            continue;
        }

        $oppStmt = $pdo->prepare(
            "SELECT id, status, last_seen_at, updated_at
             FROM knd_mind_wars_matchmaking_queue
             WHERE user_id = ? AND season_id = ? AND status = 'matched'
             ORDER BY updated_at DESC, id DESC
             LIMIT 1
             FOR UPDATE"
        );
        $oppStmt->execute([$opponentUserId, $seasonId]);
        $opp = $oppStmt->fetch(PDO::FETCH_ASSOC);

        $expireSelf = $pdo->prepare(
            "UPDATE knd_mind_wars_matchmaking_queue
             SET status = 'expired', updated_at = NOW()
             WHERE id = ? AND status = 'matched'"
        );
        $expireSelf->execute([$selfId]);
        $affected += (int) $expireSelf->rowCount();

        if (!$opp) {
            continue;
        }

        $oppId = (int) ($opp['id'] ?? 0);
        if ($oppId <= 0) {
            continue;
        }

        $seenTs = strtotime((string) ($opp['last_seen_at'] ?? '')) ?: 0;
        $updTs = strtotime((string) ($opp['updated_at'] ?? '')) ?: time();
        $lastTouch = max($seenTs, $updTs);
        $isActive = (time() - $lastTouch) <= MW_QUEUE_HEARTBEAT_STALE_SECONDS;

        if ($isActive) {
            $requeue = $pdo->prepare(
                "UPDATE knd_mind_wars_matchmaking_queue
                 SET status = 'queued',
                     matched_with_user_id = NULL,
                     matched_at = NULL,
                     match_expires_at = NULL,
                     expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND),
                     updated_at = NOW(),
                     last_seen_at = NOW()
                 WHERE id = ? AND status = 'matched'"
            );
            $requeue->execute([MW_QUEUE_TIMEOUT_SECONDS, $oppId]);
            $affected += (int) $requeue->rowCount();
        } else {
            $expireOpp = $pdo->prepare(
                "UPDATE knd_mind_wars_matchmaking_queue
                 SET status = 'expired', updated_at = NOW()
                 WHERE id = ? AND status = 'matched'"
            );
            $expireOpp->execute([$oppId]);
            $affected += (int) $expireOpp->rowCount();
        }
    }

    return $affected;
}

function mw_try_match_queue_pair(PDO $pdo, int $seasonId, array $selfRow): ?array {
    $selfId = (int) ($selfRow['id'] ?? 0);
    $selfUserId = (int) ($selfRow['user_id'] ?? 0);
    $hasLevelSnapshot = mw_queue_supports_avatar_level_snapshot($pdo);
    $selfAvatarLevel = $hasLevelSnapshot
        ? max(1, (int) ($selfRow['avatar_level_snapshot'] ?? 1))
        : max(1, (int) ($selfRow['rank_score_snapshot'] ?? 1));
    $createdAt = strtotime((string) ($selfRow['created_at'] ?? 'now')) ?: time();
    $queuedFor = max(0, time() - $createdAt);
    $window = mw_queue_level_window($queuedFor);
    $levelField = $hasLevelSnapshot ? 'avatar_level_snapshot' : 'rank_score_snapshot';

    $pick = $pdo->prepare(
        "SELECT id, user_id, $levelField AS match_level
         FROM knd_mind_wars_matchmaking_queue
         WHERE season_id = ?
           AND status = 'queued'
           AND user_id <> ?
           AND ABS($levelField - ?) <= ?
         ORDER BY ABS($levelField - ?) ASC, created_at ASC
         LIMIT 1
         FOR UPDATE"
    );
    $pick->execute([$seasonId, $selfUserId, $selfAvatarLevel, $window, $selfAvatarLevel]);
    $opponent = $pick->fetch(PDO::FETCH_ASSOC);
    if (!$opponent) {
        return null;
    }

    $opponentId = (int) $opponent['id'];
    $opponentUserId = (int) $opponent['user_id'];

    $hasPresence = mw_queue_supports_presence_columns($pdo);
    if ($hasPresence) {
        $upd = $pdo->prepare(
            "UPDATE knd_mind_wars_matchmaking_queue
             SET status = 'matched',
                 matched_with_user_id = ?,
                 matched_at = NOW(),
                 match_expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND),
                 last_seen_at = NOW(),
                 updated_at = NOW()
             WHERE id = ? AND status = 'queued'"
        );
        $upd->execute([$opponentUserId, MW_QUEUE_MATCH_GRACE_SECONDS, $selfId]);
    } else {
        $upd = $pdo->prepare(
            "UPDATE knd_mind_wars_matchmaking_queue
             SET status = 'matched',
                 matched_with_user_id = ?,
                 matched_at = NOW(),
                 updated_at = NOW()
             WHERE id = ? AND status = 'queued'"
        );
        $upd->execute([$opponentUserId, $selfId]);
    }
    if ((int) $upd->rowCount() !== 1) {
        return null;
    }
    if ($hasPresence) {
        $upd->execute([$selfUserId, MW_QUEUE_MATCH_GRACE_SECONDS, $opponentId]);
    } else {
        $upd->execute([$selfUserId, $opponentId]);
    }
    if ((int) $upd->rowCount() !== 1) {
        return null;
    }

    return [
        'opponent_user_id' => $opponentUserId,
        'opponent_avatar_level' => (int) ($opponent['match_level'] ?? 1),
        'level_window' => $window,
        'rank_window' => $window,
    ];
}

/**
 * Ranked PvP matchmaking: enqueue or instant match. Caller must validate avatar ownership first.
 * Runs full transaction (begin/commit/rollback).
 *
 * @param array $avatar Row from mw_validate_owned_avatar()
 * @return array Payload for json_success (status matched|queued)
 */
function mw_perform_ranked_queue_enqueue(PDO $pdo, int $userId, int $avatarItemId, array $avatar): array {
    $pdo->beginTransaction();
    try {
        mw_cleanup_stale_queue($pdo);
        mw_cleanup_stale_queue_presence($pdo);
        $season = mw_ensure_season($pdo);
        $seasonId = (int) ($season['id'] ?? 0);
        $rankScore = mw_get_rank_score_snapshot($pdo, $userId, $seasonId);
        $avatarLevel = max(1, (int) ($avatar['avatar_level'] ?? 1));
        $hasLevelSnapshot = mw_queue_supports_avatar_level_snapshot($pdo);
        $levelValue = $hasLevelSnapshot ? $avatarLevel : $avatarLevel;
        $queueRankSnapshot = $hasLevelSnapshot ? $rankScore : $levelValue;

        if ($hasLevelSnapshot) {
            $existingStmt = $pdo->prepare(
                "SELECT id, queue_token, user_id, rank_score_snapshot, avatar_level_snapshot, created_at
                 FROM knd_mind_wars_matchmaking_queue
                 WHERE user_id = ? AND season_id = ? AND status = 'queued'
                 ORDER BY created_at ASC
                 FOR UPDATE"
            );
        } else {
            $existingStmt = $pdo->prepare(
                "SELECT id, queue_token, user_id, rank_score_snapshot, created_at
                 FROM knd_mind_wars_matchmaking_queue
                 WHERE user_id = ? AND season_id = ? AND status = 'queued'
                 ORDER BY created_at ASC
                 FOR UPDATE"
            );
        }
        $existingStmt->execute([$userId, $seasonId]);
        $existingRows = $existingStmt->fetchAll(PDO::FETCH_ASSOC);

        $selfRow = null;
        if (!empty($existingRows)) {
            $selfRow = $existingRows[0];
            if (count($existingRows) > 1) {
                $extraIds = array_map(static function (array $r): int { return (int) $r['id']; }, array_slice($existingRows, 1));
                if (!empty($extraIds)) {
                    $in = implode(',', array_fill(0, count($extraIds), '?'));
                    $cancel = $pdo->prepare("UPDATE knd_mind_wars_matchmaking_queue SET status = 'cancelled', updated_at = NOW() WHERE id IN ($in)");
                    $cancel->execute($extraIds);
                }
            }
        }

        if (!$selfRow) {
            $queueToken = mw_queue_generate_token();
            if ($hasLevelSnapshot) {
                $hasPresence = mw_queue_supports_presence_columns($pdo);
                if ($hasPresence) {
                    $insert = $pdo->prepare(
                        "INSERT INTO knd_mind_wars_matchmaking_queue
                         (queue_token, season_id, user_id, avatar_item_id, avatar_level_snapshot, rank_score_snapshot, status, last_seen_at, expires_at)
                         VALUES (?, ?, ?, ?, ?, ?, 'queued', NOW(), DATE_ADD(NOW(), INTERVAL ? SECOND))"
                    );
                    $insert->execute([$queueToken, $seasonId, $userId, $avatarItemId, $levelValue, $queueRankSnapshot, MW_QUEUE_TIMEOUT_SECONDS]);
                } else {
                    $insert = $pdo->prepare(
                        "INSERT INTO knd_mind_wars_matchmaking_queue
                         (queue_token, season_id, user_id, avatar_item_id, avatar_level_snapshot, rank_score_snapshot, status, expires_at)
                         VALUES (?, ?, ?, ?, ?, ?, 'queued', DATE_ADD(NOW(), INTERVAL ? SECOND))"
                    );
                    $insert->execute([$queueToken, $seasonId, $userId, $avatarItemId, $levelValue, $queueRankSnapshot, MW_QUEUE_TIMEOUT_SECONDS]);
                }
            } else {
                $hasPresence = mw_queue_supports_presence_columns($pdo);
                if ($hasPresence) {
                    $insert = $pdo->prepare(
                        "INSERT INTO knd_mind_wars_matchmaking_queue
                         (queue_token, season_id, user_id, avatar_item_id, rank_score_snapshot, status, last_seen_at, expires_at)
                         VALUES (?, ?, ?, ?, ?, 'queued', NOW(), DATE_ADD(NOW(), INTERVAL ? SECOND))"
                    );
                    $insert->execute([$queueToken, $seasonId, $userId, $avatarItemId, $queueRankSnapshot, MW_QUEUE_TIMEOUT_SECONDS]);
                } else {
                    $insert = $pdo->prepare(
                        "INSERT INTO knd_mind_wars_matchmaking_queue
                         (queue_token, season_id, user_id, avatar_item_id, rank_score_snapshot, status, expires_at)
                         VALUES (?, ?, ?, ?, ?, 'queued', DATE_ADD(NOW(), INTERVAL ? SECOND))"
                    );
                    $insert->execute([$queueToken, $seasonId, $userId, $avatarItemId, $queueRankSnapshot, MW_QUEUE_TIMEOUT_SECONDS]);
                }
            }
            $selfId = (int) $pdo->lastInsertId();
            if ($hasLevelSnapshot) {
                $load = $pdo->prepare("SELECT id, queue_token, user_id, rank_score_snapshot, avatar_level_snapshot, created_at FROM knd_mind_wars_matchmaking_queue WHERE id = ? LIMIT 1 FOR UPDATE");
            } else {
                $load = $pdo->prepare("SELECT id, queue_token, user_id, rank_score_snapshot, created_at FROM knd_mind_wars_matchmaking_queue WHERE id = ? LIMIT 1 FOR UPDATE");
            }
            $load->execute([$selfId]);
            $selfRow = $load->fetch(PDO::FETCH_ASSOC);
        } else {
            if ($hasLevelSnapshot) {
                $hasPresence = mw_queue_supports_presence_columns($pdo);
                if ($hasPresence) {
                    $refresh = $pdo->prepare(
                        "UPDATE knd_mind_wars_matchmaking_queue
                         SET avatar_item_id = ?, avatar_level_snapshot = ?, rank_score_snapshot = ?, last_seen_at = NOW(),
                             matched_with_user_id = NULL, matched_at = NULL, match_expires_at = NULL,
                             expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND), updated_at = NOW()
                         WHERE id = ?"
                    );
                    $refresh->execute([$avatarItemId, $levelValue, $queueRankSnapshot, MW_QUEUE_TIMEOUT_SECONDS, (int) $selfRow['id']]);
                } else {
                    $refresh = $pdo->prepare(
                        "UPDATE knd_mind_wars_matchmaking_queue
                         SET avatar_item_id = ?, avatar_level_snapshot = ?, rank_score_snapshot = ?, expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND), updated_at = NOW()
                         WHERE id = ?"
                    );
                    $refresh->execute([$avatarItemId, $levelValue, $queueRankSnapshot, MW_QUEUE_TIMEOUT_SECONDS, (int) $selfRow['id']]);
                }
                $selfRow['avatar_level_snapshot'] = $levelValue;
            } else {
                $hasPresence = mw_queue_supports_presence_columns($pdo);
                if ($hasPresence) {
                    $refresh = $pdo->prepare(
                        "UPDATE knd_mind_wars_matchmaking_queue
                         SET avatar_item_id = ?, rank_score_snapshot = ?, last_seen_at = NOW(),
                             matched_with_user_id = NULL, matched_at = NULL, match_expires_at = NULL,
                             expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND), updated_at = NOW()
                         WHERE id = ?"
                    );
                    $refresh->execute([$avatarItemId, $queueRankSnapshot, MW_QUEUE_TIMEOUT_SECONDS, (int) $selfRow['id']]);
                } else {
                    $refresh = $pdo->prepare(
                        "UPDATE knd_mind_wars_matchmaking_queue
                         SET avatar_item_id = ?, rank_score_snapshot = ?, expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND), updated_at = NOW()
                         WHERE id = ?"
                    );
                    $refresh->execute([$avatarItemId, $queueRankSnapshot, MW_QUEUE_TIMEOUT_SECONDS, (int) $selfRow['id']]);
                }
            }
            $selfRow['rank_score_snapshot'] = $queueRankSnapshot;
        }

        if (!$selfRow) {
            throw new RuntimeException('Failed to create queue entry.');
        }

        $match = mw_try_match_queue_pair($pdo, $seasonId, $selfRow);
        if ($match) {
            $pdo->commit();
            return [
                'status' => 'matched',
                'queue_token' => (string) ($selfRow['queue_token'] ?? ''),
                'match' => $match,
            ];
        }

        $createdAt = strtotime((string) ($selfRow['created_at'] ?? 'now')) ?: time();
        $queuedFor = max(0, time() - $createdAt);
        $pdo->commit();
        return [
            'status' => 'queued',
            'queue_token' => (string) ($selfRow['queue_token'] ?? ''),
            'queued_for_seconds' => $queuedFor,
            'level_window' => mw_queue_level_window($queuedFor),
            'rank_window' => mw_queue_rank_window($queuedFor),
            'avatar_level_snapshot' => (int) ($selfRow['avatar_level_snapshot'] ?? $selfRow['rank_score_snapshot'] ?? 1),
        ];
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Start a PvE Mind Wars battle (1v1 or 3v3). Full DB insert. Returns same shape as start_battle API.
 *
 * @param array $post Same keys as start_battle.php uses from $_POST
 */
function mw_start_pve_battle_for_user(PDO $pdo, int $userId, array $post): array {
    $format = strtolower(trim((string) ($post['format'] ?? '1v1')));
    $is3v3 = ($format === '3v3');

    $avatarItemIds = [];
    if ($is3v3 && isset($post['avatar_item_ids']) && is_array($post['avatar_item_ids'])) {
        foreach ($post['avatar_item_ids'] as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $avatarItemIds[] = $id;
            }
        }
        if (count($avatarItemIds) < 3) {
            throw new InvalidArgumentException('INVALID_AVATAR: 3v3 requires 3 avatars.');
        }
        $avatarItemIds = array_slice(array_unique($avatarItemIds), 0, 3);
    } else {
        if ($is3v3) {
            throw new InvalidArgumentException('INVALID_AVATAR: 3v3 requires 3 avatars.');
        }
        $avatarItemId = (int) ($post['avatar_item_id'] ?? 0);
        if ($avatarItemId < 1) {
            throw new InvalidArgumentException('INVALID_AVATAR: Select an avatar to start battle.');
        }
        $avatarItemIds = [$avatarItemId];
    }

    $mode = mw_normalize_mode((string) ($post['mode'] ?? 'pve'));
    if ($mode === 'pvp_ranked') {
        $mode = 'pve';
    }
    $difficulty = mw_normalize_difficulty((string) ($post['difficulty'] ?? 'normal'));

    $playerQueue = [];
    foreach ($avatarItemIds as $aid) {
        $avatar = mw_validate_owned_avatar($pdo, $userId, $aid);
        if (!$avatar) {
            throw new InvalidArgumentException('AVATAR_NOT_OWNED: You do not own avatar item ' . $aid . '.');
        }
        $playerQueue[] = mw_build_fighter($avatar, false);
    }
    $player = $playerQueue[0];
    $playerLevel = max(1, (int) ($player['level'] ?? 1));
    $enemyAvatar = mw_pick_enemy_avatar($pdo, $playerLevel, $difficulty);
    $enemyKndItemId = mw_resolve_enemy_to_knd_item_id($pdo, $enemyAvatar);
    $enemy = mw_build_fighter($enemyAvatar, true);

    $playerFirst = mw_roll_initiative($player, $enemy);
    $battleToken = bin2hex(random_bytes(32));

    $state = [
        'turn' => 1,
        'max_turns' => MW_MAX_TURNS,
        'player_first' => $playerFirst,
        'player' => $player,
        'enemy' => $enemy,
        'log' => [],
        'next_actor' => $playerFirst ? 'player' : 'enemy',
        'player_next_attack_crit' => false,
        'meta' => [
            'mode' => $mode,
            'difficulty' => $difficulty,
            'format' => $is3v3 ? '3v3' : '1v1',
            'enemy_wave_index' => 0,
            'player_queue' => $is3v3 ? $playerQueue : null,
            'player_queue_index' => 0,
        ],
    ];
    $state = mw_normalize_battle_state($state);

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO knd_mind_wars_battles (battle_token, user_id, avatar_item_id, enemy_avatar_id, mode, state_json, turns_played)
             VALUES (?, ?, ?, ?, ?, ?, 0)"
        );
        $stmt->execute([
            $battleToken,
            $userId,
            $avatarItemIds[0],
            $enemyKndItemId,
            $mode,
            json_encode($state, JSON_UNESCAPED_UNICODE),
        ]);
        $pdo->commit();
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    $logMsg = ($playerFirst ? 'You go first!' : 'Enemy goes first!')
        . ' Your Lv.' . (int) ($player['level'] ?? 1)
        . ' vs Enemy Lv.' . (int) ($enemy['level'] ?? 1) . '.';
    $state['log'][] = ['type' => 'info', 'msg' => $logMsg];
    $state = mw_normalize_battle_state($state);

    return [
        'battle_token' => $battleToken,
        'state' => $state,
        'player' => $state['player'],
        'enemy' => $state['enemy'],
    ];
}

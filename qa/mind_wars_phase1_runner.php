<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/mind_wars.php';
require_once __DIR__ . '/../includes/mind_wars_combat_actions.php';

function base_state(): array {
    return [
        'turn' => 1,
        'max_turns' => 12,
        'player_first' => true,
        'next_actor' => 'player',
        'player_next_attack_crit' => false,
        'log' => [],
        'player' => [
            'name' => 'TesterP',
            'hp' => 120,
            'hp_max' => 120,
            'mind' => 20,
            'focus' => 12,
            'speed' => 10,
            'luck' => 0,
            'energy' => 0,
            'defending' => false,
            'ability_cooldown' => 0,
            'ability_code' => 'generic_strike',
            'special_code' => 'generic_finisher',
            'passive_code' => null,
            'states' => [],
            'effects' => [],
            'battle_bonus' => [],
        ],
        'enemy' => [
            'name' => 'TesterE',
            'hp' => 120,
            'hp_max' => 120,
            'mind' => 20,
            'focus' => 12,
            'speed' => 10,
            'luck' => 0,
            'energy' => 0,
            'defending' => false,
            'ability_cooldown' => 0,
            'ability_code' => 'generic_strike',
            'special_code' => 'generic_finisher',
            'passive_code' => null,
            'states' => [],
            'effects' => [],
            'battle_bonus' => [],
        ],
    ];
}

function deep_merge(array $base, array $patch): array {
    foreach ($patch as $k => $v) {
        if (is_array($v) && isset($base[$k]) && is_array($base[$k])) {
            $base[$k] = deep_merge($base[$k], $v);
        } else {
            $base[$k] = $v;
        }
    }
    return $base;
}

function state_slice(array $state): array {
    return [
        'turn' => $state['turn'],
        'next_actor' => $state['next_actor'],
        'player_next_attack_crit' => $state['player_next_attack_crit'],
        'player' => [
            'hp' => $state['player']['hp'],
            'energy' => $state['player']['energy'],
            'states' => $state['player']['states'],
            'effects' => $state['player']['effects'],
            'battle_bonus' => $state['player']['battle_bonus'],
        ],
        'enemy' => [
            'hp' => $state['enemy']['hp'],
            'energy' => $state['enemy']['energy'],
            'states' => $state['enemy']['states'],
            'effects' => $state['enemy']['effects'],
            'battle_bonus' => $state['enemy']['battle_bonus'],
        ],
    ];
}

function run_engine_step(array $state, string $action): array {
    $isAdvance = ($action === 'advance');
    if ($isAdvance) {
        $state = mw_process_bot_turn($state);
        $events = mw_end_turn_phase($state['enemy'], (int) ($state['turn'] ?? 0));
        mw_log_effect_events($state, 'enemy', (int) ($state['turn'] ?? 0), $events, 'advance', null);
        $state['next_actor'] = 'player';
        $state = mw_tick_cooldowns($state);
    } else {
        $state = mw_process_player_action($action, $state);
        $events = mw_end_turn_phase($state['player'], (int) ($state['turn'] ?? 0));
        mw_log_effect_events($state, 'player', (int) ($state['turn'] ?? 0), $events, $action, null);
    }

    if (!mw_has_effect($state['player'], 'next_attack_crit')) {
        $state['player_next_attack_crit'] = false;
        if (isset($state['player']['battle_bonus']['next_crit'])) {
            unset($state['player']['battle_bonus']['next_crit']);
        }
    }

    $battleOver = false;
    if ($state['enemy']['hp'] <= 0 || $state['player']['hp'] <= 0) {
        $battleOver = true;
    }

    if (!$battleOver && !$isAdvance) {
        $state['next_actor'] = 'enemy';
        $state = mw_process_bot_turn($state);
        $events = mw_end_turn_phase($state['enemy'], (int) ($state['turn'] ?? 0));
        mw_log_effect_events($state, 'enemy', (int) ($state['turn'] ?? 0), $events, 'advance', null);
        if ($state['enemy']['hp'] <= 0 || $state['player']['hp'] <= 0) {
            $battleOver = true;
        }
    }

    if (!$battleOver && !$isAdvance) {
        $state['turn']++;
        $state['next_actor'] = 'player';
        $state = mw_tick_cooldowns($state);
    }

    return $state;
}

function log_tail(array $state, int $n = 6): array {
    $log = $state['log'] ?? [];
    $slice = array_slice($log, -$n);
    return array_map(static function ($e) {
        return (string) ($e['msg'] ?? '');
    }, $slice);
}

function assert_true(bool $cond, string $msg, array &$failures): void {
    if (!$cond) {
        $failures[] = $msg;
    }
}

$cc = ['type' => 'crowd_control', 'stacks' => 1, 'duration' => 1, 'potency' => 0, 'tick_phase' => 'start_turn', 'source' => 'test', 'applied_turn' => 1, 'max_stacks' => 1, 'stack_mode' => 'refresh'];

$tests = [
    [
        'id' => 'T21',
        'fixture' => [
            'player' => ['effects' => ['stun' => $cc], 'states' => ['stun' => true]],
            'enemy' => ['effects' => ['stun' => $cc], 'states' => ['stun' => true]],
        ],
        'actions' => ['attack'],
        'assert' => static function (array $before, array $after, array &$failures): void {
            assert_true(!isset($after['player']['effects']['stun']), 'player stun should expire', $failures);
            assert_true(!isset($after['enemy']['effects']['stun']), 'enemy stun should expire', $failures);
        },
    ],
    [
        'id' => 'T01',
        'fixture' => ['player' => ['energy' => 5, 'special_code' => 'wrath_of_olympus', 'mind' => 1], 'enemy' => ['hp' => 40, 'hp_max' => 120]],
        'actions' => ['special'],
        'assert' => static function (array $before, array $after, array &$failures): void {
            $joined = implode("\n", log_tail($GLOBALS['__mw_last_state'], 10));
            assert_true(stripos($joined, 'stun') !== false, 'expected stun log', $failures);
            assert_true(stripos($joined, 'misses the turn') !== false, 'expected enemy skip log', $failures);
        },
    ],
    [
        'id' => 'T02',
        'fixture' => ['player' => ['energy' => 5, 'special_code' => 'absolute_zero']],
        'actions' => ['special'],
        'assert' => static function (array $before, array $after, array &$failures): void {
            $joined = implode("\n", log_tail($GLOBALS['__mw_last_state'], 10));
            assert_true(stripos($joined, 'freeze') !== false, 'expected freeze log', $failures);
        },
    ],
    [
        'id' => 'T03',
        'fixture' => ['player' => ['energy' => 5, 'special_code' => 'stone_eternity']],
        'actions' => ['special'],
        'assert' => static function (array $before, array $after, array &$failures): void {
            $joined = implode("\n", log_tail($GLOBALS['__mw_last_state'], 10));
            assert_true(stripos($joined, 'petrify') !== false, 'expected petrify log', $failures);
        },
    ],
    [
        'id' => 'T04',
        'fixture' => [
            'enemy' => [
                'effects' => ['stun' => $cc, 'freeze' => $cc, 'petrify' => $cc],
                'states' => ['stun' => true, 'freeze' => true, 'petrify' => true],
            ],
        ],
        'actions' => ['attack'],
        'assert' => static function (array $before, array $after, array &$failures): void {
            $joined = implode("\n", log_tail($GLOBALS['__mw_last_state'], 8));
            assert_true(stripos($joined, 'petrify') !== false, 'priority should block by petrify first', $failures);
        },
    ],
    [
        'id' => 'T05',
        'fixture' => ['player' => ['energy' => 5, 'special_code' => 'storm_protocol']],
        'actions' => ['special'],
        'assert' => static function (array $before, array $after, array &$failures): void {
            $joined = implode("\n", log_tail($GLOBALS['__mw_last_state'], 12));
            assert_true(stripos($joined, 'shock') !== false, 'expected shock logs', $failures);
            assert_true($after['enemy']['hp'] < $before['enemy']['hp'], 'enemy hp should reduce overall', $failures);
        },
    ],
    [
        'id' => 'T08',
        'fixture' => [
            'next_actor' => 'enemy',
            'player' => [
                'speed' => 0,
                'effects' => ['shield' => ['type' => 'utility', 'stacks' => 1, 'duration' => 2, 'potency' => 30, 'tick_phase' => 'end_turn', 'source' => 'test', 'applied_turn' => 1, 'max_stacks' => 1, 'stack_mode' => 'replace_if_stronger']],
                'states' => ['shield' => true],
            ],
            'enemy' => ['ability_cooldown' => 99, 'mind' => 1],
        ],
        'actions' => ['advance'],
        'assert' => static function (array $before, array $after, array &$failures): void {
            $potency = (int) ($after['player']['effects']['shield']['potency'] ?? -1);
            assert_true($potency >= 0 && $potency < 30, 'shield potency should decrease but remain', $failures);
        },
    ],
    [
        'id' => 'T09',
        'fixture' => [
            'next_actor' => 'enemy',
            'player' => [
                'effects' => ['shield' => ['type' => 'utility', 'stacks' => 1, 'duration' => 2, 'potency' => 5, 'tick_phase' => 'end_turn', 'source' => 'test', 'applied_turn' => 1, 'max_stacks' => 1, 'stack_mode' => 'replace_if_stronger']],
                'states' => ['shield' => true],
            ],
            'enemy' => ['ability_cooldown' => 99, 'mind' => 20],
        ],
        'actions' => ['advance'],
        'assert' => static function (array $before, array $after, array &$failures): void {
            assert_true(!isset($after['player']['effects']['shield']), 'shield should break/remove', $failures);
        },
    ],
    [
        'id' => 'T11',
        'fixture' => [
            'player' => ['ability_code' => 'generic_burst', 'energy' => 0],
            'enemy' => ['effects' => ['stun' => $cc], 'states' => ['stun' => true]],
        ],
        'actions' => ['ability', 'attack'],
        'assert' => static function (array $before, array $after, array &$failures): void {
            assert_true(($after['player_next_attack_crit'] ?? false) === false, 'next crit should be consumed', $failures);
            assert_true(!isset($after['player']['effects']['next_attack_crit']), 'next_attack_crit effect should be removed', $failures);
            $joined = implode("\n", log_tail($GLOBALS['__mw_last_state'], 14));
            assert_true(stripos($joined, 'next attack will crit') !== false, 'should log next crit setup', $failures);
            assert_true(stripos($joined, 'critical hit') !== false, 'should log crit hit', $failures);
        },
    ],
    [
        'id' => 'T12',
        'fixture' => [
            'player' => ['ability_code' => 'generic_focus'],
            'enemy' => ['effects' => ['stun' => $cc], 'states' => ['stun' => true]],
        ],
        'actions' => ['ability', 'attack'],
        'assert' => static function (array $before, array $after, array &$failures): void {
            assert_true(!isset($after['player']['battle_bonus']['focus_up_once']), 'focus_up_once should be consumed', $failures);
        },
    ],
    [
        'id' => 'T15',
        'fixture' => [
            'player' => ['luck' => 0, 'energy' => 0],
            'enemy' => ['energy' => 0, 'effects' => ['stun' => $cc], 'states' => ['stun' => true]],
        ],
        'actions' => ['attack'],
        'assert' => static function (array $before, array $after, array &$failures): void {
            assert_true((int) $after['player']['energy'] >= 1, 'player should gain base attack energy', $failures);
            assert_true((int) $after['enemy']['energy'] >= 0, 'enemy energy should be valid', $failures);
        },
    ],
    [
        'id' => 'T16',
        'fixture' => [
            'player_next_attack_crit' => true,
            'player' => ['energy' => 0, 'luck' => 0],
            'enemy' => ['effects' => ['stun' => $cc], 'states' => ['stun' => true]],
        ],
        'actions' => ['attack'],
        'assert' => static function (array $before, array $after, array &$failures): void {
            assert_true((int) $after['player']['energy'] >= 3, 'crit attack should grant +3 energy total', $failures);
        },
    ],
    [
        'id' => 'T17',
        'fixture' => [
            'player_next_attack_crit' => true,
            'player' => ['passive_code' => 'spark_of_genius', 'energy' => 0, 'luck' => 0],
            'enemy' => ['effects' => ['stun' => $cc], 'states' => ['stun' => true]],
        ],
        'actions' => ['attack'],
        'assert' => static function (array $before, array $after, array &$failures): void {
            assert_true((int) $after['player']['energy'] >= 4, 'spark_of_genius should add +1 on crit', $failures);
        },
    ],
    [
        'id' => 'T22',
        'fixture' => [
            'next_actor' => 'enemy',
            'enemy' => [
                'hp' => 120,
                'effects' => [
                    'stun' => $cc,
                    'shock' => ['type' => 'debuff', 'stacks' => 1, 'duration' => 2, 'potency' => 8, 'tick_phase' => 'end_turn', 'source' => 'test', 'applied_turn' => 1, 'max_stacks' => 3, 'stack_mode' => 'stack'],
                    'chill' => ['type' => 'debuff', 'stacks' => 1, 'duration' => 2, 'potency' => 0.2, 'tick_phase' => 'end_turn', 'source' => 'test', 'applied_turn' => 1, 'max_stacks' => 1, 'stack_mode' => 'refresh'],
                    'focus_down' => ['type' => 'debuff', 'stacks' => 1, 'duration' => 2, 'potency' => 0.2, 'tick_phase' => 'end_turn', 'source' => 'test', 'applied_turn' => 1, 'max_stacks' => 1, 'stack_mode' => 'refresh'],
                    'shield' => ['type' => 'utility', 'stacks' => 1, 'duration' => 1, 'potency' => 10, 'tick_phase' => 'end_turn', 'source' => 'test', 'applied_turn' => 1, 'max_stacks' => 1, 'stack_mode' => 'replace_if_stronger'],
                ],
                'states' => ['stun' => true, 'shock' => true, 'chill' => true, 'focus_down' => true, 'shield' => true],
            ],
        ],
        'actions' => ['advance'],
        'assert' => static function (array $before, array $after, array &$failures): void {
            assert_true((int) $after['enemy']['hp'] <= 112, 'shock should tick for at least 8', $failures);
            assert_true(!isset($after['enemy']['effects']['shield']), 'shield duration should expire/remove', $failures);
        },
    ],
    [
        'id' => 'T23',
        'fixture' => [],
        'actions' => ['attack'],
        'assert' => static function (array $before, array $after, array &$failures): void {
            assert_true(isset($after['player']), 'state must contain player', $failures);
            assert_true(isset($after['enemy']), 'state must contain enemy', $failures);
            assert_true(isset($after['turn']), 'state must contain turn', $failures);
            assert_true(isset($after['next_actor']), 'state must contain next_actor', $failures);
        },
    ],
];

$results = [];
foreach ($tests as $test) {
    $state = deep_merge(base_state(), $test['fixture']);
    $before = state_slice($state);
    $steps = [];
    foreach ($test['actions'] as $action) {
        $pre = state_slice($state);
        $state = run_engine_step($state, $action);
        $post = state_slice($state);
        $steps[] = [
            'action' => $action,
            'pre' => $pre,
            'post' => $post,
            'log_tail' => log_tail($state, 8),
        ];
    }
    $after = state_slice($state);
    $GLOBALS['__mw_last_state'] = $state;
    $failures = [];
    $test['assert']($before, $after, $failures);
    $results[] = [
        'id' => $test['id'],
        'status' => empty($failures) ? 'PASS' : 'FAIL',
        'failures' => $failures,
        'before' => $before,
        'after' => $after,
        'steps' => $steps,
    ];
}

echo json_encode(['ok' => true, 'results' => $results], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;


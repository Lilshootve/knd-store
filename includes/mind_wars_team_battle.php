<?php
/**
 * Mind Wars - Team battle engine (2v2, 3v3 linear format).
 * Orchestrator over the 1v1 engine. Requires mind_wars.php and mind_wars_combat_actions.php.
 */

if (!function_exists('mw_build_fighter')) {
    require_once __DIR__ . '/mind_wars.php';
}
if (!function_exists('mw_execute_combat_action')) {
    require_once __DIR__ . '/mind_wars_combat_actions.php';
}

/**
 * Determine turn order: 1 if A acts first, -1 if B acts first.
 * speed > luck > Team A.
 */
function mw_team_turn_order(array $fighterA, array $fighterB): int {
    $speedA = (int) ($fighterA['speed'] ?? 0);
    $speedB = (int) ($fighterB['speed'] ?? 0);
    if ($speedA !== $speedB) {
        return $speedA > $speedB ? 1 : -1;
    }
    $luckA = (int) ($fighterA['luck'] ?? 0);
    $luckB = (int) ($fighterB['luck'] ?? 0);
    if ($luckA !== $luckB) {
        return $luckA > $luckB ? 1 : -1;
    }
    return 1;
}

/**
 * Execute one turn: attackingTeam's frontline attacks defendingTeam's frontline.
 * Returns outcome string or null to continue.
 *
 * @param array &$attackingTeam Team whose frontline attacks
 * @param array &$defendingTeam Team whose frontline is targeted
 * @param array &$log Log entries (appended)
 * @param array $context ['difficulty' => 'normal', 'turn' => 1]
 * @return string|null 'attacker_wins'|'defender_wins'|'draw' or null
 */
function mw_process_team_turn(array &$attackingTeam, array &$defendingTeam, array &$log, array $context = []): ?string {
    if (empty($attackingTeam) || empty($defendingTeam)) {
        return 'draw';
    }

    $attacker = $attackingTeam[0];
    $defender = $defendingTeam[0];

    $result = mw_execute_combat_action($attacker, $defender, $context);

    $attackingTeam[0] = $result['attacker'];
    $defendingTeam[0] = $result['defender'];

    foreach ($result['log'] as $entry) {
        $log[] = $entry;
    }

    $attackerDead = (($result['attacker']['hp'] ?? 0) <= 0);
    $defenderDead = (($result['defender']['hp'] ?? 0) <= 0);

    if ($attackerDead && $defenderDead) {
        $log[] = ['type' => 'fighter_defeated', 'msg' => 'Both fighters defeated - draw!', 'value' => 0];
        return 'draw';
    }

    if ($defenderDead) {
        $defeatedName = (string) ($result['defender']['name'] ?? 'Unknown');
        $log[] = ['type' => 'fighter_defeated', 'msg' => "Fighter {$defeatedName} defeated.", 'value' => 0];
        array_shift($defendingTeam);
        if (empty($defendingTeam)) {
            return 'attacker_wins';
        }
        $nextName = (string) ($defendingTeam[0]['name'] ?? 'Unknown');
        $log[] = ['type' => 'fighter_enters', 'msg' => "Next fighter enters: {$nextName}.", 'value' => 0];
        return null;
    }

    if ($attackerDead) {
        $defeatedName = (string) ($result['attacker']['name'] ?? 'Unknown');
        $log[] = ['type' => 'fighter_defeated', 'msg' => "Fighter {$defeatedName} defeated.", 'value' => 0];
        array_shift($attackingTeam);
        if (empty($attackingTeam)) {
            return 'defender_wins';
        }
        $nextName = (string) ($attackingTeam[0]['name'] ?? 'Unknown');
        $log[] = ['type' => 'fighter_enters', 'msg' => "Next fighter enters: {$nextName}.", 'value' => 0];
        return null;
    }

    return null;
}

/**
 * Run a full team battle. Both sides use the same engine (mw_execute_combat_action).
 *
 * @param array $teamA Array of fighters (from mw_build_fighter). Order = frontline first.
 * @param array $teamB Array of fighters. Order = frontline first.
 * @param array $options max_turns (int), difficulty (string)
 * @return array ['winner' => 'A'|'B'|'draw', 'rounds' => int, 'log' => [], 'teamA_remaining' => int, 'teamB_remaining' => int]
 */
function mw_run_team_battle(array $teamA, array $teamB, array $options = []): array {
    $teamA = array_values($teamA);
    $teamB = array_values($teamB);

    if (empty($teamA) && empty($teamB)) {
        return [
            'winner' => 'draw',
            'rounds' => 0,
            'log' => [],
            'teamA_remaining' => 0,
            'teamB_remaining' => 0,
        ];
    }
    if (empty($teamA)) {
        return [
            'winner' => 'B',
            'rounds' => 0,
            'log' => [['type' => 'team_win', 'msg' => 'Team B wins!', 'value' => 0]],
            'teamA_remaining' => 0,
            'teamB_remaining' => count($teamB),
        ];
    }
    if (empty($teamB)) {
        return [
            'winner' => 'A',
            'rounds' => 0,
            'log' => [['type' => 'team_win', 'msg' => 'Team A wins!', 'value' => 0]],
            'teamA_remaining' => count($teamA),
            'teamB_remaining' => 0,
        ];
    }

    $maxTurns = (int) ($options['max_turns'] ?? 30);
    $difficulty = (string) ($options['difficulty'] ?? 'normal');
    $log = [];
    $rounds = 0;

    while ($rounds < $maxTurns) {
        $rounds++;
        $log[] = ['type' => 'round', 'msg' => "=== Round {$rounds} ===", 'value' => 0];

        $nameA = (string) ($teamA[0]['name'] ?? 'Unknown');
        $hpA = (int) ($teamA[0]['hp'] ?? 0);
        $log[] = ['type' => 'frontline', 'msg' => "A frontline: {$nameA} ({$hpA} HP)", 'value' => 0];

        $nameB = (string) ($teamB[0]['name'] ?? 'Unknown');
        $hpB = (int) ($teamB[0]['hp'] ?? 0);
        $log[] = ['type' => 'frontline', 'msg' => "B frontline: {$nameB} ({$hpB} HP)", 'value' => 0];

        $order = mw_team_turn_order($teamA[0], $teamB[0]);
        $ctx = ['difficulty' => $difficulty, 'turn' => $rounds];

        if ($order >= 0) {
            $outcome = mw_process_team_turn($teamA, $teamB, $log, $ctx);
            if ($outcome === 'attacker_wins') {
                $log[] = ['type' => 'team_win', 'msg' => 'Team A wins!', 'value' => 0];
                return [
                    'winner' => 'A',
                    'rounds' => $rounds,
                    'log' => $log,
                    'teamA_remaining' => count($teamA),
                    'teamB_remaining' => count($teamB),
                ];
            }
            if ($outcome === 'defender_wins') {
                $log[] = ['type' => 'team_win', 'msg' => 'Team B wins!', 'value' => 0];
                return [
                    'winner' => 'B',
                    'rounds' => $rounds,
                    'log' => $log,
                    'teamA_remaining' => count($teamA),
                    'teamB_remaining' => count($teamB),
                ];
            }
            if ($outcome === 'draw') {
                $log[] = ['type' => 'team_win', 'msg' => 'Draw!', 'value' => 0];
                return [
                    'winner' => 'draw',
                    'rounds' => $rounds,
                    'log' => $log,
                    'teamA_remaining' => count($teamA),
                    'teamB_remaining' => count($teamB),
                ];
            }

            mw_team_tick_cooldowns($teamA, $teamB);

            if (empty($teamA) || empty($teamB)) {
                $winner = empty($teamB) ? 'A' : 'B';
                $log[] = ['type' => 'team_win', 'msg' => "Team {$winner} wins!", 'value' => 0];
                return [
                    'winner' => $winner,
                    'rounds' => $rounds,
                    'log' => $log,
                    'teamA_remaining' => count($teamA),
                    'teamB_remaining' => count($teamB),
                ];
            }

            $outcome = mw_process_team_turn($teamB, $teamA, $log, $ctx);
        } else {
            $outcome = mw_process_team_turn($teamB, $teamA, $log, $ctx);
            if ($outcome === 'attacker_wins') {
                $log[] = ['type' => 'team_win', 'msg' => 'Team B wins!', 'value' => 0];
                return [
                    'winner' => 'B',
                    'rounds' => $rounds,
                    'log' => $log,
                    'teamA_remaining' => count($teamA),
                    'teamB_remaining' => count($teamB),
                ];
            }
            if ($outcome === 'defender_wins') {
                $log[] = ['type' => 'team_win', 'msg' => 'Team A wins!', 'value' => 0];
                return [
                    'winner' => 'A',
                    'rounds' => $rounds,
                    'log' => $log,
                    'teamA_remaining' => count($teamA),
                    'teamB_remaining' => count($teamB),
                ];
            }
            if ($outcome === 'draw') {
                $log[] = ['type' => 'team_win', 'msg' => 'Draw!', 'value' => 0];
                return [
                    'winner' => 'draw',
                    'rounds' => $rounds,
                    'log' => $log,
                    'teamA_remaining' => count($teamA),
                    'teamB_remaining' => count($teamB),
                ];
            }

            mw_team_tick_cooldowns($teamA, $teamB);

            if (empty($teamA) || empty($teamB)) {
                $winner = empty($teamA) ? 'B' : 'A';
                $log[] = ['type' => 'team_win', 'msg' => "Team {$winner} wins!", 'value' => 0];
                return [
                    'winner' => $winner,
                    'rounds' => $rounds,
                    'log' => $log,
                    'teamA_remaining' => count($teamA),
                    'teamB_remaining' => count($teamB),
                ];
            }

            $outcome = mw_process_team_turn($teamA, $teamB, $log, $ctx);
        }

        if ($outcome === 'attacker_wins') {
            $log[] = ['type' => 'team_win', 'msg' => ($order >= 0 ? 'Team B wins!' : 'Team A wins!'), 'value' => 0];
            return [
                'winner' => $order >= 0 ? 'B' : 'A',
                'rounds' => $rounds,
                'log' => $log,
                'teamA_remaining' => count($teamA),
                'teamB_remaining' => count($teamB),
            ];
        }
        if ($outcome === 'defender_wins') {
            $log[] = ['type' => 'team_win', 'msg' => ($order >= 0 ? 'Team A wins!' : 'Team B wins!'), 'value' => 0];
            return [
                'winner' => $order >= 0 ? 'A' : 'B',
                'rounds' => $rounds,
                'log' => $log,
                'teamA_remaining' => count($teamA),
                'teamB_remaining' => count($teamB),
            ];
        }
        if ($outcome === 'draw') {
            $log[] = ['type' => 'team_win', 'msg' => 'Draw!', 'value' => 0];
            return [
                'winner' => 'draw',
                'rounds' => $rounds,
                'log' => $log,
                'teamA_remaining' => count($teamA),
                'teamB_remaining' => count($teamB),
            ];
        }

        mw_team_tick_cooldowns($teamA, $teamB);

        if (empty($teamA) || empty($teamB)) {
            $winner = empty($teamB) ? 'A' : 'B';
            $log[] = ['type' => 'team_win', 'msg' => "Team {$winner} wins!", 'value' => 0];
            return [
                'winner' => $winner,
                'rounds' => $rounds,
                'log' => $log,
                'teamA_remaining' => count($teamA),
                'teamB_remaining' => count($teamB),
            ];
        }
    }

    return mw_team_resolve_timeout($teamA, $teamB, $log, $rounds);
}

/**
 * Tick cooldowns for both frontlines.
 */
function mw_team_tick_cooldowns(array &$teamA, array &$teamB): void {
    $state = [
        'player' => &$teamA[0],
        'enemy' => &$teamB[0],
    ];
    $state = mw_tick_cooldowns($state);
}

/**
 * Resolve timeout: compare total HP of both teams.
 */
function mw_team_resolve_timeout(array $teamA, array $teamB, array $log, int $rounds): array {
    $hpA = 0;
    $hpB = 0;
    foreach ($teamA as $f) {
        $hpA += (int) ($f['hp'] ?? $f['hp_max'] ?? 0);
    }
    foreach ($teamB as $f) {
        $hpB += (int) ($f['hp'] ?? $f['hp_max'] ?? 0);
    }
    if ($hpA > $hpB) {
        $winner = 'A';
    } elseif ($hpB > $hpA) {
        $winner = 'B';
    } else {
        $winner = 'draw';
    }
    $msg = $winner === 'draw'
        ? 'Timeout! Draw by remaining HP.'
        : "Timeout! Team {$winner} wins by remaining HP.";
    $log[] = ['type' => 'timeout', 'msg' => $msg, 'value' => 0];
    return [
        'winner' => $winner,
        'rounds' => $rounds,
        'log' => $log,
        'teamA_remaining' => count($teamA),
        'teamB_remaining' => count($teamB),
    ];
}

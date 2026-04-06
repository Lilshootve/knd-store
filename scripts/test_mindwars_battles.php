<?php
/**
 * Mind Wars Battle Simulator - CLI
 * Simulates team battles (2-3 chars per side) using sequential 1v1 combat.
 *
 * Usage:
 *   php scripts/test_mindwars_battles.php
 *   php scripts/test_mindwars_battles.php --seed=12345
 *   php scripts/test_mindwars_battles.php --battles=200 --json=results.json
 */

require_once __DIR__ . '/../config/bootstrap.php';
declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    die('CLI only.');
}

const MW_SIM_MAX_TURNS = 30;
const MW_SIM_DEFAULT_BATTLES = 100;

// Parse argv
$seed = null;
$battles = MW_SIM_DEFAULT_BATTLES;
$jsonPath = null;
foreach ($argv ?? [] as $arg) {
    if (strpos($arg, '--seed=') === 0) {
        $seed = (int) substr($arg, 7);
    } elseif (strpos($arg, '--battles=') === 0) {
        $battles = max(1, (int) substr($arg, 10));
    } elseif (strpos($arg, '--json=') === 0) {
        $jsonPath = substr($arg, 7);
    }
}

// RNG seed - must be first
if ($seed !== null) {
    mt_srand($seed);
} else {
    $seed = random_int(1, 999999);
    mt_srand($seed);
    echo "Using RNG seed: {$seed}\n";
}

require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/mind_wars.php';
require_once BASE_PATH . '/includes/mind_wars_combat_actions.php';
require_once BASE_PATH . '/includes/mind_wars_team_battle.php';

/** All avatars from mw_avatars (legendary, epic, special, rare, common) */
const SIM_ALLOWED_RARITIES = ['legendary', 'epic', 'special', 'rare', 'common'];

/** Get avatar pool - all avatars from mw_avatars with allowed rarities */
function sim_get_avatar_pool(): array {
    $fallback = [];
    foreach (array_keys(MW_LEGENDARY_PROFILES) as $name) {
        $fallback[] = ['name' => $name, 'rarity' => 'legendary', 'item_id' => 0, 'avatar_level' => 1];
    }
    foreach (array_keys(MW_EPIC_CLASS_MAP) as $name) {
        $fallback[] = ['name' => ucwords(str_replace('_', ' ', $name)), 'rarity' => 'epic', 'item_id' => 0, 'avatar_level' => 1];
    }
    foreach (MW_GREEK_ROTATION_ORDER as $greek) {
        $greekCap = ucfirst($greek);
        $fallback[] = ['name' => "KND Cadet {$greekCap}", 'rarity' => 'common', 'item_id' => 0, 'avatar_level' => 1];
        $fallback[] = ['name' => "KND Vanguard {$greekCap}", 'rarity' => 'rare', 'item_id' => 0, 'avatar_level' => 1];
        $fallback[] = ['name' => "KND Specialist {$greekCap}", 'rarity' => 'special', 'item_id' => 0, 'avatar_level' => 1];
    }
    try {
        $pdo = getDBConnection();
        if ($pdo) {
            $placeholders = implode(',', array_fill(0, count(SIM_ALLOWED_RARITIES), '?'));
            $stmt = $pdo->prepare(
                "SELECT a.id, a.name, a.rarity
                 FROM mw_avatars a
                 WHERE a.rarity IN ({$placeholders})
                 ORDER BY a.rarity ASC, a.name ASC"
            );
            $stmt->execute(SIM_ALLOWED_RARITIES);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                $out = [];
                foreach ($rows as $row) {
                    $out[] = [
                        'name' => (string) ($row['name'] ?? 'Avatar'),
                        'rarity' => (string) ($row['rarity'] ?? 'common'),
                        'item_id' => (int) ($row['id'] ?? 0),
                        'avatar_level' => 1,
                    ];
                }
                return $out;
            }
        }
    } catch (Throwable $e) {
        // fall through to fallback
    }
    return $fallback;
}

/** Pick random avatars without repetition */
function sim_pick_avatars(array $pool, int $count): array {
    if (count($pool) < $count) {
        $count = count($pool);
    }
    $indices = array_keys($pool);
    $picked = [];
    for ($i = 0; $i < $count; $i++) {
        $idx = mt_rand(0, count($indices) - 1);
        $key = array_splice($indices, $idx, 1)[0];
        $picked[] = $pool[$key];
    }
    return $picked;
}

/** Extract metrics from battle log (structured) */
function sim_extract_metrics(array $log, array $teamANames, array $teamBNames): array {
    $damageByChar = [];
    $effectsCount = 0;
    $ccCount = 0;
    $teamADamage = 0;
    $teamBDamage = 0;

    foreach ($log as $entry) {
        $type = (string) ($entry['type'] ?? '');
        $value = (int) ($entry['value'] ?? $entry['damage'] ?? 0);
        $actorName = (string) ($entry['actor_name'] ?? $entry['actor'] ?? '');

        if (in_array($type, ['damage', 'crit'], true) && $value > 0) {
            if ($actorName !== '' && $actorName !== 'player' && $actorName !== 'enemy') {
                $damageByChar[$actorName] = ($damageByChar[$actorName] ?? 0) + $value;
                if (in_array($actorName, $teamANames, true)) {
                    $teamADamage += $value;
                } elseif (in_array($actorName, $teamBNames, true)) {
                    $teamBDamage += $value;
                }
            }
        }
        if ($type === 'status') {
            $effectsCount++;
        }
        if ($type === 'cc') {
            $ccCount++;
        }
    }

    return [
        'damage_by_char' => $damageByChar,
        'effects_count' => $effectsCount,
        'cc_count' => $ccCount,
        'team_a_damage' => $teamADamage,
        'team_b_damage' => $teamBDamage,
    ];
}

// Main
$pool = sim_get_avatar_pool();
if (empty($pool)) {
    fwrite(STDERR, "No avatar pool available.\n");
    exit(1);
}

$results = [];
$charWins = [];
$charAppearances = [];
$charDamage = [];
$teamAWins = 0;
$teamBWins = 0;
$draws = 0;
$teamADurations = [];
$teamBDurations = [];
$teamATotalDamage = 0;
$teamBTotalDamage = 0;
$totalCC = 0;

echo "Running {$battles} battles...\n";

for ($b = 0; $b < $battles; $b++) {
    $teamSize = mt_rand(2, 3);
    $teamAAvatars = sim_pick_avatars($pool, $teamSize);
    $teamBAvatars = sim_pick_avatars($pool, $teamSize);

    $teamA = [];
    $teamB = [];
    foreach ($teamAAvatars as $a) {
        $teamA[] = mw_build_fighter($a, false);
    }
    foreach ($teamBAvatars as $a) {
        $teamB[] = mw_build_fighter($a, true);
    }

    $teamANames = array_column($teamA, 'name');
    $teamBNames = array_column($teamB, 'name');

    foreach ($teamANames as $n) {
        $charAppearances[$n] = ($charAppearances[$n] ?? 0) + 1;
    }
    foreach ($teamBNames as $n) {
        $charAppearances[$n] = ($charAppearances[$n] ?? 0) + 1;
    }

    $result = mw_run_team_battle($teamA, $teamB, [
        'max_turns' => MW_SIM_MAX_TURNS,
        'difficulty' => 'normal',
    ]);

    $winner = $result['winner'];
    $totalTurns = $result['rounds'];
    $battleLog = $result['log'];

    $metrics = sim_extract_metrics($battleLog, $teamANames, $teamBNames);
    $totalCC += $metrics['cc_count'];
    $teamATotalDamage += $metrics['team_a_damage'];
    $teamBTotalDamage += $metrics['team_b_damage'];

    foreach ($metrics['damage_by_char'] as $name => $dmg) {
        $charDamage[$name] = ($charDamage[$name] ?? 0) + $dmg;
    }

    if ($winner === 'A') {
        $teamAWins++;
        foreach ($teamANames as $n) {
            $charWins[$n] = ($charWins[$n] ?? 0) + 1;
        }
        $teamADurations[] = $totalTurns;
    } elseif ($winner === 'B') {
        $teamBWins++;
        foreach ($teamBNames as $n) {
            $charWins[$n] = ($charWins[$n] ?? 0) + 1;
        }
        $teamBDurations[] = $totalTurns;
    } else {
        $draws++;
    }

    $results[] = [
        'winner' => $winner,
        'turns' => $totalTurns,
        'team_a' => $teamANames,
        'team_b' => $teamBNames,
        'damage_by_char' => $metrics['damage_by_char'],
        'effects_count' => $metrics['effects_count'],
        'cc_count' => $metrics['cc_count'],
    ];
}

// Stats
$allDurations = array_column($results, 'turns');
$avgDuration = !empty($allDurations) ? array_sum($allDurations) / count($allDurations) : 0;
$avgCC = $battles > 0 ? $totalCC / $battles : 0;

$teamAAvgDuration = !empty($teamADurations) ? array_sum($teamADurations) / count($teamADurations) : 0;
$teamBAvgDuration = !empty($teamBDurations) ? array_sum($teamBDurations) / count($teamBDurations) : 0;

arsort($charAppearances);
arsort($charDamage);

echo "\n";
echo "=== MIND WARS BATTLE SIMULATOR RESULTS ===\n";
echo "Battles: {$battles} | Seed: {$seed}\n";
echo "-----------------------------------------\n";
echo "Team A wins: {$teamAWins} | Team B wins: {$teamBWins} | Draws: {$draws}\n";
echo "Avg combat duration: " . number_format($avgDuration, 1) . " turns\n";
echo "Avg CC per combat: " . number_format($avgCC, 1) . "\n";
echo "Team A avg duration (when won): " . number_format($teamAAvgDuration, 1) . "\n";
echo "Team B avg duration (when won): " . number_format($teamBAvgDuration, 1) . "\n";
echo "\n--- Winrate by character (min 30 appearances) ---\n";

foreach ($charAppearances as $name => $count) {
    if ($count >= 5) {
        $wins = $charWins[$name] ?? 0;
        $wr = $count > 0 ? round(100 * $wins / $count, 1) : 0;
        echo sprintf("  %-25s %5.1f%% (%d/%d)\n", $name, $wr, $wins, $count);
    }
}

echo "\n--- Most used characters (top 10) ---\n";
$top = array_slice(array_keys($charAppearances), 0, 10, true);
foreach ($top as $name) {
    echo sprintf("  %-25s %d\n", $name, $charAppearances[$name]);
}

echo "\n--- Highest damage characters (top 10) ---\n";
$topDmg = array_slice(array_keys($charDamage), 0, 10, true);
foreach ($topDmg as $name) {
    echo sprintf("  %-25s %d\n", $name, $charDamage[$name]);
}

// Persist JSON
$jsonData = [
    'seed' => $seed,
    'timestamp' => date('Y-m-d H:i:s'),
    'battles' => $battles,
    'results' => $results,
    'stats' => [
        'team_a_wins' => $teamAWins,
        'team_b_wins' => $teamBWins,
        'draws' => $draws,
        'avg_duration' => round($avgDuration, 2),
        'avg_cc_per_combat' => round($avgCC, 2),
    ],
    'team_stats' => [
        'team_a_wins' => $teamAWins,
        'team_b_wins' => $teamBWins,
        'draws' => $draws,
        'team_a_avg_duration' => round($teamAAvgDuration, 2),
        'team_b_avg_duration' => round($teamBAvgDuration, 2),
        'team_a_total_damage' => $teamATotalDamage,
        'team_b_total_damage' => $teamBTotalDamage,
    ],
    'char_winrate' => $charWins,
    'char_appearances' => $charAppearances,
    'char_damage' => $charDamage,
];

$outPath = $jsonPath ?? ('results_' . date('Ymd_His') . '.json');
$outPath = (strpos($outPath, '/') !== false || strpos($outPath, '\\') !== false) ? $outPath : __DIR__ . '/' . $outPath;
file_put_contents($outPath, json_encode($jsonData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "\nResults saved to: {$outPath}\n";

<?php
declare(strict_types=1);

/**
 * Ejecuta N batallas SquadWars completas en memoria (motor + SubmitRoundService).
 *
 * Uso:
 *   php scripts/squadwars_batch_smoke.php
 *   php scripts/squadwars_batch_smoke.php --battles=30 --max-rounds=60
 *   php scripts/squadwars_batch_smoke.php --battles=100 --json=tmp/squad_batch.json
 *
 * No escribe en BD salvo que añadas otro modo; es stress del engine.
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);

if (php_sapi_name() !== 'cli') {
    echo "CLI only\n";
    exit(1);
}

$battles = 30;
$maxRounds = 60;
$jsonOut = null;
$vary = true;

foreach ($argv as $arg) {
    if (strpos((string) $arg, '--battles=') === 0) {
        $battles = max(1, (int) substr((string) $arg, 10));
    }
    if (strpos((string) $arg, '--max-rounds=') === 0) {
        $maxRounds = max(1, (int) substr((string) $arg, 13));
    }
    if (strpos((string) $arg, '--json=') === 0) {
        $jsonOut = substr((string) $arg, 7);
    }
    if ($arg === '--no-vary') {
        $vary = false;
    }
}

require_once __DIR__ . '/../config/bootstrap.php';
require_once BASE_PATH . '/games/squadwars/bootstrap.php';
require_once __DIR__ . '/squadwars_batch_fixtures.php';

$actionsTemplate = [
    ['unitId' => 'p1', 'action' => 'attack', 'targetScope' => SquadTargetScope::SLOT_ENEMY, 'targetSlot' => 'front'],
    ['unitId' => 'p2', 'action' => 'attack', 'targetScope' => SquadTargetScope::SLOT_ENEMY, 'targetSlot' => 'front'],
    ['unitId' => 'p3', 'action' => 'attack', 'targetScope' => SquadTargetScope::SLOT_ENEMY, 'targetSlot' => 'front'],
];

$svc = knd_squadwars_create_submit_service(null);

$winsPlayer = 0;
$winsEnemy = 0;
$incomplete = 0;
$errors = 0;
$roundsTotal = 0;
$roundsMin = PHP_INT_MAX;
$roundsMax = 0;
$details = [];

for ($i = 1; $i <= $battles; $i++) {
    try {
        $state = squad_batch_build_state($i, $vary);
        $token = (string) ($state['battleId'] ?? '');
        $roundsPlayed = 0;

        while ($roundsPlayed < $maxRounds) {
            if (($state['phase'] ?? '') === 'finished') {
                break;
            }
            $ver = (int) ($state['version'] ?? 1);
            $r = (int) ($state['round'] ?? 1);
            $svc->submit($state, $token, $r, $actionsTemplate, $ver);
            $roundsPlayed++;
            if (($state['phase'] ?? '') === 'finished') {
                break;
            }
        }

        if (($state['phase'] ?? '') !== 'finished') {
            $incomplete++;
            $details[] = ['battle' => $i, 'outcome' => 'incomplete', 'rounds' => $roundsPlayed];
        } else {
            $winner = (string) ($state['winner'] ?? '');
            if ($winner === 'player') {
                $winsPlayer++;
            } elseif ($winner === 'enemy') {
                $winsEnemy++;
            }
            $roundsTotal += $roundsPlayed;
            $roundsMin = min($roundsMin, $roundsPlayed);
            $roundsMax = max($roundsMax, $roundsPlayed);
            $details[] = ['battle' => $i, 'outcome' => $winner, 'rounds' => $roundsPlayed];
        }
    } catch (Throwable $e) {
        $errors++;
        $details[] = ['battle' => $i, 'outcome' => 'error', 'message' => $e->getMessage()];
    }
}

$finished = $winsPlayer + $winsEnemy;
$avgRounds = $finished > 0 ? round($roundsTotal / $finished, 2) : 0.0;
if ($roundsMin === PHP_INT_MAX) {
    $roundsMin = 0;
}

$report = [
    'battles_requested' => $battles,
    'max_rounds_cap' => $maxRounds,
    'vary_hp' => $vary,
    'player_wins' => $winsPlayer,
    'enemy_wins' => $winsEnemy,
    'incomplete' => $incomplete,
    'errors' => $errors,
    'avg_rounds_finished' => $avgRounds,
    'min_rounds_finished' => $roundsMin,
    'max_rounds_finished' => $roundsMax,
    'details' => $details,
];

echo "=== SQUADWARS BATCH SMOKE ===\n";
echo "battles: {$battles}  max_rounds: {$maxRounds}  vary_hp: " . ($vary ? 'yes' : 'no') . "\n";
echo "player_wins: {$winsPlayer}  enemy_wins: {$winsEnemy}  incomplete: {$incomplete}  errors: {$errors}\n";
echo "rounds (finished only): avg={$avgRounds} min={$roundsMin} max={$roundsMax}\n";

if ($jsonOut !== null) {
    $dir = dirname($jsonOut);
    if ($dir !== '' && $dir !== '.' && !is_dir($dir)) {
        if (!@mkdir($dir, 0775, true) && !is_dir($dir)) {
            fwrite(STDERR, "Cannot create directory for json: {$dir}\n");
            exit(1);
        }
    }
    file_put_contents($jsonOut, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
    echo "wrote: {$jsonOut}\n";
}

$exit = ($errors > 0 || $incomplete > 0) ? 2 : 0;
exit($exit);

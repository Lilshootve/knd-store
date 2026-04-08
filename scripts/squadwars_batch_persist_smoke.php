<?php
declare(strict_types=1);

/**
 * N batallas SquadWars con persistencia real (transacción por ronda, como submit_round HTTP).
 *
 * Uso:
 *   php scripts/squadwars_batch_persist_smoke.php
 *   php scripts/squadwars_batch_persist_smoke.php --battles=5 --user=1 --cleanup
 *
 * Requiere BD con knd_squadwars_battles (ver scripts/smoke_combat_persistence.php).
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);

if (php_sapi_name() !== 'cli') {
    echo "CLI only\n";
    exit(1);
}

$battles = 5;
$maxRounds = 60;
$userId = 1;
$cleanup = false;
$vary = true;

foreach ($argv as $arg) {
    if (strpos((string) $arg, '--battles=') === 0) {
        $battles = max(1, (int) substr((string) $arg, 10));
    }
    if (strpos((string) $arg, '--max-rounds=') === 0) {
        $maxRounds = max(1, (int) substr((string) $arg, 13));
    }
    if (strpos((string) $arg, '--user=') === 0) {
        $userId = max(1, (int) substr((string) $arg, 7));
    }
    if ($arg === '--cleanup') {
        $cleanup = true;
    }
    if ($arg === '--no-vary') {
        $vary = false;
    }
}

try {
    require_once __DIR__ . '/../config/bootstrap.php';
    require_once BASE_PATH . '/includes/config.php';
    require_once BASE_PATH . '/games/squadwars/bootstrap.php';
    require_once BASE_PATH . '/games/squadwars/infrastructure/SquadBattleRepository.php';
    require_once __DIR__ . '/squadwars_batch_fixtures.php';
} catch (Throwable $e) {
    fwrite(STDERR, '[squadwars_batch_persist_smoke] bootstrap failed: ' . $e->getMessage() . "\n");
    exit(1);
}

$pdo = getDBConnection();
if (!$pdo instanceof PDO) {
    fwrite(STDERR, "DB connection failed\n");
    exit(1);
}

$actionsTemplate = [
    ['unitId' => 'p1', 'action' => 'attack', 'targetScope' => SquadTargetScope::SLOT_ENEMY, 'targetSlot' => 'front'],
    ['unitId' => 'p2', 'action' => 'attack', 'targetScope' => SquadTargetScope::SLOT_ENEMY, 'targetSlot' => 'front'],
    ['unitId' => 'p3', 'action' => 'attack', 'targetScope' => SquadTargetScope::SLOT_ENEMY, 'targetSlot' => 'front'],
];

$repo = new SquadBattleRepository($pdo);
$svc = knd_squadwars_create_submit_service($pdo);

$tokensCreated = [];
$errors = 0;
$incomplete = 0;
$winsPlayer = 0;
$winsEnemy = 0;

for ($i = 1; $i <= $battles; $i++) {
    $token = '';
    try {
        $state = squad_batch_build_state($i, $vary);
        $state['userId'] = $userId;
        $token = $repo->createBattle($state);
        $tokensCreated[] = $token;

        $row = $repo->findByToken($token);
        if ($row === null || $row['id'] === '') {
            throw new RuntimeException('row_missing_after_create');
        }
        $battleId = (string) $row['id'];

        $roundsPlayed = 0;
        while ($roundsPlayed < $maxRounds) {
            $pdo->beginTransaction();
            try {
                $battle = $repo->findByTokenForUser($token, $userId, true);
                if ($battle === null) {
                    throw new RuntimeException('battle_not_found_for_user');
                }
                $loaded = $battle['state'];
                if (!is_array($loaded)) {
                    throw new RuntimeException('invalid_state');
                }
                if (($loaded['phase'] ?? '') === 'finished') {
                    $pdo->rollBack();
                    break;
                }

                $ver = (int) ($loaded['version'] ?? 1);
                $r = (int) ($loaded['round'] ?? 1);
                $svc->submit($loaded, $token, $r, $actionsTemplate, $ver);
                $repo->saveState((string) $battle['id'], $loaded);
                $pdo->commit();
            } catch (Throwable $txe) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $txe;
            }

            $roundsPlayed++;
            if (($loaded['phase'] ?? '') === 'finished') {
                break;
            }
        }

        $final = $repo->findByToken($token);
        $st = is_array($final) && is_array($final['state'] ?? null) ? $final['state'] : null;
        if (!is_array($st)) {
            throw new RuntimeException('final_state_missing');
        }
        if (($st['phase'] ?? '') !== 'finished') {
            $incomplete++;
        } else {
            $w = (string) ($st['winner'] ?? '');
            if ($w === 'player') {
                $winsPlayer++;
            } elseif ($w === 'enemy') {
                $winsEnemy++;
            }
        }
    } catch (Throwable $e) {
        $errors++;
        fwrite(STDERR, "battle {$i} token={$token} ERROR: " . $e->getMessage() . "\n");
    }
}

if ($cleanup && $errors === 0) {
    $del = $pdo->prepare('DELETE FROM knd_squadwars_battles WHERE battle_token = ? LIMIT 1');
    foreach ($tokensCreated as $t) {
        $del->execute([$t]);
    }
}

echo "=== SQUADWARS BATCH PERSIST SMOKE ===\n";
echo "battles: {$battles}  user_id: {$userId}  cleanup: " . ($cleanup ? 'yes' : 'no') . "\n";
echo "player_wins: {$winsPlayer}  enemy_wins: {$winsEnemy}  incomplete: {$incomplete}  errors: {$errors}\n";

$exit = ($errors > 0 || $incomplete > 0) ? 2 : 0;
exit($exit);

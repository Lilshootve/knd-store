<?php
declare(strict_types=1);

/**
 * Escribe y lee una batalla SquadWars real en knd_squadwars_battles (sin HTTP).
 *
 * Uso:
 *   php scripts/squadwars_persistence_smoke.php
 *   php scripts/squadwars_persistence_smoke.php --user=1
 *   php scripts/squadwars_persistence_smoke.php --cleanup
 *
 * --user=N  user_id guardado en la fila (default 1). La tabla no exige FK a users en la migración actual.
 * --cleanup borra la fila de prueba al final si todo OK.
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);

if (php_sapi_name() !== 'cli') {
    echo "CLI only\n";
    exit(1);
}

$userId = 1;
$cleanup = false;
foreach ($argv as $arg) {
    if (strpos((string) $arg, '--user=') === 0) {
        $userId = max(1, (int) substr((string) $arg, 7));
    }
    if ($arg === '--cleanup') {
        $cleanup = true;
    }
}

try {
    require_once __DIR__ . '/../config/bootstrap.php';
    require_once BASE_PATH . '/includes/config.php';
    require_once BASE_PATH . '/games/squadwars/bootstrap.php';
    require_once BASE_PATH . '/games/squadwars/infrastructure/SquadBattleRepository.php';
} catch (Throwable $e) {
    fwrite(STDERR, '[squadwars_persistence_smoke] bootstrap failed: ' . $e->getMessage() . "\n");
    exit(1);
}

$pdo = getDBConnection();
if (!$pdo instanceof PDO) {
    fwrite(STDERR, "DB connection failed\n");
    exit(1);
}

$token = 'smoke_' . bin2hex(random_bytes(12));
$state = SquadStateV1::emptyShell($token);
$state['userId'] = $userId;
$state['units'] = [
    'p1' => [
        'side' => 'player', 'slot' => 'front', 'hp' => 500, 'hp_max' => 500, 'energy' => 2,
        'alive' => true, 'stats' => ['mind' => 80, 'focus' => 70, 'speed' => 90, 'luck' => 50],
        'effects' => [], 'cooldowns' => [],
    ],
    'p2' => [
        'side' => 'player', 'slot' => 'mid', 'hp' => 400, 'hp_max' => 400, 'energy' => 2,
        'alive' => true, 'stats' => ['mind' => 60, 'focus' => 60, 'speed' => 70, 'luck' => 60],
        'effects' => [], 'cooldowns' => [],
    ],
    'p3' => [
        'side' => 'player', 'slot' => 'back', 'hp' => 350, 'hp_max' => 350, 'energy' => 2,
        'alive' => true, 'stats' => ['mind' => 50, 'focus' => 80, 'speed' => 50, 'luck' => 70],
        'effects' => [], 'cooldowns' => [],
    ],
    'e1' => [
        'side' => 'enemy', 'slot' => 'front', 'hp' => 450, 'hp_max' => 450, 'energy' => 2,
        'alive' => true, 'stats' => ['mind' => 70, 'focus' => 75, 'speed' => 65, 'luck' => 55],
        'effects' => [], 'cooldowns' => [],
    ],
    'e2' => [
        'side' => 'enemy', 'slot' => 'mid', 'hp' => 380, 'hp_max' => 380, 'energy' => 2,
        'alive' => true, 'stats' => ['mind' => 65, 'focus' => 60, 'speed' => 60, 'luck' => 50],
        'effects' => [], 'cooldowns' => [],
    ],
    'e3' => [
        'side' => 'enemy', 'slot' => 'back', 'hp' => 320, 'hp_max' => 320, 'energy' => 2,
        'alive' => true, 'stats' => ['mind' => 90, 'focus' => 50, 'speed' => 55, 'luck' => 45],
        'effects' => [], 'cooldowns' => [],
    ],
];

$repo = new SquadBattleRepository($pdo);
$pdo->beginTransaction();
try {
    $createdToken = $repo->createBattle($state);
    if ($createdToken !== $token) {
        throw new RuntimeException('token_mismatch');
    }
    $row = $repo->findByToken($token);
    if ($row === null || !isset($row['id'])) {
        throw new RuntimeException('row_not_found_after_insert');
    }
    $battleId = (string) $row['id'];
    $loaded = $row['state'];
    if (!is_array($loaded)) {
        throw new RuntimeException('invalid_state_loaded');
    }

    $service = knd_squadwars_create_submit_service($pdo);
    $actions = [
        ['unitId' => 'p1', 'action' => 'attack', 'targetScope' => 'slot_enemy', 'targetSlot' => 'front'],
        ['unitId' => 'p2', 'action' => 'attack', 'targetScope' => 'slot_enemy', 'targetSlot' => 'front'],
        ['unitId' => 'p3', 'action' => 'attack', 'targetScope' => 'slot_enemy', 'targetSlot' => 'front'],
    ];
    $result = $service->execute($loaded, [
        'battle_token' => $token,
        'round' => 1,
        'actions' => $actions,
        'clientStateVersion' => 1,
    ]);

    $repo->saveState($battleId, $loaded);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . "\n");
    exit(1);
}

$after = $repo->findByToken($token);
if ($after === null) {
    fwrite(STDERR, "FAIL: could not reload battle\n");
    exit(1);
}
$st = $after['state'];
if (!is_array($st)) {
    fwrite(STDERR, "FAIL: corrupted state after save\n");
    exit(1);
}

$v = (int) ($st['version'] ?? 0);
$r = (int) ($st['round'] ?? 0);
$ph = (string) ($st['phase'] ?? '');

echo "=== SQUADWARS PERSISTENCE SMOKE ===\n";
echo "battle_token: {$token}\n";
echo "user_id:      {$userId}\n";
echo "after_save:   version={$v} round={$r} phase={$ph}\n";
echo 'resolved_round (respuesta round): ' . $result->round
    . '  nextSubmitRound (state tras save): ' . $r
    . '  battleOver=' . ($result->battleOver ? 'true' : 'false') . "\n";

if ($v < 2 || $r < 2 || $ph !== 'planning') {
    fwrite(STDERR, "FAIL: unexpected state after persist (expected version>=2, round>=2, phase=planning)\n");
    exit(1);
}

echo "OK: round persistido correctamente.\n";

if ($cleanup) {
    $pdo->prepare('DELETE FROM knd_squadwars_battles WHERE battle_token = ? LIMIT 1')->execute([$token]);
    echo "cleanup: fila eliminada.\n";
} else {
    echo "hint: borrar manualmente o re-ejecutar con --cleanup\n";
}

exit(0);

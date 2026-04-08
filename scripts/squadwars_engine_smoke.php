<?php
declare(strict_types=1);

/**
 * Smoke test CLI: php scripts/squadwars_engine_smoke.php
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once BASE_PATH . '/games/squadwars/bootstrap.php';

$state = SquadStateV1::emptyShell('test-battle');
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

$svc = knd_squadwars_create_submit_service(null);
$actions = [
    ['unitId' => 'p1', 'action' => 'attack', 'targetScope' => 'slot_enemy', 'targetSlot' => 'front'],
    ['unitId' => 'p2', 'action' => 'attack', 'targetScope' => 'slot_enemy', 'targetSlot' => 'front'],
    ['unitId' => 'p3', 'action' => 'attack', 'targetScope' => 'slot_enemy', 'targetSlot' => 'front'],
];
$result = $svc->submit($state, 'test-battle', 1, $actions, 1);

echo json_encode($result->toApiArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
echo "state.version=" . ($state['version'] ?? '?') . " round=" . ($state['round'] ?? '?') . " phase=" . ($state['phase'] ?? '?') . "\n";

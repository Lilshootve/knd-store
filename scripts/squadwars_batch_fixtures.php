<?php
declare(strict_types=1);

/**
 * Estado 3v3 de prueba para scripts CLI (batch in-memory / batch persistido).
 * Requiere cargarse después de games/squadwars/bootstrap.php (SquadStateV1).
 *
 * @return array<string, mixed>
 */
function squad_batch_build_state(int $index, bool $vary): array
{
    $token = 'batch_' . $index . '_' . bin2hex(random_bytes(4));
    $state = SquadStateV1::emptyShell($token);

    $j = static function (int $base, int $spread) use ($vary): int {
        if (!$vary || $spread <= 0) {
            return $base;
        }
        return max(80, $base + random_int(-$spread, $spread));
    };

    $state['units'] = [
        'p1' => [
            'side' => 'player', 'slot' => 'front', 'hp' => $j(500, 40), 'hp_max' => $j(500, 40), 'energy' => 2,
            'alive' => true, 'stats' => ['mind' => 80, 'focus' => 70, 'speed' => 90, 'luck' => 50],
            'effects' => [], 'cooldowns' => [],
        ],
        'p2' => [
            'side' => 'player', 'slot' => 'mid', 'hp' => $j(400, 35), 'hp_max' => $j(400, 35), 'energy' => 2,
            'alive' => true, 'stats' => ['mind' => 60, 'focus' => 60, 'speed' => 70, 'luck' => 60],
            'effects' => [], 'cooldowns' => [],
        ],
        'p3' => [
            'side' => 'player', 'slot' => 'back', 'hp' => $j(350, 30), 'hp_max' => $j(350, 30), 'energy' => 2,
            'alive' => true, 'stats' => ['mind' => 50, 'focus' => 80, 'speed' => 50, 'luck' => 70],
            'effects' => [], 'cooldowns' => [],
        ],
        'e1' => [
            'side' => 'enemy', 'slot' => 'front', 'hp' => $j(450, 40), 'hp_max' => $j(450, 40), 'energy' => 2,
            'alive' => true, 'stats' => ['mind' => 70, 'focus' => 75, 'speed' => 65, 'luck' => 55],
            'effects' => [], 'cooldowns' => [],
        ],
        'e2' => [
            'side' => 'enemy', 'slot' => 'mid', 'hp' => $j(380, 35), 'hp_max' => $j(380, 35), 'energy' => 2,
            'alive' => true, 'stats' => ['mind' => 65, 'focus' => 60, 'speed' => 60, 'luck' => 50],
            'effects' => [], 'cooldowns' => [],
        ],
        'e3' => [
            'side' => 'enemy', 'slot' => 'back', 'hp' => $j(320, 30), 'hp_max' => $j(320, 30), 'energy' => 2,
            'alive' => true, 'stats' => ['mind' => 90, 'focus' => 50, 'speed' => 55, 'luck' => 45],
            'effects' => [], 'cooldowns' => [],
        ],
    ];

    foreach (['p1', 'p2', 'p3', 'e1', 'e2', 'e3'] as $uid) {
        $state['units'][$uid]['hp'] = min((int) $state['units'][$uid]['hp'], (int) $state['units'][$uid]['hp_max']);
    }

    return $state;
}

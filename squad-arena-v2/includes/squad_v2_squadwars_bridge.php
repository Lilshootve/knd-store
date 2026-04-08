<?php
declare(strict_types=1);

/**
 * Mapea el payload visual de squad-arena-v2 (allies/enemies) a SquadStateV1 para knd_squadwars_battles.
 *
 * Requiere: games/squadwars/bootstrap.php (SquadStateV1).
 * Stats/HP alineados con squad_v2_build_battle_payload; energía con constantes Mind Wars.
 */
require_once BASE_PATH . '/includes/mind_wars.php';

/**
 * @param list<array<string, mixed>> $allies
 * @param list<array<string, mixed>> $enemies
 * @return array<string, mixed>
 */
function squad_v2_squadwars_state_from_payload(int $userId, array $allies, array $enemies): array
{
    $token = 'squadv2_' . bin2hex(random_bytes(16));
    $state = SquadStateV1::emptyShell($token);
    $state['userId'] = max(1, $userId);
    $state['meta']['source'] = 'squad-arena-v2';
    $state['meta']['clientMode'] = 'visual_local';

    $energyStart = (int) MW_ENERGY_START;
    $energyMax = (int) MW_MAX_ENERGY;

    $units = [];

    foreach (array_values($allies) as $i => $a) {
        if (!is_array($a)) {
            continue;
        }
        $uid = 'p' . ($i + 1);
        if ($i >= 3) {
            break;
        }
        $maxHp = max(1, (int) ($a['maxHp'] ?? 500));
        $slot = (string) ($a['pos'] ?? (['front', 'mid', 'back'][$i] ?? 'front'));
        $mwId = (int) ($a['id'] ?? 0);
        $units[$uid] = [
            'side' => 'player',
            'slot' => $slot,
            'hp' => $maxHp,
            'hp_max' => $maxHp,
            'energy' => min($energyMax, max(0, $energyStart)),
            'alive' => true,
            'stats' => [
                'mind' => max(1, (int) ($a['mind'] ?? 50)),
                'focus' => max(1, (int) ($a['focus'] ?? 50)),
                'speed' => max(1, (int) ($a['speed'] ?? 50)),
                'luck' => max(1, (int) ($a['luck'] ?? 50)),
            ],
            'effects' => [],
            'cooldowns' => [],
            'meta' => [
                'arenaUnitId' => $mwId,
                'displayName' => (string) ($a['name'] ?? ''),
            ],
        ];
    }

    foreach (array_values($enemies) as $j => $e) {
        if (!is_array($e)) {
            continue;
        }
        $uid = 'e' . ($j + 1);
        if ($j >= 3) {
            break;
        }
        $maxHp = max(1, (int) ($e['maxHp'] ?? 450));
        $slot = (string) ($e['pos'] ?? (['front', 'mid', 'back'][$j] ?? 'front'));
        $rawArenaId = (int) ($e['id'] ?? 0);
        if (isset($e['mwAvatarId'])) {
            $mwEnemy = (int) $e['mwAvatarId'];
        } elseif ($rawArenaId > 200000 && $rawArenaId < 290000) {
            $mwEnemy = $rawArenaId - 200000;
        } else {
            $mwEnemy = $rawArenaId;
        }
        $units[$uid] = [
            'side' => 'enemy',
            'slot' => $slot,
            'hp' => $maxHp,
            'hp_max' => $maxHp,
            'energy' => min($energyMax, max(0, $energyStart)),
            'alive' => true,
            'stats' => [
                'mind' => max(1, (int) ($e['mind'] ?? 50)),
                'focus' => max(1, (int) ($e['focus'] ?? 50)),
                'speed' => max(1, (int) ($e['speed'] ?? 50)),
                'luck' => max(1, (int) ($e['luck'] ?? 50)),
            ],
            'effects' => [],
            'cooldowns' => [],
            'meta' => [
                'arenaUnitId' => (int) ($e['id'] ?? 0),
                'mwAvatarId' => $mwEnemy,
                'displayName' => (string) ($e['name'] ?? ''),
            ],
        ];
    }

    $state['units'] = $units;

    return $state;
}

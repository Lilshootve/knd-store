<?php
declare(strict_types=1);

/**
 * Resolución de KO en PvE 3v3 por cola: oleadas de enemigo y relevos de jugador.
 * Extraído desde api/mind-wars/perform_action.php para aislar responsabilidad 3v3 lineal.
 *
 * @return array{0: array, 1: bool, 2: string|null}
 */
function knd_mw3v3_resolve_pve_knockouts(PDO $pdo, array $state, string $difficulty, bool $battleOver, ?string $result): array
{
    if ($battleOver) {
        return [$state, $battleOver, $result];
    }
    $format = (string) ($state['meta']['format'] ?? '1v1');
    $is3v3 = ($format === '3v3');
    $enemyWaveIndex = (int) ($state['meta']['enemy_wave_index'] ?? 0);
    $playerQueue = $state['meta']['player_queue'] ?? null;
    $playerQueueIndex = (int) ($state['meta']['player_queue_index'] ?? 0);

    if (($state['enemy']['hp'] ?? 0) <= 0) {
        if ($is3v3 && $enemyWaveIndex < 2) {
            $playerLevel = max(1, (int) ($state['player']['level'] ?? 1));
            $newEnemyAvatar = mw_pick_enemy_avatar($pdo, $playerLevel, $difficulty);
            $newEnemy = mw_build_fighter($newEnemyAvatar, true);
            $state['enemy'] = $newEnemy;
            $state['meta']['enemy_wave_index'] = $enemyWaveIndex + 1;
            $state['log'][] = ['type' => 'info', 'msg' => 'Enemy defeated. Next opponent entering...'];
            $state['next_actor'] = 'player';
            $state = mw_tick_cooldowns($state);
            if (($state['player']['energy'] ?? 0) < MW_ENERGY_ATTACK_COST) {
                $state['player']['energy'] = min(MW_MAX_ENERGY, (int) ($state['player']['energy'] ?? 0) + MW_ENERGY_STUCK_GAIN);
            }
        } else {
            $battleOver = true;
            $result = 'win';
        }
    } elseif (($state['player']['hp'] ?? 0) <= 0) {
        if ($is3v3 && is_array($playerQueue) && $playerQueueIndex < count($playerQueue) - 1) {
            $nextPlayer = $playerQueue[$playerQueueIndex + 1];
            $maxHp = (int) ($nextPlayer['hp_max'] ?? $nextPlayer['max'] ?? MW_HP_BASE);
            $nextPlayer['hp'] = $maxHp;
            $nextPlayer['hp_max'] = $maxHp;
            $nextPlayer['energy'] = min(3, MW_MAX_ENERGY);
            $nextPlayer['defending'] = false;
            $nextPlayer['ability_cooldown'] = 0;
            $state['player'] = $nextPlayer;
            $state['meta']['player_queue_index'] = $playerQueueIndex + 1;
            $state['log'][] = ['type' => 'info', 'msg' => 'Avatar eliminated. Next avatar deployed.'];
            $state['next_actor'] = 'player';
            $state = mw_tick_cooldowns($state);
        } else {
            $battleOver = true;
            $result = 'lose';
        }
    }

    return [$state, $battleOver, $result];
}

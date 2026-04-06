<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once BASE_PATH . '/includes/mind_wars.php';

function p3_assert(bool $cond, string $message, array &$fails): void {
    if (!$cond) {
        $fails[] = $message;
    }
}

$tests = [];

$tests[] = (function (): array {
    $id = 'P3_TRAINING_BACKCOMPAT_TO_PVE';
    $baseWin = mw_rewards_for_result('win');
    $trainingWin = mw_rewards_for_result_in_mode('win', 'training');
    $fails = [];
    p3_assert(mw_normalize_mode('training') === 'pve', 'training should normalize to pve', $fails);
    p3_assert(($trainingWin['rank'] ?? null) === ($baseWin['rank'] ?? null), 'training rewards should match pve after normalization', $fails);
    p3_assert(($trainingWin['xp'] ?? null) === ($baseWin['xp'] ?? null), 'training xp should match pve after normalization', $fails);
    p3_assert(($trainingWin['knowledge_energy'] ?? null) === ($baseWin['knowledge_energy'] ?? null), 'training KE should match pve after normalization', $fails);
    return ['id' => $id, 'status' => empty($fails) ? 'PASS' : 'FAIL', 'failures' => $fails, 'observed' => ['base' => $baseWin, 'training' => $trainingWin]];
})();

$tests[] = (function (): array {
    $id = 'P3_MODE_PERSISTENCE';
    $state = mw_normalize_battle_state([
        'meta' => [
            'mode' => 'training',
            'difficulty' => 'hard',
        ],
        'turn' => 2,
        'next_actor' => 'enemy',
    ]);
    $fails = [];
    p3_assert(($state['meta']['mode'] ?? null) === 'pve', 'training mode should normalize to pve in state', $fails);
    p3_assert(($state['meta']['difficulty'] ?? null) === 'hard', 'difficulty should persist in normalized state', $fails);

    $future = mw_normalize_battle_state(['meta' => ['mode' => 'pvp_ranked', 'difficulty' => 'normal']]);
    p3_assert(($future['meta']['mode'] ?? null) === 'pvp_ranked', 'pvp_ranked should remain as future mode marker', $fails);

    $fallback = mw_normalize_battle_state(['meta' => ['mode' => '???', 'difficulty' => '???']]);
    p3_assert(($fallback['meta']['mode'] ?? null) === 'pve', 'invalid mode should fallback to pve', $fails);
    p3_assert(($fallback['meta']['difficulty'] ?? null) === 'normal', 'invalid difficulty should fallback to normal', $fails);
    return ['id' => $id, 'status' => empty($fails) ? 'PASS' : 'FAIL', 'failures' => $fails, 'observed' => ['state' => $state, 'future' => $future, 'fallback' => $fallback]];
})();

$tests[] = (function (): array {
    $id = 'P3_DIFFICULTY_AI_PROFILE';
    $bot = [
        'hp' => 100,
        'hp_max' => 100,
        'energy' => 0,
        'ability_cooldown' => 0,
        'ability_code' => 'generic_burst',
        'special_code' => 'generic_finisher',
    ];
    $player = ['hp' => 100, 'hp_max' => 100];

    $iterations = 800;
    $abilityCounts = ['easy' => 0, 'normal' => 0, 'hard' => 0];
    foreach (['easy', 'normal', 'hard'] as $difficulty) {
        for ($i = 0; $i < $iterations; $i++) {
            $action = mw_bot_choose_action_with_difficulty($bot, $player, $difficulty);
            if ($action === 'ability') {
                $abilityCounts[$difficulty]++;
            }
        }
    }

    $specialBot = $bot;
    $specialBot['energy'] = MW_MAX_ENERGY;
    $specialHard = mw_bot_choose_action_with_difficulty($specialBot, $player, 'hard');

    $fails = [];
    p3_assert($abilityCounts['easy'] < $abilityCounts['normal'], 'easy should use ability less than normal', $fails);
    p3_assert($abilityCounts['normal'] < $abilityCounts['hard'], 'hard should use ability more than normal', $fails);
    p3_assert($specialHard === 'special', 'hard should prioritize special at max energy', $fails);

    return [
        'id' => $id,
        'status' => empty($fails) ? 'PASS' : 'FAIL',
        'failures' => $fails,
        'observed' => [
            'iterations' => $iterations,
            'ability_counts' => $abilityCounts,
            'hard_full_energy_action' => $specialHard,
        ],
    ];
})();

$tests[] = (function (): array {
    $id = 'P3_API_CONTRACT_SHAPE';
    $state = mw_normalize_battle_state([
        'meta' => ['mode' => 'pve', 'difficulty' => 'easy'],
    ]);
    $payload = mw_build_cached_response_payload($state, false, null, null);
    $fails = [];
    p3_assert(array_key_exists('state', $payload), 'payload must include state key', $fails);
    p3_assert(array_key_exists('battle_over', $payload), 'payload must include battle_over key', $fails);
    p3_assert(array_key_exists('result', $payload), 'payload must include result key', $fails);
    p3_assert(array_key_exists('rewards', $payload), 'payload must include rewards key', $fails);
    return ['id' => $id, 'status' => empty($fails) ? 'PASS' : 'FAIL', 'failures' => $fails, 'observed' => $payload];
})();

echo json_encode(['ok' => true, 'results' => $tests], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;


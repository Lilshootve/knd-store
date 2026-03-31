<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/mind_wars.php';

function p2_assert(bool $cond, string $message, array &$fails): void {
    if (!$cond) {
        $fails[] = $message;
    }
}

function p2_guard_action_allowed(array $state, string $action): string {
    $nextActor = (string)($state['next_actor'] ?? 'player');
    if ($action === 'advance') {
        return $nextActor === 'enemy' ? 'OK' : 'NOT_BOT_TURN';
    }
    return $nextActor === 'player' ? 'OK' : 'NOT_YOUR_TURN';
}

$tests = [];

$tests[] = (function (): array {
    $id = 'P2_STATE_NORMALIZE';
    $input = [
        'turn' => -5,
        'next_actor' => 'invalid',
        'player' => ['hp' => -10, 'energy' => 99, 'states' => 'oops'],
        'enemy' => ['hp_max' => 0, 'effects' => 'oops'],
        'log' => 'bad',
    ];
    $out = mw_normalize_battle_state($input);
    $fails = [];
    p2_assert($out['turn'] === 1, 'turn should normalize to >=1', $fails);
    p2_assert($out['next_actor'] === 'player', 'next_actor should normalize to player/enemy', $fails);
    p2_assert($out['player']['hp'] === 0, 'player hp should clamp >=0', $fails);
    p2_assert($out['player']['energy'] === MW_MAX_ENERGY, 'player energy should clamp to max', $fails);
    p2_assert(is_array($out['player']['states']), 'player states should be array', $fails);
    p2_assert($out['enemy']['hp_max'] >= 1, 'enemy hp_max should clamp >=1', $fails);
    p2_assert(is_array($out['enemy']['effects']), 'enemy effects should be array', $fails);
    p2_assert(is_array($out['meta']), 'meta must exist', $fails);
    return ['id' => $id, 'status' => empty($fails) ? 'PASS' : 'FAIL', 'failures' => $fails, 'observed' => $out];
})();

$tests[] = (function (): array {
    $id = 'P2_RESUME_STATE_CONTINUITY';
    $saved = mw_normalize_battle_state([
        'turn' => 6,
        'next_actor' => 'enemy',
        'player' => ['hp' => 66, 'energy' => 2],
        'enemy' => ['hp' => 23, 'energy' => 4],
        'log' => [['type' => 'status', 'msg' => 'Saved snapshot']],
    ]);
    $resumed = mw_normalize_battle_state($saved);
    $fails = [];
    p2_assert($resumed['turn'] === 6, 'resume must preserve turn', $fails);
    p2_assert($resumed['next_actor'] === 'enemy', 'resume must preserve actor ownership', $fails);
    p2_assert($resumed['player']['hp'] === 66, 'resume must preserve fighter hp', $fails);
    return ['id' => $id, 'status' => empty($fails) ? 'PASS' : 'FAIL', 'failures' => $fails, 'observed' => $resumed];
})();

$tests[] = (function (): array {
    $id = 'P2_RESUME_NO_AUTO_ADVANCE_EXPECTATION';
    $saved = mw_normalize_battle_state([
        'turn' => 7,
        'next_actor' => 'enemy',
        'player' => ['hp' => 52, 'energy' => 3],
        'enemy' => ['hp' => 41, 'energy' => 4],
        'log' => [['type' => 'status', 'msg' => 'Battle restored']],
    ]);
    $resumed = mw_normalize_battle_state($saved);

    $fails = [];
    p2_assert($resumed['turn'] === 7, 'resume should not auto-advance turn', $fails);
    p2_assert($resumed['next_actor'] === 'enemy', 'resume should keep pending enemy turn', $fails);
    p2_assert(($resumed['enemy']['hp'] ?? null) === 41, 'resume should not mutate enemy state', $fails);
    p2_assert(($resumed['player']['hp'] ?? null) === 52, 'resume should not mutate player state', $fails);
    return ['id' => $id, 'status' => empty($fails) ? 'PASS' : 'FAIL', 'failures' => $fails, 'observed' => $resumed];
})();

$tests[] = (function (): array {
    $id = 'P2_CACHED_RESPONSE_SANITIZE';
    $state = mw_normalize_battle_state([
        'turn' => 3,
        'next_actor' => 'enemy',
        'meta' => [
            'last_response' => ['state' => ['turn' => 2], 'battle_over' => false, 'result' => null, 'rewards' => null],
        ],
    ]);
    $payload = mw_build_cached_response_payload($state, false, null, null);
    $fails = [];
    p2_assert(isset($payload['state']), 'payload must include state', $fails);
    p2_assert(!isset($payload['state']['meta']['last_response']), 'payload state must not recursively include last_response', $fails);
    p2_assert($payload['battle_over'] === false, 'battle_over should persist', $fails);
    return ['id' => $id, 'status' => empty($fails) ? 'PASS' : 'FAIL', 'failures' => $fails, 'observed' => $payload];
})();

$tests[] = (function (): array {
    $id = 'P2_DUPLICATE_ACTION_REPLAY_NO_MUTATION';
    $base = mw_normalize_battle_state([
        'turn' => 4,
        'next_actor' => 'enemy',
        'player' => ['hp' => 80, 'energy' => 2],
        'enemy' => ['hp' => 44, 'energy' => 3],
    ]);

    $freshResponse = mw_build_cached_response_payload($base, false, null, null);
    $stored = $base;
    $stored['meta']['last_action_id'] = 'mw_action_same';
    $stored['meta']['last_response'] = $freshResponse;

    $before = json_encode($stored, JSON_UNESCAPED_UNICODE);
    $replayed = $stored['meta']['last_response'] ?? null;
    $after = json_encode($stored, JSON_UNESCAPED_UNICODE);

    $fails = [];
    p2_assert(is_array($replayed), 'replay payload should exist', $fails);
    p2_assert($before === $after, 'replay path must not mutate stored state', $fails);
    p2_assert(($replayed['state']['turn'] ?? null) === 4, 'replay should return cached turn', $fails);
    p2_assert(($replayed['state']['next_actor'] ?? null) === 'enemy', 'replay should return cached actor', $fails);
    return ['id' => $id, 'status' => empty($fails) ? 'PASS' : 'FAIL', 'failures' => $fails, 'observed' => $replayed];
})();

$tests[] = (function (): array {
    $id = 'P2_STALE_SUBMISSION_GUARD';
    $state = mw_normalize_battle_state([
        'turn' => 5,
        'next_actor' => 'enemy',
    ]);
    $fails = [];
    p2_assert(p2_guard_action_allowed($state, 'attack') === 'NOT_YOUR_TURN', 'stale second submit should be rejected', $fails);
    p2_assert(p2_guard_action_allowed($state, 'advance') === 'OK', 'enemy advance should be allowed', $fails);
    return ['id' => $id, 'status' => empty($fails) ? 'PASS' : 'FAIL', 'failures' => $fails, 'observed' => $state];
})();

$tests[] = (function (): array {
    $id = 'P2_IDEMPOTENCY_REPLAY_SHAPE';
    $state = mw_normalize_battle_state([
        'turn' => 4,
        'next_actor' => 'player',
        'player' => ['hp' => 80, 'energy' => 2],
        'enemy' => ['hp' => 44, 'energy' => 3],
    ]);
    $response = mw_build_cached_response_payload($state, false, null, null);
    $state['meta']['last_action_id'] = 'mw_action_abc';
    $state['meta']['last_response'] = $response;

    $cached = $state['meta']['last_response'] ?? null;
    $fails = [];
    p2_assert(is_array($cached), 'cached response should be array', $fails);
    p2_assert(array_key_exists('state', $cached), 'cached response should include state', $fails);
    p2_assert(array_key_exists('battle_over', $cached), 'cached response should include battle_over', $fails);
    p2_assert(array_key_exists('result', $cached), 'cached response should include result', $fails);
    p2_assert(array_key_exists('rewards', $cached), 'cached response should include rewards', $fails);
    p2_assert(($state['meta']['last_action_id'] ?? null) === 'mw_action_abc', 'last_action_id should persist', $fails);
    return ['id' => $id, 'status' => empty($fails) ? 'PASS' : 'FAIL', 'failures' => $fails, 'observed' => $state['meta']];
})();

$tests[] = (function (): array {
    $id = 'P2_INTERRUPTED_STATE_RECOVERY';
    $interrupted = [
        'player' => ['name' => 'InterruptedP', 'hp' => 50],
        'enemy' => ['name' => 'InterruptedE'],
    ];
    $out = mw_normalize_battle_state($interrupted);
    $fails = [];
    p2_assert(isset($out['player']['effects']) && is_array($out['player']['effects']), 'player effects must recover', $fails);
    p2_assert(isset($out['enemy']['effects']) && is_array($out['enemy']['effects']), 'enemy effects must recover', $fails);
    p2_assert(isset($out['player']['battle_bonus']) && is_array($out['player']['battle_bonus']), 'player battle_bonus must recover', $fails);
    p2_assert(isset($out['enemy']['battle_bonus']) && is_array($out['enemy']['battle_bonus']), 'enemy battle_bonus must recover', $fails);
    p2_assert(isset($out['log']) && is_array($out['log']), 'log must recover as array', $fails);
    return ['id' => $id, 'status' => empty($fails) ? 'PASS' : 'FAIL', 'failures' => $fails, 'observed' => $out];
})();

echo json_encode(['ok' => true, 'results' => $tests], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;


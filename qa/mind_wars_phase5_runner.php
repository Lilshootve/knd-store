<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once BASE_PATH . '/includes/mind_wars.php';

function p5_assert(bool $cond, string $message, array &$fails): void {
    if (!$cond) {
        $fails[] = $message;
    }
}

$tests = [];

$tests[] = (function (): array {
    $id = 'P5_MODE_ACCEPTS_PVP_RANKED';
    $fails = [];
    p5_assert(mw_normalize_mode('pvp_ranked') === 'pvp_ranked', 'pvp_ranked mode must remain valid', $fails);
    p5_assert(mw_normalize_mode('training') === 'pve', 'legacy training must normalize to pve', $fails);
    return ['id' => $id, 'status' => empty($fails) ? 'PASS' : 'FAIL', 'failures' => $fails];
})();

$tests[] = (function (): array {
    $id = 'P5_QUEUE_HELPERS_STABLE';
    $fails = [];
    $window = mw_queue_level_window(45);
    p5_assert($window >= MW_QUEUE_LEVEL_BASE_WINDOW, 'level window should be at least base window', $fails);
    p5_assert($window <= MW_QUEUE_LEVEL_MAX_WINDOW, 'level window should not exceed max', $fails);
    $token = mw_queue_generate_token();
    p5_assert(strlen($token) === 64, 'queue token should remain 64 chars', $fails);
    return ['id' => $id, 'status' => empty($fails) ? 'PASS' : 'FAIL', 'failures' => $fails, 'observed' => ['window' => $window, 'token' => $token]];
})();

$tests[] = (function (): array {
    $id = 'P5_ENDPOINTS_PRESENT';
    $fails = [];
    $required = [
        __DIR__ . '/../api/mind-wars/pvp_join_matched.php',
        __DIR__ . '/../api/mind-wars/queue_status.php',
        __DIR__ . '/../api/mind-wars/perform_action.php',
        __DIR__ . '/../api/mind-wars/get_battle_state.php',
    ];
    foreach ($required as $path) {
        p5_assert(is_file($path), 'missing endpoint file: ' . basename($path), $fails);
    }
    return ['id' => $id, 'status' => empty($fails) ? 'PASS' : 'FAIL', 'failures' => $fails];
})();

$tests[] = (function (): array {
    $id = 'P5_SQL_PARTICIPANTS_DEFINED';
    $fails = [];
    $sql = @file_get_contents(__DIR__ . '/../sql/knd_mind_wars.sql');
    $sql = is_string($sql) ? $sql : '';
    p5_assert(stripos($sql, 'knd_mind_wars_battle_participants') !== false, 'participants table must be declared in sql', $fails);
    p5_assert(stripos($sql, "UNIQUE KEY `uk_battle_side`") !== false, 'participants table must enforce unique side per battle', $fails);
    return ['id' => $id, 'status' => empty($fails) ? 'PASS' : 'FAIL', 'failures' => $fails];
})();

echo json_encode(['ok' => true, 'results' => $tests], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;


<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once BASE_PATH . '/games/mindwars1v1/bootstrap.php';

function p4_assert(bool $cond, string $message, array &$fails): void {
    if (!$cond) {
        $fails[] = $message;
    }
}

$tests = [];

$tests[] = (function (): array {
    $id = 'P4_MODE_NORMALIZATION_COMPAT';
    $fails = [];
    p4_assert(mw_normalize_mode('training') === 'pve', 'training must normalize to pve', $fails);
    p4_assert(mw_normalize_mode('pvp_ranked') === 'pvp_ranked', 'pvp_ranked must remain valid mode', $fails);
    p4_assert(mw_normalize_mode('???') === 'pve', 'invalid mode must fallback to pve', $fails);
    return ['id' => $id, 'status' => empty($fails) ? 'PASS' : 'FAIL', 'failures' => $fails];
})();

$tests[] = (function (): array {
    $id = 'P4_LEVEL_WINDOW_GROWTH';
    $fails = [];
    $w0 = mw_queue_level_window(0);
    $w20 = mw_queue_level_window(20);
    $w40 = mw_queue_level_window(40);
    $w999 = mw_queue_level_window(9999);
    p4_assert($w0 === 1, 'window should be fixed at 1', $fails);
    p4_assert($w20 === 1, 'window should remain fixed at 1', $fails);
    p4_assert($w40 === 1, 'window should remain fixed at 1', $fails);
    p4_assert($w999 === 1, 'window should remain fixed at 1', $fails);
    return [
        'id' => $id,
        'status' => empty($fails) ? 'PASS' : 'FAIL',
        'failures' => $fails,
        'observed' => ['w0' => $w0, 'w20' => $w20, 'w40' => $w40, 'w999' => $w999],
    ];
})();

$tests[] = (function (): array {
    $id = 'P4_AFK_CONSTANTS_AND_HELPERS';
    $fails = [];
    p4_assert(defined('MW_QUEUE_HEARTBEAT_STALE_SECONDS') && MW_QUEUE_HEARTBEAT_STALE_SECONDS > 0, 'heartbeat stale seconds should be defined and positive', $fails);
    p4_assert(defined('MW_QUEUE_MATCH_GRACE_SECONDS') && MW_QUEUE_MATCH_GRACE_SECONDS > 0, 'match grace seconds should be defined and positive', $fails);
    p4_assert(function_exists('mw_queue_touch_presence'), 'mw_queue_touch_presence helper should exist', $fails);
    p4_assert(function_exists('mw_cleanup_stale_queue_presence'), 'mw_cleanup_stale_queue_presence helper should exist', $fails);
    p4_assert(function_exists('mw_queue_supports_presence_columns'), 'mw_queue_supports_presence_columns helper should exist', $fails);
    return ['id' => $id, 'status' => empty($fails) ? 'PASS' : 'FAIL', 'failures' => $fails];
})();

$tests[] = (function (): array {
    $id = 'P4_SQL_AFK_COLUMNS_DEFINED';
    $fails = [];
    $sql = @file_get_contents(__DIR__ . '/../sql/knd_mind_wars.sql');
    $sql = is_string($sql) ? $sql : '';
    p4_assert(stripos($sql, 'last_seen_at') !== false, 'queue table should define last_seen_at', $fails);
    p4_assert(stripos($sql, 'match_expires_at') !== false, 'queue table should define match_expires_at', $fails);
    return ['id' => $id, 'status' => empty($fails) ? 'PASS' : 'FAIL', 'failures' => $fails];
})();

$tests[] = (function (): array {
    $id = 'P4_QUEUE_TOKEN_SHAPE';
    $fails = [];
    $token = mw_queue_generate_token();
    p4_assert(strlen($token) === 64, 'queue token should be 64 chars', $fails);
    p4_assert((bool) preg_match('/^[a-f0-9]{64}$/', $token), 'queue token should be lowercase hex', $fails);
    return ['id' => $id, 'status' => empty($fails) ? 'PASS' : 'FAIL', 'failures' => $fails, 'observed' => ['token' => $token]];
})();

$tests[] = (function (): array {
    $id = 'P4_ENDPOINTS_PRESENT';
    $fails = [];
    $paths = [
        __DIR__ . '/../api/mind-wars/queue_enqueue.php',
        __DIR__ . '/../api/mind-wars/queue_dequeue.php',
        __DIR__ . '/../api/mind-wars/queue_status.php',
    ];
    foreach ($paths as $path) {
        p4_assert(is_file($path), 'Missing endpoint: ' . basename($path), $fails);
    }
    return ['id' => $id, 'status' => empty($fails) ? 'PASS' : 'FAIL', 'failures' => $fails];
})();

echo json_encode(['ok' => true, 'results' => $tests], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;


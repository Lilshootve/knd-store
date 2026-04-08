<?php
declare(strict_types=1);

/**
 * Orquesta smoke tests de combate (local / CI sin MySQL).
 *
 * Por defecto solo ejecuta el batch en memoria (no requiere BD).
 *
 * Uso:
 *   php scripts/run_combat_smoke_suite.php
 *   php scripts/run_combat_smoke_suite.php --with-db
 *
 * --with-db  añade: tablas + 1 ciclo persist + batch persistido (requiere getDBConnection() OK).
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);

if (php_sapi_name() !== 'cli') {
    echo "CLI only\n";
    exit(1);
}

$withDb = in_array('--with-db', $argv, true);

$root = dirname(__DIR__);

$run = static function (string $label, array $args): int {
    $cmd = array_merge([PHP_BINARY, ...$args]);
    echo "--- {$label} ---\n";
    $descriptors = [0 => ['pipe', 'r'], 1 => STDOUT, 2 => STDERR];
    $proc = proc_open($cmd, $descriptors, $pipes, dirname(__DIR__));
    if (!is_resource($proc)) {
        fwrite(STDERR, "proc_open failed for: {$label}\n");
        return 1;
    }
    fclose($pipes[0]);
    $code = proc_close($proc);
    echo "\n";
    return $code;
};

$code = $run('squadwars_batch_smoke (memory)', [$root . '/scripts/squadwars_batch_smoke.php', '--battles=30']);
if ($code !== 0) {
    exit($code);
}

if ($withDb) {
    $code = $run('smoke_combat_persistence (schema)', [$root . '/scripts/smoke_combat_persistence.php']);
    if ($code !== 0) {
        exit($code);
    }
    $code = $run('squadwars_persistence_smoke (1 row + cleanup)', [
        $root . '/scripts/squadwars_persistence_smoke.php',
        '--cleanup',
    ]);
    if ($code !== 0) {
        exit($code);
    }
    $code = $run('squadwars_batch_persist_smoke', [
        $root . '/scripts/squadwars_batch_persist_smoke.php',
        '--battles=5',
        '--cleanup',
    ]);
    if ($code !== 0) {
        exit($code);
    }
}

echo "=== COMBAT SMOKE SUITE OK ===\n";
exit(0);

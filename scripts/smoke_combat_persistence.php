<?php
declare(strict_types=1);

/**
 * Verifica que existan las tablas de persistencia separada por modo (plan KND Combat).
 *
 * Uso:
 *   php scripts/smoke_combat_persistence.php
 *
 * Si falta alguna tabla: aplicar sql/migrations/knd_combat_mode_tables.sql en tu BD.
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);

if (php_sapi_name() !== 'cli') {
    echo "CLI only\n";
    exit(1);
}

try {
    require_once __DIR__ . '/../config/bootstrap.php';
    require_once BASE_PATH . '/includes/config.php';
} catch (Throwable $e) {
    fwrite(STDERR, '[smoke_combat_persistence] bootstrap failed: ' . $e->getMessage() . "\n");
    exit(1);
}

$expected = [
    'knd_mw1v1_battles',
    'knd_mw3v3_battles',
    'knd_squadwars_battles',
    'knd_squad_skills',
];

$pdo = getDBConnection();
if (!$pdo instanceof PDO) {
    fwrite(STDERR, "DB connection failed\n");
    exit(1);
}

$dbName = DB_NAME;
$stmt = $pdo->prepare(
    'SELECT TABLE_NAME FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
);

echo "=== COMBAT PERSISTENCE TABLES (schema: {$dbName}) ===\n";
$missing = [];
foreach ($expected as $table) {
    $stmt->execute([$dbName, $table]);
    $ok = (bool) $stmt->fetchColumn();
    echo ($ok ? 'OK  ' : 'MISS') . "  {$table}\n";
    if (!$ok) {
        $missing[] = $table;
    }
}
fflush(STDOUT);

if ($missing === []) {
    echo "\nTodas las tablas del plan estan presentes.\n";
    echo "Siguiente: probar API Squad (submit_round) o motor in-memory: php scripts/squadwars_engine_smoke.php\n";
    exit(0);
}

echo "\nFaltan tablas: " . implode(', ', $missing) . "\n";
echo "Ejecuta en MySQL el archivo: " . BASE_PATH . "/sql/migrations/knd_combat_mode_tables.sql\n";
exit(2);

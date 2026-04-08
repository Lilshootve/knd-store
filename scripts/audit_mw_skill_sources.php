<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

try {
    require_once __DIR__ . '/../config/bootstrap.php';
    require_once BASE_PATH . '/includes/config.php';
    require_once BASE_PATH . '/shared/combat/SkillSourceRouter.php';
} catch (Throwable $e) {
    fwrite(STDERR, '[audit_mw_skill_sources] bootstrap failed: ' . $e->getMessage() . "\n");
    exit(1);
}

if (php_sapi_name() !== 'cli') {
    echo "CLI only\n";
    exit(1);
}

/**
 * Usage:
 *   php scripts/audit_mw_skill_sources.php
 *   php scripts/audit_mw_skill_sources.php --limit=30
 */
$limit = 30;
foreach ($argv as $arg) {
    if (strpos((string) $arg, '--limit=') === 0) {
        $limit = max(1, (int) substr((string) $arg, 8));
    }
}

$pdo = getDBConnection();
if (!$pdo instanceof PDO) {
    fwrite(STDERR, "DB connection failed\n");
    exit(1);
}

$rows = $pdo->query(
    "SELECT a.id AS avatar_id, a.name
     FROM mw_avatars a
     ORDER BY a.id ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$summary = [
    'avatars_total' => 0,
    'json_complete' => 0,
    'legacy_complete' => 0,
    'both_complete' => 0,
    'json_only' => 0,
    'legacy_only' => 0,
    'none_complete' => 0,
];

$issues = [];

foreach ($rows as $r) {
    $avatarId = (int) ($r['avatar_id'] ?? 0);
    $name = (string) ($r['name'] ?? ('avatar_' . $avatarId));
    if ($avatarId <= 0) {
        continue;
    }
    $summary['avatars_total']++;

    $bundle = knd_skill_router_fetch_avatar_bundle($pdo, $avatarId);
    $jsonOk = !empty($bundle['json_complete']);
    $legacyOk = !empty($bundle['legacy_complete']);

    if ($jsonOk) {
        $summary['json_complete']++;
    }
    if ($legacyOk) {
        $summary['legacy_complete']++;
    }

    if ($jsonOk && $legacyOk) {
        $summary['both_complete']++;
    } elseif ($jsonOk) {
        $summary['json_only']++;
        $issues[] = "{$avatarId} {$name}: json_only (legacy incomplete)";
    } elseif ($legacyOk) {
        $summary['legacy_only']++;
        $issues[] = "{$avatarId} {$name}: legacy_only (json incomplete)";
    } else {
        $summary['none_complete']++;
        $issues[] = "{$avatarId} {$name}: missing_both";
    }
}

echo "=== MW SKILL SOURCE AUDIT ===\n";
echo "avatars_total:   {$summary['avatars_total']}\n";
echo "json_complete:   {$summary['json_complete']}\n";
echo "legacy_complete: {$summary['legacy_complete']}\n";
echo "both_complete:   {$summary['both_complete']}\n";
echo "json_only:       {$summary['json_only']}\n";
echo "legacy_only:     {$summary['legacy_only']}\n";
echo "none_complete:   {$summary['none_complete']}\n";
fflush(STDOUT);

if ($issues === []) {
    echo "\nNo migration blockers found.\n";
    echo "exit_code: 0\n";
    fflush(STDOUT);
    exit(0);
}

echo "\nSample issues (limit {$limit}):\n";
$shown = 0;
foreach ($issues as $issue) {
    echo " - {$issue}\n";
    $shown++;
    if ($shown >= $limit) {
        break;
    }
}
fflush(STDOUT);

// Non-zero exit if there are inconsistencies, useful for CI checks.
echo "exit_code: 2 (hay avatares sin paridad legacy/json; revisa la lista arriba)\n";
fflush(STDOUT);
exit(2);

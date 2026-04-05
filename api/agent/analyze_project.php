<?php
/**
 * KND Agent — Project Analyzer
 * GET/POST /api/agent/analyze_project.php
 *
 * Scans the KND Store project and returns:
 *   - PHP files (relative paths)
 *   - API endpoints (/api/**)
 *   - Database tables (via PDO SHOW TABLES)
 *
 * Protected by KND_WORKER_TOKEN (Authorization: Bearer <token>)
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/env.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/json.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// ── Auth ──────────────────────────────────────────────────────────────────────
$token = getenv('KND_WORKER_TOKEN') ?: '';
$provided = '';

$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (str_starts_with($auth, 'Bearer ')) {
    $provided = substr($auth, 7);
} elseif (!empty($_GET['token'])) {
    $provided = $_GET['token'];
} elseif (!empty($_POST['token'])) {
    $provided = $_POST['token'];
}

if ($token !== '' && !hash_equals($token, $provided)) {
    json_error('UNAUTHORIZED', 'Invalid or missing token.', 401);
}

// ── Scan files ────────────────────────────────────────────────────────────────
$root      = defined('KND_ROOT') ? KND_ROOT : dirname(__DIR__, 2);
$files     = [];
$endpoints = [];

$skip_dirs = ['.git', 'node_modules', 'vendor', '.aider.tags.cache.v4'];

$iter = new RecursiveIteratorIterator(
    new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        function (SplFileInfo $fi) use ($skip_dirs): bool {
            if ($fi->isDir()) {
                return !in_array($fi->getFilename(), $skip_dirs, true);
            }
            return true;
        }
    )
);

foreach ($iter as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }

    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));

    $files[] = $relative;

    // Collect API endpoints: any .php file under api/
    if (str_starts_with($relative, 'api/')) {
        $endpoint = '/' . preg_replace('/\.php$/', '', $relative);
        $endpoints[] = $endpoint;
    }
}

sort($files);
sort($endpoints);

// ── DB tables ─────────────────────────────────────────────────────────────────
$db_tables = [];

$pdo = getDBConnection();
if ($pdo) {
    try {
        $stmt = $pdo->query('SHOW TABLES');
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $db_tables[] = $row[0];
        }
        sort($db_tables);
    } catch (Throwable $e) {
        // Non-fatal — return empty list
        error_log('[agent/analyze] SHOW TABLES failed: ' . $e->getMessage());
    }
}

json_success([
    'files'     => $files,
    'endpoints' => $endpoints,
    'db_tables' => $db_tables,
    'stats'     => [
        'total_php_files'    => count($files),
        'total_endpoints'    => count($endpoints),
        'total_db_tables'    => count($db_tables),
        'scanned_at'         => date('c'),
    ],
]);

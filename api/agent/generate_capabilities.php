<?php
/**
 * KND Agent — Capability Engine
 * POST /api/agent/generate_capabilities.php
 *
 * Receives analyzer output (or auto-calls the analyzer internally)
 * and returns a structured capability map for the AI agent system.
 *
 * Body (JSON, optional — if omitted the analyzer is called internally):
 *   { "files": [], "endpoints": [], "db_tables": [] }
 *
 * Protected by KND_WORKER_TOKEN
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/env.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/json.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// ── Auth ──────────────────────────────────────────────────────────────────────
$token    = trim((string) (knd_env('KND_WORKER_TOKEN') ?? ''));
$provided = '';

$auth = knd_request_authorization_header();
if (str_starts_with($auth, 'Bearer ')) {
    $provided = substr($auth, 7);
} elseif (!empty($_GET['token'])) {
    $provided = $_GET['token'];
}

if ($token !== '' && !hash_equals($token, $provided)) {
    json_error('UNAUTHORIZED', 'Invalid or missing token.', 401);
}

// ── Load analyzer data ────────────────────────────────────────────────────────
$raw = file_get_contents('php://input');
$input = ($raw !== false && $raw !== '') ? json_decode($raw, true) : null;

if (!is_array($input) || empty($input['endpoints'])) {
    // Auto-run analyzer
    $analyzer = require_analyzer_data();
    if ($analyzer === null) {
        json_error('ANALYZER_FAILED', 'Could not load project analysis data.', 500);
    }
    $input = $analyzer;
}

function require_analyzer_data(): ?array
{
    $root      = defined('KND_ROOT') ? KND_ROOT : dirname(__DIR__, 2);
    $files     = [];
    $endpoints = [];
    $db_tables = [];

    $skip_dirs = ['.git', 'node_modules', 'vendor', '.aider.tags.cache.v4'];
    $iter = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            function (SplFileInfo $fi) use ($skip_dirs): bool {
                if ($fi->isDir()) return !in_array($fi->getFilename(), $skip_dirs, true);
                return true;
            }
        )
    );

    foreach ($iter as $file) {
        if ($file->getExtension() !== 'php') continue;
        $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        $files[] = $rel;
        if (str_starts_with($rel, 'api/')) {
            $endpoints[] = '/' . preg_replace('/\.php$/', '', $rel);
        }
    }

    $pdo = getDBConnection();
    if ($pdo) {
        try {
            $stmt = $pdo->query('SHOW TABLES');
            while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                $db_tables[] = $row[0];
            }
        } catch (Throwable $e) { /* ignore */ }
    }

    return ['files' => $files, 'endpoints' => $endpoints, 'db_tables' => $db_tables];
}

// ── Build capabilities ────────────────────────────────────────────────────────
$endpoints  = (array)($input['endpoints']  ?? []);
$db_tables  = (array)($input['db_tables']  ?? []);
$files      = (array)($input['files']      ?? []);

// Endpoint pattern analysis
$has_auth       = (bool) array_filter($endpoints, fn($e) => str_contains($e, '/auth/'));
$has_ai         = (bool) array_filter($endpoints, fn($e) => str_contains($e, '/ai/') || str_contains($e, '/labs/') || str_contains($e, '/iris'));
$has_games      = (bool) array_filter($endpoints, fn($e) => str_contains($e, '/mind-wars') || str_contains($e, '/knowledge-duel') || str_contains($e, '/deathroll'));
$has_payments   = (bool) array_filter($endpoints, fn($e) => str_contains($e, '/paypal') || str_contains($e, '/support-credits') || str_contains($e, '/checkout'));
$has_avatars    = (bool) array_filter($endpoints, fn($e) => str_contains($e, '/avatar'));
$has_queue      = (bool) array_filter($endpoints, fn($e) => str_contains($e, '/queue') || str_contains($e, '/worker'));
$has_admin      = (bool) array_filter($endpoints, fn($e) => str_contains($e, '/admin'));
$has_3d         = (bool) array_filter($endpoints, fn($e) => str_contains($e, '/3d-lab') || str_contains($e, '/triposr') || str_contains($e, '/instantmesh'));
$has_presence   = (bool) array_filter($endpoints, fn($e) => str_contains($e, '/presence'));

// DB capabilities
$can_read_data   = count($db_tables) > 0;
$can_update_data = $can_read_data;
$can_manage_files = count(array_filter($files, fn($f) => str_contains($f, '/labs/'))) > 0;

// Build endpoint groups
$endpoint_groups = [];
foreach ($endpoints as $ep) {
    $parts = explode('/', trim($ep, '/'));
    $group = $parts[1] ?? 'root';
    $endpoint_groups[$group][] = $ep;
}
ksort($endpoint_groups);

$capabilities = [
    // Core data
    'can_read_data'             => $can_read_data,
    'can_update_data'           => $can_update_data,
    'can_delete_data'           => $can_update_data,   // tables present = write access possible

    // File management
    'can_manage_files'          => $can_manage_files,
    'can_upload_files'          => $can_manage_files,
    'can_generate_images'       => $has_ai,
    'can_generate_3d'           => $has_3d,

    // Auth & users
    'can_authenticate_users'    => $has_auth,
    'can_manage_users'          => $has_admin,

    // Games
    'can_run_game_mind_wars'    => $has_games,
    'can_run_game_knowledge_duel' => $has_games,
    'can_run_game_deathroll'    => $has_games,

    // AI
    'can_call_ai_image'         => $has_ai,
    'can_call_iris'             => (bool) array_filter($endpoints, fn($e) => str_contains($e, '/iris')),
    'can_call_ollama'           => (bool) array_filter($endpoints, fn($e) => str_contains($e, '/ollama')),

    // Commerce
    'can_process_payments'      => $has_payments,
    'can_manage_avatars'        => $has_avatars,

    // Infrastructure
    'can_manage_queue'          => $has_queue,
    'can_track_presence'        => $has_presence,
    'can_access_admin'          => $has_admin,
];

$summary = [
    'total_capabilities'    => count($capabilities),
    'enabled_capabilities'  => count(array_filter($capabilities)),
    'disabled_capabilities' => count(array_filter($capabilities, fn($v) => !$v)),
    'endpoint_groups'       => array_keys($endpoint_groups),
    'endpoint_group_counts' => array_map('count', $endpoint_groups),
    'generated_at'          => date('c'),
];

json_success([
    'capabilities'   => $capabilities,
    'endpoint_groups'=> $endpoint_groups,
    'summary'        => $summary,
]);

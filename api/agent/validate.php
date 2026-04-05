<?php
/**
 * KND Agent — Validation Layer
 * POST /api/agent/validate.php
 *
 * Validates a proposed tool call BEFORE execution.
 * Returns { ok: true, data: { safe: bool, issues: [], warnings: [] } }
 *
 * Body (JSON):
 *   { "tool": "db_execute", "input": { "sql": "...", "params": [] } }
 *
 * Also exposed as an internal PHP function validate_tool_call() for use by execute.php.
 *
 * Protected by KND_WORKER_TOKEN
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/env.php';
require_once __DIR__ . '/../../includes/json.php';

// ──────────────────────────────────────────────────────────────────────────────
// Core validation function (reusable by execute.php)
// ──────────────────────────────────────────────────────────────────────────────

function validate_tool_call(string $tool, array $input): array
{
    $issues   = [];
    $warnings = [];

    switch ($tool) {

        // ── db_query ──────────────────────────────────────────────────────────
        case 'db_query':
            $sql = strtoupper(trim($input['sql'] ?? ''));

            if (!str_starts_with($sql, 'SELECT')) {
                $issues[] = 'db_query only allows SELECT statements.';
            }

            foreach (['INSERT', 'UPDATE', 'DELETE', 'DROP', 'TRUNCATE', 'ALTER', 'CREATE', 'REPLACE'] as $kw) {
                if (str_contains($sql, $kw)) {
                    $issues[] = "Keyword '{$kw}' is not allowed in db_query.";
                }
            }

            // Warn if no LIMIT
            if (!str_contains($sql, 'LIMIT')) {
                $warnings[] = 'No LIMIT clause — a maximum of 500 rows will be enforced.';
            }
            break;

        // ── db_execute ────────────────────────────────────────────────────────
        case 'db_execute':
            $sql     = strtoupper(trim($input['sql'] ?? ''));
            $sqlRaw  = trim($input['sql'] ?? '');

            // Hard blocks
            if (preg_match('/\b(DROP\s+(TABLE|DATABASE|INDEX|VIEW)|TRUNCATE)\b/i', $sqlRaw)) {
                $issues[] = 'DROP TABLE, DROP DATABASE and TRUNCATE are unconditionally blocked.';
            }

            // DELETE without WHERE
            if (preg_match('/^\s*DELETE\s+FROM\s+\w+\s*$/i', $sqlRaw)) {
                $issues[] = 'DELETE without a WHERE clause is blocked (would delete entire table).';
            }

            // ALTER requires confirmation
            if (preg_match('/\bALTER\s+TABLE\b/i', $sqlRaw)) {
                if (empty($input['confirm_alter'])) {
                    $issues[] = 'ALTER TABLE requires confirm_alter: true in the input payload.';
                } else {
                    $warnings[] = 'ALTER TABLE operation — confirmed by caller.';
                }
            }

            // Warn about bulk updates (no WHERE)
            if (preg_match('/^\s*UPDATE\s+\w+\s+SET\b/i', $sqlRaw) && !preg_match('/\bWHERE\b/i', $sqlRaw)) {
                $warnings[] = 'UPDATE without WHERE clause will affect all rows.';
            }

            // Block raw SELECT in db_execute (use db_query instead)
            if (str_starts_with($sql, 'SELECT')) {
                $warnings[] = 'SELECT statements should use the db_query tool, not db_execute.';
            }
            break;

        // ── file_manager ──────────────────────────────────────────────────────
        case 'file_manager':
            $action  = $input['action'] ?? '';
            $path    = $input['path']   ?? '';

            // Path traversal
            if (str_contains($path, '..')) {
                $issues[] = 'Path traversal (../) is not allowed.';
            }

            // Allowed base paths
            $allowed_bases = ['uploads/', 'storage/generated/', 'storage/labs/', 'storage/tmp/'];
            $path_ok = false;
            foreach ($allowed_bases as $base) {
                if (str_starts_with($path, $base)) {
                    $path_ok = true;
                    break;
                }
            }
            if (!$path_ok && $path !== '') {
                $issues[] = "Path '{$path}' is outside the allowed base directories: " . implode(', ', $allowed_bases);
            }

            // Block executable writes
            if ($action === 'write') {
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                $blocked_exts = ['php', 'phtml', 'phar', 'sh', 'bash', 'exe', 'py', 'rb'];
                if (in_array($ext, $blocked_exts, true)) {
                    $issues[] = "Writing files with extension '.{$ext}' is blocked.";
                }
            }

            // Overwrite protection
            if ($action === 'write' && empty($input['confirm_overwrite'])) {
                $warnings[] = 'If the file already exists it will NOT be overwritten unless confirm_overwrite: true is set.';
            }

            // Delete warning
            if ($action === 'delete') {
                $warnings[] = 'Delete action will permanently remove the file — this is logged.';
            }
            break;

        // ── agent tools (kael_dispatch, iris_chat) ────────────────────────────
        case 'kael_dispatch':
            if (empty($input['task'])) {
                $issues[] = 'task is required for kael_dispatch.';
            }
            break;

        case 'iris_chat':
            if (empty($input['message'])) {
                $issues[] = 'message is required for iris_chat.';
            }
            if (isset($input['message']) && strlen($input['message']) > 16000) {
                $issues[] = 'message exceeds maximum length of 16 000 characters.';
            }
            break;

        default:
            $issues[] = "Unknown tool: '{$tool}'.";
            break;
    }

    return [
        'safe'     => count($issues) === 0,
        'tool'     => $tool,
        'issues'   => $issues,
        'warnings' => $warnings,
    ];
}

// ──────────────────────────────────────────────────────────────────────────────
// HTTP endpoint
// ──────────────────────────────────────────────────────────────────────────────

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Auth
$token    = getenv('KND_WORKER_TOKEN') ?: '';
$provided = '';
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (str_starts_with($auth, 'Bearer ')) {
    $provided = substr($auth, 7);
} elseif (!empty($_GET['token'])) {
    $provided = $_GET['token'];
}
if ($token !== '' && !hash_equals($token, $provided)) {
    json_error('UNAUTHORIZED', 'Invalid or missing token.', 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('METHOD_NOT_ALLOWED', 'POST only.', 405);
}

$raw = file_get_contents('php://input');
if (!$raw) {
    json_error('EMPTY_BODY', 'Request body is required.', 400);
}

$body = json_decode($raw, true);
if (!is_array($body) || !isset($body['tool'])) {
    json_error('INVALID_JSON', 'Expected JSON with "tool" and "input" fields.', 400);
}

$tool  = (string)($body['tool']  ?? '');
$input = (array) ($body['input'] ?? []);

$result = validate_tool_call($tool, $input);

json_success($result);

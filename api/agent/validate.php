<?php
/**
 * KND Agent — Validation Layer
 *
 * Two modes:
 *   A) Included by execute.php  → only validate_tool_call() runs
 *   B) Called directly via HTTP → full JSON API
 *
 * POST /api/agent/validate.php
 * Body: { "tool": "db_execute", "input": { "sql": "...", "params": [] } }
 *
 * Protected by KND_WORKER_TOKEN
 */

declare(strict_types=1);

// ──────────────────────────────────────────────────────────────────────────────
// validate_tool_call() — pure function, no side-effects, safe to include
// ──────────────────────────────────────────────────────────────────────────────

if (!function_exists('validate_tool_call')) {

function validate_tool_call(string $tool, array $input, ?string $businessType = null): array
{
    $issues   = [];
    $warnings = [];

    switch ($tool) {

        // ── db_query ──────────────────────────────────────────────────────────
        case 'db_query':
            $sql    = trim($input['sql'] ?? '');
            $sqlUp  = strtoupper($sql);

            if ($sql === '') {
                $issues[] = 'sql is required.';
                break;
            }

            // Must start with SELECT (after stripping comments)
            $stripped = preg_replace('/\/\*.*?\*\//s', '', $sql);
            $stripped = preg_replace('/--[^\n]*/', '', $stripped);
            if (!preg_match('/^\s*SELECT\b/i', $stripped)) {
                $issues[] = 'db_query only allows SELECT statements.';
            }

            // Disallow any mutation keywords even inside subqueries
            foreach (['INSERT', 'UPDATE', 'DELETE', 'DROP', 'TRUNCATE', 'ALTER', 'CREATE', 'REPLACE'] as $kw) {
                if (preg_match('/\b' . $kw . '\b/i', $stripped)) {
                    $issues[] = "Keyword '{$kw}' is not permitted in db_query.";
                }
            }

            if (!preg_match('/\bLIMIT\b/i', $sql)) {
                $warnings[] = 'No LIMIT clause — 500 rows maximum will be enforced automatically.';
            }
            break;

        // ── db_execute ────────────────────────────────────────────────────────
        case 'db_execute':
            $sql    = trim($input['sql'] ?? '');
            $sqlUp  = strtoupper($sql);

            if ($sql === '') {
                $issues[] = 'sql is required.';
                break;
            }

            // Absolute blocks
            if (preg_match('/\b(DROP\s+(TABLE|DATABASE|INDEX|VIEW|PROCEDURE|FUNCTION)|TRUNCATE)\b/i', $sql)) {
                $issues[] = 'DROP and TRUNCATE operations are unconditionally blocked.';
            }

            // DELETE without WHERE = full table wipe
            if (preg_match('/^\s*DELETE\s+FROM\s+\S+\s*$/i', $sql)) {
                $issues[] = 'DELETE without WHERE clause is blocked — it would delete all rows.';
            }

            // ALTER needs explicit confirmation
            if (preg_match('/\bALTER\s+TABLE\b/i', $sql)) {
                if (empty($input['confirm_alter'])) {
                    $issues[] = 'ALTER TABLE requires confirm_alter: true in the input.';
                } else {
                    $warnings[] = 'ALTER TABLE confirmed by caller.';
                }
            }

            // Bulk UPDATE without WHERE
            if (preg_match('/^\s*UPDATE\s+\S+\s+SET\b/i', $sql) && !preg_match('/\bWHERE\b/i', $sql)) {
                $warnings[] = 'UPDATE without WHERE will affect every row in the table.';
            }

            // SELECT belongs in db_query
            if (preg_match('/^\s*SELECT\b/i', $sql)) {
                $warnings[] = 'Use db_query (not db_execute) for SELECT statements.';
            }
            break;

        // ── file_manager ──────────────────────────────────────────────────────
        case 'file_manager':
            $action = $input['action'] ?? '';
            $path   = $input['path']   ?? '';

            $allowed_actions = ['read', 'write', 'delete', 'list', 'exists'];
            if (!in_array($action, $allowed_actions, true)) {
                $issues[] = "Unknown action '{$action}'. Allowed: " . implode(', ', $allowed_actions);
            }

            if ($path === '' && $action !== '') {
                $issues[] = 'path is required.';
            }

            // Path traversal
            $normalised = str_replace('\\', '/', $path);
            if (str_contains($normalised, '..')) {
                $issues[] = 'Path traversal sequences (../) are not allowed.';
            }

            // Must be inside an allowed base
            $allowed_bases = ['uploads/', 'storage/generated/', 'storage/labs/', 'storage/tmp/'];
            $path_ok = false;
            foreach ($allowed_bases as $base) {
                if (str_starts_with($normalised, $base)) {
                    $path_ok = true;
                    break;
                }
            }
            if (!$path_ok && $path !== '') {
                $issues[] = "Path must start with one of: " . implode(', ', $allowed_bases);
            }

            // Block executable file writes
            if ($action === 'write') {
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                $blocked = ['php', 'phtml', 'phar', 'sh', 'bash', 'exe', 'bat', 'cmd', 'py', 'rb', 'pl'];
                if (in_array($ext, $blocked, true)) {
                    $issues[] = "Writing '.{$ext}' files is blocked for security.";
                }
                if (empty($input['content'])) {
                    $warnings[] = 'content is empty — an empty file will be written.';
                }
                if (!empty($input['confirm_overwrite'])) {
                    $warnings[] = 'Overwrite confirmed — existing file will be replaced.';
                }
            }

            if ($action === 'delete') {
                $warnings[] = 'Delete is permanent and logged.';
            }
            break;

        // ── kael_dispatch ─────────────────────────────────────────────────────
        case 'kael_dispatch':
            if (empty($input['task'])) {
                $issues[] = 'task is required for kael_dispatch.';
            }
            break;

        // ── iris_chat ─────────────────────────────────────────────────────────
        case 'iris_chat':
            if (empty($input['message'])) {
                $issues[] = 'message is required for iris_chat.';
            } elseif (strlen($input['message']) > 16000) {
                $issues[] = 'message exceeds the 16 000 character limit.';
            }
            break;

        default:
            if ($businessType === 'retail') {
                if (!function_exists('validate_retail_tool_call')) {
                    require_once __DIR__ . '/../../retail/validate.php';
                }
                if (function_exists('validate_retail_tool_call')) {
                    return validate_retail_tool_call($tool, $input);
                }
            }
            $issues[] = "Unknown tool: '{$tool}'. Available: db_query, db_execute, file_manager, kael_dispatch, iris_chat.";
            break;
    }

    return [
        'safe'     => count($issues) === 0,
        'tool'     => $tool,
        'issues'   => $issues,
        'warnings' => $warnings,
    ];
}

} // end if (!function_exists)

// ──────────────────────────────────────────────────────────────────────────────
// HTTP handler — only runs when this file is called DIRECTLY (not included)
// ──────────────────────────────────────────────────────────────────────────────

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {

    require_once __DIR__ . '/../../includes/env.php';
    require_once __DIR__ . '/../../includes/json.php';

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    // Auth
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

    $businessType = null;
    if (isset($body['business_type']) && is_string($body['business_type'])
        && preg_match('/^[a-z0-9_]+$/', $body['business_type'])) {
        $businessType = $body['business_type'];
    }

    $result = validate_tool_call(
        (string)($body['tool']  ?? ''),
        (array) ($body['input'] ?? []),
        $businessType
    );

    json_success($result);
}

<?php
/**
 * KND Agent — Execution Endpoint
 * POST /api/agent/execute.php
 *
 * Full pipeline: receive → validate → (simulate | execute) → log → respond
 *
 * Body (JSON):
 * {
 *   "tool"    : "db_query",
 *   "input"   : { "sql": "SELECT ...", "params": [] },
 *   "simulate": false          // optional — dry-run without executing
 * }
 *
 * OR natural-language shortcut (auto-mapped to a tool call):
 * {
 *   "message" : "dame los últimos 10 usuarios"
 * }
 *
 * Response envelope:
 * {
 *   "status"   : "success | error | blocked | simulated",
 *   "tool"     : "db_query",
 *   "data"     : { ... },
 *   "error"    : null | "message",
 *   "warnings" : [],
 *   "simulate" : false,
 *   "logged"   : true
 * }
 *
 * Protected by KND_WORKER_TOKEN (Bearer header or ?token= query param)
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/env.php';
require_once __DIR__ . '/../../includes/config.php';

// validate_tool_call() lives here — guarded so HTTP block won't fire on include
require_once __DIR__ . '/validate.php';

// intent_map() lives here
require_once __DIR__ . '/intent_mapper.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

// ── Auth ──────────────────────────────────────────────────────────────────────
$_knd_token    = getenv('KND_WORKER_TOKEN') ?: '';
$_knd_provided = '';
$_knd_auth     = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

if (str_starts_with($_knd_auth, 'Bearer ')) {
    $_knd_provided = substr($_knd_auth, 7);
} elseif (!empty($_GET['token'])) {
    $_knd_provided = $_GET['token'];
}

if ($_knd_token !== '' && !hash_equals($_knd_token, $_knd_provided)) {
    agent_respond('error', '', null, 'UNAUTHORIZED: Invalid or missing token.', [], false, 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    agent_respond('error', '', null, 'METHOD_NOT_ALLOWED: POST only.', [], false, 405);
}

// ── Parse body ────────────────────────────────────────────────────────────────
$_raw = file_get_contents('php://input');
if (!$_raw || trim($_raw) === '') {
    agent_respond('error', '', null, 'EMPTY_BODY: Request body is required.', [], false, 400);
}

$body = json_decode($_raw, true);
if (!is_array($body)) {
    agent_respond('error', '', null, 'INVALID_JSON: Body must be a JSON object.', [], false, 400);
}

// ── Natural language → tool call mapping ──────────────────────────────────────
if (isset($body['message']) && !isset($body['tool'])) {
    $mapped = intent_map((string)$body['message']);
    if ($mapped === null) {
        agent_respond(
            'error', '', null,
            'INTENT_UNKNOWN: Cannot map this message to a known tool. '
            . 'Use {"tool":"...", "input":{...}} for explicit calls.',
            [], false, 422
        );
    }
    $body['tool']  = $mapped['tool'];
    $body['input'] = $mapped['input'];
    $body['_intent_mapped'] = true;
}

$tool     = (string)($body['tool']    ?? '');
$input    = (array) ($body['input']   ?? []);
$simulate = (bool)  ($body['simulate'] ?? false);

if ($tool === '') {
    agent_respond('error', '', null, 'MISSING_TOOL: "tool" field is required.', [], $simulate, 400);
}

// ── Validate ──────────────────────────────────────────────────────────────────
$validation = validate_tool_call($tool, $input);

if (!$validation['safe']) {
    $msg = 'VALIDATION_FAILED: ' . implode(' | ', $validation['issues']);
    agent_log_entry($tool, $input, null, 'blocked');
    agent_respond('blocked', $tool, null, $msg, $validation['warnings'], $simulate, 422);
}

// ── Simulate mode (dry run) ───────────────────────────────────────────────────
if ($simulate) {
    $preview = build_simulate_preview($tool, $input);
    agent_log_entry($tool, $input, $preview, 'simulated');
    agent_respond('simulated', $tool, $preview, null, $validation['warnings'], true);
}

// ── Execute ───────────────────────────────────────────────────────────────────
$data   = null;
$status = 'success';
$error  = null;

try {
    switch ($tool) {

        case 'db_query':
            $data = run_db_query($input);
            break;

        case 'db_execute':
            $data = run_db_execute($input);
            break;

        case 'file_manager':
            $data = run_file_manager($input);
            break;

        case 'kael_dispatch':
            $data = run_kael_dispatch($input);
            break;

        case 'iris_chat':
            $data = run_iris_chat($input);
            break;

        default:
            $status = 'error';
            $error  = "UNKNOWN_TOOL: '{$tool}' is not registered.";
            break;
    }
} catch (Throwable $e) {
    $status = 'error';
    $error  = $e->getMessage();
    error_log('[knd/agent/execute] ' . $tool . ' exception: ' . $e->getMessage());
}

// ── Log ───────────────────────────────────────────────────────────────────────
agent_log_entry($tool, $input, $data, $status);

// ── Respond ───────────────────────────────────────────────────────────────────
agent_respond($status, $tool, $data, $error, $validation['warnings'], false);


// ══════════════════════════════════════════════════════════════════════════════
// TOOL IMPLEMENTATIONS
// ══════════════════════════════════════════════════════════════════════════════

/**
 * db_query — read-only SELECT
 */
function run_db_query(array $input): array
{
    $pdo = getDBConnection();
    if (!$pdo) throw new RuntimeException('DB connection failed.');

    $sql    = trim($input['sql']);
    $params = (array)($input['params'] ?? []);

    // Auto-inject LIMIT if missing (safety cap)
    if (!preg_match('/\bLIMIT\b/i', $sql)) {
        $cap = min(500, (int)($input['limit'] ?? 100));
        $sql .= ' LIMIT ' . $cap;
    }

    $t0   = microtime(true);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $ms   = round((microtime(true) - $t0) * 1000, 2);

    // Truncate if payload exceeds 1 MB
    $truncated = false;
    if (strlen(json_encode($rows)) > 1_048_576) {
        $rows      = array_slice($rows, 0, 100);
        $truncated = true;
    }

    return [
        'rows'         => $rows,
        'row_count'    => count($rows),
        'truncated'    => $truncated,
        'execution_ms' => $ms,
    ];
}

/**
 * db_execute — INSERT / UPDATE / DELETE (guarded)
 */
function run_db_execute(array $input): array
{
    $pdo = getDBConnection();
    if (!$pdo) throw new RuntimeException('DB connection failed.');

    $sql    = trim($input['sql']);
    $params = (array)($input['params'] ?? []);

    $t0   = microtime(true);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $ms   = round((microtime(true) - $t0) * 1000, 2);

    return [
        'affected_rows'  => $stmt->rowCount(),
        'last_insert_id' => (int)$pdo->lastInsertId() ?: null,
        'execution_ms'   => $ms,
    ];
}

/**
 * file_manager — read / write / delete / list / exists
 */
function run_file_manager(array $input): array
{
    $action = $input['action'] ?? '';
    $path   = str_replace('\\', '/', $input['path'] ?? '');

    // Resolve absolute path safely
    $root = rtrim(
        defined('KND_ROOT') ? KND_ROOT : dirname(__DIR__, 2),
        '/\\'
    );
    $full = $root . '/' . ltrim($path, '/');

    // Normalise without requiring the path to exist yet
    $full = _fm_normalise_path($full);

    // Guard: must stay inside root
    if (!str_starts_with($full, $root . '/') && $full !== $root) {
        throw new RuntimeException('Resolved path escapes project root.');
    }

    switch ($action) {

        case 'read':
            if (!is_file($full)) throw new RuntimeException("File not found: {$path}");
            $content = file_get_contents($full);
            if ($content === false) throw new RuntimeException("Cannot read: {$path}");
            return [
                'path'    => $path,
                'content' => $content,
                'size'    => strlen($content),
            ];

        case 'write':
            if (is_file($full) && empty($input['confirm_overwrite'])) {
                throw new RuntimeException(
                    "File already exists. Pass confirm_overwrite: true to overwrite."
                );
            }
            $encoding = $input['encoding'] ?? 'text';
            $content  = $input['content']  ?? '';

            if ($encoding === 'base64') {
                $decoded = base64_decode($content, true);
                if ($decoded === false) throw new RuntimeException('Invalid base64 content.');
                $content = $decoded;
            }

            if (strlen($content) > 10_485_760) {
                throw new RuntimeException('Content exceeds 10 MB write limit.');
            }

            $dir = dirname($full);
            if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
                throw new RuntimeException("Cannot create directory: " . dirname($path));
            }

            if (file_put_contents($full, $content) === false) {
                throw new RuntimeException("Write failed: {$path}");
            }

            return [
                'path'    => $path,
                'written' => true,
                'size'    => strlen($content),
            ];

        case 'delete':
            if (!is_file($full)) throw new RuntimeException("File not found: {$path}");
            if (!unlink($full)) throw new RuntimeException("Delete failed: {$path}");
            return ['path' => $path, 'deleted' => true];

        case 'exists':
            return ['path' => $path, 'exists' => file_exists($full)];

        case 'list':
            if (!is_dir($full)) throw new RuntimeException("Directory not found: {$path}");
            $entries = [];
            foreach (scandir($full) as $name) {
                if ($name === '.' || $name === '..') continue;
                $item = $full . '/' . $name;
                $entries[] = [
                    'name'     => $name,
                    'type'     => is_dir($item) ? 'dir' : 'file',
                    'size'     => is_file($item) ? filesize($item) : null,
                    'modified' => date('c', filemtime($item)),
                ];
            }
            return ['path' => $path, 'entries' => $entries, 'count' => count($entries)];

        default:
            throw new RuntimeException("Unknown file_manager action: '{$action}'");
    }
}

/** Normalise a path without requiring it to exist (realpath() would return false) */
function _fm_normalise_path(string $path): string
{
    $parts  = explode('/', str_replace('\\', '/', $path));
    $result = [];
    foreach ($parts as $part) {
        if ($part === '' || $part === '.') continue;
        if ($part === '..') { array_pop($result); continue; }
        $result[] = $part;
    }
    return '/' . implode('/', $result);
}

/**
 * kael_dispatch — POST task to Kael orchestrator
 */
function run_kael_dispatch(array $input): array
{
    $base     = rtrim(getenv('KND_API_BASE') ?: 'https://kndstore.com', '/');
    $kael_url = $base . '/api/kael/gate';

    // Prefer local Next.js agent server when available
    $agents = getenv('IRIS_AGENTS_CHAT_URL') ?: '';
    if ($agents) {
        $kael_url = preg_replace('#/api/iris(/chat)?$#', '/api/kael/gate', $agents);
    }

    $payload = json_encode([
        'task'     => $input['task'],
        'context'  => $input['context']  ?? [],
        'priority' => $input['priority'] ?? 'normal',
    ]);

    [$body, $code, $err] = _http_post($kael_url, $payload, [
        'Content-Type: application/json',
        'X-API-Key: ' . (getenv('KND_WORKER_TOKEN') ?: ''),
    ], 30);

    if ($err) throw new RuntimeException("Kael dispatch error: {$err}");

    return [
        'http_status' => $code,
        'response'    => json_decode($body, true) ?? ['raw' => substr($body, 0, 500)],
    ];
}

/**
 * iris_chat — POST message to Iris AI
 */
function run_iris_chat(array $input): array
{
    $base     = rtrim(getenv('KND_API_BASE') ?: 'https://kndstore.com', '/');
    $iris_url = $base . '/api/iris.php';

    $payload = json_encode([
        'message'              => $input['message'],
        'conversation_history' => $input['conversation_history'] ?? [],
    ]);

    [$body, $code, $err] = _http_post($iris_url, $payload, [
        'Content-Type: application/json',
    ], 60);

    if ($err) throw new RuntimeException("Iris chat error: {$err}");

    return [
        'http_status' => $code,
        'response'    => json_decode($body, true) ?? ['raw' => substr($body, 0, 500)],
    ];
}

/** Thin cURL wrapper: returns [body, http_code, error_string] */
function _http_post(string $url, string $payload, array $headers, int $timeout): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    return [$body ?: '', $code, $err];
}


// ══════════════════════════════════════════════════════════════════════════════
// SIMULATE PREVIEW
// ══════════════════════════════════════════════════════════════════════════════

function build_simulate_preview(string $tool, array $input): array
{
    $preview = [
        'tool'        => $tool,
        'input'       => $input,
        'would_execute' => true,
        'note'        => 'Simulation mode — no changes were made to the database or filesystem.',
    ];

    switch ($tool) {
        case 'db_query':
            $sql = $input['sql'] ?? '';
            if (!preg_match('/\bLIMIT\b/i', $sql)) {
                $sql .= ' LIMIT ' . min(500, (int)($input['limit'] ?? 100));
            }
            $preview['resolved_sql'] = $sql;
            $preview['params']       = $input['params'] ?? [];
            break;

        case 'db_execute':
            $preview['resolved_sql'] = $input['sql'] ?? '';
            $preview['params']       = $input['params'] ?? [];
            break;

        case 'file_manager':
            $preview['action'] = $input['action'] ?? '';
            $preview['path']   = $input['path']   ?? '';
            if (($input['action'] ?? '') === 'write') {
                $preview['content_size'] = strlen($input['content'] ?? '');
            }
            break;

        case 'kael_dispatch':
            $preview['task']     = $input['task'] ?? '';
            $preview['priority'] = $input['priority'] ?? 'normal';
            break;

        case 'iris_chat':
            $preview['message_length'] = strlen($input['message'] ?? '');
            break;
    }

    return $preview;
}


// ══════════════════════════════════════════════════════════════════════════════
// LOGGING
// ══════════════════════════════════════════════════════════════════════════════

function agent_log_entry(string $tool, array $input, ?array $result, string $status): void
{
    try {
        $pdo = getDBConnection();
        if (!$pdo) return;

        // Create table if it doesn't exist yet (idempotent, cheap after first run)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS knd_agent_logs (
                id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                timestamp  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                tool       VARCHAR(100)    NOT NULL DEFAULT '',
                action     TEXT,
                result     MEDIUMTEXT,
                status     VARCHAR(50)     NOT NULL DEFAULT 'ok',
                ip         VARCHAR(45)              DEFAULT NULL,
                INDEX idx_tool      (tool),
                INDEX idx_status    (status),
                INDEX idx_timestamp (timestamp)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $action_str = @json_encode($input, JSON_UNESCAPED_UNICODE);
        $result_str = @json_encode($result, JSON_UNESCAPED_UNICODE);
        $ip         = $_SERVER['REMOTE_ADDR'] ?? null;

        // Truncate to column limits
        $action_str = $action_str ? substr($action_str, 0, 65535) : null;
        $result_str = $result_str ? substr($result_str, 0, 65535) : null;

        $pdo->prepare(
            'INSERT INTO knd_agent_logs (tool, action, result, status, ip) VALUES (?, ?, ?, ?, ?)'
        )->execute([$tool, $action_str, $result_str, $status, $ip]);

    } catch (Throwable $e) {
        error_log('[knd/agent/execute] log failed: ' . $e->getMessage());
    }
}


// ══════════════════════════════════════════════════════════════════════════════
// RESPONSE HELPER
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Send standard JSON response and exit.
 *
 * @param string      $status   success | error | blocked | simulated
 * @param string      $tool     Tool name
 * @param array|null  $data     Result payload
 * @param string|null $error    Error message (null on success)
 * @param array       $warnings Non-blocking warnings from validation
 * @param bool        $simulate Was this a simulation?
 * @param int         $code     HTTP status code
 */
function agent_respond(
    string  $status,
    string  $tool,
    ?array  $data,
    ?string $error,
    array   $warnings = [],
    bool    $simulate = false,
    int     $code = 200
): never {
    http_response_code($code);
    echo json_encode([
        'status'   => $status,
        'tool'     => $tool,
        'data'     => $data,
        'error'    => $error,
        'warnings' => $warnings,
        'simulate' => $simulate,
        'logged'   => true,
        'ts'       => date('c'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

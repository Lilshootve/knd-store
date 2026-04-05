<?php
/**
 * KND Agent — Execution Endpoint
 * POST /api/agent/execute.php
 *
 * Receives a tool call, validates it, executes it, logs the result, and returns it.
 *
 * Body (JSON):
 *   { "tool": "db_query", "input": { "sql": "SELECT ...", "params": [] } }
 *
 * Protected by KND_WORKER_TOKEN
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/env.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/json.php';

// Bring in the validate_tool_call() function
require_once __DIR__ . '/validate.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// ── Auth ──────────────────────────────────────────────────────────────────────
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

// ── Parse input ───────────────────────────────────────────────────────────────
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

// ── Validate ──────────────────────────────────────────────────────────────────
$validation = validate_tool_call($tool, $input);

if (!$validation['safe']) {
    agent_log($tool, $input, ['validation_failed' => $validation['issues']], 'blocked');
    json_error(
        'VALIDATION_FAILED',
        'Tool call blocked: ' . implode(' | ', $validation['issues']),
        422
    );
}

// ── Execute ───────────────────────────────────────────────────────────────────
$result = null;
$status = 'ok';

try {
    switch ($tool) {

        case 'db_query':
            $result = execute_db_query($input);
            break;

        case 'db_execute':
            $result = execute_db_write($input);
            break;

        case 'file_manager':
            $result = execute_file_manager($input);
            break;

        case 'kael_dispatch':
            $result = execute_kael_dispatch($input);
            break;

        case 'iris_chat':
            $result = execute_iris_chat($input);
            break;

        default:
            $status = 'error';
            $result = ['error' => "Unknown tool: '{$tool}'"];
            break;
    }
} catch (Throwable $e) {
    $status = 'error';
    $result = ['error' => $e->getMessage()];
    error_log('[agent/execute] ' . $tool . ' threw: ' . $e->getMessage());
}

agent_log($tool, $input, $result, $status);

json_success([
    'tool'     => $tool,
    'status'   => $status,
    'result'   => $result,
    'warnings' => $validation['warnings'],
]);

// ──────────────────────────────────────────────────────────────────────────────
// Tool implementations
// ──────────────────────────────────────────────────────────────────────────────

function execute_db_query(array $input): array
{
    $pdo = getDBConnection();
    if (!$pdo) {
        throw new RuntimeException('Database connection failed.');
    }

    $sql    = $input['sql'];
    $params = (array)($input['params'] ?? []);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Cap result size
    $json = json_encode($rows);
    if (strlen($json) > 1_048_576) {
        $rows = array_slice($rows, 0, 100);
        $truncated = true;
    }

    return [
        'rows'      => $rows,
        'row_count' => count($rows),
        'truncated' => $truncated ?? false,
    ];
}

function execute_db_write(array $input): array
{
    $pdo = getDBConnection();
    if (!$pdo) {
        throw new RuntimeException('Database connection failed.');
    }

    $sql    = $input['sql'];
    $params = (array)($input['params'] ?? []);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return [
        'affected_rows' => $stmt->rowCount(),
        'last_insert_id' => $pdo->lastInsertId() ?: null,
    ];
}

function execute_file_manager(array $input): array
{
    $action = $input['action'] ?? '';
    $path   = $input['path']   ?? '';

    $root  = defined('KND_ROOT') ? KND_ROOT : dirname(__DIR__, 2);
    $full  = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    $full  = realpath($full) ?: $full;

    // Security: confirm it's inside root
    if (!str_starts_with($full, $root)) {
        throw new RuntimeException('Path is outside the project root.');
    }

    switch ($action) {
        case 'read':
            if (!file_exists($full)) {
                throw new RuntimeException("File not found: {$path}");
            }
            $content = file_get_contents($full);
            return ['content' => $content, 'size' => strlen($content)];

        case 'write':
            if (file_exists($full) && empty($input['confirm_overwrite'])) {
                throw new RuntimeException("File exists and confirm_overwrite is not set.");
            }
            $encoding = $input['encoding'] ?? 'text';
            $content  = $input['content'] ?? '';
            if ($encoding === 'base64') {
                $content = base64_decode($content, true);
                if ($content === false) {
                    throw new RuntimeException('Invalid base64 content.');
                }
            }
            if (strlen($content) > 10_485_760) {
                throw new RuntimeException('File content exceeds 10 MB limit.');
            }
            @mkdir(dirname($full), 0755, true);
            file_put_contents($full, $content);
            return ['written' => true, 'path' => $path, 'size' => strlen($content)];

        case 'delete':
            if (!file_exists($full)) {
                throw new RuntimeException("File not found: {$path}");
            }
            unlink($full);
            return ['deleted' => true, 'path' => $path];

        case 'exists':
            return ['exists' => file_exists($full), 'path' => $path];

        case 'list':
            if (!is_dir($full)) {
                throw new RuntimeException("Directory not found: {$path}");
            }
            $items = [];
            foreach (scandir($full) as $entry) {
                if ($entry === '.' || $entry === '..') continue;
                $items[] = [
                    'name' => $entry,
                    'type' => is_dir($full . DIRECTORY_SEPARATOR . $entry) ? 'dir' : 'file',
                ];
            }
            return ['items' => $items, 'path' => $path];

        default:
            throw new RuntimeException("Unknown file_manager action: {$action}");
    }
}

function execute_kael_dispatch(array $input): array
{
    $kael_url = rtrim(getenv('KND_API_BASE') ?: 'https://kndstore.com', '/') . '/api/kael/gate';
    // Forward to the local KND-Agents server if running locally
    $agents_url = getenv('IRIS_AGENTS_CHAT_URL') ?: '';
    if ($agents_url) {
        $kael_url = preg_replace('#/api/iris/chat$#', '/api/kael/gate', $agents_url);
    }

    $payload = json_encode([
        'task'     => $input['task'],
        'context'  => $input['context']  ?? [],
        'priority' => $input['priority'] ?? 'normal',
    ]);

    $ch = curl_init($kael_url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'X-API-Key: ' . (getenv('KND_WORKER_TOKEN') ?: ''),
        ],
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        throw new RuntimeException("Kael dispatch failed: {$err}");
    }

    $decoded = json_decode($response, true);
    return [
        'http_status' => $http_code,
        'response'    => $decoded ?? ['raw' => $response],
    ];
}

function execute_iris_chat(array $input): array
{
    $iris_url = rtrim(getenv('KND_API_BASE') ?: 'https://kndstore.com', '/') . '/api/iris.php';

    $payload = json_encode([
        'message'              => $input['message'],
        'conversation_history' => $input['conversation_history'] ?? [],
    ]);

    $ch = curl_init($iris_url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        throw new RuntimeException("Iris chat failed: {$err}");
    }

    $decoded = json_decode($response, true);
    return [
        'http_status' => $http_code,
        'response'    => $decoded ?? ['raw' => $response],
    ];
}

// ──────────────────────────────────────────────────────────────────────────────
// Logging helper
// ──────────────────────────────────────────────────────────────────────────────

function agent_log(string $tool, array $input, ?array $result, string $status): void
{
    try {
        $pdo = getDBConnection();
        if (!$pdo) return;

        // Ensure table exists (idempotent)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS knd_agent_logs (
                id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                timestamp  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                action     VARCHAR(255) NOT NULL DEFAULT '',
                tool       VARCHAR(100) NOT NULL DEFAULT '',
                result     MEDIUMTEXT,
                status     VARCHAR(50) NOT NULL DEFAULT 'ok',
                INDEX idx_tool      (tool),
                INDEX idx_status    (status),
                INDEX idx_timestamp (timestamp)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $action     = substr(json_encode($input) ?: '', 0, 255);
        $result_str = substr(json_encode($result) ?: '', 0, 65535);

        $stmt = $pdo->prepare(
            'INSERT INTO knd_agent_logs (action, tool, result, status) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$action, $tool, $result_str, $status]);
    } catch (Throwable $e) {
        error_log('[agent/execute] logging failed: ' . $e->getMessage());
    }
}

<?php
/**
 * KND Agent — Tool Registry
 * GET /api/agent/tools.php
 * GET /api/agent/tools.php?name=db_query
 *
 * Returns the full tool registry or a single tool definition.
 * Tools are used by the Kael/Iris agent system to know what actions are available.
 *
 * Protected by KND_WORKER_TOKEN
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/env.php';
require_once __DIR__ . '/../../../includes/json.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// ── Auth ──────────────────────────────────────────────────────────────────────
$token    = trim((string) (knd_env('KND_WORKER_TOKEN') ?? ''));
$provided = $_GET['token'] ?? knd_request_authorization_header();
if (str_starts_with($provided, 'Bearer ')) {
    $provided = substr($provided, 7);
}
if ($token !== '' && !hash_equals($token, $provided)) {
    json_error('UNAUTHORIZED', 'Invalid or missing token.', 401);
}

// ── Tool Definitions ──────────────────────────────────────────────────────────
$tools = [

    'db_query' => [
        'name'        => 'db_query',
        'description' => 'Execute a read-only SELECT query on the KND Store MySQL database. '
                       . 'Returns rows as an array of associative arrays.',
        'category'    => 'database',
        'safe'        => true,
        'input_schema' => [
            'type'       => 'object',
            'required'   => ['sql'],
            'properties' => [
                'sql' => [
                    'type'        => 'string',
                    'description' => 'A valid SELECT SQL statement.',
                    'examples'    => ['SELECT id, username FROM users LIMIT 10'],
                ],
                'params' => [
                    'type'        => 'array',
                    'description' => 'Ordered array of PDO prepared-statement parameters.',
                    'items'       => ['type' => ['string', 'integer', 'number', 'null']],
                    'default'     => [],
                ],
            ],
        ],
        'safety_rules' => [
            'Only SELECT statements are allowed.',
            'LIMIT is enforced to a maximum of 500 rows.',
            'No subqueries that mutate data (INSERT/UPDATE/DELETE/DROP).',
            'Result sets are capped at 1 MB before returning.',
        ],
    ],

    'db_execute' => [
        'name'        => 'db_execute',
        'description' => 'Execute a write SQL statement (INSERT, UPDATE) on the KND Store database. '
                       . 'DELETE requires a WHERE clause. DROP statements are always blocked.',
        'category'    => 'database',
        'safe'        => false,
        'input_schema' => [
            'type'       => 'object',
            'required'   => ['sql'],
            'properties' => [
                'sql' => [
                    'type'        => 'string',
                    'description' => 'A valid INSERT or UPDATE SQL statement.',
                    'examples'    => [
                        'INSERT INTO knd_agent_logs (action, tool, result, status) VALUES (?, ?, ?, ?)',
                        'UPDATE users SET last_seen = NOW() WHERE id = ?',
                    ],
                ],
                'params' => [
                    'type'        => 'array',
                    'description' => 'Ordered array of PDO prepared-statement parameters.',
                    'items'       => ['type' => ['string', 'integer', 'number', 'null']],
                    'default'     => [],
                ],
            ],
        ],
        'safety_rules' => [
            'DROP TABLE and DROP DATABASE are blocked unconditionally.',
            'TRUNCATE is blocked unconditionally.',
            'DELETE without a WHERE clause is blocked.',
            'ALTER TABLE requires explicit confirmation flag.',
            'All statements go through the validate.php layer first.',
        ],
    ],

    'file_manager' => [
        'name'        => 'file_manager',
        'description' => 'Read or write files within the KND Store uploads/generated-content directories. '
                       . 'Cannot access files outside the allowed base paths.',
        'category'    => 'filesystem',
        'safe'        => false,
        'input_schema' => [
            'type'       => 'object',
            'required'   => ['action', 'path'],
            'properties' => [
                'action' => [
                    'type'        => 'string',
                    'enum'        => ['read', 'write', 'delete', 'list', 'exists'],
                    'description' => 'Operation to perform on the file.',
                ],
                'path' => [
                    'type'        => 'string',
                    'description' => 'Relative path from the allowed base directory (e.g. "uploads/avatars/1.png").',
                ],
                'content' => [
                    'type'        => 'string',
                    'description' => 'File content for write operations (base64 or plain text).',
                ],
                'encoding' => [
                    'type'        => 'string',
                    'enum'        => ['text', 'base64'],
                    'default'     => 'text',
                    'description' => 'Encoding of the content field.',
                ],
                'confirm_overwrite' => [
                    'type'        => 'boolean',
                    'default'     => false,
                    'description' => 'Must be true to overwrite an existing file.',
                ],
            ],
        ],
        'safety_rules' => [
            'Paths are restricted to allowed_base_paths; any path traversal (../) is blocked.',
            'Overwriting an existing file requires confirm_overwrite: true.',
            'Executable file types (.php, .phtml, .phar, .sh, .exe) cannot be written.',
            'Max file write size is 10 MB.',
            'Delete operations are logged and require the action: "delete" flag explicitly.',
        ],
        'allowed_base_paths' => [
            'uploads/',
            'storage/generated/',
            'storage/labs/',
            'storage/tmp/',
        ],
    ],

    'kael_dispatch' => [
        'name'        => 'kael_dispatch',
        'description' => 'Send a task to the Kael agent orchestrator. '
                       . 'Kael will decide which sub-agent handles it.',
        'category'    => 'agent',
        'safe'        => true,
        'input_schema' => [
            'type'       => 'object',
            'required'   => ['task'],
            'properties' => [
                'task' => [
                    'type'        => 'string',
                    'description' => 'Natural-language task description for Kael.',
                ],
                'context' => [
                    'type'        => 'object',
                    'description' => 'Optional structured context to pass to the agent.',
                    'default'     => [],
                ],
                'priority' => [
                    'type'        => 'string',
                    'enum'        => ['low', 'normal', 'high'],
                    'default'     => 'normal',
                ],
            ],
        ],
        'safety_rules' => [
            'Kael applies its own validation pipeline.',
            'Tasks are logged to knd_agent_logs before dispatch.',
        ],
    ],

    'iris_chat' => [
        'name'        => 'iris_chat',
        'description' => 'Send a message to the Iris AI and receive a response.',
        'category'    => 'ai',
        'safe'        => true,
        'input_schema' => [
            'type'       => 'object',
            'required'   => ['message'],
            'properties' => [
                'message' => [
                    'type'        => 'string',
                    'description' => 'The user message to send to Iris.',
                ],
                'conversation_history' => [
                    'type'        => 'array',
                    'description' => 'Prior conversation turns [{role, content}].',
                    'default'     => [],
                ],
            ],
        ],
        'safety_rules' => [
            'Message length is capped at 16 000 characters.',
            'Response is proxied via /api/iris.php to avoid CORS issues.',
        ],
    ],
];

// ── Return single tool or all ─────────────────────────────────────────────────
$name = trim($_GET['name'] ?? '');

if ($name !== '') {
    if (!isset($tools[$name])) {
        json_error('TOOL_NOT_FOUND', "Tool '{$name}' does not exist.", 404);
    }
    json_success(['tool' => $tools[$name]]);
}

json_success([
    'tools' => array_values($tools),
    'count' => count($tools),
    'names' => array_keys($tools),
]);

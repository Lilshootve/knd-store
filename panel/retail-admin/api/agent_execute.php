<?php
/**
 * POST /retail-admin/api/agent_execute.php
 * Session + CSRF → forwards to /api/agent/execute.php (worker token server-side only).
 *
 * Body (JSON): { "tool": "...", "input": {...}, "simulate"?: bool, "confirm_id"?: string, "currency"?: string }
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../../../config/bootstrap.php';
defined('KND_ROOT') or define('KND_ROOT', BASE_PATH);

require_once BASE_PATH . '/includes/env.php';
require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/csrf.php';
require_once BASE_PATH . '/includes/knd_agent_bridge.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST only']);
    exit;
}

$csrfBypass = false;

if (trim((string) (knd_env('KND_DISABLE_CSRF') ?? '')) === '1') {
    $csrfBypass = true;
}

if (!$csrfBypass) {
    $expectedAgent = knd_agents_token();
    if ($expectedAgent !== '') {
        $authHeader = knd_request_authorization_header();
        $provided = '';
        if (str_starts_with($authHeader, 'Bearer ')) {
            $provided = trim(substr($authHeader, 7));
        }
        if ($provided !== '' && hash_equals($expectedAgent, $provided)) {
            $csrfBypass = true;
        }
    }
}

if ($csrfBypass) {
    error_log('[agent_execute] CSRF bypass via token or env');
}

if (!$csrfBypass) {
    csrf_guard();
}

api_require_login();

$raw = file_get_contents('php://input');
$payload = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON body']);
    exit;
}

$tool = isset($payload['tool']) && is_string($payload['tool']) ? trim($payload['tool']) : '';
if ($tool === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing tool']);
    exit;
}

$allowed = array_flip(knd_agent_bridge_allowed_tools());
if (!isset($allowed[$tool])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Tool not allowed from retail admin bridge']);
    exit;
}

$input = isset($payload['input']) && is_array($payload['input']) ? $payload['input'] : [];
$simulate = !empty($payload['simulate']);
$confirmId = isset($payload['confirm_id']) && is_string($payload['confirm_id']) ? $payload['confirm_id'] : null;
$currency = isset($payload['currency']) && is_string($payload['currency']) ? $payload['currency'] : null;

$uid = current_user_id();
if ($uid === null || $uid <= 0) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not logged in']);
    exit;
}

$body = [
    'tool'    => $tool,
    'input'   => $input,
    'user_id' => (string) $uid,
    'mode'    => 'admin',
];
if ($tool !== 'db_query') {
    $body['business_type'] = 'retail';
}

if ($confirmId !== null && $confirmId !== '') {
    $body['confirm_id'] = $confirmId;
}
if ($simulate) {
    $body['simulate'] = true;
}
if ($currency !== null && $currency !== '') {
    $body['currency'] = $currency;
}

$forward = knd_agent_execute_forward($body);

if (!empty($forward['curl_error']) && $forward['json'] === null) {
    http_response_code($forward['http_code'] ?: 503);
    echo json_encode([
        'ok'    => false,
        'error' => $forward['curl_error'],
    ]);
    exit;
}

$j = $forward['json'] ?? [];
// Pass through execute.php envelope for Iris parity (status, tool, data, error, confirm_id, …)
echo json_encode(array_merge(['ok' => true], $j), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

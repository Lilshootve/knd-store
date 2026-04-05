<?php
/**
 * Iris — same-origin proxy to KND Agents (avoids browser CORS / mixed content).
 * Browser POSTs JSON; PHP forwards to IRIS_AGENTS_CHAT_URL (default http://127.0.0.1:3000/api/iris/chat).
 *
 * If the UI shows "System unavailable" and the network tab shows 503 here, check PHP error_log
 * for lines starting with [iris-proxy]. Typical causes:
 * - Agents is not running on the host PHP can reach.
 * - On shared hosting, 127.0.0.1 is NOT your PC; set IRIS_AGENTS_CHAT_URL to a URL reachable from the server.
 * - Firewall / selinux blocking PHP from connecting to the upstream port.
 *
 * .env (recommended in production):
 *   IRIS_AGENTS_CHAT_URL=https://your-agents-host/api/iris/chat
 *   IRIS_AGENTS_API_KEY=...   (sent as X-API-Key if set)
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/env.php';
require_once dirname(__DIR__) . '/includes/session.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['type' => 'chat', 'response' => 'System unavailable']);
    exit;
}

function iris_log(string $message): void
{
    error_log('[iris-proxy] ' . $message);
}

function iris_fail(int $code, string $logDetail): void
{
    iris_log($logDetail);
    http_response_code($code);
    echo json_encode(['type' => 'chat', 'response' => 'System unavailable']);
    exit;
}

$raw = file_get_contents('php://input');
if ($raw === false || $raw === '') {
    iris_fail(400, 'empty body');
}

$body = json_decode($raw, true);
if (!is_array($body) || !isset($body['message']) || !is_string($body['message']) || trim($body['message']) === '') {
    iris_fail(400, 'invalid json or missing message');
}

$message = mb_substr(trim($body['message']), 0, 16000);
$context = ['includeLastRun' => true];
if (isset($body['context']) && is_array($body['context'])) {
    $context = $body['context'];
}
$history = [];
if (isset($body['conversation_history']) && is_array($body['conversation_history'])) {
    $history = $body['conversation_history'];
}

// Determine mode from session — admins get full access, everyone else is public/safe
$irisMode = !empty($_SESSION['admin_logged_in']) ? 'admin' : 'public';

try {
    $payload = json_encode([
        'message'              => $message,
        'context'              => $context,
        'conversation_history' => $history,
        'iris_mode'            => $irisMode,
    ], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    iris_fail(400, 'payload encode failed: ' . $e->getMessage());
}

$upstream = knd_env('IRIS_AGENTS_CHAT_URL', 'http://127.0.0.1:3000/api/iris/chat') ?? 'http://127.0.0.1:3000/api/iris/chat';
$upstream = trim($upstream);
if ($upstream === '') {
    $upstream = 'http://127.0.0.1:3000/api/iris/chat';
}

$headers = ['Content-Type: application/json'];
$apiKey = knd_env('IRIS_AGENTS_API_KEY', null);
if ($apiKey !== null && trim($apiKey) !== '') {
    $headers[] = 'X-API-Key: ' . trim($apiKey);
}

$ch = curl_init($upstream);
if ($ch === false) {
    iris_fail(503, 'curl_init failed for upstream');
}

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 120,
    CURLOPT_CONNECTTIMEOUT => 15,
    // Prefer IPv4 when talking to localhost (avoids some ::1 vs 127.0.0.1 mismatches)
    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
]);

$response = curl_exec($ch);
$errno = curl_errno($ch);
$errstr = curl_error($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($errno !== 0 || $response === false) {
    iris_fail(503, sprintf('curl errno=%d error=%s http_code=%d upstream=%s', $errno, $errstr, $httpCode, $upstream));
}

$response = (string) $response;
$response = preg_replace('/^\xEF\xBB\xBF/', '', $response) ?? $response;

if ($response === '') {
    iris_fail(503, sprintf('empty upstream body http_code=%d upstream=%s', $httpCode, $upstream));
}

try {
    $decoded = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    $snippet = mb_substr(preg_replace('/\s+/', ' ', $response) ?? '', 0, 200);
    iris_fail(503, sprintf('upstream not json http_code=%d snippet=%s err=%s', $httpCode, $snippet, $e->getMessage()));
}

if (!is_array($decoded) || !isset($decoded['type']) || !is_string($decoded['type'])) {
    iris_fail(503, sprintf('upstream json missing type http_code=%d upstream=%s', $httpCode, $upstream));
}

// Always 200 to the browser when JSON matches Iris shape (curl succeeded).
if ($httpCode < 200 || $httpCode >= 300) {
    iris_log(sprintf('upstream http %d (forwarding valid json type=%s)', $httpCode, $decoded['type']));
}

http_response_code(200);
echo json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;

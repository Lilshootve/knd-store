<?php
/**
 * Iris — same-origin proxy to KND Agents (avoids browser CORS / mixed content).
 * Browser POSTs JSON; PHP forwards to IRIS_AGENTS_CHAT_URL (default http://127.0.0.1:3000/api/iris/chat).
 *
 * .env (optional):
 *   IRIS_AGENTS_CHAT_URL=https://internal-host/api/iris/chat
 *   IRIS_AGENTS_API_KEY=...   (sent as X-API-Key if set)
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/env.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['type' => 'chat', 'response' => 'System unavailable']);
    exit;
}

function iris_fail(int $code): void
{
    http_response_code($code);
    echo json_encode(['type' => 'chat', 'response' => 'System unavailable']);
    exit;
}

$raw = file_get_contents('php://input');
if ($raw === false || $raw === '') {
    iris_fail(400);
}

$body = json_decode($raw, true);
if (!is_array($body) || !isset($body['message']) || !is_string($body['message']) || trim($body['message']) === '') {
    iris_fail(400);
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

$payload = json_encode([
    'message' => $message,
    'context' => $context,
    'conversation_history' => $history,
], JSON_THROW_ON_ERROR);

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
    iris_fail(503);
}

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 120,
    CURLOPT_CONNECTTIMEOUT => 15,
]);

$response = curl_exec($ch);
$errno = curl_errno($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($errno !== 0 || $response === false || $response === '') {
    iris_fail(503);
}

// Pass through valid JSON; normalize failures to generic message for the UI
try {
    $decoded = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    iris_fail(503);
}

if (!is_array($decoded) || !isset($decoded['type']) || !is_string($decoded['type'])) {
    iris_fail(503);
}

$outCode = $httpCode >= 200 && $httpCode < 600 ? $httpCode : 200;
if ($outCode < 200 || $outCode >= 300) {
    iris_fail(503);
}

http_response_code($outCode);
echo json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;

<?php
// api/ollama-proxy.php
// Simple proxy to forward chat requests to the Cloudflare endpoint

header('Content-Type: application/json');

$body = file_get_contents('php://input');
if ($body === false) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request body']);
    exit;
}

$ch = curl_init('https://ai.kndstore.com/api/chat');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, false); // stream directly
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 300);

// Execute and stream response
curl_exec($ch);
$err = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($err) {
    http_response_code(502);
    echo json_encode(['error' => 'Proxy error: ' . $err]);
}
?>
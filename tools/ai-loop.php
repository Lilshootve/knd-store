<?php
// tools/ai-loop.php
// Self‑improvement loop using Ollama

header('Content-Type: application/json');

$input = file_get_contents('php://input');
$payload = json_decode($input, true);
if (!is_array($payload) || empty($payload['task'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing task in request']);
    exit;
}
$task = $payload['task'];

$ollamaUrl = 'http://localhost:11434/api/chat';
$model = 'slekrem/gpt-oss-claude-code-32k';

function callOllama(string $prompt, string $model, string $ollamaUrl): string {
    $messages = [
        ['role' => 'user', 'content' => $prompt]
    ];
    $body = json_encode([
        'model' => $model,
        'messages' => $messages,
        'stream' => false
    ]);

    $ch = curl_init($ollamaUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);

    $response = curl_exec($ch);
    if ($response === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new Exception('Curl error: ' . $err);
    }
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception('Ollama returned HTTP ' . $httpCode . ': ' . $response);
    }

    $data = json_decode($response, true);
    if (!is_array($data) || !isset($data['message']['content'])) {
        throw new Exception('Invalid response from Ollama');
    }
    return $data['message']['content'];
}

try {
    $currentCode = callOllama($task, $model, $ollamaUrl);
    for ($i = 0; $i < 3; $i++) {
        // Step 3: review
        $reviewPrompt = "Review this code and list what can be improved\n\n" . $currentCode;
        $improvements = callOllama($reviewPrompt, $model, $ollamaUrl);

        // Step 4: generate better version
        $improvePrompt = "Generate a better version of the code based on the following improvements:\n\n" . $improvements . "\n\nHere is the current code:\n\n" . $currentCode;
        $currentCode = callOllama($improvePrompt, $model, $ollamaUrl);
    }

    echo json_encode(['final_code' => $currentCode]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
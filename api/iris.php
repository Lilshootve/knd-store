<?php
/**
 * Iris API — same-origin JSON endpoint for iris.php (KAEL-lite mock).
 * POST body: { "input": "..." } or { "prompt": "..." }
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['type' => 'message', 'message' => 'Invalid request']);
    exit;
}

function iris_invalid(): void
{
    http_response_code(400);
    echo json_encode(['type' => 'message', 'message' => 'Invalid request']);
    exit;
}

function iris_unavailable(): void
{
    http_response_code(500);
    echo json_encode(['type' => 'message', 'message' => 'System unavailable']);
    exit;
}

function iris_read_input(array $body): ?string
{
    $prompt = $body['prompt'] ?? null;
    $input = $body['input'] ?? null;
    if (is_string($prompt) && trim($prompt) !== '') {
        return trim($prompt);
    }
    if (is_string($input) && trim($input) !== '') {
        return trim($input);
    }
    return null;
}

/**
 * @return array{type: string, message?: string, target?: string}
 */
function iris_mock_decision(string $text): array
{
    $lower = strtolower($text);

    foreach (['image', 'draw', 'generate', 'art'] as $k) {
        if (str_contains($lower, $k)) {
            return [
                'type' => 'redirect',
                'target' => '/knd-labs?prompt=' . rawurlencode($text),
            ];
        }
    }
    foreach (['play', 'game', 'battle'] as $k) {
        if (str_contains($lower, $k)) {
            return ['type' => 'redirect', 'target' => '/knd-games'];
        }
    }
    foreach (['buy', 'product', 'store'] as $k) {
        if (str_contains($lower, $k)) {
            return [
                'type' => 'redirect',
                'target' => '/store?search=' . rawurlencode($text),
            ];
        }
    }

    return [
        'type' => 'message',
        'message' => 'Iris understood your request but no direct action was triggered.',
    ];
}

try {
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        iris_invalid();
    }

    $body = json_decode($raw, true);
    if (!is_array($body)) {
        iris_invalid();
    }

    $trimmed = iris_read_input($body);
    if ($trimmed === null) {
        iris_invalid();
    }

    echo json_encode(iris_mock_decision($trimmed));
} catch (Throwable $e) {
    iris_unavailable();
}

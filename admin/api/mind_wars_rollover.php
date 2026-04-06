<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../_guard.php';
require_once BASE_PATH . '/includes/csrf.php';
require_once BASE_PATH . '/includes/mind_wars.php';

admin_require_login();

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'ok' => false,
        'error' => ['code' => 'METHOD_NOT_ALLOWED', 'message' => 'POST required.'],
    ]);
    exit;
}

csrf_guard();

$pdo = getDBConnection();
if (!$pdo) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => ['code' => 'DB_DOWN', 'message' => 'Database connection failed.'],
    ]);
    exit;
}

$force = !empty($_POST['force']) && (string) $_POST['force'] === '1';

try {
    $result = mw_rollover_season($pdo, $force);
    echo json_encode([
        'ok' => true,
        'rollover' => $result,
    ], JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => ['code' => 'ROLLOVER_FAILED', 'message' => 'Could not rollover season.'],
    ]);
}


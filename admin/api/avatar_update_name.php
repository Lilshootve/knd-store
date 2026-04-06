<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../_guard.php';
require_once BASE_PATH . '/includes/csrf.php';

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

$itemId = (int) ($_POST['item_id'] ?? 0);
$newName = trim((string) ($_POST['name'] ?? ''));
$newName = (string) preg_replace('/\s+/', ' ', $newName);

if ($itemId <= 0) {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'error' => ['code' => 'INVALID_ITEM_ID', 'message' => 'Invalid item ID.'],
    ]);
    exit;
}
if ($newName === '' || mb_strlen($newName) > 120) {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'error' => ['code' => 'INVALID_NAME', 'message' => 'Name is required (1-120 chars).'],
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE knd_avatar_items SET name = ? WHERE id = ?");
    $stmt->execute([$newName, $itemId]);

    if ($stmt->rowCount() === 0) {
        $existsStmt = $pdo->prepare("SELECT id FROM knd_avatar_items WHERE id = ? LIMIT 1");
        $existsStmt->execute([$itemId]);
        if (!$existsStmt->fetch(PDO::FETCH_ASSOC)) {
            http_response_code(404);
            echo json_encode([
                'ok' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Avatar item not found.'],
            ]);
            exit;
        }
    }

    echo json_encode([
        'ok' => true,
        'item_id' => $itemId,
        'name' => $newName,
    ], JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => ['code' => 'UPDATE_FAILED', 'message' => 'Could not update avatar name.'],
    ]);
}


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

$avatarId = trim((string) ($_POST['avatar_id'] ?? ''));
$mind = max(0, min(100, (int) ($_POST['mind'] ?? 0)));
$focus = max(0, min(100, (int) ($_POST['focus'] ?? 0)));
$speed = max(0, min(100, (int) ($_POST['speed'] ?? 0)));
$luck = max(0, min(100, (int) ($_POST['luck'] ?? 0)));

if ($avatarId === '') {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'error' => ['code' => 'INVALID_AVATAR_ID', 'message' => 'Avatar ID is required.'],
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE avatar_stats SET mind = ?, focus = ?, speed = ?, luck = ? WHERE avatar_id = ?");
    $stmt->execute([$mind, $focus, $speed, $luck, $avatarId]);

    if ($stmt->rowCount() === 0) {
        $existsStmt = $pdo->prepare("SELECT avatar_id FROM avatar_stats WHERE avatar_id = ? LIMIT 1");
        $existsStmt->execute([$avatarId]);
        if (!$existsStmt->fetch(PDO::FETCH_ASSOC)) {
            http_response_code(404);
            echo json_encode([
                'ok' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Avatar not found in avatar_stats.'],
            ]);
            exit;
        }
    }

    echo json_encode([
        'ok' => true,
        'avatar_id' => $avatarId,
        'mind' => $mind,
        'focus' => $focus,
        'speed' => $speed,
        'luck' => $luck,
    ], JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => ['code' => 'UPDATE_FAILED', 'message' => 'Could not update avatar stats.'],
    ]);
}

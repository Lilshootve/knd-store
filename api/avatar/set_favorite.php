<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/csrf.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Invalid method']);
    exit;
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not logged in']);
    exit;
}

$csrf = $_POST['csrf_token'] ?? '';
if (!csrf_validate($csrf)) {
    http_response_code(419);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$userId = (int) ($_SESSION['dr_user_id'] ?? 0);
$itemId = isset($_POST['item_id']) ? (int) $_POST['item_id'] : 0;

$pdo = getDBConnection();
if (!$pdo) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB connection error']);
    exit;
}

try {
    if ($itemId <= 0) {
        $stmt = $pdo->prepare("UPDATE users SET favorite_avatar_id = NULL WHERE id = ?");
        $stmt->execute([$userId]);

        echo json_encode([
            'ok' => true,
            'message' => 'Favorite avatar cleared'
        ]);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT ai.id, ai.name, ai.asset_path
        FROM knd_user_avatar_inventory inv
        INNER JOIN knd_avatar_items ai ON ai.id = inv.item_id
        WHERE inv.user_id = ? AND inv.item_id = ? AND ai.is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$userId, $itemId]);
    $ownedItem = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ownedItem) {
        http_response_code(403);
        echo json_encode([
            'ok' => false,
            'error' => 'You do not own this avatar'
        ]);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE users SET favorite_avatar_id = ? WHERE id = ?");
    $stmt->execute([$itemId, $userId]);

    echo json_encode([
        'ok' => true,
        'favorite' => [
            'id' => (int) $ownedItem['id'],
            'name' => $ownedItem['name'],
            'asset_path' => $ownedItem['asset_path'],
        ]
    ]);
} catch (\Throwable $e) {
    error_log('[avatar/set_favorite] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
        'line' => $e->getLine(),
        'file' => basename($e->getFile())
    ]);
}
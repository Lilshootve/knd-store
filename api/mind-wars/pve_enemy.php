<?php
require_once __DIR__ . '/../../config/bootstrap.php';
ob_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/games/mindwars1v1/bootstrap.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/json.php';

try {
    api_require_login();

    $pdo = getDBConnection();
    if (!$pdo) {
        json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500);
    }

    $playerLevel = max(1, (int) ($_GET['player_level'] ?? 1));
    $enemy = mw_pick_enemy_avatar($pdo, $playerLevel, 'normal');

    $playerMnd = isset($_GET['mnd']) ? (int) $_GET['mnd'] : null;
    $playerFcs = isset($_GET['fcs']) ? (int) $_GET['fcs'] : null;
    $playerSpd = isset($_GET['spd']) ? (int) $_GET['spd'] : null;
    $playerLck = isset($_GET['lck']) ? (int) $_GET['lck'] : null;

    if ($playerMnd !== null && $playerFcs !== null && $playerSpd !== null && $playerLck !== null) {
        $cap = function ($enemyVal, $playerVal) {
            return min((int) $enemyVal, max((int) $playerVal, 50) + 10);
        };
        $enemy['mind'] = $cap($enemy['mind'] ?? 50, $playerMnd);
        $enemy['focus'] = $cap($enemy['focus'] ?? 50, $playerFcs);
        $enemy['speed'] = $cap($enemy['speed'] ?? 50, $playerSpd);
        $enemy['luck'] = $cap($enemy['luck'] ?? 50, $playerLck);
    }

    $avatarId = (int) ($enemy['id'] ?? 0);
    $img = trim((string) ($enemy['image'] ?? ''));
    if ($img === '') {
        $image = mw_resolve_avatar_image($pdo, (string) ($enemy['name'] ?? ''), '');
    } else {
        $image = (strpos($img, '/') === 0 || strpos($img, 'http') === 0) ? $img : '/assets/avatars/' . ltrim($img, '/');
    }

    $data = [
        'id' => $avatarId,
        'name' => (string) ($enemy['name'] ?? 'Avatar'),
        'rarity' => (string) ($enemy['rarity'] ?? 'common'),
        'class' => (string) ($enemy['class'] ?? 'Fighter'),
        'stats' => [
            'mind' => (int) ($enemy['mind'] ?? 50),
            'focus' => (int) ($enemy['focus'] ?? 50),
            'speed' => (int) ($enemy['speed'] ?? 50),
            'luck' => (int) ($enemy['luck'] ?? 50),
        ],
        'skills' => [
            'passive' => (string) ($enemy['passive_code'] ?? $enemy['passive'] ?? ''),
            'ability' => (string) ($enemy['ability_code'] ?? $enemy['ability'] ?? ''),
            'special' => (string) ($enemy['special_code'] ?? $enemy['special'] ?? ''),
            'heal' => !empty($enemy['heal']) ? (string) $enemy['heal'] : null,
        ],
        'image' => $image,
    ];

    if (ob_get_level()) ob_end_clean();
    json_success($data);
} catch (\Throwable $e) {
    if (ob_get_level()) ob_end_clean();
    error_log('mind-wars/pve_enemy error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString());
    $isAguacate = stripos($e->getMessage(), 'AGUACATE') !== false;
    $msg = $isAguacate ? $e->getMessage() : 'An unexpected error occurred.';
    if (!$isAguacate && (!empty($_GET['debug']) || !empty($_SERVER['HTTP_X_DEBUG']))) {
        $msg = $e->getMessage() . ' (file: ' . basename($e->getFile()) . ':' . $e->getLine() . ')';
    }
    http_response_code($isAguacate ? 400 : 500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => ['code' => $isAguacate ? 'AGUACATE' : 'INTERNAL_ERROR', 'message' => $msg]], JSON_UNESCAPED_UNICODE);
    exit;
}

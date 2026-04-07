<?php
declare(strict_types=1);
/**
 * Store ordered squad (3× mw_avatars.id) in session for battlefield.php.
 */
require_once __DIR__ . '/../../config/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/squad_battle_bootstrap.php';

api_require_login();

$pdo = getDBConnection();
if (!$pdo) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB_ERROR']);

    exit;
}

$userId = (int) (current_user_id() ?? ($_SESSION['user_id'] ?? 0));
if ($userId < 1) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'AUTH_REQUIRED']);

    exit;
}

$raw = file_get_contents('php://input');
$body = $raw ? json_decode($raw, true) : null;
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'INVALID_JSON']);

    exit;
}

$ids = $body['ids'] ?? null;
if (!is_array($ids) || count($ids) !== 3) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'NEED_THREE_IDS']);

    exit;
}

$mode = isset($body['mode']) ? preg_replace('/[^a-z0-9_-]/i', '', (string) $body['mode']) : 'pve';
if ($mode === '') {
    $mode = 'pve';
}

$ordered = [];
foreach ($ids as $x) {
    $ordered[] = (int) $x;
}

$probe = squad_v2_build_battle_payload($pdo, $userId, $ordered);
if (!($probe['ok'] ?? false)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => $probe['error'] ?? 'INVALID_SQUAD']);

    exit;
}

$_SESSION['squad_arena_v2_engagement'] = [
    'ally_mw_ids' => $ordered,
    'mode' => $mode,
    'ts' => time(),
];

$_SESSION['squad_arena_v2_active'] = [
    'battle_token' => bin2hex(random_bytes(16)),
    'ally_mw_ids' => $ordered,
    'mode' => $mode,
    'ts' => time(),
    'rewards_claimed' => false,
];

echo json_encode(['ok' => true, 'mode' => $mode], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

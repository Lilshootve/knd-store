<?php
require_once __DIR__ . '/../../config/bootstrap.php';
// KND Store - Request rematch offer (Death Roll 1v1)

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/csrf.php';
require_once BASE_PATH . '/includes/rate_limit.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/deathroll_1v1.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('METHOD_NOT_ALLOWED', 'POST only.', 405);
}

csrf_guard();
api_require_login();

$code = strtoupper(trim($_POST['code'] ?? ''));
if (!validate_room_code($code)) {
    json_error('INVALID_CODE', 'Invalid room code.');
}

$pdo = getDBConnection();
if (!$pdo) { json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500); }
$userId = current_user_id();

rate_limit_guard($pdo, "rematch_offer:{$userId}", 10, 60);

$stmt = $pdo->prepare('SELECT * FROM deathroll_games_1v1 WHERE code = ? LIMIT 1');
$stmt->execute([$code]);
$game = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$game) {
    json_error('ROOM_NOT_FOUND', 'Room not found.');
}

if ($game['status'] !== 'finished') {
    json_error('GAME_NOT_FINISHED', 'Game is not finished yet.');
}

$p1 = (int) $game['player1_user_id'];
$p2 = (int) $game['player2_user_id'];

if (!in_array($userId, [$p1, $p2], true)) {
    json_error('NOT_A_PLAYER', 'You are not a player in this game.');
}

$opponent = ($userId === $p1) ? $p2 : $p1;

// Check for existing pending offer on this game
$stmt = $pdo->prepare(
    'SELECT * FROM deathroll_rematch_offers WHERE game_id = ? AND status = "pending" LIMIT 1'
);
$stmt->execute([$game['id']]);
$existing = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existing) {
    // Idempotent: return existing offer
    $stmt = $pdo->prepare('SELECT username FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$existing['offered_to_user_id']]);
    $toUser = $stmt->fetch(PDO::FETCH_ASSOC);
    json_success([
        'offer_id' => (int) $existing['id'],
        'offer_status' => 'pending',
        'offered_by' => (int) $existing['offered_by_user_id'],
        'offered_to' => (int) $existing['offered_to_user_id'],
        'offered_to_username' => $toUser ? $toUser['username'] : '?',
    ]);
}

// Create new offer
$now = gmdate('Y-m-d H:i:s');
$stmt = $pdo->prepare(
    'INSERT INTO deathroll_rematch_offers (game_id, offered_by_user_id, offered_to_user_id, status, created_at, updated_at)
     VALUES (?, ?, ?, "pending", ?, ?)'
);
$stmt->execute([$game['id'], $userId, $opponent, $now, $now]);

$stmt = $pdo->prepare('SELECT username FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$opponent]);
$oppUser = $stmt->fetch(PDO::FETCH_ASSOC);

json_success([
    'offer_id' => (int) $pdo->lastInsertId(),
    'offer_status' => 'pending',
    'offered_by' => $userId,
    'offered_to' => $opponent,
    'offered_to_username' => $oppUser ? $oppUser['username'] : '?',
]);

<?php
// KND Store - Check rematch offer state (Death Roll 1v1)

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/deathroll_1v1.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('METHOD_NOT_ALLOWED', 'GET only.', 405);
}

api_require_login();

$code = strtoupper(trim($_GET['code'] ?? ''));
if (!validate_room_code($code)) {
    json_error('INVALID_CODE', 'Invalid room code.');
}

$pdo = getDBConnection();
if (!$pdo) { json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500); }
$userId = current_user_id();

$stmt = $pdo->prepare('SELECT id FROM deathroll_games_1v1 WHERE code = ? LIMIT 1');
$stmt->execute([$code]);
$game = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$game) {
    json_error('ROOM_NOT_FOUND', 'Room not found.', 404);
}

$stmt = $pdo->prepare(
    'SELECT o.*, u.username AS offered_by_username
     FROM deathroll_rematch_offers o
     JOIN users u ON u.id = o.offered_by_user_id
     WHERE o.game_id = ?
     ORDER BY (o.status = \'pending\') DESC, o.id DESC
     LIMIT 1'
);
$stmt->execute([$game['id']]);
$offer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$offer) {
    json_success(['has_offer' => false]);
}

json_success([
    'has_offer' => true,
    'offer_id' => (int) $offer['id'],
    'offer_status' => $offer['status'],
    'offered_by' => (int) $offer['offered_by_user_id'],
    'offered_by_username' => $offer['offered_by_username'],
    'offered_to' => (int) $offer['offered_to_user_id'],
    'new_code' => $offer['new_game_code'],
    'new_join_url' => $offer['new_game_code'] ? '/death-roll-game.php?code=' . $offer['new_game_code'] : null,
]);

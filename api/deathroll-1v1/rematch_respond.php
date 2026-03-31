<?php
// KND Store - Accept/decline rematch (Death Roll 1v1)

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/rate_limit.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/deathroll_1v1.php';
require_once __DIR__ . '/../../includes/support_credits.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('METHOD_NOT_ALLOWED', 'POST only.', 405);
}

csrf_guard();
api_require_login();

$code = strtoupper(trim($_POST['code'] ?? ''));
$action = trim($_POST['action'] ?? '');

if (!validate_room_code($code)) {
    json_error('INVALID_CODE', 'Invalid room code.');
}
if (!in_array($action, ['accept', 'decline'], true)) {
    json_error('INVALID_ACTION', 'Action must be accept or decline.');
}

$pdo = getDBConnection();
if (!$pdo) { json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500); }
$userId = current_user_id();

rate_limit_guard($pdo, "rematch_respond:{$userId}", 10, 60);

$stmt = $pdo->prepare('SELECT * FROM deathroll_games_1v1 WHERE code = ? LIMIT 1');
$stmt->execute([$code]);
$game = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$game) {
    json_error('ROOM_NOT_FOUND', 'Room not found.');
}

// Find pending offer for this game
$stmt = $pdo->prepare(
    'SELECT * FROM deathroll_rematch_offers WHERE game_id = ? AND status = "pending" LIMIT 1'
);
$stmt->execute([$game['id']]);
$offer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$offer) {
    json_error('NO_PENDING_OFFER', 'No pending rematch offer found.');
}

if ((int) $offer['offered_to_user_id'] !== $userId) {
    json_error('NOT_OFFERED_TO_YOU', 'This rematch offer is not directed to you.');
}

$now = gmdate('Y-m-d H:i:s');

if ($action === 'decline') {
    $stmt = $pdo->prepare(
        'UPDATE deathroll_rematch_offers SET status = "declined", updated_at = ? WHERE id = ?'
    );
    $stmt->execute([$now, $offer['id']]);
    json_success(['offer_status' => 'declined']);
}

// Accept: charge both players and create new game
$loserId  = (int) $game['loser_user_id'];
$winnerId = (int) $game['winner_user_id'];
$entryKp  = (int) ($game['entry_kp'] ?? 100);
$payoutKp = (int) ($game['payout_kp'] ?? (int) floor($entryKp * 1.5));
$houseKp  = (int) ($game['house_kp'] ?? (($entryKp * 2) - $payoutKp));

$pdo->beginTransaction();
try {
    $p1Bal = get_available_points($pdo, $loserId);
    if ($p1Bal < $entryKp) {
        $pdo->rollBack();
        json_error('P1_INSUFFICIENT_KP', 'Opponent does not have enough KND Points (' . $entryKp . ' KP) for rematch.');
    }
    $p2Bal = get_available_points($pdo, $winnerId);
    if ($p2Bal < $entryKp) {
        $pdo->rollBack();
        json_error('INSUFFICIENT_KP', 'You need at least ' . $entryKp . ' KP for rematch.');
    }

    $newCode = generate_room_code($pdo);
    $rematchMax = (int) ($game['initial_max'] ?? 1000);

    $stmt = $pdo->prepare(
        'INSERT INTO deathroll_games_1v1
         (code, visibility, status, created_by_user_id, player1_user_id, player2_user_id,
          current_max, initial_max, turn_user_id, turn_started_at,
          entry_kp, payout_kp, house_kp, charged_at,
          created_at, updated_at, last_activity_at)
         VALUES (?, ?, "playing", ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $newCode, $game['visibility'], $userId,
        $loserId, $winnerId, $rematchMax, $rematchMax, $loserId,
        $now,
        $entryKp, $payoutKp, $houseKp, $now,
        $now, $now, $now,
    ]);
    $newGameId = (int) $pdo->lastInsertId();

    // Debit both players
    $pdo->prepare(
        "INSERT INTO points_ledger (user_id, source_type, source_id, entry_type, status, points, created_at)
         VALUES (?, 'adjustment', ?, 'spend', 'spent', ?, ?)"
    )->execute([$loserId, $newGameId, -$entryKp, $now]);
    $pdo->prepare(
        "INSERT INTO points_ledger (user_id, source_type, source_id, entry_type, status, points, created_at)
         VALUES (?, 'adjustment', ?, 'spend', 'spent', ?, ?)"
    )->execute([$winnerId, $newGameId, -$entryKp, $now]);

    $stmt = $pdo->prepare(
        'UPDATE deathroll_rematch_offers SET status = "accepted", new_game_code = ?, updated_at = ? WHERE id = ?'
    );
    $stmt->execute([$newCode, $now, $offer['id']]);

    $pdo->commit();
    unset($_SESSION['sc_badge_cache']);

    json_success([
        'offer_status' => 'accepted',
        'new_code' => $newCode,
        'join_url' => '/death-roll-game.php?code=' . $newCode,
    ]);
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('LASTROLL_POINTS_FATAL rematch: ' . $e->getMessage());
    json_error('SERVER_ERROR', 'Internal error.', 500);
}

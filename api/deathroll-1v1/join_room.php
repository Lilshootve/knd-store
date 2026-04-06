<?php
require_once __DIR__ . '/../../config/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
ini_set('display_errors', '0');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/csrf.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/deathroll_1v1.php';
require_once BASE_PATH . '/includes/support_credits.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('METHOD_NOT_ALLOWED', 'POST only.', 405);
    }

    csrf_guard();
    api_require_verified_email();

    $pdo = getDBConnection();
    if (!$pdo) { json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500); }
    $userId = current_user_id();

    $code = strtoupper(trim($_POST['code'] ?? ''));
    if (!validate_room_code($code)) {
        json_error('INVALID_CODE', 'Room code must be 8 uppercase alphanumeric characters.');
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'SELECT * FROM deathroll_games_1v1 WHERE code = ? FOR UPDATE'
    );
    $stmt->execute([$code]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$game) {
        $pdo->rollBack();
        json_error('ROOM_NOT_FOUND', 'Room not found.');
    }

    if ($game['status'] !== 'waiting') {
        $pdo->rollBack();
        json_error('ROOM_NOT_WAITING', 'This room is no longer accepting players.');
    }

    if ($game['player2_user_id'] !== null) {
        $pdo->rollBack();
        json_error('ROOM_FULL', 'This room is already full.');
    }

    if ((int) $game['player1_user_id'] === $userId) {
        $pdo->rollBack();
        json_error('CANNOT_JOIN_OWN', 'You cannot join your own room.');
    }

    if ($game['charged_at'] !== null) {
        $pdo->rollBack();
        json_error('ALREADY_CHARGED', 'This game was already charged.');
    }

    $entryKp = (int) ($game['entry_kp'] ?? 100);

    $p1Id = (int) $game['player1_user_id'];
    $p2Id = $userId;

    $p1Balance = get_available_points($pdo, $p1Id);
    if ($p1Balance < $entryKp) {
        $pdo->rollBack();
        json_error('P1_INSUFFICIENT_KP', 'Room creator does not have enough KND Points. Match cancelled.');
    }

    $p2Balance = get_available_points($pdo, $p2Id);
    if ($p2Balance < $entryKp) {
        $pdo->rollBack();
        json_error('INSUFFICIENT_KP', 'You need at least ' . $entryKp . ' KP to join this match.');
    }

    $now = gmdate('Y-m-d H:i:s');
    $gameId = (int) $game['id'];

    // Debit P1
    $pdo->prepare(
        "INSERT INTO points_ledger (user_id, source_type, source_id, entry_type, status, points, created_at)
         VALUES (?, 'adjustment', ?, 'spend', 'spent', ?, ?)"
    )->execute([$p1Id, $gameId, -$entryKp, $now]);

    // Debit P2
    $pdo->prepare(
        "INSERT INTO points_ledger (user_id, source_type, source_id, entry_type, status, points, created_at)
         VALUES (?, 'adjustment', ?, 'spend', 'spent', ?, ?)"
    )->execute([$p2Id, $gameId, -$entryKp, $now]);

    // Update game
    $stmt = $pdo->prepare(
        'UPDATE deathroll_games_1v1
         SET player2_user_id = ?, status = "playing",
             turn_started_at = ?, updated_at = ?, last_activity_at = ?,
             charged_at = ?
         WHERE id = ?'
    );
    $stmt->execute([$userId, $now, $now, $now, $now, $gameId]);

    $game['player2_user_id'] = $userId;
    $game['status'] = 'playing';
    $game['turn_started_at'] = $now;
    $game['charged_at'] = $now;

    $state = build_game_state($pdo, $game, $userId);

    $pdo->commit();

    unset($_SESSION['sc_badge_cache']);

    $state['my_kp_balance'] = get_available_points($pdo, $userId);
    $state['code'] = $game['code'];
    $state['join_url'] = '/death-roll-game.php?code=' . $game['code'];
    json_success($state);
} catch (Throwable $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('DR1V1_FATAL ' . basename(__FILE__) . ': ' . $e->getMessage());
    error_log($e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => ['code' => 'SERVER_ERROR', 'message' => 'Internal error']]);
    exit;
}

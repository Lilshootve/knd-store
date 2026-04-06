<?php
require_once __DIR__ . '/../../config/bootstrap.php';
// KND Store - Roll endpoint (Death Roll 1v1)

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
ini_set('display_errors', '0');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/csrf.php';
require_once BASE_PATH . '/includes/rate_limit.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/deathroll_1v1.php';
require_once BASE_PATH . '/includes/support_credits.php';
require_once BASE_PATH . '/includes/knd_daily.php';
require_once BASE_PATH . '/includes/knd_xp.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('METHOD_NOT_ALLOWED', 'POST only.', 405);
    }

    csrf_guard();
    api_require_verified_email();

    $code = strtoupper(trim($_POST['code'] ?? ''));
    if (!validate_room_code($code)) {
        json_error('INVALID_CODE', 'Invalid room code.');
    }

    $pdo = getDBConnection();
    if (!$pdo) { json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500); }
    $userId = current_user_id();

    rate_limit_guard($pdo, "roll:{$userId}:{$code}", 5, 10);

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

    if ($game['status'] !== 'playing') {
        $pdo->rollBack();
        json_error('GAME_NOT_PLAYING', 'Game is not in progress.');
    }

    // Check turn timeout inside transaction
    if (!empty($game['turn_started_at'])) {
        $elapsed = time() - strtotime($game['turn_started_at'] . ' UTC');
        if ($elapsed >= 8) {
            $pdo->rollBack();
            json_error('TURN_TIMEOUT', 'Turn has timed out.');
        }
    }

    if ((int) $game['turn_user_id'] !== $userId) {
        $pdo->rollBack();
        json_error('NOT_YOUR_TURN', 'It is not your turn.');
    }

    $opponent = ((int) $game['player1_user_id'] === $userId)
        ? (int) $game['player2_user_id']
        : (int) $game['player1_user_id'];

    if (!$opponent) {
        $pdo->rollBack();
        json_error('NO_OPPONENT', 'No opponent in this game.');
    }

    $maxBefore = (int) $game['current_max'];
    $rolled = random_int(1, $maxBefore);
    $now = gmdate('Y-m-d H:i:s');
    $xpResult = [];

    $stmt = $pdo->prepare(
        'INSERT INTO deathroll_game_rolls_1v1 (game_id, user_id, max_value, roll_value, created_at)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$game['id'], $userId, $maxBefore, $rolled, $now]);

    if ($rolled === 1) {
        $stmt = $pdo->prepare(
            'UPDATE deathroll_games_1v1
             SET status = "finished", finished_reason = "normal", current_max = ?,
                 loser_user_id = ?, winner_user_id = ?,
                 turn_user_id = NULL, turn_started_at = NULL,
                 updated_at = ?, last_activity_at = ?
             WHERE id = ?'
        );
        $stmt->execute([$rolled, $userId, $opponent, $now, $now, $game['id']]);

        // Payout winner if game was charged and not yet paid
        if (!empty($game['charged_at']) && empty($game['payout_at'])) {
            $payoutKp = (int) ($game['payout_kp'] ?? 150);
            $expiresAt = gmdate('Y-m-d H:i:s', strtotime('+12 months'));
            $pdo->prepare(
                "INSERT INTO points_ledger (user_id, source_type, source_id, entry_type, status, points, available_at, expires_at, created_at)
                 VALUES (?, 'adjustment', ?, 'earn', 'available', ?, ?, ?, ?)"
            )->execute([$opponent, $game['id'], $payoutKp, $now, $expiresAt, $now]);

            $pdo->prepare(
                'UPDATE deathroll_games_1v1 SET payout_at = ? WHERE id = ?'
            )->execute([$now, $game['id']]);

            // XP: winner +20, loser +5 (season + all-time)
            xp_add($pdo, $opponent, 20, 'lastroll_win', 'lastroll_game', (int)$game['id']);
            $xpResult = xp_add($pdo, $userId, 5, 'lastroll_lose', 'lastroll_game', (int)$game['id']);
        }

        $game['status'] = 'finished';
        $game['finished_reason'] = 'normal';
        $game['current_max'] = $rolled;
        $game['loser_user_id'] = $userId;
        $game['winner_user_id'] = $opponent;
        $game['turn_user_id'] = null;
        $game['turn_started_at'] = null;
    } else {
        $stmt = $pdo->prepare(
            'UPDATE deathroll_games_1v1
             SET current_max = ?, turn_user_id = ?, turn_started_at = ?,
                 updated_at = ?, last_activity_at = ?
             WHERE id = ?'
        );
        $stmt->execute([$rolled, $opponent, $now, $now, $now, $game['id']]);

        $game['current_max'] = $rolled;
        $game['turn_user_id'] = $opponent;
        $game['turn_started_at'] = $now;
    }

    $state = build_game_state($pdo, $game, $userId);
    if (!empty($xpResult)) {
        $state['level_up'] = $xpResult['level_up'];
        $state['old_level'] = $xpResult['old_level'];
        $state['new_level'] = $xpResult['new_level'];
        $state['xp_delta'] = 5;
        $state['xp_total'] = $xpResult['new_xp'] ?? 0;
        $state['level'] = $xpResult['new_level'];
    }

    $pdo->commit();
    unset($_SESSION['sc_badge_cache'], $_SESSION['xp_badge_cache']);

    if ($game['status'] === 'finished') {
        try {
            mission_increment($pdo, $userId, 'play_lastroll_3');
            mission_increment($pdo, (int)$opponent, 'play_lastroll_3');
            mission_increment($pdo, (int)$game['winner_user_id'], 'win_lastroll_1');
        } catch (\Throwable $e) {
            error_log('mission_increment LR error: ' . $e->getMessage());
        }
    }

    $state['my_kp_balance'] = get_available_points($pdo, $userId);
    json_success($state);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('DR1V1_FATAL ' . basename(__FILE__) . ': ' . $e->getMessage());
    error_log($e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => ['code' => 'SERVER_ERROR', 'message' => 'Internal error']]);
    exit;
}

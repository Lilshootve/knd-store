<?php
// KND Store - Death Roll 1v1 helper functions (isolated from single-player)

require_once __DIR__ . '/knd_xp.php';

// Fallback constants if config.php hasn't been updated yet
if (!defined('LASTROLL_ENTRY_KP'))  define('LASTROLL_ENTRY_KP', 100);
if (!defined('LASTROLL_PAYOUT_KP')) define('LASTROLL_PAYOUT_KP', 150);
if (!defined('LASTROLL_HOUSE_KP'))  define('LASTROLL_HOUSE_KP', 50);

/**
 * Generate a unique 8-char uppercase alphanumeric room code.
 */
function generate_room_code(PDO $pdo): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no I/O/0/1 to avoid confusion
    $maxAttempts = 20;
    for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $stmt = $pdo->prepare('SELECT 1 FROM deathroll_games_1v1 WHERE code = ? LIMIT 1');
        $stmt->execute([$code]);
        if (!$stmt->fetch()) {
            return $code;
        }
    }
    throw new RuntimeException('Could not generate unique room code');
}

/**
 * Send a JSON success response.
 */
function json_success(array $data = []): void {
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Send a JSON error response.
 */
function json_error(string $code, string $message, int $httpStatus = 400): void {
    http_response_code($httpStatus);
    header('Content-Type: application/json');
    echo json_encode([
        'ok' => false,
        'error' => ['code' => $code, 'message' => $message]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Validate room code format.
 */
function validate_room_code(string $code): bool {
    return (bool) preg_match('/^[A-Z0-9]{8}$/', $code);
}

/**
 * Validate username format: 3-24 chars, alphanumeric + underscore.
 */
function validate_username(string $username): bool {
    return (bool) preg_match('/^[A-Za-z0-9_]{3,24}$/', $username);
}

/**
 * Opportunistic garbage collection for stale/abandoned games.
 * Called with low probability (~5%) to avoid extra load.
 */
function deathroll_gc(PDO $pdo): void {
    if (random_int(1, 100) > 5) {
        return;
    }
    $now = gmdate('Y-m-d H:i:s');

    $pdo->prepare(
        "UPDATE deathroll_games_1v1
         SET status = 'finished', finished_reason = 'abandoned', updated_at = ?
         WHERE status = 'waiting' AND last_activity_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 MINUTE)"
    )->execute([$now]);

    // For playing games: refund KP if charged before marking abandoned
    $staleGames = $pdo->prepare(
        "SELECT id, player1_user_id, player2_user_id, entry_kp, charged_at, payout_at
         FROM deathroll_games_1v1
         WHERE status = 'playing' AND last_activity_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 5 MINUTE)
         LIMIT 20"
    );
    $staleGames->execute();
    $abandoned = $staleGames->fetchAll(PDO::FETCH_ASSOC);
    foreach ($abandoned as $sg) {
        // Refund both players if game was charged and not yet paid out
        if (!empty($sg['charged_at']) && empty($sg['payout_at'])) {
            $refundKp = (int) ($sg['entry_kp'] ?? 100);
            $expiresRef = gmdate('Y-m-d H:i:s', strtotime('+12 months'));
            foreach ([$sg['player1_user_id'], $sg['player2_user_id']] as $pid) {
                if ($pid) {
                    $pdo->prepare(
                        "INSERT INTO points_ledger (user_id, source_type, source_id, entry_type, status, points, available_at, expires_at, created_at)
                         VALUES (?, 'adjustment', ?, 'earn', 'available', ?, ?, ?, ?)"
                    )->execute([(int)$pid, (int)$sg['id'], $refundKp, $now, $expiresRef, $now]);
                }
            }
            $pdo->prepare('UPDATE deathroll_games_1v1 SET payout_at = ? WHERE id = ?')
                ->execute([$now, $sg['id']]);
        }
        $pdo->prepare(
            "UPDATE deathroll_games_1v1
             SET status = 'finished', finished_reason = 'abandoned', turn_user_id = NULL, updated_at = ?
             WHERE id = ? AND status = 'playing'"
        )->execute([$now, $sg['id']]);
    }

    $pdo->prepare(
        "DELETE FROM deathroll_games_1v1
         WHERE status = 'finished' AND updated_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)"
    )->execute();
}

/**
 * Check and apply turn timeout (8s). Returns updated $game array.
 * Uses optimistic locking on turn_started_at to prevent double-finish.
 */
function check_turn_timeout(PDO $pdo, array $game): array {
    if ($game['status'] !== 'playing' || !$game['turn_started_at'] || !$game['turn_user_id']) {
        return $game;
    }

    $turnStartUtc = strtotime($game['turn_started_at'] . ' UTC');
    $serverNow = time();
    $elapsed = $serverNow - $turnStartUtc;

    if ($elapsed < 8) {
        return $game;
    }

    $now = gmdate('Y-m-d H:i:s');
    $turnUserId = (int) $game['turn_user_id'];
    $opponent = ((int) $game['player1_user_id'] === $turnUserId)
        ? (int) $game['player2_user_id']
        : (int) $game['player1_user_id'];

    if (!$opponent) {
        return $game;
    }

    $stmt = $pdo->prepare(
        "UPDATE deathroll_games_1v1
         SET status = 'finished', finished_reason = 'timeout',
             loser_user_id = ?, winner_user_id = ?,
             turn_user_id = NULL, updated_at = ?, last_activity_at = ?
         WHERE id = ? AND status = 'playing' AND turn_started_at = ?"
    );
    $stmt->execute([$turnUserId, $opponent, $now, $now, $game['id'], $game['turn_started_at']]);

    if ($stmt->rowCount() > 0) {
        $game['status'] = 'finished';
        $game['finished_reason'] = 'timeout';
        $game['loser_user_id'] = $turnUserId;
        $game['winner_user_id'] = $opponent;
        $game['turn_user_id'] = null;

        // Payout on timeout if charged and not yet paid
        if (!empty($game['charged_at']) && empty($game['payout_at'])) {
            $payoutKp = (int) ($game['payout_kp'] ?? 150);
            $expiresAt = gmdate('Y-m-d H:i:s', strtotime('+12 months'));
            $pdo->prepare(
                "INSERT INTO points_ledger (user_id, source_type, source_id, entry_type, status, points, available_at, expires_at, created_at)
                 VALUES (?, 'adjustment', ?, 'earn', 'available', ?, ?, ?, ?)"
            )->execute([$opponent, $game['id'], $payoutKp, $now, $expiresAt, $now]);

            $pdo->prepare('UPDATE deathroll_games_1v1 SET payout_at = ? WHERE id = ?')
                ->execute([$now, $game['id']]);

            xp_add($pdo, $opponent, 20, 'lastroll_win', 'lastroll_game', (int)$game['id']);
            xp_add($pdo, $turnUserId, 5, 'lastroll_lose', 'lastroll_game', (int)$game['id']);
            unset($_SESSION['xp_badge_cache']);

            $game['payout_at'] = $now;
        }
    }

    return $game;
}

/**
 * Build the full game state array for API responses.
 */
function build_game_state(PDO $pdo, array $game, int $currentUserId): array {
    $p1 = null;
    $p2 = null;

    $stmt = $pdo->prepare('SELECT id, username FROM users WHERE id = ?');
    $stmt->execute([$game['player1_user_id']]);
    $p1 = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($game['player2_user_id']) {
        $stmt->execute([$game['player2_user_id']]);
        $p2 = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    $turnUid = isset($game['turn_user_id']) ? (int) $game['turn_user_id'] : 0;
    $canRoll = ($game['status'] === 'playing')
        && ($turnUid === (int) $currentUserId);

    $stmt = $pdo->prepare(
        'SELECT r.max_value, r.roll_value, r.created_at, u.username
         FROM deathroll_game_rolls_1v1 r
         JOIN users u ON u.id = r.user_id
         WHERE r.game_id = ?
         ORDER BY r.created_at ASC
         LIMIT 50'
    );
    $stmt->execute([$game['id']]);
    $rolls = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $turnDuration = 8;
    $turnSecondsLeft = null;
    $serverTime = gmdate('Y-m-d H:i:s');
    if ($game['status'] === 'playing' && !empty($game['turn_started_at'])) {
        $elapsed = time() - strtotime($game['turn_started_at'] . ' UTC');
        $turnSecondsLeft = max(0, min($turnDuration, $turnDuration - $elapsed));
    }

    return [
        'game' => [
            'code'              => $game['code'],
            'visibility'        => $game['visibility'],
            'status'            => $game['status'],
            'current_max'       => (int) $game['current_max'],
            'initial_max'       => (int) ($game['initial_max'] ?? 1000),
            'turn_user_id'      => $game['turn_user_id'] ? (int) $game['turn_user_id'] : null,
            'winner_user_id'    => $game['winner_user_id'] ? (int) $game['winner_user_id'] : null,
            'loser_user_id'     => $game['loser_user_id'] ? (int) $game['loser_user_id'] : null,
            'finished_reason'   => $game['finished_reason'] ?? null,
            'turn_duration'     => $turnDuration,
            'turn_seconds_left' => $turnSecondsLeft,
            'turn_started_at'   => $game['turn_started_at'] ?? null,
            'server_time'       => $serverTime,
            'entry_kp'          => (int) ($game['entry_kp'] ?? (defined('LASTROLL_ENTRY_KP') ? LASTROLL_ENTRY_KP : 100)),
            'payout_kp'         => (int) ($game['payout_kp'] ?? (defined('LASTROLL_PAYOUT_KP') ? LASTROLL_PAYOUT_KP : 150)),
            'charged'           => !empty($game['charged_at']),
            'paid_out'          => !empty($game['payout_at']),
        ],
        'players' => [
            'p1' => $p1 ? ['id' => (int)$p1['id'], 'username' => $p1['username']] : null,
            'p2' => $p2 ? ['id' => (int)$p2['id'], 'username' => $p2['username']] : null,
        ],
        'me' => [
            'id'       => $currentUserId,
            'username' => ($p1 && (int)$p1['id'] === $currentUserId) ? $p1['username']
                        : (($p2 && (int)$p2['id'] === $currentUserId) ? $p2['username'] : null),
            'can_roll' => $canRoll,
        ],
        'rolls' => $rolls,
    ];
}

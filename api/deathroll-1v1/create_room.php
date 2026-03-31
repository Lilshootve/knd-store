<?php
// KND Store - Create room endpoint (Death Roll 1v1)

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
ini_set('display_errors', '0');

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/rate_limit.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/deathroll_1v1.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('METHOD_NOT_ALLOWED', 'POST only.', 405);
    }

    csrf_guard();
    api_require_verified_email();

    $pdo = getDBConnection();
    if (!$pdo) { json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500); }

    $userId = current_user_id();
    if (!$userId) { json_error('AUTH_FAILED', 'Unable to identify user.', 401); }

    rate_limit_guard($pdo, "create_room:{$userId}", 10, 60);

    $visibility = $_POST['visibility'] ?? '';
    if (!in_array($visibility, ['public', 'private'], true)) {
        json_error('INVALID_INPUT', 'Visibility must be "public" or "private".');
    }

    $initialMax = isset($_POST['initial_max']) ? (int) $_POST['initial_max'] : 1000;
    if ($initialMax < 10 || $initialMax > 10000) {
        json_error('INVALID_INITIAL_MAX', 'Initial max must be between 10 and 10000.');
    }

    $entryKp = isset($_POST['entry_kp']) ? (int) $_POST['entry_kp'] : 100;
    if ($entryKp < 5 || $entryKp > 1000) {
        json_error('INVALID_ENTRY_KP', 'Entry KP must be between 5 and 1000.');
    }
    $payoutKp = (int) floor($entryKp * 1.5);
    $houseKp  = ($entryKp * 2) - $payoutKp;
    if ($houseKp < 0) { $houseKp = 0; }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) as cnt FROM deathroll_games_1v1
         WHERE created_by_user_id = ? AND status IN ("waiting", "playing")'
    );
    $stmt->execute([$userId]);
    $activeCount = (int) $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
    if ($activeCount >= 5) {
        json_error('ROOM_LIMIT', 'You already have 5 active rooms. Finish or leave one first.');
    }

    $code = generate_room_code($pdo);
    $now = gmdate('Y-m-d H:i:s');

    $stmt = $pdo->prepare(
        'INSERT INTO deathroll_games_1v1
         (code, visibility, status, created_by_user_id, player1_user_id, current_max, initial_max,
          turn_user_id, entry_kp, payout_kp, house_kp, created_at, updated_at, last_activity_at)
         VALUES (?, ?, "waiting", ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$code, $visibility, $userId, $userId, $initialMax, $initialMax, $userId, $entryKp, $payoutKp, $houseKp, $now, $now, $now]);

    json_success([
        'code' => $code,
        'join_url' => '/death-roll-game.php?code=' . $code,
    ]);
} catch (Throwable $e) {
    error_log('DR1V1_FATAL ' . basename(__FILE__) . ': ' . $e->getMessage());
    error_log($e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => ['code' => 'SERVER_ERROR', 'message' => 'Internal error']]);
    exit;
}

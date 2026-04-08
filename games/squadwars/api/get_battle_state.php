<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/json.php';
require_once BASE_PATH . '/games/squadwars/bootstrap.php';
require_once BASE_PATH . '/games/squadwars/infrastructure/SquadBattleRepository.php';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
        json_error('METHOD_NOT_ALLOWED', 'GET only.', 405);
    }
    api_require_login();
    $userId = (int) current_user_id();

    $battleToken = trim((string) ($_GET['battle_token'] ?? ''));
    if ($battleToken === '') {
        json_error('invalid_payload', 'battle_token is required.', 400);
    }

    $pdo = getDBConnection();
    if (!$pdo instanceof PDO) {
        json_error('invalid_payload', 'Database connection failed.', 500);
    }

    $repo = new SquadBattleRepository($pdo);
    $battle = $repo->findByTokenForUser($battleToken, $userId, false);
    if ($battle === null) {
        json_error('battle_not_found', 'Battle not found.', 404);
    }

    $state = $battle['state'] ?? null;
    if (!is_array($state)) {
        json_error('invalid_payload', 'Corrupted battle state.', 409);
    }

    json_success([
        'battle_token' => $battleToken,
        'state' => $state,
        'stateVersion' => (int) ($state['version'] ?? 1),
        'round' => (int) ($state['round'] ?? 1),
    ]);
} catch (Throwable $e) {
    error_log('[squadwars_get_battle_state] ' . $e->getMessage());
    json_error('SERVER_ERROR', 'Server error.', 500);
}


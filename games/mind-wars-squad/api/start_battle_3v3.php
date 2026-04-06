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
require_once BASE_PATH . '/includes/csrf.php';
require_once BASE_PATH . '/includes/json.php';
require_once BASE_PATH . '/includes/mind_wars.php';
require_once BASE_PATH . '/includes/mw_squad.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('METHOD_NOT_ALLOWED', 'POST only.', 405);
    }

    $rawBody = (string) file_get_contents('php://input');
    $jsonBody = json_decode($rawBody, true);
    if (empty($_POST['csrf_token']) && is_array($jsonBody) && !empty($jsonBody['csrf_token'])) {
        $_POST['csrf_token'] = (string) $jsonBody['csrf_token'];
    }

    csrf_guard();
    api_require_login();

    $pdo = getDBConnection();
    if (!$pdo) {
        json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500);
    }

    $userId = (int) current_user_id();
    $contentType = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
    $payload = [];
    if (stripos($contentType, 'application/json') !== false) {
        $payload = is_array($jsonBody) ? $jsonBody : [];
    } else {
        $payload = $_POST;
    }

    $difficulty = mw_normalize_difficulty((string) ($payload['difficulty'] ?? 'normal'));
    $slots = $payload['player_slots'] ?? [];
    if (is_string($slots)) {
        $slots = json_decode($slots, true) ?: [];
    }
    if (!is_array($slots) || count($slots) !== 3) {
        json_error('INVALID_REQUEST', 'Exactly 3 player slots are required.');
    }

    $avatarItemIds = [];
    foreach ($slots as $slot) {
        $itemId = (int) (($slot['item_id'] ?? 0));
        if ($itemId < 1) {
            json_error('INVALID_REQUEST', 'Invalid item_id in player_slots.');
        }
        $avatarItemIds[] = $itemId;
    }
    if (count(array_unique($avatarItemIds)) !== 3) {
        json_error('INVALID_REQUEST', 'Each squad slot must use a different avatar.');
    }

    $playerUnits = [];
    foreach ($avatarItemIds as $itemId) {
        $avatar = mw_validate_owned_avatar($pdo, $userId, $itemId);
        if (!$avatar) {
            json_error('AVATAR_NOT_OWNED', 'You do not own selected avatar item: ' . $itemId, 403);
        }
        $playerUnits[] = mw_build_fighter($avatar, false);
    }

    $avgLevel = (int) round(array_sum(array_map(static fn($u) => (int) ($u['level'] ?? 1), $playerUnits)) / 3);
    [$enemyUnits, $enemyKndItemId] = mw_squad_build_enemy_units_for_battle($pdo, $avgLevel, $difficulty);

    $state = mw_squad_build_state($playerUnits, $enemyUnits, [
        'mode' => 'pve_3v3',
        'difficulty' => $difficulty,
        'format' => '3v3_squad',
    ]);

    $battleToken = mw_squad_generate_token();
    $created = mw_squad_create_battle($pdo, $userId, $battleToken, $state, (int) $avatarItemIds[0], max(1, $enemyKndItemId));
    if (!$created) {
        json_error('BATTLE_CREATE_FAILED', 'Could not create squad battle.', 500);
    }

    json_success([
        'battle_token' => $battleToken,
        'state' => $state,
        'next_actor' => (string) ($state['next_actor'] ?? 'player'),
        'next_actor_slot' => (int) ($state['meta']['next_actor_slot'] ?? 0),
        'format' => '3v3_squad',
    ]);
} catch (Throwable $e) {
    error_log('mind-wars-squad/start_battle_3v3 error: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'An unexpected error occurred.', 500);
}

<?php
/**
 * Squad Arena v2 — apply Mind Wars PVE-style rewards after a client-resolved battle.
 * Mirrors api/mind-wars/pve_submit.php (XP, KE on squad lead avatar, season rank) with session-bound battle_token.
 */
require_once __DIR__ . '/../../config/bootstrap.php';
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/csrf.php';
require_once BASE_PATH . '/includes/json.php';
require_once BASE_PATH . '/includes/rate_limit.php';
require_once BASE_PATH . '/includes/mind_wars.php';
require_once BASE_PATH . '/includes/knowledge_duel.php';
require_once BASE_PATH . '/includes/squad_v2_reward_helpers.php';
require_once BASE_PATH . '/includes/mind_wars_rewards.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('METHOD_NOT_ALLOWED', 'POST only.', 405);
    }
    csrf_guard();
    api_require_login();

    $pdo = getDBConnection();
    if (!$pdo) {
        json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500);
    }

    $userId = (int) current_user_id();
    if ($userId < 1) {
        json_error('AUTH_REQUIRED', 'Login required.', 401);
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    rate_limit_guard($pdo, "squad_v2_submit_user:{$userId}", 30, 60);
    rate_limit_guard($pdo, "squad_v2_submit_ip:{$ip}", 80, 60);

    $raw = file_get_contents('php://input');
    $body = $raw ? json_decode($raw, true) : null;
    if (!is_array($body)) {
        json_error('INVALID_JSON', 'Expected JSON body.', 400);
    }

    $battleToken = trim((string) ($body['battle_token'] ?? ''));
    if (strlen($battleToken) < 16) {
        json_error('INVALID_TOKEN', 'Missing battle token.', 400);
    }

    $active = $_SESSION['squad_arena_v2_active'] ?? null;
    if (!is_array($active) || empty($active['battle_token'])) {
        json_error('NO_ACTIVE_BATTLE', 'No active squad battle in session.', 409);
    }
    if (!hash_equals((string) $active['battle_token'], $battleToken)) {
        json_error('TOKEN_MISMATCH', 'Battle token does not match session.', 403);
    }
    if (!empty($active['rewards_claimed'])) {
        json_error('ALREADY_CLAIMED', 'Rewards already applied for this battle.', 409);
    }
    $started = (int) ($active['ts'] ?? 0);
    if ($started > 0 && (time() - $started) > 7200) {
        json_error('BATTLE_EXPIRED', 'Battle session expired. Start a new match.', 410);
    }

    $rawResult = strtolower(trim((string) ($body['result'] ?? '')));
    $result = ($rawResult === 'win' || $rawResult === 'lose') ? $rawResult : 'lose';

    $allyMwIds = $active['ally_mw_ids'] ?? null;
    if (!is_array($allyMwIds) || count($allyMwIds) !== 3) {
        json_error('INVALID_SESSION', 'Invalid squad data in session.', 500);
    }
    if (!squad_v2_user_owns_mw_ids($pdo, $userId, $allyMwIds)) {
        json_error('SQUAD_NOT_OWNED', 'Squad no longer valid for this account.', 403);
    }

    $leaderMw = (int) ($allyMwIds[0] ?? 0);
    $avatarItemId = squad_v2_item_id_for_mw_avatar($pdo, $userId, $leaderMw);
    if ($avatarItemId === null || $avatarItemId < 1) {
        json_error('LEADER_ITEM_MISSING', 'Could not resolve inventory item for squad lead.', 400);
    }

    $avatar = mw_validate_owned_avatar($pdo, $userId, $avatarItemId);
    if (!$avatar) {
        json_error('AVATAR_NOT_OWNED', 'Lead avatar is not in your inventory.', 403);
    }

    $rawMode = (string) ($active['mode'] ?? 'pve');
    $rewards = squad_v2_rewards_for_mode($result, $rawMode);
    $resultLabel = $result === 'win' ? 'win' : 'lose';

    $pdo->beginTransaction();
    try {
        mw_apply_rewards_to_user($pdo, $userId, $avatarItemId, $rewards, $resultLabel);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    $_SESSION['squad_arena_v2_active']['rewards_claimed'] = true;

    if ($result === 'win' && is_file(BASE_PATH . '/includes/knd_badges.php')) {
        require_once BASE_PATH . '/includes/knd_badges.php';
        if (function_exists('badges_check_and_grant')) {
            foreach (['mind_wars_wins', 'mind_wars_streak', 'mind_wars_special', 'mind_wars_legendary'] as $t) {
                badges_check_and_grant($pdo, $userId, $t);
            }
        }
    }

    json_success([
        'rewards' => $rewards,
        'result' => $result,
        'avatar_item_id' => $avatarItemId,
    ]);
} catch (Throwable $e) {
    error_log('squad-arena-v2/submit_result: ' . $e->getMessage());
    if (stripos($e->getMessage(), 'AGUACATE') !== false) {
        json_error('AGUACATE', $e->getMessage(), 400);
    }
    json_error('INTERNAL_ERROR', 'Could not apply rewards.', 500);
}

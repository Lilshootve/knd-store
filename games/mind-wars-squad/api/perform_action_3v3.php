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
require_once BASE_PATH . '/games/mindwars1v1/bootstrap.php';
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

    $token = trim((string) ($payload['battle_token'] ?? ''));
    $actorSlot = (int) ($payload['actor_slot'] ?? -1);
    $action = trim((string) ($payload['action'] ?? ''));
    $targetSlot = (int) ($payload['target_slot'] ?? 0);

    if ($token === '') {
        json_error('INVALID_REQUEST', 'battle_token is required.');
    }
    if (!in_array($action, ['attack', 'ability', 'special', 'defend'], true)) {
        json_error('INVALID_REQUEST', 'Invalid action.');
    }
    if ($actorSlot < 0 || $actorSlot > 2 || $targetSlot < 0 || $targetSlot > 2) {
        json_error('INVALID_REQUEST', 'slot must be 0-2.');
    }

    $state = mw_squad_load_state($pdo, $token, $userId);
    if (!$state) {
        json_error('BATTLE_NOT_FOUND', 'Battle not found or already finished.', 404);
    }
    if ((string) ($state['meta']['format'] ?? '') !== '3v3_squad') {
        json_error('INVALID_BATTLE_STATE', 'This battle is not a 3v3 squad battle.', 409);
    }
    if (!empty($state['meta']['battle_over'])) {
        json_error('BATTLE_ENDED', 'Battle is already finished.', 409);
    }
    if ((string) ($state['next_actor'] ?? 'player') !== 'player') {
        json_error('NOT_YOUR_TURN', 'It is not the player turn.', 409);
    }

    $outcome = mw_squad_process_action($state, [
        'side' => 'player',
        'actor_slot' => $actorSlot,
        'action' => $action,
        'target_slot' => $targetSlot,
    ]);
    if (empty($outcome['ok'])) {
        json_error('INVALID_ACTION', (string) ($outcome['error'] ?? 'Invalid action.'));
    }

    $playerActionResult = $outcome['action_result'] ?? null;
    $aiActions = [];
    $rewards = null;

    if (!empty($outcome['battle_over'])) {
        $pdo->beginTransaction();
        try {
            $rewards = mw_squad_commit_endgame($pdo, $token, $userId, $state);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('mind-wars-squad/perform_action_3v3 endgame: ' . $e->getMessage());
            json_error('INTERNAL_ERROR', 'Could not finalize battle rewards.', 500);
        }
        json_success([
            'state' => $state,
            'action_result' => $playerActionResult,
            'ai_actions' => [],
            'next_actor' => null,
            'next_actor_slot' => null,
            'battle_over' => true,
            'winner' => $outcome['winner'] ?? null,
            'rewards' => $rewards,
        ]);
    }

    $safety = 0;
    while ((string) ($state['next_actor'] ?? '') === 'enemy' && $safety < 10) {
        $safety++;

        if ((int) ($state['turn'] ?? 1) > (int) ($state['max_turns'] ?? MW_SQUAD_MAX_TURNS)) {
            $winner = mw_squad_winner_by_hp($state);
            $state = mw_squad_end_battle($state, $winner);
            $pdo->beginTransaction();
            try {
                $rewards = mw_squad_commit_endgame($pdo, $token, $userId, $state);
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('mind-wars-squad/perform_action_3v3 endgame: ' . $e->getMessage());
                json_error('INTERNAL_ERROR', 'Could not finalize battle rewards.', 500);
            }
            json_success([
                'state' => $state,
                'action_result' => $playerActionResult,
                'ai_actions' => $aiActions,
                'next_actor' => null,
                'next_actor_slot' => null,
                'battle_over' => true,
                'winner' => $winner,
                'rewards' => $rewards,
            ]);
        }

        $aiInput = mw_squad_ai_decide($state);
        $aiOutcome = mw_squad_process_action($state, [
            'side' => 'enemy',
            'actor_slot' => (int) ($aiInput['actor_slot'] ?? 0),
            'action' => (string) ($aiInput['action'] ?? 'attack'),
            'target_slot' => (int) ($aiInput['target_slot'] ?? 0),
        ]);

        if (empty($aiOutcome['ok'])) {
            break;
        }
        $aiActions[] = $aiOutcome['action_result'] ?? null;

        if (!empty($aiOutcome['battle_over'])) {
            $pdo->beginTransaction();
            try {
                $rewards = mw_squad_commit_endgame($pdo, $token, $userId, $state);
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('mind-wars-squad/perform_action_3v3 endgame: ' . $e->getMessage());
                json_error('INTERNAL_ERROR', 'Could not finalize battle rewards.', 500);
            }
            json_success([
                'state' => $state,
                'action_result' => $playerActionResult,
                'ai_actions' => $aiActions,
                'next_actor' => null,
                'next_actor_slot' => null,
                'battle_over' => true,
                'winner' => $aiOutcome['winner'] ?? null,
                'rewards' => $rewards,
            ]);
        }
    }

    mw_squad_save_state($pdo, $token, $userId, $state);
    json_success([
        'state' => $state,
        'action_result' => $playerActionResult,
        'ai_actions' => $aiActions,
        'next_actor' => (string) ($state['next_actor'] ?? 'player'),
        'next_actor_slot' => (int) ($state['meta']['next_actor_slot'] ?? 0),
        'battle_over' => false,
        'winner' => null,
        'rewards' => null,
    ]);
} catch (Throwable $e) {
    error_log('mind-wars-squad/perform_action_3v3 error: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'An unexpected error occurred.', 500);
}

/**
 * Grant rewards, persist battle columns, Mind Wars badges — caller wraps in transaction.
 *
 * @return array<string, mixed>
 */
function mw_squad_commit_endgame(PDO $pdo, string $token, int $userId, array $state): array
{
    $rewards = mw_squad_finalize_battle($pdo, $token, $userId, $state);
    require_once BASE_PATH . '/includes/knd_badges.php';
    foreach (['mind_wars_wins', 'mind_wars_streak', 'mind_wars_special', 'mind_wars_legendary'] as $t) {
        badges_check_and_grant($pdo, $userId, $t);
    }

    return $rewards;
}

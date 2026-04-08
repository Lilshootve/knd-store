<?php
declare(strict_types=1);

/**
 * Endpoint SquadWars submit_round.
 * Flujo: cargar battle -> validar payload -> ejecutar service -> persistir state -> responder.
 */
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
require_once BASE_PATH . '/includes/rate_limit.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_error('METHOD_NOT_ALLOWED', 'POST only.', 405);
}

require_once BASE_PATH . '/games/squadwars/bootstrap.php';
require_once BASE_PATH . '/games/squadwars/infrastructure/SquadBattleRepository.php';

try {
    $pdo = getDBConnection();
    if (!$pdo instanceof PDO) {
        json_error('invalid_payload', 'Database connection failed.', 500);
    }

    $rawBody = (string) file_get_contents('php://input');
    $payload = json_decode($rawBody, true);
    if (!is_array($payload)) {
        $payload = [];
    }
    if (empty($_POST['csrf_token']) && isset($payload['csrf_token'])) {
        $_POST['csrf_token'] = (string) $payload['csrf_token'];
    }

    csrf_guard();
    api_require_login();
    $userId = (int) current_user_id();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    rate_limit_guard($pdo, "squad_submit_user:{$userId}", 60, 60);
    rate_limit_guard($pdo, "squad_submit_ip:{$ip}", 120, 60);

    if (!is_array($payload)) {
        json_error('invalid_payload', 'Invalid JSON payload.', 400);
    }

    $battleToken = trim((string) ($payload['battle_token'] ?? ''));
    $round = (int) ($payload['round'] ?? 0);
    $actions = $payload['actions'] ?? null;
    $clientStateVersion = $payload['clientStateVersion'] ?? null;

    if ($battleToken === '' || $round < 1 || !is_array($actions) || !is_numeric($clientStateVersion)) {
        json_error('invalid_payload', 'Required fields: battle_token, round, actions[], clientStateVersion.', 400);
    }

    $repo = new SquadBattleRepository($pdo);
    $pdo->beginTransaction();
    try {
        $battle = $repo->findByTokenForUser($battleToken, $userId, true);
        if ($battle === null) {
            $pdo->rollBack();
            json_error('battle_not_found', 'Battle not found.', 404);
        }

        $state = $battle['state'] ?? null;
        if (!is_array($state)) {
            $pdo->rollBack();
            json_error('invalid_payload', 'Corrupted battle state.', 409);
        }

        $service = knd_squadwars_create_submit_service($pdo);
        $result = $service->execute($state, [
            'battle_token' => $battleToken,
            'round' => $round,
            'actions' => $actions,
            'clientStateVersion' => (int) $clientStateVersion,
        ]);

        // Validación extra de serialización para evitar persistir estados no codificables.
        $probe = json_encode(
            $state,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );
        if (!is_string($probe) || json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('invalid_payload');
        }

        $repo->saveState((string) ($battle['id'] ?? ''), $state);
        $pdo->commit();

        error_log(
            sprintf(
                '[squadwars_submit_round] user_id=%d battle_id=%s round=%d actions=%d battleOver=%s winner=%s',
                $userId,
                (string) ($battle['id'] ?? ''),
                $round,
                count($actions),
                $result->battleOver ? '1' : '0',
                (string) ($result->winner ?? '')
            )
        );

        $out = $result->toApiArray();
        // Contrato: `round` = ronda resuelta por este submit. Tras guardar, `state.round` es la ronda
        // que el cliente debe enviar en el siguiente POST (salvo battleOver).
        $out['nextSubmitRound'] = (int) ($state['round'] ?? 1);

        json_success($out);
    } catch (Throwable $txe) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $txe;
    }
} catch (InvalidArgumentException $e) {
    $code = (string) $e->getMessage();
    if ($code === 'state_out_of_sync') {
        json_error('state_out_of_sync', 'Client state is out of sync. Refresh battle state.', 409);
    }
    if ($code === 'round_mismatch') {
        json_error('invalid_payload', 'Round mismatch for submit_round.', 409);
    }
    json_error('invalid_payload', 'Invalid payload.', 400);
} catch (RuntimeException $e) {
    $code = (string) $e->getMessage();
    if ($code === 'battle_not_found') {
        json_error('battle_not_found', 'Battle not found.', 404);
    }
    if ($code === 'invalid_payload') {
        json_error('invalid_payload', 'Invalid payload.', 400);
    }
    error_log('[squadwars_submit_round_runtime] ' . $e->getMessage());
    json_error('SERVER_ERROR', 'Server error.', 500);
} catch (Throwable $e) {
    error_log('[squadwars_submit_round_fatal] ' . $e->getMessage());
    json_error('SERVER_ERROR', 'Server error.', 500);
}

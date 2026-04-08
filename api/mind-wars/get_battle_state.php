<?php
require_once __DIR__ . '/../../config/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/json.php';
require_once BASE_PATH . '/includes/mind_wars_arena_bootstrap.php';

function mw_viewer_result(?string $baseResult, ?string $viewerSide): ?string {
    $baseResult = is_string($baseResult) ? strtolower($baseResult) : null;
    $viewerSide = is_string($viewerSide) ? strtolower($viewerSide) : null;
    if ($baseResult === null || $viewerSide !== 'enemy') {
        return $baseResult;
    }
    if ($baseResult === 'win') return 'lose';
    if ($baseResult === 'lose') return 'win';
    return $baseResult;
}

try {
    api_require_login();
    $pdo = getDBConnection();
    if (!$pdo) {
        json_error('DB_CONNECTION_FAILED', 'Database connection failed.', 500);
    }

    $userId = (int) current_user_id();
    $battleToken = trim((string) ($_GET['battle_token'] ?? ''));
    if (strlen($battleToken) < 32) {
        json_error('INVALID_REQUEST', 'Missing or invalid battle_token.');
    }

    $stmt = $pdo->prepare(
        "SELECT id, battle_token, user_id, state_json, mode, result, turns_played, user_hp_final, enemy_hp_final, xp_gained, knowledge_energy_gained, rank_gained
         FROM knd_mind_wars_battles
         WHERE battle_token = ?
         LIMIT 1"
    );
    $stmt->execute([$battleToken]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        json_error('BATTLE_NOT_FOUND', 'Battle not found.', 404);
    }
    $mode = mw_normalize_mode((string) ($row['mode'] ?? 'pve'));
    $viewerSide = null;
    $viewerUser = null;
    $opponentUser = null;
    if ($mode === 'pvp_ranked') {
        $part = $pdo->prepare(
            "SELECT side FROM knd_mind_wars_battle_participants
             WHERE battle_id = ? AND user_id = ? LIMIT 1"
        );
        $part->execute([(int) $row['id'], $userId]);
        $viewerSide = $part->fetchColumn();
        if (!$viewerSide) {
            json_error('BATTLE_NOT_FOUND', 'Battle not found.', 404);
        }
        $viewerSide = (string) $viewerSide;

        $usersStmt = $pdo->prepare(
            "SELECT p.user_id, p.side, u.username
             FROM knd_mind_wars_battle_participants p
             JOIN users u ON u.id = p.user_id
             WHERE p.battle_id = ?
             LIMIT 2"
        );
        $usersStmt->execute([(int) $row['id']]);
        while ($ur = $usersStmt->fetch(PDO::FETCH_ASSOC)) {
            $u = [
                'id' => (int) ($ur['user_id'] ?? 0),
                'username' => (string) ($ur['username'] ?? ''),
            ];
            if ((string) ($ur['side'] ?? '') === $viewerSide) {
                $viewerUser = $u;
            } else {
                $opponentUser = $u;
            }
        }

        if (!$viewerUser) {
            $viewerUser = ['id' => $userId, 'username' => 'user_' . $userId];
        }
    } else {
        if ((int) ($row['user_id'] ?? 0) !== $userId) {
            json_error('BATTLE_NOT_FOUND', 'Battle not found.', 404);
        }
    }

    $state = json_decode($row['state_json'], true);
    if (!is_array($state)) {
        $state = [
            'player' => [],
            'enemy' => [],
            'log' => [['type' => 'status', 'msg' => 'Recovered malformed battle state.']],
            'turn' => 1,
            'next_actor' => 'player',
        ];
    }
    $state = mw_normalize_battle_state($state);
    $state['meta']['mode'] = $mode;
    $state['meta']['difficulty'] = mw_normalize_difficulty((string) ($state['meta']['difficulty'] ?? 'normal'));

    $baseResult = $row['result'] !== null ? (string) $row['result'] : null;
    $viewerResult = mw_viewer_result($baseResult, $viewerSide);
    $viewerRewards = null;
    if ($mode === 'pvp_ranked' && $viewerSide && isset($state['meta']['pvp_rewards']) && is_array($state['meta']['pvp_rewards'])) {
        $rw = $state['meta']['pvp_rewards'][$viewerSide] ?? null;
        if (is_array($rw)) {
            $viewerRewards = [
                'xp' => (int) ($rw['xp'] ?? 0),
                'knowledge_energy' => (int) ($rw['knowledge_energy'] ?? 0),
                'rank' => (int) ($rw['rank'] ?? 0),
            ];
        }
    }

    json_success([
        'battle_token' => $row['battle_token'],
        'state' => $state,
        'result' => $viewerResult,
        'turns_played' => (int) $row['turns_played'],
        'user_hp_final' => $row['user_hp_final'] !== null ? (int) $row['user_hp_final'] : null,
        'enemy_hp_final' => $row['enemy_hp_final'] !== null ? (int) $row['enemy_hp_final'] : null,
        'xp_gained' => $viewerRewards ? (int) $viewerRewards['xp'] : ($row['xp_gained'] !== null ? (int) $row['xp_gained'] : null),
        'knowledge_energy_gained' => $viewerRewards ? (int) $viewerRewards['knowledge_energy'] : ($row['knowledge_energy_gained'] !== null ? (int) $row['knowledge_energy_gained'] : null),
        'rank_gained' => $viewerRewards ? (int) $viewerRewards['rank'] : ($row['rank_gained'] !== null ? (int) $row['rank_gained'] : null),
        'viewer_side' => $viewerSide,
        'viewer_user' => $viewerUser,
        'opponent_user' => $opponentUser,
    ]);
} catch (\Throwable $e) {
    error_log('mind-wars/get_battle_state error: ' . $e->getMessage());
    json_error('INTERNAL_ERROR', 'An unexpected error occurred.', 500);
}

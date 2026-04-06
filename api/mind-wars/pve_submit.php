<?php
require_once __DIR__ . '/../../config/bootstrap.php';
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
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    rate_limit_guard($pdo, "mw_pve_submit_user:{$userId}", 30, 60);
    rate_limit_guard($pdo, "mw_pve_submit_ip:{$ip}", 60, 60);

    $avatarItemId = (int) ($_POST['avatar_item_id'] ?? 0);
    if ($avatarItemId < 1) {
        json_error('INVALID_AVATAR', 'Select an avatar.', 400);
    }

    $rawResult = trim((string) ($_POST['result'] ?? ''));
    $result = ($rawResult === 'win' || $rawResult === 'lose') ? $rawResult : 'lose';
    if ($rawResult === 'surrender') {
        $result = 'lose';
    }

    $avatar = mw_validate_owned_avatar($pdo, $userId, $avatarItemId);
    if (!$avatar) {
        json_error('AVATAR_NOT_OWNED', 'You do not own this avatar.', 403);
    }

    $rewards = mw_rewards_for_result($result);

    $pdo->beginTransaction();
    try {
        if ($avatarItemId > 0) {
            $avatarLock = $pdo->prepare("SELECT knowledge_energy, avatar_level FROM knd_user_avatar_inventory WHERE user_id = ? AND item_id = ? LIMIT 1 FOR UPDATE");
            $avatarLock->execute([$userId, $avatarItemId]);
            $avatarRow = $avatarLock->fetch(PDO::FETCH_ASSOC);
            if ($avatarRow) {
                $before = kd_avatar_progress($avatarRow);
                $after = kd_apply_progress_total((int) $before['total'], (int) $before['level'], (int) ($rewards['knowledge_energy'] ?? 0), 'kd_ke_required_for_level');
                $pdo->prepare("UPDATE knd_user_avatar_inventory SET knowledge_energy = ?, avatar_level = ? WHERE user_id = ? AND item_id = ?")
                    ->execute([(int) $after['total'], (int) $after['level'], $userId, $avatarItemId]);
            }
        }

        $userXp = $pdo->prepare("SELECT xp, level FROM knd_user_xp WHERE user_id = ? LIMIT 1 FOR UPDATE");
        $userXp->execute([$userId]);
        $ux = $userXp->fetch(PDO::FETCH_ASSOC);
        $userTotalBefore = $ux ? (int) ($ux['xp'] ?? 0) : 0;
        $userLevelBefore = $ux ? max(1, (int) ($ux['level'] ?? 1)) : 1;
        $userBefore = kd_normalize_total_and_level($userTotalBefore, $userLevelBefore, 'kd_xp_required_for_level');
        $userAfter = kd_apply_progress_total((int) $userBefore['total'], (int) $userBefore['level'], (int) ($rewards['xp'] ?? 0), 'kd_xp_required_for_level');
        if ($ux) {
            $pdo->prepare("UPDATE knd_user_xp SET xp = ?, level = ? WHERE user_id = ?")->execute([(int) $userAfter['total'], (int) $userAfter['level'], $userId]);
        } else {
            $pdo->prepare("INSERT INTO knd_user_xp (user_id, xp, level) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE xp = VALUES(xp), level = VALUES(level)")
                ->execute([$userId, (int) $userAfter['total'], (int) $userAfter['level']]);
        }

        $season = mw_ensure_season($pdo);
        $pdo->prepare(
            "INSERT INTO knd_mind_wars_rankings (season_id, user_id, rank_score, wins, losses) VALUES (?, ?, ?, 0, 0)
             ON DUPLICATE KEY UPDATE rank_score = rank_score + ?, wins = wins + ?, losses = losses + ?"
        )->execute([
            (int) $season['id'],
            $userId,
            (int) ($rewards['rank'] ?? 0),
            (int) ($rewards['rank'] ?? 0),
            $result === 'win' ? 1 : 0,
            $result === 'lose' ? 1 : 0,
        ]);

        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    json_success([
        'rewards' => $rewards,
        'result' => $result,
    ]);
} catch (\Throwable $e) {
    error_log('mind-wars/pve_submit error: ' . $e->getMessage());
    if (stripos($e->getMessage(), 'AGUACATE') !== false) {
        json_error('AGUACATE', $e->getMessage(), 400);
    }
    json_error('INTERNAL_ERROR', $e->getMessage(), 500);
}

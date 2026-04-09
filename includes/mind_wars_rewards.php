<?php
/**
 * Mind Wars - Apply battle rewards to user.
 * Requires: mind_wars.php, knowledge_duel.php
 */

if (!function_exists('mw_apply_rewards_to_user')) {
function mw_apply_rewards_to_user(PDO $pdo, int $userId, int $avatarItemId, array $rewards, string $result): void {
    if (!mw_user_exists_in_db($pdo, $userId)) {
        error_log(sprintf('mw_apply_rewards_to_user: skip user_id=%d (no users row; orphan PvP participant or stale id)', $userId));
        return;
    }
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
}
}

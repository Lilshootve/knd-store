<?php
// KND XP & Level system - single source of truth
// Level curve: required_xp_to_reach_level(L) = 100 * (L^2)
// Max level: 30. XP keeps accumulating at 30 for leaderboard.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/knd_badges.php';

define('XP_MAX_LEVEL', 30);

/**
 * Deterministic level from total XP. Level 1..30.
 * required_xp_to_reach_level(L) = 100 * L^2
 * Level L when 100*(L-1)^2 <= xp < 100*L^2 (L>=1); level 1 at 0-99 XP.
 */
function xp_calc_level(int $xp): int {
    if ($xp < 0) return 1;
    $l = (int) floor(sqrt($xp / 100)) + 1;
    return min(XP_MAX_LEVEL, max(1, $l));
}

function get_active_season(PDO $pdo): ?array {
    $pdo->prepare(
        "UPDATE knd_seasons SET is_active = 0 WHERE is_active = 1 AND ends_at <= NOW()"
    )->execute();

    $stmt = $pdo->prepare(
        "SELECT * FROM knd_seasons WHERE is_active = 1 AND starts_at <= NOW() AND ends_at > NOW() ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function ensure_active_season(PDO $pdo): ?array {
    $season = get_active_season($pdo);
    if ($season) return $season;

    $pdo->prepare(
        "INSERT INTO knd_seasons (code, name, starts_at, ends_at, is_active) VALUES ('GENESIS_S1', 'KND Genesis Season', NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), 1)"
    )->execute();
    return get_active_season($pdo);
}

/**
 * Add XP to user. Single source of truth.
 * @param string $source e.g. 'lastroll_win', 'lastroll_lose', 'insight_win', 'insight_lose', 'daily_day7', 'drop_reward', 'mission_reward'
 * @param string|null $refType e.g. 'lastroll_game', 'above_under_roll', 'knd_drop'
 * @param int|null $refId
 * @return array{level_up:bool,old_level:int,new_level:int}
 */
function xp_add(PDO $pdo, int $userId, int $xpDelta, string $source, ?string $refType = null, ?int $refId = null): array {
    $noLevelUp = ['level_up' => false, 'old_level' => 0, 'new_level' => 0];
    if ($xpDelta <= 0) return $noLevelUp;

    $countMatch = in_array($source, ['lastroll_win', 'lastroll_lose', 'insight_win', 'insight_lose'], true);
    $isWin = in_array($source, ['lastroll_win', 'insight_win'], true) ? true : (in_array($source, ['lastroll_lose', 'insight_lose'], true) ? false : null);

    try {
        $season = ensure_active_season($pdo);
    } catch (\Throwable $e) {
        $season = null;
    }
    $sid = $season ? (int) $season['id'] : null;

    $ownTx = !$pdo->inTransaction();
    if ($ownTx) $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT xp FROM knd_user_xp WHERE user_id = ? FOR UPDATE");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $oldXp = $row ? (int) $row['xp'] : 0;
        $oldLevel = xp_calc_level($oldXp);
        $newXp = $oldXp + $xpDelta;
        $level = xp_calc_level($newXp);

        $pdo->prepare(
            "INSERT INTO knd_user_xp (user_id, xp, level, updated_at) VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
               xp = xp + ?,
               level = LEAST(30, GREATEST(1, FLOOR(SQRT((xp + ?) / 100)) + 1)),
               updated_at = NOW()"
        )->execute([$userId, $newXp, $level, $xpDelta, $xpDelta]);

        if ($sid && $countMatch) {
            $w = $isWin ? 1 : 0;
            $l = $isWin ? 0 : 1;
            $pdo->prepare(
                "INSERT INTO knd_season_stats (season_id, user_id, xp_earned, matches_played, wins, losses, updated_at)
                 VALUES (?, ?, ?, 1, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE
                   xp_earned = xp_earned + ?,
                   matches_played = matches_played + 1,
                   wins = wins + ?,
                   losses = losses + ?,
                   updated_at = NOW()"
            )->execute([$sid, $userId, $xpDelta, $w, $l, $xpDelta, $w, $l]);
        } elseif ($sid) {
            $pdo->prepare(
                "INSERT INTO knd_season_stats (season_id, user_id, xp_earned, matches_played, wins, losses, updated_at)
                 VALUES (?, ?, ?, 0, 0, 0, NOW())
                 ON DUPLICATE KEY UPDATE xp_earned = xp_earned + ?, updated_at = NOW()"
            )->execute([$sid, $userId, $xpDelta, $xpDelta]);
        }

        // Sync user_xp for leaderboard / legacy readers
        $pdo->prepare(
            "INSERT INTO user_xp (user_id, xp, updated_at) VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE xp = xp + ?, updated_at = NOW()"
        )->execute([$userId, $xpDelta, $xpDelta]);

        // Optional audit log (table may not exist)
        try {
            $pdo->prepare(
                "INSERT INTO knd_xp_ledger (user_id, xp_delta, source, ref_type, ref_id) VALUES (?, ?, ?, ?, ?)"
            )->execute([$userId, $xpDelta, $source, $refType, $refId]);
        } catch (\Throwable $e) { /* ignore if table missing */ }

        if ($ownTx) $pdo->commit();

        $levelUp = $level > $oldLevel;
        return [
            'level_up'   => $levelUp,
            'old_level'  => $oldLevel,
            'new_level'  => $level,
            'new_xp'     => $newXp,
        ];
    } catch (\Throwable $e) {
        if ($ownTx && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/**
 * Admin-only: adjust user XP by delta (positive or negative). Updates knd_user_xp and user_xp.
 * @return array{level:int,xp:int}
 */
function xp_admin_adjust(PDO $pdo, int $userId, int $delta): array {
    $ownTx = !$pdo->inTransaction();
    if ($ownTx) $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT xp FROM knd_user_xp WHERE user_id = ? FOR UPDATE');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $oldXp = $row ? (int) $row['xp'] : 0;
        $newXp = max(0, $oldXp + $delta);
        $level = xp_calc_level($newXp);

        $pdo->prepare(
            'INSERT INTO knd_user_xp (user_id, xp, level, updated_at) VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE xp = ?, level = ?, updated_at = NOW()'
        )->execute([$userId, $newXp, $level, $newXp, $level]);

        $diff = $newXp - $oldXp;
        $pdo->prepare(
            'INSERT INTO user_xp (user_id, xp, updated_at) VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE xp = xp + ?, updated_at = NOW()'
        )->execute([$userId, $newXp, $diff]);

        if ($ownTx) $pdo->commit();
        if (isset($_SESSION['xp_badge_cache'])) unset($_SESSION['xp_badge_cache']);
        return ['level' => $level, 'xp' => $newXp];
    } catch (\Throwable $e) {
        if ($ownTx && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

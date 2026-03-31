<?php
// KND Arena - Daily Login & Missions helpers

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/support_credits.php';
require_once __DIR__ . '/knd_xp.php';

$DAILY_REWARDS_KP = [1 => 20, 2 => 25, 3 => 30, 4 => 35, 5 => 40, 6 => 45, 7 => 60];
$DAILY_DAY7_BONUS_XP = 20;

function daily_get_status(PDO $pdo, int $userId): array {
    global $DAILY_REWARDS_KP;

    $today = gmdate('Y-m-d');
    $yesterday = gmdate('Y-m-d', strtotime('-1 day'));

    $stmt = $pdo->prepare('SELECT streak, last_claim_date FROM knd_daily_login WHERE user_id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $streak = 0;
    $lastClaim = null;
    $canClaim = true;

    if ($row) {
        $lastClaim = $row['last_claim_date'];
        $streak = (int) $row['streak'];

        if ($lastClaim === $today) {
            $canClaim = false;
        } elseif ($lastClaim === $yesterday) {
            // streak continues, next day will be streak+1
        } else {
            $streak = 0;
        }
    }

    $nextDay = $canClaim ? min($streak + 1, 7) : $streak;
    if ($nextDay < 1) $nextDay = 1;
    $todayReward = $canClaim ? ($DAILY_REWARDS_KP[$nextDay] ?? 20) : 0;

    return [
        'streak'          => $streak,
        'next_day'        => $nextDay,
        'can_claim'       => $canClaim,
        'today_reward_kp' => $todayReward,
        'is_day7'         => $canClaim && $nextDay === 7,
        'last_claim_date' => $lastClaim,
        'rewards_map'     => $DAILY_REWARDS_KP,
    ];
}

function daily_claim(PDO $pdo, int $userId): array {
    global $DAILY_REWARDS_KP, $DAILY_DAY7_BONUS_XP;

    $today = gmdate('Y-m-d');
    $yesterday = gmdate('Y-m-d', strtotime('-1 day'));
    $now = gmdate('Y-m-d H:i:s');

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT streak, last_claim_date FROM knd_daily_login WHERE user_id = ? FOR UPDATE');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $streak = 0;
        $lastClaim = null;

        if ($row) {
            $lastClaim = $row['last_claim_date'];
            $streak = (int) $row['streak'];

            if ($lastClaim === $today) {
                $pdo->rollBack();
                return ['error' => 'ALREADY_CLAIMED', 'message' => 'Already claimed today.'];
            }

            if ($lastClaim === $yesterday) {
                $streak++;
            } else {
                $streak = 1;
            }
        } else {
            $streak = 1;
        }

        if ($streak > 7) $streak = 1;

        $rewardKp = $DAILY_REWARDS_KP[$streak] ?? 20;
        $bonusXp = ($streak === 7) ? $DAILY_DAY7_BONUS_XP : 0;

        if ($row) {
            $pdo->prepare('UPDATE knd_daily_login SET streak = ?, last_claim_date = ?, updated_at = ? WHERE user_id = ?')
                ->execute([$streak, $today, $now, $userId]);
        } else {
            $pdo->prepare('INSERT INTO knd_daily_login (user_id, streak, last_claim_date, updated_at) VALUES (?, ?, ?, ?)')
                ->execute([$userId, $streak, $today, $now]);
        }

        $expiresAt = gmdate('Y-m-d H:i:s', strtotime('+12 months'));
        $pdo->prepare(
            "INSERT INTO points_ledger (user_id, source_type, source_id, entry_type, status, points, available_at, expires_at, created_at)
             VALUES (?, 'daily_login', 0, 'earn', 'available', ?, ?, ?, ?)"
        )->execute([$userId, $rewardKp, $now, $expiresAt, $now]);

        $levelUp = null;
        $xpRes = null;
        if ($bonusXp > 0) {
            $xpRes = xp_add($pdo, $userId, $bonusXp, 'daily_day7', null, null);
            if ($xpRes['level_up']) {
                $levelUp = ['level_up' => true, 'old_level' => $xpRes['old_level'], 'new_level' => $xpRes['new_level']];
            }
        }

        $pdo->commit();
        unset($_SESSION['sc_badge_cache'], $_SESSION['xp_badge_cache']);

        $out = [
            'ok'        => true,
            'streak'    => $streak,
            'reward_kp' => $rewardKp,
            'bonus_xp'  => $bonusXp,
            'balance'   => get_available_points($pdo, $userId),
        ];
        if ($bonusXp > 0 && $xpRes) {
            $out['xp_delta'] = $bonusXp;
            $out['xp_total'] = $xpRes['new_xp'] ?? 0;
            $out['level'] = $xpRes['new_level'];
        }
        if ($levelUp) {
            $out['level_up'] = true;
            $out['old_level'] = $levelUp['old_level'];
            $out['new_level'] = $levelUp['new_level'];
        } else {
            $out['level_up'] = false;
        }
        return $out;
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function missions_get_today(PDO $pdo, int $userId): array {
    $today = gmdate('Y-m-d');

    $missions = $pdo->prepare('SELECT * FROM knd_daily_missions WHERE is_active = 1 ORDER BY id');
    $missions->execute();
    $allMissions = $missions->fetchAll(PDO::FETCH_ASSOC);

    if (empty($allMissions)) return [];

    $missionIds = array_column($allMissions, 'id');
    $placeholders = implode(',', array_fill(0, count($missionIds), '?'));
    $params = array_merge([$userId, $today], $missionIds);
    $stmt = $pdo->prepare(
        "SELECT mission_id, progress, completed_date, claimed_date
         FROM knd_user_mission_progress
         WHERE user_id = ? AND progress_date = ? AND mission_id IN ({$placeholders})"
    );
    $stmt->execute($params);
    $progressMap = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $progressMap[(int)$p['mission_id']] = $p;
    }

    $result = [];
    foreach ($allMissions as $m) {
        $mid = (int)$m['id'];
        $prog = $progressMap[$mid] ?? null;
        $progress = $prog ? (int)$prog['progress'] : 0;
        $completed = $prog && $prog['completed_date'] !== null;
        $claimed = $prog && $prog['claimed_date'] !== null;

        $result[] = [
            'code'        => $m['code'],
            'title'       => $m['title'],
            'description' => $m['description'],
            'target'      => (int)$m['target'],
            'reward_kp'   => (int)$m['reward_kp'],
            'reward_xp'   => (int)$m['reward_xp'],
            'progress'    => min($progress, (int)$m['target']),
            'completed'   => $completed,
            'claimed'     => $claimed,
            'can_claim'   => $completed && !$claimed,
        ];
    }
    return $result;
}

function mission_increment(PDO $pdo, int $userId, string $missionCode, int $amount = 1): void {
    $today = gmdate('Y-m-d');
    $now = gmdate('Y-m-d H:i:s');

    try {
        $stmt = $pdo->prepare('SELECT id, target FROM knd_daily_missions WHERE code = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$missionCode]);
        $mission = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$mission) return;

        $mid = (int)$mission['id'];
        $target = (int)$mission['target'];

        $stmt = $pdo->prepare(
            'INSERT INTO knd_user_mission_progress (user_id, mission_id, progress_date, progress, updated_at)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE progress = progress + ?, updated_at = ?'
        );
        $stmt->execute([$userId, $mid, $today, $amount, $now, $amount, $now]);

        $stmt = $pdo->prepare(
            'SELECT progress, completed_date FROM knd_user_mission_progress
             WHERE user_id = ? AND mission_id = ? AND progress_date = ?'
        );
        $stmt->execute([$userId, $mid, $today]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && (int)$row['progress'] >= $target && $row['completed_date'] === null) {
            $pdo->prepare(
                'UPDATE knd_user_mission_progress SET completed_date = ? WHERE user_id = ? AND mission_id = ? AND progress_date = ?'
            )->execute([$today, $userId, $mid, $today]);
        }
    } catch (\Throwable $e) {
        error_log('mission_increment error: ' . $e->getMessage());
    }
}

function mission_claim(PDO $pdo, int $userId, string $missionCode): array {
    $today = gmdate('Y-m-d');
    $now = gmdate('Y-m-d H:i:s');

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT id, reward_kp, reward_xp FROM knd_daily_missions WHERE code = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$missionCode]);
        $mission = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$mission) {
            $pdo->rollBack();
            return ['error' => 'MISSION_NOT_FOUND', 'message' => 'Mission not found.'];
        }

        $mid = (int)$mission['id'];

        $stmt = $pdo->prepare(
            'SELECT completed_date, claimed_date FROM knd_user_mission_progress
             WHERE user_id = ? AND mission_id = ? AND progress_date = ? FOR UPDATE'
        );
        $stmt->execute([$userId, $mid, $today]);
        $prog = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$prog || $prog['completed_date'] === null) {
            $pdo->rollBack();
            return ['error' => 'NOT_COMPLETED', 'message' => 'Mission not completed yet.'];
        }
        if ($prog['claimed_date'] !== null) {
            $pdo->rollBack();
            return ['error' => 'ALREADY_CLAIMED', 'message' => 'Already claimed today.'];
        }

        $pdo->prepare(
            'UPDATE knd_user_mission_progress SET claimed_date = ?, updated_at = ?
             WHERE user_id = ? AND mission_id = ? AND progress_date = ?'
        )->execute([$today, $now, $userId, $mid, $today]);

        $rewardKp = (int)$mission['reward_kp'];
        $rewardXp = (int)$mission['reward_xp'];

        if ($rewardKp > 0) {
            $expiresAt = gmdate('Y-m-d H:i:s', strtotime('+12 months'));
            $pdo->prepare(
                "INSERT INTO points_ledger (user_id, source_type, source_id, entry_type, status, points, available_at, expires_at, created_at)
                 VALUES (?, 'daily_mission', ?, 'earn', 'available', ?, ?, ?, ?)"
            )->execute([$userId, $mid, $rewardKp, $now, $expiresAt, $now]);
        }

        $levelUp = null;
        $xpRes = null;
        if ($rewardXp > 0) {
            $xpRes = xp_add($pdo, $userId, $rewardXp, 'mission_reward', 'daily_mission', $mid);
            if ($xpRes['level_up']) {
                $levelUp = ['level_up' => true, 'old_level' => $xpRes['old_level'], 'new_level' => $xpRes['new_level']];
            }
        }

        $pdo->commit();
        unset($_SESSION['sc_badge_cache'], $_SESSION['xp_badge_cache']);

        $out = [
            'ok'        => true,
            'code'      => $missionCode,
            'reward_kp' => $rewardKp,
            'reward_xp' => $rewardXp,
            'balance'   => get_available_points($pdo, $userId),
        ];
        if ($rewardXp > 0 && $xpRes) {
            $out['xp_delta'] = $rewardXp;
            $out['xp_total'] = $xpRes['new_xp'] ?? 0;
            $out['level'] = $xpRes['new_level'];
        }
        if ($levelUp) {
            $out['level_up'] = true;
            $out['old_level'] = $levelUp['old_level'];
            $out['new_level'] = $levelUp['new_level'];
        } else {
            $out['level_up'] = false;
        }
        return $out;
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

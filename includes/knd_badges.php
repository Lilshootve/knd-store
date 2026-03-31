<?php
// KND Badges - Milestone-based badge system

require_once __DIR__ . '/config.php';

/**
 * Get all active badges
 * @return array Array of badge definitions
 */
function badges_get_all(PDO $pdo): array {
    $stmt = $pdo->prepare(
        "SELECT id, code, name, description, icon_path, unlock_type, unlock_threshold 
         FROM knd_badges 
         WHERE is_active = 1 
         ORDER BY unlock_type, unlock_threshold"
    );
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get user's unlocked badges
 * @return array Array of badge data with unlock timestamps
 */
function badges_get_user_badges(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare(
        "SELECT b.id, b.code, b.name, b.description, b.icon_path, b.unlock_type, b.unlock_threshold, ub.unlocked_at
         FROM knd_user_badges ub
         JOIN knd_badges b ON b.id = ub.badge_id
         WHERE ub.user_id = ? AND b.is_active = 1
         ORDER BY ub.unlocked_at DESC"
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Check if user has a specific badge
 * @param int $userId User ID
 * @param string $badgeCode Badge code (e.g., 'GENERATOR_10')
 * @return bool True if user has the badge
 */
function badges_user_has_badge(PDO $pdo, int $userId, string $badgeCode): bool {
    $stmt = $pdo->prepare(
        "SELECT 1 FROM knd_user_badges ub
         JOIN knd_badges b ON b.id = ub.badge_id
         WHERE ub.user_id = ? AND b.code = ? AND b.is_active = 1"
    );
    $stmt->execute([$userId, $badgeCode]);
    return (bool)$stmt->fetch();
}

/**
 * Grant a badge to a user (idempotent)
 * @param int $userId User ID
 * @param string $badgeCode Badge code
 * @return bool True if badge was newly granted, false if already owned
 */
function badges_grant_badge(PDO $pdo, int $userId, string $badgeCode): bool {
    // Get badge ID
    $stmt = $pdo->prepare("SELECT id FROM knd_badges WHERE code = ? AND is_active = 1");
    $stmt->execute([$badgeCode]);
    $badge = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$badge) {
        error_log("Badge not found: $badgeCode");
        return false;
    }
    
    $badgeId = (int)$badge['id'];
    
    // Grant badge (INSERT IGNORE makes it idempotent)
    $stmt = $pdo->prepare(
        "INSERT IGNORE INTO knd_user_badges (user_id, badge_id, unlocked_at) 
         VALUES (?, ?, NOW())"
    );
    $stmt->execute([$userId, $badgeId]);
    
    return $stmt->rowCount() > 0;
}

/**
 * Get user's milestone counts for badge eligibility
 * @return array Associative array with counts for each milestone type
 */
function badges_get_user_milestones(PDO $pdo, int $userId): array {
    $milestones = [
        'generator' => 0,
        'drop' => 0,
        'collector' => 0,
        'legendary_pull' => 0,
        'level' => 0,
        'mind_wars_wins' => 0,
        'mind_wars_streak' => 0,
        'mind_wars_special' => 0,
        'mind_wars_legendary' => 0,
    ];
    
    // Generator count: completed labs jobs
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM knd_labs_jobs WHERE user_id = ? AND status = 'done'"
    );
    $stmt->execute([$userId]);
    $milestones['generator'] = (int)$stmt->fetchColumn();
    
    // Drop count: total drops
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM knd_drops WHERE user_id = ?"
    );
    $stmt->execute([$userId]);
    $milestones['drop'] = (int)$stmt->fetchColumn();
    
    // Collector count: unique avatar items owned
    $stmt = $pdo->prepare(
        "SELECT COUNT(DISTINCT item_id) FROM knd_user_avatar_inventory WHERE user_id = ?"
    );
    $stmt->execute([$userId]);
    $milestones['collector'] = (int)$stmt->fetchColumn();
    
    // Legendary pull: check if user has any legendary drops
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM knd_drops WHERE user_id = ? AND rarity = 'legendary'"
    );
    $stmt->execute([$userId]);
    $milestones['legendary_pull'] = (int)$stmt->fetchColumn();
    
    // Level: current user level
    $stmt = $pdo->prepare(
        "SELECT level FROM knd_user_xp WHERE user_id = ?"
    );
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $milestones['level'] = $row ? (int)$row['level'] : 1;
    
    // Mind Wars milestones (tables may not exist in older installs)
    try {
        // PvE wins: battles without participants
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM knd_mind_wars_battles b
             WHERE b.user_id = ? AND b.result = 'win'
             AND NOT EXISTS (SELECT 1 FROM knd_mind_wars_battle_participants p WHERE p.battle_id = b.id)"
        );
        $stmt->execute([$userId]);
        $pveWins = (int)$stmt->fetchColumn();
        
        // PvP wins: from participants
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM knd_mind_wars_battle_participants p
             JOIN knd_mind_wars_battles b ON b.id = p.battle_id
             WHERE p.user_id = ? AND b.result IS NOT NULL
             AND ((p.side = 'player' AND b.result = 'win') OR (p.side = 'enemy' AND b.result = 'lose'))"
        );
        $stmt->execute([$userId]);
        $pvpWins = (int)$stmt->fetchColumn();
        
        $milestones['mind_wars_wins'] = $pveWins + $pvpWins;
        
        // Win streak: battles ordered by created_at DESC, count consecutive wins
        $battles = [];
        $stmt = $pdo->prepare(
            "SELECT b.result, b.created_at FROM knd_mind_wars_battles b
             WHERE b.user_id = ? AND b.result IS NOT NULL
             AND NOT EXISTS (SELECT 1 FROM knd_mind_wars_battle_participants p WHERE p.battle_id = b.id)
             ORDER BY b.created_at DESC"
        );
        $stmt->execute([$userId]);
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $battles[] = ['won' => $r['result'] === 'win', 'created_at' => $r['created_at']];
        }
        $stmt = $pdo->prepare(
            "SELECT b.result, b.created_at, p.side FROM knd_mind_wars_battle_participants p
             JOIN knd_mind_wars_battles b ON b.id = p.battle_id
             WHERE p.user_id = ? AND b.result IS NOT NULL
             ORDER BY b.created_at DESC"
        );
        $stmt->execute([$userId]);
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $won = ($r['side'] === 'player' && $r['result'] === 'win') || ($r['side'] === 'enemy' && $r['result'] === 'lose');
            $battles[] = ['won' => $won, 'created_at' => $r['created_at']];
        }
        usort($battles, function ($a, $b) {
            return strcmp($b['created_at'], $a['created_at']);
        });
        $streak = 0;
        foreach ($battles as $b) {
            if ($b['won']) {
                $streak++;
            } else {
                break;
            }
        }
        $milestones['mind_wars_streak'] = $streak;
        
        // Special uses: count action_type='special' in battle_log_json
        $specialCount = 0;
        $stmt = $pdo->prepare(
            "SELECT b.id, b.battle_log_json, b.user_id FROM knd_mind_wars_battles b
             WHERE b.user_id = ? AND b.battle_log_json IS NOT NULL
             AND NOT EXISTS (SELECT 1 FROM knd_mind_wars_battle_participants p WHERE p.battle_id = b.id)"
        );
        $stmt->execute([$userId]);
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $log = json_decode($r['battle_log_json'], true);
            if (is_array($log)) {
                foreach ($log as $e) {
                    if (isset($e['action_type']) && $e['action_type'] === 'special' && isset($e['actor']) && $e['actor'] === 'player') {
                        $specialCount++;
                    }
                }
            }
        }
        $stmt = $pdo->prepare(
            "SELECT b.id, b.battle_log_json FROM knd_mind_wars_battle_participants p
             JOIN knd_mind_wars_battles b ON b.id = p.battle_id
             WHERE p.user_id = ? AND b.battle_log_json IS NOT NULL"
        );
        $stmt->execute([$userId]);
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $stmtSide = $pdo->prepare("SELECT side FROM knd_mind_wars_battle_participants WHERE battle_id = ? AND user_id = ?");
            $stmtSide->execute([$r['id'], $userId]);
            $userSide = $stmtSide->fetchColumn();
            $log = json_decode($r['battle_log_json'], true);
            if (is_array($log)) {
                foreach ($log as $e) {
                    if (isset($e['action_type']) && $e['action_type'] === 'special' && isset($e['actor']) && $e['actor'] === $userSide) {
                        $specialCount++;
                    }
                }
            }
        }
        $milestones['mind_wars_special'] = $specialCount;
        
        // Legendary defeated: PvE enemy_avatar_id in legendary, or PvP defeated opponent's legendary
        $legendaryCount = 0;
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM knd_mind_wars_battles b
             JOIN knd_avatar_items ai ON ai.id = b.enemy_avatar_id AND ai.rarity = 'legendary'
             WHERE b.user_id = ? AND b.result = 'win'
             AND NOT EXISTS (SELECT 1 FROM knd_mind_wars_battle_participants p WHERE p.battle_id = b.id)"
        );
        $stmt->execute([$userId]);
        $legendaryCount += (int)$stmt->fetchColumn();
        $stmt = $pdo->prepare(
            "SELECT b.id FROM knd_mind_wars_battle_participants p
             JOIN knd_mind_wars_battles b ON b.id = p.battle_id
             WHERE p.user_id = ? AND b.result IS NOT NULL
             AND ((p.side = 'player' AND b.result = 'win') OR (p.side = 'enemy' AND b.result = 'lose'))"
        );
        $stmt->execute([$userId]);
        $wonBattleIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($wonBattleIds as $bid) {
            $stmtDefeated = $pdo->prepare(
                "SELECT p2.avatar_item_id FROM knd_mind_wars_battle_participants p
                 JOIN knd_mind_wars_battle_participants p2 ON p2.battle_id = p.battle_id AND p2.user_id != p.user_id
                 WHERE p.battle_id = ? AND p.user_id = ?"
            );
            $stmtDefeated->execute([$bid, $userId]);
            $defeatedAvatarId = $stmtDefeated->fetchColumn();
            if ($defeatedAvatarId) {
                $stmtLeg = $pdo->prepare("SELECT 1 FROM knd_avatar_items WHERE id = ? AND rarity = 'legendary'");
                $stmtLeg->execute([$defeatedAvatarId]);
                if ($stmtLeg->fetch()) {
                    $legendaryCount++;
                }
            }
        }
        $milestones['mind_wars_legendary'] = $legendaryCount;
    } catch (Throwable $e) {
        // Mind Wars tables may not exist
    }
    
    return $milestones;
}

/**
 * Check and grant eligible badges for a user based on a specific unlock type
 * @param int $userId User ID
 * @param string $unlockType Type of unlock to check (generator, drop, collector, legendary_pull, level)
 * @return array Array of newly granted badge codes
 */
function badges_check_and_grant(PDO $pdo, int $userId, string $unlockType): array {
    $validTypes = ['generator', 'drop', 'collector', 'legendary_pull', 'level', 'mind_wars_wins', 'mind_wars_streak', 'mind_wars_special', 'mind_wars_legendary'];
    if (!in_array($unlockType, $validTypes, true)) {
        return [];
    }
    
    // Get user's current milestone counts
    $milestones = badges_get_user_milestones($pdo, $userId);
    $currentCount = $milestones[$unlockType] ?? 0;
    
    // Get all badges for this unlock type
    $stmt = $pdo->prepare(
        "SELECT id, code, unlock_threshold 
         FROM knd_badges 
         WHERE unlock_type = ? AND is_active = 1 
         ORDER BY unlock_threshold ASC"
    );
    $stmt->execute([$unlockType]);
    $badges = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $newlyGranted = [];
    
    foreach ($badges as $badge) {
        $threshold = (int)$badge['unlock_threshold'];
        $code = $badge['code'];
        
        // Check if user meets threshold and doesn't already have badge
        if ($currentCount >= $threshold && !badges_user_has_badge($pdo, $userId, $code)) {
            if (badges_grant_badge($pdo, $userId, $code)) {
                $newlyGranted[] = $code;
            }
        }
    }
    
    return $newlyGranted;
}

/**
 * Check all badge types for a user and grant eligible badges
 * @param int $userId User ID
 * @return array Array of newly granted badge codes
 */
function badges_check_all(PDO $pdo, int $userId): array {
    $allNewBadges = [];
    $types = ['generator', 'drop', 'collector', 'legendary_pull', 'level', 'mind_wars_wins', 'mind_wars_streak', 'mind_wars_special', 'mind_wars_legendary'];
    
    foreach ($types as $type) {
        $newBadges = badges_check_and_grant($pdo, $userId, $type);
        $allNewBadges = array_merge($allNewBadges, $newBadges);
    }
    
    return $allNewBadges;
}

/**
 * Get user's badge progress for display
 * @return array Array with badge progress information
 */
function badges_get_user_progress(PDO $pdo, int $userId): array {
    $milestones = badges_get_user_milestones($pdo, $userId);
    $allBadges = badges_get_all($pdo);
    $userBadges = badges_get_user_badges($pdo, $userId);
    
    $unlockedCodes = array_column($userBadges, 'code');
    
    $progress = [];
    foreach ($allBadges as $badge) {
        $unlocked = in_array($badge['code'], $unlockedCodes, true);
        $currentCount = $milestones[$badge['unlock_type']] ?? 0;
        $threshold = (int)$badge['unlock_threshold'];
        
        $progress[] = [
            'code' => $badge['code'],
            'name' => $badge['name'],
            'description' => $badge['description'],
            'icon_path' => $badge['icon_path'],
            'unlock_type' => $badge['unlock_type'],
            'threshold' => $threshold,
            'current' => $currentCount,
            'unlocked' => $unlocked,
            'progress_percent' => $threshold > 0 ? min(100, round(($currentCount / $threshold) * 100)) : 100,
        ];
    }
    
    return $progress;
}

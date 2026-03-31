<?php
// KND Drop Chamber - business logic

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/support_credits.php';
require_once __DIR__ . '/knd_xp.php';
require_once __DIR__ . '/knd_badges.php';
require_once __DIR__ . '/knd_avatar.php';

if (!defined('DROP_ENTRY_KP')) define('DROP_ENTRY_KP', 100);
if (!defined('DROP_COOLDOWN_SEC')) define('DROP_COOLDOWN_SEC', 3);

$DROP_XP_MAP = [
    'common'    => 2,
    'special'   => 3,
    'rare'      => 4,
    'epic'      => 7,
    'legendary' => 12,
];

// Fragment values for duplicate avatar items
$FRAGMENT_VALUES = [
    'common'    => 5,
    'special'   => 15,
    'rare'      => 30,
    'epic'      => 75,
    'legendary' => 200,
];

// Base rarity weights for avatar drops
$RARITY_WEIGHTS = [
    'common'    => 55,
    'special'   => 25,
    'rare'      => 12,
    'epic'      => 6,
    'legendary' => 2,
];

// Pity system constants
define('PITY_BOOST_PER_DROP', 2);  // Add 2% to rare+ chance per drop
define('PITY_MAX_BOOST', 30);      // Cap at 30% additional chance

function get_active_drop_season(PDO $pdo): ?array {
    $pdo->prepare(
        "UPDATE knd_drop_seasons SET is_active = 0 WHERE is_active = 1 AND ends_at <= NOW()"
    )->execute();

    $stmt = $pdo->prepare(
        "SELECT * FROM knd_drop_seasons WHERE is_active = 1 AND starts_at <= NOW() AND ends_at > NOW() ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function get_drop_configs_for_display(PDO $pdo, int $seasonId): array {
    $stmt = $pdo->prepare(
        "SELECT rarity, reward_kp FROM knd_drop_configs WHERE season_id = ? AND is_active = 1 ORDER BY FIELD(rarity,'common','rare','epic','legendary'), reward_kp ASC"
    );
    $stmt->execute([$seasonId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $grouped = [];
    foreach ($rows as $r) {
        $grouped[$r['rarity']][] = (int)$r['reward_kp'];
    }
    return $grouped;
}

function pick_drop_config(PDO $pdo, int $seasonId): array {
    $stmt = $pdo->prepare(
        "SELECT * FROM knd_drop_configs WHERE season_id = ? AND is_active = 1"
    );
    $stmt->execute([$seasonId]);
    $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($configs)) {
        throw new \RuntimeException('No drop configs available.');
    }

    $totalWeight = 0;
    foreach ($configs as $c) {
        $totalWeight += (int)$c['weight'];
    }

    $roll = random_int(1, $totalWeight);
    $cumulative = 0;
    foreach ($configs as $c) {
        $cumulative += (int)$c['weight'];
        if ($roll <= $cumulative) {
            return $c;
        }
    }

    return $configs[count($configs) - 1];
}

/**
 * Get or initialize user's pity counter
 */
function get_user_pity(PDO $pdo, int $userId): int {
    $stmt = $pdo->prepare("SELECT drops_since_rare FROM knd_user_pity WHERE user_id = ?");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$row) {
        // Initialize pity counter
        $pdo->prepare("INSERT INTO knd_user_pity (user_id, drops_since_rare) VALUES (?, 0)")->execute([$userId]);
        return 0;
    }
    
    return (int)$row['drops_since_rare'];
}

/**
 * Update user's pity counter
 */
function update_user_pity(PDO $pdo, int $userId, string $rarity): void {
    $isRarePlus = in_array($rarity, ['rare', 'epic', 'legendary']);
    
    if ($isRarePlus) {
        // Reset pity counter
        $pdo->prepare("UPDATE knd_user_pity SET drops_since_rare = 0 WHERE user_id = ?")->execute([$userId]);
    } else {
        // Increment pity counter
        $pdo->prepare(
            "INSERT INTO knd_user_pity (user_id, drops_since_rare) VALUES (?, 1)
             ON DUPLICATE KEY UPDATE drops_since_rare = drops_since_rare + 1"
        )->execute([$userId]);
    }
}

/**
 * Select rarity with weighted random selection and pity boost
 */
function select_rarity_with_pity(PDO $pdo, int $userId): array {
    global $RARITY_WEIGHTS;
    
    $pityCounter = get_user_pity($pdo, $userId);
    $pityBoost = min($pityCounter * PITY_BOOST_PER_DROP, PITY_MAX_BOOST);
    
    // Apply pity boost by reducing common/special and increasing rare+
    $weights = $RARITY_WEIGHTS;
    
    if ($pityBoost > 0) {
        // Calculate total rare+ weight
        $rarePlusTotal = $weights['rare'] + $weights['epic'] + $weights['legendary'];
        
        // Reduce common and special proportionally
        $reduction = $pityBoost;
        $commonReduction = ($weights['common'] / ($weights['common'] + $weights['special'])) * $reduction;
        $specialReduction = $reduction - $commonReduction;
        
        $weights['common'] = max(1, $weights['common'] - $commonReduction);
        $weights['special'] = max(1, $weights['special'] - $specialReduction);
        
        // Distribute boost to rare+ proportionally
        $weights['rare'] += ($weights['rare'] / $rarePlusTotal) * $reduction;
        $weights['epic'] += ($weights['epic'] / $rarePlusTotal) * $reduction;
        $weights['legendary'] += ($weights['legendary'] / $rarePlusTotal) * $reduction;
    }
    
    // Weighted random selection
    $totalWeight = array_sum($weights);
    $roll = mt_rand(1, (int)$totalWeight);
    
    $cumulative = 0;
    foreach ($weights as $rarity => $weight) {
        $cumulative += $weight;
        if ($roll <= $cumulative) {
            return ['rarity' => $rarity, 'pity_boost' => $pityBoost];
        }
    }
    
    return ['rarity' => 'common', 'pity_boost' => $pityBoost];
}

/**
 * Select random avatar item by rarity.
 * Only returns items that exist in mw_avatars (Mind Wars avatars) - matched by name.
 */
function select_avatar_item_by_rarity(PDO $pdo, string $rarity): ?array {
    avatar_sync_items_from_assets($pdo);
    $stmt = $pdo->prepare(
        "SELECT ai.id, ai.code, ai.slot, ai.name, ai.rarity, ai.asset_path
         FROM knd_avatar_items ai
         INNER JOIN mw_avatars mw ON LOWER(TRIM(mw.name)) = LOWER(TRIM(ai.name))
         WHERE mw.rarity = ? AND ai.is_active = 1
         ORDER BY RAND()
         LIMIT 1"
    );
    $stmt->execute([$rarity]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Check if user owns an avatar item
 */
function user_owns_avatar_item(PDO $pdo, int $userId, int $itemId): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM knd_user_avatar_inventory WHERE user_id = ? AND item_id = ?");
    $stmt->execute([$userId, $itemId]);
    return (bool)$stmt->fetch();
}

/**
 * Grant avatar item to user
 */
function grant_avatar_item(PDO $pdo, int $userId, int $itemId): void {
    $now = gmdate('Y-m-d H:i:s');
    $pdo->prepare(
        "INSERT IGNORE INTO knd_user_avatar_inventory (user_id, item_id, acquired_at) VALUES (?, ?, ?)"
    )->execute([$userId, $itemId, $now]);
}

/**
 * Award fragments to user for duplicate item
 */
function award_fragments(PDO $pdo, int $userId, int $amount): void {
    $pdo->prepare(
        "INSERT INTO knd_user_fragments (user_id, amount) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE amount = amount + ?"
    )->execute([$userId, $amount, $amount]);
}

/**
 * Get user's fragment balance
 */
function get_user_fragments(PDO $pdo, int $userId): int {
    $stmt = $pdo->prepare("SELECT amount FROM knd_user_fragments WHERE user_id = ?");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? (int)$row['amount'] : 0;
}

/**
 * Record drop reward in knd_user_drop_rewards
 */
function record_drop_reward(PDO $pdo, int $userId, int $dropId, int $itemId, string $rarity, bool $wasDuplicate, int $fragmentsAwarded, int $pityBoost): void {
    $pdo->prepare(
        "INSERT INTO knd_user_drop_rewards (user_id, drop_id, reward_item_id, rarity, was_duplicate, fragments_awarded, pity_boost)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    )->execute([$userId, $dropId, $itemId, $rarity, $wasDuplicate ? 1 : 0, $fragmentsAwarded, $pityBoost]);
}

function drop_play(PDO $pdo, int $userId): array {
    global $DROP_XP_MAP, $FRAGMENT_VALUES;

    $entryKp = DROP_ENTRY_KP;

    $season = get_active_drop_season($pdo);
    if (!$season) {
        return ['error' => 'NO_SEASON', 'message' => 'No active drop season.'];
    }

    // Cooldown check — done entirely in DB to avoid PHP/DB timezone mismatch
    $stmt = $pdo->prepare(
        "SELECT TIMESTAMPDIFF(SECOND, created_at, NOW()) AS elapsed
         FROM knd_drops WHERE user_id = ? ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([$userId]);
    $lastDrop = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($lastDrop && (int)$lastDrop['elapsed'] < DROP_COOLDOWN_SEC) {
        $wait = DROP_COOLDOWN_SEC - (int)$lastDrop['elapsed'];
        return ['error' => 'COOLDOWN', 'message' => "Wait {$wait}s before next drop."];
    }

    $pdo->beginTransaction();
    try {
        $available = get_available_points($pdo, $userId);
        if ($available < $entryKp) {
            $pdo->rollBack();
            return ['error' => 'INSUFFICIENT_POINTS', 'message' => "Need {$entryKp} KP. You have {$available}."];
        }

        // Debit entry
        $pdo->prepare(
            "INSERT INTO points_ledger (user_id, source_type, source_id, entry_type, status, points, created_at)
             VALUES (?, 'drop_entry', 0, 'spend', 'spent', ?, NOW())"
        )->execute([$userId, -$entryKp]);

        // Select rarity with pity system
        $rarityResult = select_rarity_with_pity($pdo, $userId);
        $rarity = $rarityResult['rarity'];
        $pityBoost = $rarityResult['pity_boost'];
        
        // Select random avatar item of that rarity
        $item = select_avatar_item_by_rarity($pdo, $rarity);
        if (!$item) {
            $pdo->rollBack();
            return ['error' => 'NO_ITEMS', 'message' => "No avatar items available for rarity: {$rarity}"];
        }
        
        $itemId = (int)$item['id'];
        $wasDuplicate = user_owns_avatar_item($pdo, $userId, $itemId);
        $fragmentsAwarded = 0;
        
        if ($wasDuplicate) {
            // Convert duplicate to fragments
            $fragmentsAwarded = $FRAGMENT_VALUES[$rarity] ?? 5;
            award_fragments($pdo, $userId, $fragmentsAwarded);
        } else {
            // Grant new item
            grant_avatar_item($pdo, $userId, $itemId);
        }
        
        // Update pity counter
        update_user_pity($pdo, $userId, $rarity);
        
        $xp = $DROP_XP_MAP[$rarity] ?? 2;

        // Find a valid legacy config_id to satisfy FK in knd_drops
$stmt = $pdo->prepare(
    "SELECT id
     FROM knd_drop_configs
     WHERE season_id = ? AND rarity = ? AND is_active = 1
     ORDER BY id ASC
     LIMIT 1"
);
$stmt->execute([(int)$season['id'], $rarity]);
$configRow = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$configRow) {
    $stmt = $pdo->prepare(
        "SELECT id
         FROM knd_drop_configs
         WHERE season_id = ? AND is_active = 1
         ORDER BY id ASC
         LIMIT 1"
    );
    $stmt->execute([(int)$season['id']]);
    $configRow = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$configRow) {
    $pdo->rollBack();
    return ['error' => 'NO_CONFIG', 'message' => 'No valid drop config found for this season.'];
}

$configId = (int)$configRow['id'];

// Record drop (reward_kp stays 0, but config_id must be valid)
$pdo->prepare(
    "INSERT INTO knd_drops (user_id, season_id, entry_kp, rarity, reward_kp, config_id, xp_awarded, created_at)
     VALUES (?, ?, ?, ?, 0, ?, ?, NOW())"
)->execute([$userId, (int)$season['id'], $entryKp, $rarity, $configId, $xp]);

$dropId = (int)$pdo->lastInsertId();
        
        // Record reward details
        record_drop_reward($pdo, $userId, $dropId, $itemId, $rarity, $wasDuplicate, $fragmentsAwarded, $pityBoost);

        $levelUp = null;
        if ($xp > 0) {
            $xpRes = xp_add($pdo, $userId, $xp, 'drop_reward', 'knd_drop', $dropId);
            if ($xpRes['level_up']) {
                $levelUp = ['level_up' => true, 'old_level' => $xpRes['old_level'], 'new_level' => $xpRes['new_level']];
            }
        }

        $pdo->commit();
        unset($_SESSION['sc_badge_cache'], $_SESSION['xp_badge_cache']);

        // Check and grant badges after transaction commit (non-blocking)
        $newBadges = [];
        try {
            // Check drop milestone badges
            $dropBadges = badges_check_and_grant($pdo, $userId, 'drop');
            $newBadges = array_merge($newBadges, $dropBadges);
            
            // Check collector badges (new item acquired)
            if (!$wasDuplicate) {
                $collectorBadges = badges_check_and_grant($pdo, $userId, 'collector');
                $newBadges = array_merge($newBadges, $collectorBadges);
            }
            
            // Check legendary pull badge
            if ($rarity === 'legendary') {
                $legendaryBadges = badges_check_and_grant($pdo, $userId, 'legendary_pull');
                $newBadges = array_merge($newBadges, $legendaryBadges);
            }
            
            // Check level badges if level up occurred
            if ($levelUp) {
                $levelBadges = badges_check_and_grant($pdo, $userId, 'level');
                $newBadges = array_merge($newBadges, $levelBadges);
            }
        } catch (\Throwable $e) {
            // Log badge error but don't fail the drop
            error_log('Badge check failed in drop_play: ' . $e->getMessage());
        }

        $out = [
            'ok'            => true,
            'season'        => ['name' => $season['name'], 'ends_at' => $season['ends_at']],
            'entry'         => $entryKp,
            'rarity'        => $rarity,
            'item'          => [
                'id'        => $itemId,
                'code'      => $item['code'],
                'name'      => $item['name'],
                'slot'      => $item['slot'],
                'asset_path'=> $item['asset_path'],
            ],
            'was_duplicate' => $wasDuplicate,
            'fragments_awarded' => $fragmentsAwarded,
            'fragments_total'   => get_user_fragments($pdo, $userId),
            'pity_boost'    => $pityBoost,
            'xp_awarded'    => $xp,
            'balance'       => get_available_points($pdo, $userId),
        ];
        
        if ($xp > 0 && isset($xpRes)) {
            $out['xp_delta'] = $xp;
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
        
        // Include newly unlocked badges in response
        if (!empty($newBadges)) {
            $out['badges_unlocked'] = $newBadges;
        }
        
        return $out;
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

<?php
/**
 * Holo orb rewards — server-side roll, grant, and validation helpers.
 * XP → knd_user_xp via xp_add(); KE → knd_user_avatar_inventory via mw_grant_user_ke_bonus();
 * KND Points → points_ledger (earn / available).
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/knd_xp.php';

/** Minimum seconds between successful claims (anti-spam). */
const HOLO_ORB_COOLDOWN_SEC = 120;

/** Session key for pending orb (set by offer.php, consumed by claim.php). */
const HOLO_ORB_SESSION_KEY = 'holo_orb_pending';

/**
 * Roll reward type and amount (weights: ~70% XP, ~25% KE, ~5% KND Points).
 *
 * @return array{type:string,amount:int}
 */
function holo_orb_roll_reward(): array {
    $r = random_int(1, 100);
    if ($r <= 70) {
        return ['type' => 'xp', 'amount' => random_int(15, 40)];
    }
    if ($r <= 95) {
        return ['type' => 'ke', 'amount' => random_int(5, 15)];
    }
    return ['type' => 'knd_points', 'amount' => random_int(1, 5)];
}

/**
 * Validate pending payload from session (tamper resistance vs manual session edits).
 */
function holo_orb_validate_pending_shape(array $pending): bool {
    if (!isset($pending['type'], $pending['amount'], $pending['exp']) || !is_int($pending['exp'])) {
        return false;
    }
    $type = (string) $pending['type'];
    $amount = (int) $pending['amount'];
    return holo_orb_validate_amount_for_type($type, $amount);
}

function holo_orb_validate_amount_for_type(string $type, int $amount): bool {
    switch ($type) {
        case 'xp':
            return $amount >= 15 && $amount <= 40;
        case 'ke':
            return $amount >= 5 && $amount <= 15;
        case 'knd_points':
            return $amount >= 1 && $amount <= 5;
        default:
            return false;
    }
}

/**
 * Seconds until user may claim again (0 = eligible). Uses users.last_orb_claim_at.
 */
function holo_orb_cooldown_remaining_seconds(PDO $pdo, int $userId): int {
    $stmt = $pdo->prepare('SELECT last_orb_claim_at FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $raw = $stmt->fetchColumn();
    if ($raw === false || $raw === null || $raw === '') {
        return 0;
    }
    $last = strtotime((string) $raw . ' UTC');
    if ($last === false) {
        return 0;
    }
    $elapsed = time() - $last;
    $rem = HOLO_ORB_COOLDOWN_SEC - $elapsed;
    return $rem > 0 ? $rem : 0;
}

/**
 * Apply reward; returns actual reward_type and amount (KE may fall back to XP if no avatar row).
 *
 * @return array{reward_type:string,amount:int,level_up?:bool,new_level?:int,fallback_from_ke?:bool}
 */
function holo_orb_apply_reward(PDO $pdo, int $userId, string $type, int $amount): array {
    $amount = max(1, $amount);
    $now = gmdate('Y-m-d H:i:s');

    if ($type === 'xp') {
        $meta = xp_add($pdo, $userId, $amount, 'holo_orb', 'holo_orb', null);
        return [
            'reward_type' => 'xp',
            'amount'      => $amount,
            'level_up'    => !empty($meta['level_up']),
            'new_level'   => (int) ($meta['new_level'] ?? 0),
        ];
    }

    if ($type === 'ke') {
        require_once __DIR__ . '/mind_wars.php';
        $granted = mw_grant_user_ke_bonus($pdo, $userId, $amount);
        if ($granted <= 0) {
            $xpFallback = max(10, min(40, $amount * 2));
            $meta = xp_add($pdo, $userId, $xpFallback, 'holo_orb_ke_fallback', 'holo_orb', null);
            return [
                'reward_type'     => 'xp',
                'amount'          => $xpFallback,
                'fallback_from_ke'=> true,
                'level_up'        => !empty($meta['level_up']),
                'new_level'       => (int) ($meta['new_level'] ?? 0),
            ];
        }
        return ['reward_type' => 'ke', 'amount' => $granted];
    }

    if ($type === 'knd_points') {
        $stmt = $pdo->prepare(
            "INSERT INTO points_ledger (user_id, source_type, source_id, entry_type, status, points, created_at)
             VALUES (?, 'holo_orb', 0, 'earn', 'available', ?, ?)"
        );
        $stmt->execute([$userId, $amount, $now]);
        return ['reward_type' => 'knd_points', 'amount' => $amount];
    }

    throw new InvalidArgumentException('Invalid reward type');
}

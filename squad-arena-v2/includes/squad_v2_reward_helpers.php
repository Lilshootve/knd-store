<?php
/**
 * Squad Arena v2 — ownership + inventory mapping for MW reward submission.
 */
declare(strict_types=1);

if (!function_exists('mw_get_user_avatars')) {
    require_once __DIR__ . '/../../includes/mind_wars.php';
}

function squad_v2_item_id_for_mw_avatar(PDO $pdo, int $userId, int $mwAvatarId): ?int
{
    if ($mwAvatarId < 1) {
        return null;
    }
    foreach (mw_get_user_avatars($pdo, $userId) as $e) {
        if ((int) ($e['mw_avatar_id'] ?? 0) === $mwAvatarId) {
            $iid = (int) ($e['item_id'] ?? 0);

            return $iid > 0 ? $iid : null;
        }
    }

    return null;
}

/**
 * @param array<int,int|string> $orderedMwIds
 */
function squad_v2_user_owns_mw_ids(PDO $pdo, int $userId, array $orderedMwIds): bool
{
    $orderedMwIds = array_values(array_map('intval', $orderedMwIds));
    if (count($orderedMwIds) !== 3 || count(array_unique($orderedMwIds)) !== 3) {
        return false;
    }
    $owned = [];
    foreach (mw_get_user_avatars($pdo, $userId) as $e) {
        $mid = (int) ($e['mw_avatar_id'] ?? 0);
        if ($mid > 0) {
            $owned[$mid] = true;
        }
    }
    foreach ($orderedMwIds as $mid) {
        if ($mid < 1 || !isset($owned[$mid])) {
            return false;
        }
    }

    return true;
}

/**
 * Same rules as mw_rewards_for_result_in_mode when mode is "training" (before mw_normalize_mode strips it).
 *
 * @return array{xp:int,knowledge_energy:int,rank:int}
 */
function squad_v2_rewards_for_mode(string $result, string $rawMode): array
{
    $r = $result === 'win' ? 'win' : ($result === 'lose' ? 'lose' : 'draw');
    $rewards = [
        'xp' => $r === 'win' ? MW_XP_WIN : ($r === 'lose' ? MW_XP_LOSE : MW_XP_DRAW),
        'knowledge_energy' => $r === 'win' ? MW_KE_WIN : ($r === 'lose' ? MW_KE_LOSE : MW_KE_DRAW),
        'rank' => $r === 'win' ? MW_RANK_WIN : ($r === 'lose' ? MW_RANK_LOSE : MW_RANK_DRAW),
    ];
    $rawMode = strtolower(trim($rawMode));
    if ($rawMode === 'training') {
        $rewards['rank'] = 0;
        $rewards['knowledge_energy'] = max(0, (int) floor(((int) $rewards['knowledge_energy']) * 0.25));
    }

    return $rewards;
}

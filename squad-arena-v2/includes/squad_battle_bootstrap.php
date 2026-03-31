<?php
/**
 * Squad Arena v2 — build battle JSON from mw_avatars + user inventory.
 * Used by battlefield.php (not loaded on public static pages).
 */
declare(strict_types=1);

if (!function_exists('mw_get_user_avatars')
    || !function_exists('mw_skill_display_name')
    || !function_exists('mw_apply_combat_class_profile_bonuses')) {
    require_once __DIR__ . '/../../includes/mind_wars.php';
}
require_once __DIR__ . '/../../includes/mw_avatar_models.php';

/**
 * Human-readable skill line from DB text column and/or Mind Wars code registries.
 *
 * @return array{name:string,desc:string,mwCode:string}
 */
function squad_v2_skill_bundle(array $row, string $textKey, string $codeKey, string $kind): array
{
    $code = isset($row[$codeKey]) ? trim((string) $row[$codeKey]) : '';
    $rawText = isset($row[$textKey]) ? trim((string) $row[$textKey]) : '';
    if ($rawText !== '' && strpos($rawText, ':') !== false) {
        $sp = squad_v2_split_skill($rawText);

        return ['name' => $sp['name'], 'desc' => $sp['desc'], 'mwCode' => $code];
    }
    if ($rawText !== '') {
        return ['name' => mb_substr($rawText, 0, 64), 'desc' => '', 'mwCode' => $code];
    }
    if ($code !== '') {
        $descKind = $kind === 'passive' ? 'passive' : 'ability';

        return [
            'name' => mw_skill_display_name($code),
            'desc' => mw_skill_short_description($code, $descKind),
            'mwCode' => $code,
        ];
    }

    return ['name' => '—', 'desc' => '', 'mwCode' => ''];
}

/**
 * @return array{name:string,desc:string}
 */
function squad_v2_split_skill(?string $s): array
{
    $s = trim((string) $s);
    if ($s === '') {
        return ['name' => '—', 'desc' => ''];
    }
    $i = strpos($s, ':');
    if ($i === false) {
        return ['name' => mb_substr($s, 0, 42), 'desc' => ''];
    }

    return ['name' => trim(mb_substr($s, 0, $i)), 'desc' => trim(mb_substr($s, $i + 1))];
}

function squad_v2_default_icon(string $class): string
{
    $map = [
        'Tank' => '🛡️',
        'Controller' => '🔮',
        'Striker' => '⚔️',
        'Strategist' => '⚡',
    ];

    return $map[$class] ?? '◆';
}

/**
 * Mind/focus/speed/luck + max HP aligned with mw_get_combat_profile_from_db stat floors and
 * mw_apply_combat_class_profile_bonuses (Tank focus +10%, +10 hp_flat_bonus) plus mw_calc_hp(level).
 *
 * @return array{mind:int,focus:int,speed:int,luck:int,hpFlatBonus:int,maxHp:int}
 */
function squad_v2_mw_scaled_unit_stats(array $row, int $level): array
{
    $mind = max(1, (int) ($row['mind'] ?? 0));
    $focus = max(1, (int) ($row['focus'] ?? 0));
    $speed = max(1, (int) ($row['speed'] ?? 0));
    $luck = max(1, (int) ($row['luck'] ?? 0));
    $combatClass = mw_db_class_to_combat_class((string) ($row['class'] ?? ''));
    $profile = [
        'mind' => $mind,
        'focus' => $focus,
        'speed' => $speed,
        'luck' => $luck,
        'combat_class' => $combatClass,
        'hp_flat_bonus' => 0,
    ];
    $profile = mw_apply_combat_class_profile_bonuses($profile);
    $lvl = max(1, $level);
    $flat = (int) ($profile['hp_flat_bonus'] ?? 0);
    $maxHp = mw_calc_hp($lvl) + $flat;

    return [
        'mind' => (int) $profile['mind'],
        'focus' => (int) $profile['focus'],
        'speed' => (int) $profile['speed'],
        'luck' => (int) $profile['luck'],
        'hpFlatBonus' => $flat,
        'maxHp' => $maxHp,
    ];
}

/** Matches mw_skill_damage_reduction / mw_skill_deep_armor damage_taken_down increments (client uses one combined factor). */
function squad_v2_passive_dmg_reduction_for_code(string $mwCode): ?float
{
    switch ($mwCode) {
        case 'damage_reduction':
            return 0.05;
        case 'deep_armor':
            return 0.08;
        default:
            return null;
    }
}

/**
 * @return array{name:string,desc:string,healPct:float,target:string,maxCd:int,mwCode:string}
 */
function squad_v2_parse_heal_for_bootstrap(array $row, string $defaultName, float $defaultHealPct): array
{
    $healRaw = $row['heal'] ?? null;
    $healStr = $healRaw !== null ? trim((string) $healRaw) : '';
    $out = [
        'name' => $defaultName,
        'desc' => '',
        'healPct' => $defaultHealPct,
        'target' => 'self',
        'maxCd' => 2,
        'mwCode' => '',
    ];
    if ($healStr === '' || $healStr === '0') {
        return $out;
    }
    if ($healStr !== '' && $healStr[0] === '{') {
        $hj = json_decode($healStr, true);
        if (is_array($hj)) {
            $out['name'] = (string) ($hj['name'] ?? $out['name']);
            $out['desc'] = (string) ($hj['desc'] ?? $out['desc']);
            if (isset($hj['healPct'])) {
                $out['healPct'] = (float) $hj['healPct'];
            }
            if (isset($hj['target'])) {
                $out['target'] = (string) $hj['target'];
            }
            if (isset($hj['maxCd'])) {
                $out['maxCd'] = (int) $hj['maxCd'];
            }
            if (isset($hj['mwCode'])) {
                $out['mwCode'] = (string) $hj['mwCode'];
            }
        }

        return $out;
    }
    $h = squad_v2_split_skill($healStr);
    if ($h['name'] !== '—') {
        $out['name'] = $h['name'];
        $out['desc'] = $h['desc'];
    }

    return $out;
}

/**
 * @return list<array<string,mixed>>
 */
function squad_v2_build_abilities_from_row(array $row, string $defaultHealName, float $defaultHealPct, int $eAtk, int $eAbl, int $eSpl, int $eHeal, int $ablCooldown): array
{
    $passive = squad_v2_skill_bundle($row, 'passive', 'passive_code', 'passive');
    $abl = squad_v2_skill_bundle($row, 'ability', 'ability_code', 'ability');
    $spc = squad_v2_skill_bundle($row, 'special', 'special_code', 'special');
    $healParsed = squad_v2_parse_heal_for_bootstrap($row, $defaultHealName, $defaultHealPct);

    $passiveAb = [
        'type' => 'passive',
        'name' => $passive['name'] !== '—' ? $passive['name'] : 'Presence',
        'desc' => $passive['desc'],
        'cd' => 0,
        'maxCd' => 0,
        'passive' => true,
        'mwCode' => $passive['mwCode'],
    ];
    $dr = squad_v2_passive_dmg_reduction_for_code($passive['mwCode']);
    if ($dr !== null) {
        $passiveAb['dmgReduction'] = $dr;
    }

    return [
        $passiveAb,
        [
            'type' => 'attack',
            'name' => 'Strike',
            'desc' => 'Basic attack (' . $eAtk . '⚡), same energy rules as Mind Wars.',
            'dmg' => 1.0,
            'target' => 'default',
            'cd' => 0,
            'maxCd' => 0,
            'cost' => $eAtk . '⚡',
            'eCost' => $eAtk,
            'mwCode' => '',
        ],
        [
            'type' => 'defense',
            'name' => 'Defend',
            'desc' => 'Brace: reduce damage taken until your next turn (0⚡).',
            'cd' => 0,
            'maxCd' => 0,
            'cost' => '0⚡',
            'eCost' => 0,
            'defend' => true,
            'mwCode' => '',
        ],
        [
            'type' => 'ability',
            'name' => $abl['name'] !== '—' ? $abl['name'] : 'Ability',
            'desc' => $abl['desc'],
            'dmg' => 1.0,
            'target' => 'default',
            'cd' => 0,
            'maxCd' => $ablCooldown,
            'cost' => $eAbl . '⚡',
            'eCost' => $eAbl,
            'mwCode' => $abl['mwCode'],
        ],
        [
            'type' => 'special',
            'name' => $spc['name'] !== '—' ? $spc['name'] : 'Special',
            'desc' => $spc['desc'],
            'dmg' => 0.75,
            'target' => 'all',
            'cd' => 0,
            'maxCd' => 0,
            'cost' => $eSpl . '⚡',
            'eCost' => $eSpl,
            'mwCode' => $spc['mwCode'],
        ],
        [
            'type' => 'heal',
            'name' => $healParsed['name'],
            'desc' => $healParsed['desc'],
            'healPct' => $healParsed['healPct'],
            'target' => $healParsed['target'],
            'cd' => 0,
            'maxCd' => $healParsed['maxCd'],
            'cost' => $eHeal . '⚡',
            'eCost' => $eHeal,
            'mwCode' => $healParsed['mwCode'],
        ],
    ];
}

/**
 * @return array<string,mixed>|null
 */
function squad_v2_fetch_mw_row(PDO $pdo, int $mwId): ?array
{
    if ($mwId < 1) {
        return null;
    }
    $sql = 'SELECT a.id, a.name, a.rarity, a.class, a.image AS mw_image,
                   s.mind, s.focus, s.speed, s.luck,
                   sk.passive, sk.ability, sk.special, sk.heal,
                   sk.passive_code, sk.ability_code, sk.special_code
            FROM mw_avatars a
            LEFT JOIN mw_avatar_stats s ON s.avatar_id = a.id
            LEFT JOIN mw_avatar_skills sk ON sk.avatar_id = a.id
            WHERE a.id = ?
            LIMIT 1';
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$mwId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    } catch (Throwable $e) {
        try {
            $sql2 = 'SELECT a.id, a.name, a.rarity, a.class, a.image AS mw_image,
                            s.mind, s.focus, s.speed, s.luck,
                            sk.passive, sk.ability, sk.special,
                            sk.passive_code, sk.ability_code, sk.special_code
                     FROM mw_avatars a
                     LEFT JOIN mw_avatar_stats s ON s.avatar_id = a.id
                     LEFT JOIN mw_avatar_skills sk ON sk.avatar_id = a.id
                     WHERE a.id = ?
                     LIMIT 1';
            $stmt = $pdo->prepare($sql2);
            $stmt->execute([$mwId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $row['heal'] = null;
            }

            return $row ?: null;
        } catch (Throwable $e2) {
            error_log('squad_v2_fetch_mw_row: ' . $e2->getMessage());

            return null;
        }
    }
}

/**
 * @return list<int>
 */
function squad_v2_owned_mw_ids(PDO $pdo, int $userId): array
{
    $ids = [];
    foreach (mw_get_user_avatars($pdo, $userId) as $e) {
        $mid = (int) ($e['mw_avatar_id'] ?? 0);
        if ($mid > 0) {
            $ids[$mid] = true;
        }
    }

    return array_map('intval', array_keys($ids));
}

/**
 * @param array<int,int|string> $orderedMwIds exactly 3 distinct owned mw_avatars.id
 * @return array{ok:bool,error?:string,allies?:list<array<string,mixed>>,enemies?:list<array<string,mixed>>}
 */
function squad_v2_build_battle_payload(PDO $pdo, int $userId, array $orderedMwIds): array
{
    $orderedMwIds = array_values(array_map('intval', $orderedMwIds));
    if (count($orderedMwIds) !== 3) {
        return ['ok' => false, 'error' => 'INVALID_SQUAD_SIZE'];
    }
    if (count(array_unique($orderedMwIds)) !== 3) {
        return ['ok' => false, 'error' => 'DUPLICATE_AVATAR'];
    }

    $owned = squad_v2_owned_mw_ids($pdo, $userId);
    $ownedSet = array_fill_keys($owned, true);
    foreach ($orderedMwIds as $mid) {
        if ($mid < 1 || !isset($ownedSet[$mid])) {
            return ['ok' => false, 'error' => 'NOT_OWNED'];
        }
    }

    $levelByMw = [];
    foreach (mw_get_user_avatars($pdo, $userId) as $e) {
        $mid = (int) ($e['mw_avatar_id'] ?? 0);
        if ($mid > 0) {
            $levelByMw[$mid] = max(1, (int) ($e['avatar_level'] ?? 1));
        }
    }

    $positions = ['front', 'mid', 'back'];
    $eAtk = defined('MW_ENERGY_ATTACK_COST') ? (int) MW_ENERGY_ATTACK_COST : 1;
    $eAbl = defined('MW_ENERGY_ABILITY_COST') ? (int) MW_ENERGY_ABILITY_COST : 2;
    $eSpl = defined('MW_MAX_ENERGY') ? (int) MW_MAX_ENERGY : 5;
    $eHeal = $eAbl;
    $ablCooldown = 3;

    $allies = [];
    foreach ($orderedMwIds as $i => $mwId) {
        $row = squad_v2_fetch_mw_row($pdo, $mwId);
        if (!$row) {
            return ['ok' => false, 'error' => 'AVATAR_NOT_FOUND'];
        }
        $name = strtoupper(trim((string) ($row['name'] ?? 'UNIT')));
        if ($name === '') {
            $name = 'UNIT';
        }
        $rarity = strtolower((string) ($row['rarity'] ?? 'common'));
        $class = (string) ($row['class'] ?? '');
        $imageUrl = mw_resolve_avatar_image_for_inventory(
            $pdo,
            $mwId,
            $name,
            '',
            isset($row['mw_image']) ? (string) $row['mw_image'] : null
        );
        if ($imageUrl === '') {
            $imageUrl = '/assets/avatars/_placeholder.svg';
        }

        $modelGlb = mw_resolve_avatar_model_url($mwId, $name, $rarity);
        $modelGlbUrl = $modelGlb !== null && $modelGlb !== '' ? $modelGlb : '';

        $abilities = squad_v2_build_abilities_from_row($row, 'Restore', 0.15, $eAtk, $eAbl, $eSpl, $eHeal, $ablCooldown);
        $allyLvl = (int) ($levelByMw[$mwId] ?? 1);
        $st = squad_v2_mw_scaled_unit_stats($row, $allyLvl);

        $allies[] = [
            'id' => $mwId,
            'isEnemy' => false,
            'name' => $name,
            'class' => $class,
            'rarity' => $rarity,
            'icon' => squad_v2_default_icon($class),
            'image' => $imageUrl,
            'modelGlb' => $modelGlbUrl,
            'mind' => $st['mind'],
            'focus' => $st['focus'],
            'speed' => $st['speed'],
            'luck' => $st['luck'],
            'level' => $allyLvl,
            'maxHp' => $st['maxHp'],
            'hpFlatBonus' => $st['hpFlatBonus'],
            'pos' => $positions[$i] ?? 'front',
            'abilities' => $abilities,
        ];
    }

    $placeholders = implode(',', array_fill(0, count($orderedMwIds), '?'));
    $sqlEn = "SELECT a.id FROM mw_avatars a
              WHERE a.id NOT IN ($placeholders)
              ORDER BY RAND() LIMIT 3";
    try {
        $st = $pdo->prepare($sqlEn);
        $st->execute($orderedMwIds);
        $enemyIds = $st->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        $enemyIds = [];
    }

    if (count($enemyIds) < 3) {
        $st2 = $pdo->query('SELECT id FROM mw_avatars ORDER BY id ASC LIMIT 10');
        $fallback = $st2 ? $st2->fetchAll(PDO::FETCH_COLUMN) : [];
        foreach ($fallback as $fid) {
            $fid = (int) $fid;
            if (!in_array($fid, $orderedMwIds, true) && !in_array($fid, array_map('intval', $enemyIds), true)) {
                $enemyIds[] = $fid;
            }
            if (count($enemyIds) >= 3) {
                break;
            }
        }
    }

    $enemies = [];
    foreach (array_slice(array_map('intval', $enemyIds), 0, 3) as $j => $eid) {
        $row = squad_v2_fetch_mw_row($pdo, $eid);
        if (!$row) {
            continue;
        }
        $name = strtoupper(trim((string) ($row['name'] ?? 'RIVAL')));
        if ($name === '') {
            $name = 'RIVAL';
        }
        $rarity = strtolower((string) ($row['rarity'] ?? 'common'));
        $class = (string) ($row['class'] ?? '');
        $lvl = max(1, (int) ($levelByMw[$orderedMwIds[min($j, 2)]] ?? 4));

        $enemyImage = mw_resolve_avatar_image_for_inventory(
            $pdo,
            $eid,
            $name,
            '',
            isset($row['mw_image']) ? (string) $row['mw_image'] : null
        );
        if ($enemyImage === '') {
            $enemyImage = '/assets/avatars/_placeholder.svg';
        }

        $enemyModelGlb = mw_resolve_avatar_model_url($eid, $name, $rarity);
        $enemyModelGlbUrl = $enemyModelGlb !== null && $enemyModelGlb !== '' ? $enemyModelGlb : '';

        $abilities = squad_v2_build_abilities_from_row($row, 'Regen', 0.12, $eAtk, $eAbl, $eSpl, $eHeal, $ablCooldown);
        $est = squad_v2_mw_scaled_unit_stats($row, $lvl);

        $enemies[] = [
            'id' => 200000 + $eid,
            'mwAvatarId' => $eid,
            'isEnemy' => true,
            'name' => $name,
            'class' => $class,
            'rarity' => $rarity,
            'icon' => squad_v2_default_icon($class),
            'image' => $enemyImage,
            'modelGlb' => $enemyModelGlbUrl,
            'mind' => $est['mind'],
            'focus' => $est['focus'],
            'speed' => $est['speed'],
            'luck' => $est['luck'],
            'level' => $lvl,
            'maxHp' => $est['maxHp'],
            'hpFlatBonus' => $est['hpFlatBonus'],
            'pos' => $positions[$j] ?? 'front',
            'abilities' => $abilities,
        ];
    }

    $voidRow = ['mind' => 40, 'focus' => 40, 'speed' => 40, 'luck' => 30, 'class' => 'Striker'];
    $voidSt = squad_v2_mw_scaled_unit_stats($voidRow, 3);
    while (count($enemies) < 3) {
        $enemies[] = [
            'id' => 299900 + count($enemies),
            'isEnemy' => true,
            'name' => 'VOID UNIT',
            'class' => 'Striker',
            'rarity' => 'common',
            'icon' => '◆',
            'image' => '',
            'modelGlb' => '',
            'mind' => $voidSt['mind'],
            'focus' => $voidSt['focus'],
            'speed' => $voidSt['speed'],
            'luck' => $voidSt['luck'],
            'level' => 3,
            'maxHp' => $voidSt['maxHp'],
            'hpFlatBonus' => $voidSt['hpFlatBonus'],
            'pos' => $positions[count($enemies)] ?? 'front',
            'abilities' => [
                ['type' => 'passive', 'name' => 'Shell', 'desc' => '', 'cd' => 0, 'maxCd' => 0, 'passive' => true, 'mwCode' => ''],
                ['type' => 'attack', 'name' => 'Strike', 'desc' => '', 'dmg' => 1, 'target' => 'default', 'cd' => 0, 'maxCd' => 0, 'cost' => $eAtk . '⚡', 'eCost' => $eAtk, 'mwCode' => ''],
                ['type' => 'defense', 'name' => 'Defend', 'desc' => '', 'cd' => 0, 'maxCd' => 0, 'cost' => '0⚡', 'eCost' => 0, 'defend' => true, 'mwCode' => ''],
                ['type' => 'ability', 'name' => 'Hit', 'desc' => '', 'dmg' => 1, 'target' => 'default', 'cd' => 0, 'maxCd' => $ablCooldown, 'cost' => $eAbl . '⚡', 'eCost' => $eAbl, 'mwCode' => ''],
                ['type' => 'special', 'name' => 'Burst', 'desc' => '', 'dmg' => 0.6, 'target' => 'all', 'cd' => 0, 'maxCd' => 0, 'cost' => $eSpl . '⚡', 'eCost' => $eSpl, 'mwCode' => ''],
                ['type' => 'heal', 'name' => 'Patch', 'desc' => '', 'healPct' => 0.1, 'target' => 'self', 'cd' => 0, 'maxCd' => 2, 'cost' => $eHeal . '⚡', 'eCost' => $eHeal, 'mwCode' => ''],
            ],
        ];
    }

    return ['ok' => true, 'allies' => $allies, 'enemies' => $enemies];
}

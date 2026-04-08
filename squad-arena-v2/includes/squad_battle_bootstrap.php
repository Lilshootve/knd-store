<?php
declare(strict_types=1);
/**
 * Squad Arena v2 — build battle JSON from mw_avatars + user inventory.
 * Used by battlefield.php (not loaded on public static pages).
 */
require_once __DIR__ . '/../../config/bootstrap.php';

if (!function_exists('mw_get_user_avatars')
    || !function_exists('mw_skill_display_name')
    || !function_exists('mw_apply_combat_class_profile_bonuses')) {
    require_once BASE_PATH . '/includes/mind_wars.php';
}
require_once BASE_PATH . '/includes/mw_avatar_models.php';
require_once BASE_PATH . '/includes/mw_dynamic_skills.php';

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

/** @param list<array<string,mixed>> $payload */
function squad_v2_effect_payload_has_control(array $payload): bool
{
    $controlTypes = [
        'stun' => true,
        'freeze' => true,
        'ability_lock' => true,
        'speed_down' => true,
        'weaken' => true,
        'focus_down' => true,
        'random_debuff' => true,
        'stat_shuffle' => true,
    ];
    foreach ($payload as $fx) {
        if (!is_array($fx)) {
            continue;
        }
        $t = (string) ($fx['type'] ?? '');
        if (isset($controlTypes[$t])) {
            return true;
        }
    }

    return false;
}

/**
 * Curación / soporte explícito en effect_payload (no columna heal).
 *
 * @param list<array<string,mixed>> $payload
 * @return array{healPct:float,target:string}|null
 */
function squad_v2_heal_from_effect_payload(array $payload, string $scope, string $fallbackRules): ?array
{
    $sum = 0.0;
    $target = 'self';
    foreach ($payload as $fx) {
        if (!is_array($fx)) {
            continue;
        }
        $type = (string) ($fx['type'] ?? '');
        $effT = (string) ($fx['target'] ?? '');
        if ($type === 'heal') {
            $sum += (float) ($fx['value'] ?? 0);
            if ($effT === 'all_allies') {
                $target = 'all_allies';
            } elseif ($effT === 'single_ally' || $scope === 'single_ally') {
                $target = str_contains(strtolower($fallbackRules), 'lowest') ? 'lowest_ally' : 'self';
            } elseif ($effT !== '') {
                $target = $effT === 'self' ? 'self' : $target;
            }
        }
        if ($type === 'regen') {
            $v = (float) ($fx['value'] ?? 0);
            $dur = max(1, (int) ($fx['duration'] ?? 1));
            $sum += $v * min($dur, 3);
            if ($effT === 'all_allies') {
                $target = 'all_allies';
            } elseif ($effT === 'single_ally' || ($scope === 'single_ally' && $effT === '')) {
                $target = str_contains(strtolower($fallbackRules), 'lowest') ? 'lowest_ally' : 'self';
            }
        }
    }
    if ($sum <= 0) {
        return null;
    }

    if ($scope === 'all_allies') {
        $target = 'all_allies';
    }
    if ($scope === 'single_ally' && $target === 'self' && str_contains(strtolower($fallbackRules), 'lowest')) {
        $target = 'lowest_ally';
    }

    return ['healPct' => min(0.95, $sum), 'target' => $target];
}

function squad_v2_client_target_from_skill_json(array $d, string $defaultTarget): string
{
    $scope = strtolower((string) ($d['target_scope'] ?? ''));
    $hit = strtolower((string) ($d['hit_type'] ?? ''));
    if ($scope === 'all_enemies' || ($hit === 'aoe' && str_contains($scope, 'enemy'))) {
        return 'all';
    }
    if ($scope === 'single_ally' || $scope === 'all_allies') {
        $fb = strtolower((string) ($d['fallback_rules'] ?? ''));
        if ($scope === 'all_allies') {
            return 'all_allies';
        }

        return str_contains($fb, 'lowest') ? 'lowest_ally' : 'self';
    }

    return $defaultTarget;
}

/**
 * Tono de carta para el HUD (ability/special): damage|heal|support|control|hybrid|defense.
 *
 * @param array<string,mixed> $d Decoded ability_data o special_data
 */
function squad_v2_skill_card_tone(array $d): string
{
    $tags = [];
    foreach ($d['tags'] ?? [] as $t) {
        if (is_string($t) && $t !== '') {
            $tags[] = strtolower($t);
        }
    }
    $payload = $d['effect_payload'] ?? [];
    $payload = is_array($payload) ? $payload : [];
    $bp = (float) ($d['base_power'] ?? 0);
    $hasHealFx = squad_v2_heal_from_effect_payload($payload, (string) ($d['target_scope'] ?? ''), (string) ($d['fallback_rules'] ?? '')) !== null;
    $tagHeal = in_array('heal', $tags, true);
    $tagSupport = in_array('support', $tags, true);
    $tagDefense = in_array('defense', $tags, true);
    $tagDamage = in_array('damage', $tags, true);
    $tagControl = in_array('control', $tags, true);
    $hasCleanse = false;
    $allyShield = false;
    $allyEnergy = false;
    foreach ($payload as $fx) {
        if (!is_array($fx)) {
            continue;
        }
        $ty = (string) ($fx['type'] ?? '');
        if ($ty === 'cleanse') {
            $hasCleanse = true;
        }
        if ($ty === 'shield' && (($fx['target'] ?? '') === 'single_ally' || ($fx['target'] ?? '') === 'all_allies')) {
            $allyShield = true;
        }
        if ($ty === 'energy_gain' && (($fx['target'] ?? '') === 'all_allies' || ($fx['target'] ?? '') === 'single_ally')) {
            $allyEnergy = true;
        }
    }
    $hasControl = $tagControl || squad_v2_effect_payload_has_control($payload);
    $hasDamage = $bp > 0.0001 || $tagDamage;

    if (($hasHealFx || $tagHeal) && $hasDamage) {
        return 'hybrid';
    }
    if ($hasHealFx || ($tagHeal && $bp <= 0.0001)) {
        return 'heal';
    }
    if (($hasCleanse || $allyShield || $allyEnergy || $tagSupport) && !$hasDamage) {
        return 'support';
    }
    if ($tagDefense && !$hasHealFx && $bp <= 0.0001) {
        return 'defense';
    }
    if ($hasControl && !$hasDamage) {
        return 'control';
    }
    if ($hasControl && $hasDamage) {
        return 'hybrid';
    }

    return 'damage';
}

/**
 * @param array<string,mixed> $base ability/special skeleton
 * @param array<string,mixed> $d   decoded JSON
 * @return array<string,mixed>
 */
function squad_v2_merge_combat_skill_json(array $base, array $d, int $defaultEnergy, int $defaultCd): array
{
    if ($d === []) {
        $base['cardTone'] = 'damage';

        return $base;
    }
    $e = (int) ($d['energy_cost'] ?? $defaultEnergy);
    $base['eCost'] = max(0, $e);
    $base['cost'] = $base['eCost'] . '⚡';
    $cd = (int) ($d['cooldown'] ?? $defaultCd);
    $base['maxCd'] = max(0, $cd);
    $defTgt = (string) ($base['target'] ?? 'default');
    $base['target'] = squad_v2_client_target_from_skill_json($d, $defTgt);
    $bp = (float) ($d['base_power'] ?? 0);
    if ($bp > 0.0001) {
        $base['dmg'] = max(0.25, min(1.85, $bp / 75.0));
    }
    $payload = $d['effect_payload'] ?? [];
    $payload = is_array($payload) ? $payload : [];
    $healInfo = squad_v2_heal_from_effect_payload(
        $payload,
        (string) ($d['target_scope'] ?? ''),
        (string) ($d['fallback_rules'] ?? '')
    );
    if ($healInfo !== null) {
        $base['healPct'] = $healInfo['healPct'];
        $base['target'] = $healInfo['target'];
    }
    $base['cardTone'] = squad_v2_skill_card_tone($d);
    $noDirectHit = $bp <= 0.0001;
    if ($noDirectHit && ($healInfo !== null || in_array($base['cardTone'], ['support', 'control'], true))) {
        $base['dmg'] = 0;
    }

    return $base;
}

/**
 * @return list<array<string,mixed>>
 */
function squad_v2_build_abilities_from_row(array $row, int $eAtk, int $eAbl, int $eSpl, int $ablCooldown): array
{
    $passive = squad_v2_skill_bundle($row, 'passive', 'passive_code', 'passive');
    $abl = squad_v2_skill_bundle($row, 'ability', 'ability_code', 'ability');
    $spc = squad_v2_skill_bundle($row, 'special', 'special_code', 'special');
    $abilityData = mwd_decode_skill_json($row['ability_data'] ?? null);
    $specialData = mwd_decode_skill_json($row['special_data'] ?? null);
    $basicData = mwd_decode_skill_json($row['basic_data'] ?? null);

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

    $strike = [
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
    ];
    if ($basicData !== []) {
        $bbp = (float) ($basicData['base_power'] ?? 0);
        if ($bbp > 0.0001) {
            $strike['dmg'] = max(0.3, min(1.6, $bbp / 75.0));
        }
    }

    $abilitySlot = [
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
    ];
    $abilitySlot = squad_v2_merge_combat_skill_json($abilitySlot, $abilityData, $eAbl, $ablCooldown);

    $specialSlot = [
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
    ];
    $specialSlot = squad_v2_merge_combat_skill_json($specialSlot, $specialData, $eSpl, 0);

    return [
        $passiveAb,
        $strike,
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
        $abilitySlot,
        $specialSlot,
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
                   sk.passive, sk.ability, sk.special,
                   sk.passive_code, sk.ability_code, sk.special_code,
                   sk.basic_data, sk.passive_data, sk.ability_data, sk.special_data
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
                            sk.passive_code, sk.ability_code, sk.special_code,
                            sk.basic_data, sk.passive_data, sk.ability_data, sk.special_data
                     FROM mw_avatars a
                     LEFT JOIN mw_avatar_stats s ON s.avatar_id = a.id
                     LEFT JOIN mw_avatar_skills sk ON sk.avatar_id = a.id
                     WHERE a.id = ?
                     LIMIT 1';
            $stmt = $pdo->prepare($sql2);
            $stmt->execute([$mwId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

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

        $abilities = squad_v2_build_abilities_from_row($row, $eAtk, $eAbl, $eSpl, $ablCooldown);
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

        $abilities = squad_v2_build_abilities_from_row($row, $eAtk, $eAbl, $eSpl, $ablCooldown);
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
                ['type' => 'ability', 'name' => 'Hit', 'desc' => '', 'dmg' => 1, 'target' => 'default', 'cd' => 0, 'maxCd' => $ablCooldown, 'cost' => $eAbl . '⚡', 'eCost' => $eAbl, 'mwCode' => '', 'cardTone' => 'damage'],
                ['type' => 'special', 'name' => 'Burst', 'desc' => '', 'dmg' => 0.6, 'target' => 'all', 'cd' => 0, 'maxCd' => 0, 'cost' => $eSpl . '⚡', 'eCost' => $eSpl, 'mwCode' => '', 'cardTone' => 'damage'],
            ],
        ];
    }

    return ['ok' => true, 'allies' => $allies, 'enemies' => $enemies];
}

/**
 * Valida que el snapshot de engage (aliados/enemigos) siga coincidiendo con los 3 mw_avatars.id elegidos.
 *
 * @param array<string, mixed>|null $payload Debe traer 'allies' y 'enemies' (listas de 3).
 * @param list<int>                  $allyMwIds Orden del escuadrón
 */
function squad_v2_battle_payload_matches_squad(?array $payload, array $allyMwIds): bool
{
    if (!is_array($payload) || !isset($payload['allies'], $payload['enemies'])) {
        return false;
    }
    $al = $payload['allies'];
    $en = $payload['enemies'];
    if (!is_array($al) || !is_array($en) || count($al) !== 3 || count($en) !== 3) {
        return false;
    }
    $allyMwIds = array_values(array_map('intval', $allyMwIds));
    if (count($allyMwIds) !== 3) {
        return false;
    }
    foreach ($allyMwIds as $idx => $mid) {
        $row = $al[$idx] ?? null;
        if (!is_array($row) || (int) ($row['id'] ?? 0) !== (int) $mid) {
            return false;
        }
    }

    return true;
}

/**
 * Elimina fila SquadWars huérfana (nuevo engage o fin de batalla).
 */
function squad_v2_delete_squadwars_battle_by_token(?PDO $pdo, string $token): void
{
    if (!$pdo instanceof PDO || $token === '') {
        return;
    }
    try {
        $pdo->prepare('DELETE FROM knd_squadwars_battles WHERE battle_token = ? LIMIT 1')->execute([$token]);
    } catch (Throwable $e) {
        error_log('squad_v2_delete_squadwars_battle_by_token: ' . $e->getMessage());
    }
}

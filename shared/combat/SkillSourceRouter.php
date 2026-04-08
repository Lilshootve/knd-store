<?php
declare(strict_types=1);

/**
 * Skill source router for migration period.
 *
 * Goal:
 * - Keep legacy endpoints stable.
 * - Let new modes choose JSON-only safely.
 * - Provide one place to decide source by mode.
 */

if (!function_exists('knd_skill_router_decode_json')) {
    /**
     * @return array<string, mixed>
     */
    function knd_skill_router_decode_json($raw): array
    {
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('knd_skill_router_normalize_legacy')) {
    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    function knd_skill_router_normalize_legacy(array $row): array
    {
        $passiveCode = trim((string) ($row['passive_code'] ?? $row['passive'] ?? ''));
        $abilityCode = trim((string) ($row['ability_code'] ?? $row['ability'] ?? ''));
        $specialCode = trim((string) ($row['special_code'] ?? $row['special'] ?? ''));
        $healRaw = trim((string) ($row['heal'] ?? ''));

        return [
            'passive_code' => $passiveCode !== '' ? $passiveCode : null,
            'ability_code' => $abilityCode !== '' ? $abilityCode : null,
            'special_code' => $specialCode !== '' ? $specialCode : null,
            'heal' => ($healRaw !== '' && $healRaw !== '0') ? $healRaw : null,
            // Text fields kept for UI compatibility where still used.
            'passive_text' => isset($row['passive']) ? (string) $row['passive'] : null,
            'ability_text' => isset($row['ability']) ? (string) $row['ability'] : null,
            'special_text' => isset($row['special']) ? (string) $row['special'] : null,
        ];
    }
}

if (!function_exists('knd_skill_router_json_complete')) {
    /**
     * @param array<string, mixed> $jsonBundle
     */
    function knd_skill_router_json_complete(array $jsonBundle): bool
    {
        foreach (['basic', 'passive', 'ability', 'special'] as $k) {
            if (!isset($jsonBundle[$k]) || !is_array($jsonBundle[$k]) || $jsonBundle[$k] === []) {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('knd_skill_router_legacy_complete')) {
    /**
     * @param array<string, mixed> $legacyBundle
     */
    function knd_skill_router_legacy_complete(array $legacyBundle): bool
    {
        return !empty($legacyBundle['passive_code'])
            && !empty($legacyBundle['ability_code'])
            && !empty($legacyBundle['special_code']);
    }
}

if (!function_exists('knd_skill_router_source_for_mode')) {
    function knd_skill_router_source_for_mode(string $mode): string
    {
        $m = strtolower(trim($mode));
        if ($m === 'squadwars') {
            return 'json';
        }
        if ($m === 'mindwars1v1' || $m === 'mindwars3v3') {
            return 'legacy';
        }
        return 'legacy';
    }
}

if (!function_exists('knd_skill_router_fetch_avatar_bundle')) {
    /**
     * @return array{
     *   avatar_id:int,
     *   json:array<string,mixed>,
     *   legacy:array<string,mixed>,
     *   json_complete:bool,
     *   legacy_complete:bool
     * }
     */
    function knd_skill_router_fetch_avatar_bundle(PDO $pdo, int $avatarId): array
    {
        $stmt = $pdo->prepare(
            "SELECT avatar_id,
                    passive, ability, special, heal,
                    passive_code, ability_code, special_code,
                    basic_data, passive_data, ability_data, special_data
             FROM mw_avatar_skills
             WHERE avatar_id = ?
             LIMIT 1"
        );
        $stmt->execute([$avatarId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return [
                'avatar_id' => $avatarId,
                'json' => ['basic' => [], 'passive' => [], 'ability' => [], 'special' => []],
                'legacy' => ['passive_code' => null, 'ability_code' => null, 'special_code' => null, 'heal' => null],
                'json_complete' => false,
                'legacy_complete' => false,
            ];
        }

        $json = [
            'basic' => knd_skill_router_decode_json($row['basic_data'] ?? null),
            'passive' => knd_skill_router_decode_json($row['passive_data'] ?? null),
            'ability' => knd_skill_router_decode_json($row['ability_data'] ?? null),
            'special' => knd_skill_router_decode_json($row['special_data'] ?? null),
        ];
        $legacy = knd_skill_router_normalize_legacy($row);

        return [
            'avatar_id' => (int) ($row['avatar_id'] ?? $avatarId),
            'json' => $json,
            'legacy' => $legacy,
            'json_complete' => knd_skill_router_json_complete($json),
            'legacy_complete' => knd_skill_router_legacy_complete($legacy),
        ];
    }
}

if (!function_exists('knd_skill_router_resolve_for_mode')) {
    /**
     * Returns skills for requested mode, with safe fallback behavior:
     * - squadwars wants JSON; falls back to legacy only if JSON incomplete.
     * - mindwars* keeps legacy first; JSON is exposed as metadata.
     *
     * @return array{
     *   avatar_id:int,
     *   requested_mode:string,
     *   preferred_source:string,
     *   selected_source:string,
     *   selected_skills:array<string,mixed>,
     *   json:array<string,mixed>,
     *   legacy:array<string,mixed>,
     *   warnings:array<int,string>
     * }
     */
    function knd_skill_router_resolve_for_mode(PDO $pdo, int $avatarId, string $mode): array
    {
        $bundle = knd_skill_router_fetch_avatar_bundle($pdo, $avatarId);
        $preferred = knd_skill_router_source_for_mode($mode);
        $selected = $preferred;
        $warnings = [];

        if ($preferred === 'json' && !$bundle['json_complete']) {
            $selected = 'legacy';
            $warnings[] = 'json_incomplete_fallback_to_legacy';
        } elseif ($preferred === 'legacy' && !$bundle['legacy_complete'] && $bundle['json_complete']) {
            $selected = 'json';
            $warnings[] = 'legacy_incomplete_fallback_to_json';
        }

        $selectedSkills = $selected === 'json' ? $bundle['json'] : $bundle['legacy'];

        return [
            'avatar_id' => $bundle['avatar_id'],
            'requested_mode' => $mode,
            'preferred_source' => $preferred,
            'selected_source' => $selected,
            'selected_skills' => $selectedSkills,
            'json' => $bundle['json'],
            'legacy' => $bundle['legacy'],
            'warnings' => $warnings,
        ];
    }
}

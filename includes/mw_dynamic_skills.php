<?php
declare(strict_types=1);

/**
 * Dynamic Skills Engine (PHP procedural + PDO)
 * - Fuente: mw_avatar_skills.{basic_data,passive_data,ability_data,special_data}
 * - Sin hardcode de personajes/habilidades.
 */

if (!function_exists('mwd_decode_skill_json')) {
    /**
     * @return array<string, mixed>
     */
    function mwd_decode_skill_json($json): array
    {
        if (!is_string($json) || trim($json) === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('getAvatarSkills')) {
    /**
     * Lee skill JSON de DB por avatar_id.
     *
     * @return array{
     *   basic: array<string, mixed>,
     *   passive: array<string, mixed>,
     *   ability: array<string, mixed>,
     *   special: array<string, mixed>
     * }
     */
    function getAvatarSkills($avatar_id): array
    {
        if (!function_exists('getDBConnection')) {
            throw new RuntimeException('getDBConnection() not available');
        }
        $pdo = getDBConnection();
        if (!$pdo instanceof PDO) {
            throw new RuntimeException('DB connection failed');
        }

        $avatarId = (int) $avatar_id;
        if ($avatarId <= 0) {
            throw new InvalidArgumentException('invalid_avatar_id');
        }

        $stmt = $pdo->prepare(
            "SELECT basic_data, passive_data, ability_data, special_data
             FROM mw_avatar_skills
             WHERE avatar_id = ?
             LIMIT 1"
        );
        $stmt->execute([$avatarId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || !is_array($row)) {
            return [
                'basic' => [],
                'passive' => [],
                'ability' => [],
                'special' => [],
            ];
        }

        return [
            'basic' => mwd_decode_skill_json($row['basic_data'] ?? null),
            'passive' => mwd_decode_skill_json($row['passive_data'] ?? null),
            'ability' => mwd_decode_skill_json($row['ability_data'] ?? null),
            'special' => mwd_decode_skill_json($row['special_data'] ?? null),
        ];
    }
}

if (!function_exists('calculateDamage')) {
    /**
     * damage = base_power * (1 + attacker[scaling_stat]/100)
     * mitigation = defender.focus / (defender.focus + 100)
     * final = damage * (1 - mitigation)
     * crit: roll < luck% => *1.5 (solo daño)
     *
     * @param array<string, mixed> $attacker
     * @param array<string, mixed> $defender
     * @param array<string, mixed> $skill
     * @return array{damage:int,is_crit:bool,raw:float,mitigation:float}
     */
    function calculateDamage(array $attacker, array $defender, array $skill): array
    {
        $basePower = (float) ($skill['base_power'] ?? 0);
        $scalingStat = (string) ($skill['scaling_stat'] ?? 'mind');
        $statVal = (float) ($attacker['stats'][$scalingStat] ?? $attacker[$scalingStat] ?? 0);
        $damage = $basePower * (1 + $statVal / 100.0);

        $focus = (float) ($defender['stats']['focus'] ?? $defender['focus'] ?? 0);
        $mitigation = $focus / ($focus + 100.0);
        $final = $damage * (1 - $mitigation);

        $canCrit = !empty($skill['can_crit']);
        $isCrit = false;
        if ($canCrit) {
            $luck = (float) ($attacker['stats']['luck'] ?? $attacker['luck'] ?? 0);
            // Regla solicitada: if roll < luck -> crit. Se interpreta luck como porcentaje.
            $roll = random_int(0, 9999) / 100.0; // 0.00..99.99
            if ($roll < $luck) {
                $isCrit = true;
                $final *= 1.5;
            }
        }

        return [
            'damage' => max(1, (int) round($final)),
            'is_crit' => $isCrit,
            'raw' => $damage,
            'mitigation' => $mitigation,
        ];
    }
}

if (!function_exists('applyEffects')) {
    /**
     * Aplica effect_payload usando switch por type.
     *
     * @param array<int, array<string, mixed>> $effect_payload
     * @param array<string, mixed> $caster
     * @param array<string, mixed> $target
     * @return list<array<string, mixed>> eventos aplicados
     */
    function applyEffects(array $effect_payload, array &$caster, array &$target): array
    {
        $events = [];
        if (!isset($caster['effects']) || !is_array($caster['effects'])) {
            $caster['effects'] = [];
        }
        if (!isset($target['effects']) || !is_array($target['effects'])) {
            $target['effects'] = [];
        }

        foreach ($effect_payload as $fx) {
            if (!is_array($fx)) {
                continue;
            }
            $type = (string) ($fx['type'] ?? '');
            $value = (float) ($fx['value'] ?? 0);
            $duration = (int) ($fx['duration'] ?? 0);

            switch ($type) {
                case 'damage_amp':
                case 'weaken':
                case 'speed_down':
                case 'freeze':
                case 'ability_lock':
                    $target['effects'][] = $fx;
                    $events[] = ['type' => 'effect_applied', 'effect' => $type, 'duration' => $duration];
                    break;

                case 'energy_gain':
                    $before = (int) ($caster['energy'] ?? 0);
                    $caster['energy'] = max(0, min(5, $before + (int) $value));
                    $events[] = ['type' => 'energy_gain', 'before' => $before, 'after' => (int) $caster['energy']];
                    break;

                default:
                    $events[] = ['type' => 'effect_ignored', 'reason' => 'unsupported_type', 'effect' => $type];
                    break;
            }
        }

        return $events;
    }
}

if (!class_exists('SkillExecutor')) {
    final class SkillExecutor
    {
        /**
         * @param array<string, mixed> $caster
         * @param array<string, mixed>|null $target
         * @param array<string, mixed> $skillData
         * @param array<string, mixed> $context
         * @return array<string, mixed>
         */
        public function executeSkill(array &$caster, ?array &$target, array $skillData, array $context = []): array
        {
            $result = [
                'ok' => false,
                'error' => null,
                'damage' => 0,
                'is_crit' => false,
                'effects' => [],
                'target_id' => null,
            ];

            $energyCost = (int) ($skillData['energy_cost'] ?? 0);
            $cooldown = (int) ($skillData['cooldown'] ?? 0);
            $skillKey = (string) ($skillData['id'] ?? $skillData['name'] ?? 'skill');

            if (!isset($caster['energy'])) {
                $caster['energy'] = 0;
            }
            if (!isset($caster['cooldowns']) || !is_array($caster['cooldowns'])) {
                $caster['cooldowns'] = [];
            }

            if ((int) $caster['energy'] < $energyCost) {
                $result['error'] = 'not_enough_energy';
                return $result;
            }
            if ($cooldown > 0 && (int) ($caster['cooldowns'][$skillKey] ?? 0) > 0) {
                $result['error'] = 'skill_on_cooldown';
                return $result;
            }

            $targetScope = (string) ($skillData['target_scope'] ?? 'single_enemy');
            $resolvedTarget = $this->resolveTarget($caster, $target, $targetScope, $skillData, $context);
            if ($resolvedTarget === null) {
                $result['error'] = 'no_valid_target';
                return $result;
            }
            $target = $resolvedTarget;

            // Gasto energía + cooldown (validar/aplicar en memoria; persistencia externa).
            $caster['energy'] = max(0, (int) $caster['energy'] - $energyCost);
            if ($cooldown > 0) {
                $caster['cooldowns'][$skillKey] = $cooldown;
            }

            $damageInfo = calculateDamage($caster, $target, $skillData);
            $targetHp = (int) ($target['hp'] ?? 0);
            $target['hp'] = max(0, $targetHp - (int) $damageInfo['damage']);

            $effects = [];
            if (isset($skillData['effect_payload']) && is_array($skillData['effect_payload'])) {
                $effects = applyEffects($skillData['effect_payload'], $caster, $target);
            } elseif (isset($skillData['effects']) && is_array($skillData['effects'])) {
                // Compatibilidad con payload antiguo "effects".
                $effects = applyEffects($skillData['effects'], $caster, $target);
            }

            $result['ok'] = true;
            $result['damage'] = (int) $damageInfo['damage'];
            $result['is_crit'] = (bool) $damageInfo['is_crit'];
            $result['effects'] = $effects;
            $result['target_id'] = $target['id'] ?? null;
            $result['target_hp_after'] = (int) ($target['hp'] ?? 0);

            return $result;
        }

        /**
         * Fallback básico de target:
         * - si target inválido, elegir otro válido automáticamente.
         *
         * @param array<string, mixed> $caster
         * @param array<string, mixed>|null $target
         * @param array<string, mixed> $skillData
         * @param array<string, mixed> $context
         * @return array<string, mixed>|null
         */
        private function resolveTarget(array $caster, ?array $target, string $targetScope, array $skillData, array $context): ?array
        {
            if ($targetScope === 'self') {
                return $caster;
            }

            if ($target !== null && $this->isTargetValid($target)) {
                return $target;
            }

            $pool = $context['all_targets'] ?? [];
            if (!is_array($pool)) {
                return null;
            }

            $fallback = (string) ($skillData['fallback_rules'] ?? 'random_alive');
            $valid = array_values(array_filter($pool, fn($u) => is_array($u) && $this->isTargetValid($u)));
            if ($valid === []) {
                return null;
            }

            if ($fallback === 'lowest_hp') {
                usort($valid, fn($a, $b) => ((int) ($a['hp'] ?? 0)) <=> ((int) ($b['hp'] ?? 0)));
                return $valid[0];
            }
            if ($fallback === 'highest_hp') {
                usort($valid, fn($a, $b) => ((int) ($b['hp'] ?? 0)) <=> ((int) ($a['hp'] ?? 0)));
                return $valid[0];
            }

            return $valid[random_int(0, count($valid) - 1)];
        }

        /**
         * @param array<string, mixed> $target
         */
        private function isTargetValid(array $target): bool
        {
            return !empty($target['alive']) && (int) ($target['hp'] ?? 0) > 0;
        }
    }
}


<?php
declare(strict_types=1);

/**
 * Daño: final = raw * (1 - focus/(focus+100)); control DR y anti-burst opcional aquí o en capa superior.
 */
final class SquadEffectResolver
{
    private const EFFECT_RULES = [
        'weaken' => ['duration' => true, 'value' => true],
        'damage_amp' => ['duration' => true, 'value' => true],
        'bonus_vs_condition' => ['duration' => false, 'value' => true],
        'damage_reduction' => ['duration' => true, 'value' => true],
        'shield' => ['duration' => true, 'value' => true],
        'regen' => ['duration' => true, 'value' => true, 'stack_max' => 3],
        'energy_gain' => ['duration' => false, 'value' => true],
        'energy_drain' => ['duration' => false, 'value' => true],
        'energy_block' => ['duration' => true, 'value' => false],
        'mind_down' => ['duration' => true, 'value' => true],
        'focus_down' => ['duration' => true, 'value' => true],
        'speed_down' => ['duration' => true, 'value' => true],
        'speed_up' => ['duration' => true, 'value' => true],
        'stun' => ['duration' => true, 'value' => false, 'control' => true],
        'freeze' => ['duration' => true, 'value' => false, 'control' => true],
        'ability_lock' => ['duration' => true, 'value' => false, 'control' => true],
        'anti_heal' => ['duration' => true, 'value' => true],
        'heal' => ['duration' => false, 'value' => true],
        'bleed' => ['duration' => true, 'value' => true, 'stack_max' => 3],
        'ignore_defense' => ['duration' => false, 'value' => true],
        'bonus_per_debuff' => ['duration' => false, 'value' => true],
        'action_efficiency_down' => ['duration' => true, 'value' => true],
        'random_debuff' => ['duration' => true, 'chance' => true],
        'chaos' => ['duration' => true, 'chance' => true],
        'stat_shuffle' => ['duration' => true, 'chance' => true],
        'cleanse' => ['duration' => false, 'value' => false],
    ];

    private bool $antiBurstEnabled;
    private float $antiBurstThreshold;
    private float $antiBurstShieldRatio;

    public function __construct(
        bool $antiBurstEnabled = false,
        float $antiBurstThreshold = 0.30,
        float $antiBurstShieldRatio = 0.15
    ) {
        $this->antiBurstEnabled = $antiBurstEnabled;
        $this->antiBurstThreshold = $antiBurstThreshold;
        $this->antiBurstShieldRatio = $antiBurstShieldRatio;
    }

    /**
     * @param array{events?: list<mixed>, hit_success?: bool, targets_hit?: list<string>, effects?: list<array<string, mixed>>, self_effects?: list<mixed>, meta?: array<string, mixed>} $result
     * @param array<string, mixed> $state
     * @param list<array<string, mixed>> $events
     */
    public function applyDamage(array $result, array &$state, array &$events): void
    {
        $units = &$state['units'];
        if (!is_array($units)) {
            return;
        }
        foreach ($result['effects'] ?? [] as $fx) {
            if (!is_array($fx) || ($fx['type'] ?? '') !== 'damage') {
                continue;
            }
            $tid = (string) ($fx['targetUnitId'] ?? '');
            $src = (string) ($fx['sourceUnitId'] ?? '');
            if ($tid === '' || !isset($units[$tid])) {
                continue;
            }

            $sid = (string) ($fx['sourceUnitId'] ?? '');
            $attacker = isset($units[$sid]) && is_array($units[$sid]) ? $units[$sid] : [];
            $mind = (int) ($attacker['stats']['mind'] ?? 0);
            $basePower = (float) ($fx['base_power'] ?? 0.0);
            $scaling = (string) ($fx['scalingStat'] ?? 'mind');
            $raw = $this->scaledValue($basePower, $attacker, $scaling);

            $critMultiplier = (float) ($fx['crit_multiplier'] ?? 1.0);
            $damageAmp = $this->collectDamageAmp($units[$tid], (float) ($fx['damage_amp'] ?? 1.0));
            $weaken = $this->collectWeaken($attacker, (float) ($fx['weaken'] ?? 1.0));
            $conditionalBonus = $this->collectConditionalBonus($fx, $units[$tid], (float) ($fx['conditional_bonus'] ?? 1.0));
            $multipliers = $critMultiplier * $damageAmp * $weaken * $conditionalBonus;

            $focus = (int) ($units[$tid]['stats']['focus'] ?? 0);
            $ignoreDefense = (float) ($fx['ignore_defense'] ?? 0.0);
            $focus = (int) round($focus * (1 - min(1.0, max(0.0, $ignoreDefense))));
            $mit = $focus / ($focus + 100);
            $mit = $this->collectDamageReduction($units[$tid], $mit);
            // hard cap anti-meta roto
            $mit = min(CombatCaps::DAMAGE_REDUCTION_MAX, max(0.0, $mit));
            $reducedDamage = $raw * (1 - $mit);
            $variance = random_int(950, 1050) / 1000.0;
            $final = max(1.0, $reducedDamage * $variance * $multipliers);
            $beforeHp = (int) ($units[$tid]['hp'] ?? 0);
            $maxHp = (int) ($units[$tid]['hp_max'] ?? $beforeHp ?: 1);
            $dmgInt = (int) round($final);
            $dmgInt = $this->consumeShieldBeforeHp($units[$tid], $dmgInt);
            $units[$tid]['hp'] = max(0, $beforeHp - $dmgInt);
            $events[] = [
                'type' => 'damage',
                'source' => $sid,
                'target' => $tid,
                'value' => $dmgInt,
                'raw' => (int) round($raw),
                'variance' => $variance,
                'multipliers' => $multipliers,
                'crit' => !empty($fx['crit']),
            ];

            if ($this->antiBurstEnabled && $maxHp > 0) {
                $delta = $beforeHp - (int) ($units[$tid]['hp'] ?? 0);
                if ($delta > $maxHp * $this->antiBurstThreshold) {
                    $shield = (int) round($maxHp * $this->antiBurstShieldRatio);
                    $units[$tid]['effects'][] = ['type' => 'shield', 'value' => $shield, 'remaining' => $shield, 'turns' => 1];
                    $events[] = ['type' => 'anti_burst_shield', 'target' => $tid, 'value' => $shield];
                }
            }
        }
    }

    /**
     * @param array{events?: list<mixed>, hit_success?: bool, targets_hit?: list<string>, effects?: list<mixed>, self_effects?: list<mixed>, meta?: array<string, mixed>} $result
     */
    public function applyOnHit(array $result, array &$state, array &$events): void
    {
        // hooks reservados
    }

    /**
     * @param array{events?: list<mixed>, hit_success?: bool, targets_hit?: list<string>, effects?: list<mixed>, self_effects?: list<mixed>, meta?: array<string, mixed>} $result
     */
    public function applySecondary(array $result, array &$state, array &$events): void
    {
        $currentRound = (int) ($state['round'] ?? 1);
        // efectos no-damage con target explícito
        foreach ($result['effects'] ?? [] as $fx) {
            if (!is_array($fx) || (($fx['type'] ?? '') === 'damage')) {
                continue;
            }
            if (isset($fx['proc_roll']) && !$fx['proc_roll']) {
                continue;
            }
            if (isset($fx['hit_count']) && isset($fx['apply_on_hit']) && isset($fx['hit_index'])) {
                if ((int) $fx['hit_index'] !== (int) $fx['apply_on_hit']) {
                    continue;
                }
            }
            $targetId = (string) ($fx['targetUnitId'] ?? '');
            if ($targetId === '' || !isset($state['units'][$targetId]) || !is_array($state['units'][$targetId])) {
                $events[] = ['type' => 'invalid_effect_target', 'effect' => $fx];
                continue;
            }
            $this->applyEffectToUnit($state['units'][$targetId], $fx, $events, $currentRound);
        }
        // efectos self/buffs/debuffs
        foreach ($result['self_effects'] ?? [] as $fx) {
            if (!is_array($fx)) {
                continue;
            }
            $unitId = (string) ($fx['targetUnitId'] ?? $fx['unitId'] ?? $fx['sourceUnitId'] ?? '');
            if ($unitId !== '' && isset($state['units'][$unitId]) && is_array($state['units'][$unitId])) {
                $this->applyEffectToUnit($state['units'][$unitId], $fx, $events, $currentRound);
            } else {
                $events[] = ['type' => 'self_effect', 'payload' => $fx];
            }
        }
    }

    /**
     * Duración de stun etc. con diminishing returns por contador en unit meta.
     *
     * @param array<string, mixed> $controlFx
     */
    public function applyControlWithDiminishing(array &$targetUnit, string $controlType, array $controlFx, array &$events): void
    {
        $key = 'control_stack_' . $controlType;
        $ageKey = 'control_age_' . $controlType;
        $n = (int) ($targetUnit[$key] ?? 0) + 1;
        $targetUnit[$key] = $n;
        $targetUnit[$ageKey] = 0;
        if ($n === 1) {
            $factor = 1.0;
        } elseif ($n === 2) {
            $factor = 0.5;
        } else {
            $factor = 0.0; // immune
        }
        $controlFx['duration_scale'] = $factor;
        if ($factor > 0) {
            $targetUnit['effects'][] = $controlFx;
        }
        $events[] = ['type' => 'control', 'control' => $controlType, 'scale' => $factor];
    }

    /**
     * @param array<string, mixed> $unit
     * @param array<string, mixed> $fx
     * @param list<array<string, mixed>> $events
     */
    private function applyEffectToUnit(array &$unit, array $fx, array &$events, int $currentRound): void
    {
        $type = (string) ($fx['type'] ?? '');
        if (!$this->isCatalogEffect($fx, $events)) {
            return;
        }
        if (!isset($unit['effects']) || !is_array($unit['effects'])) {
            $unit['effects'] = [];
        }

        if ($type === 'cleanse') {
            $this->applyCleanse($unit, $fx, $events, $currentRound);
            return;
        }

        if ($type === 'anti_heal') {
            $fx['value'] = min(CombatCaps::ANTI_HEAL_MAX, max(0.0, (float) ($fx['value'] ?? 0.0)));
        }

        if ($type === 'heal') {
            $this->applyHeal($unit, $fx, $events);
            return;
        }

        if ($type === 'shield') {
            $this->applyShieldRefresh($unit, $fx, $events);
            return;
        }

        if ($type === 'energy_gain' || $type === 'energy_drain') {
            $this->applyEnergyEffect($unit, $fx, $events);
            return;
        }

        if ($type === 'bleed' || $type === 'regen') {
            $stackCount = 0;
            foreach ($unit['effects'] as $existing) {
                if (is_array($existing) && ($existing['type'] ?? '') === $type) {
                    $stackCount++;
                }
            }
            if ($stackCount < 3) {
                $fx['applied_round'] = $currentRound;
                $unit['effects'][] = $fx;
            }
            return;
        }

        if ($type === 'stun' || $type === 'freeze' || $type === 'ability_lock') {
            $fx['applied_round'] = $currentRound;
            $this->applyControlWithDiminishing($unit, $type, $fx, $events);
            return;
        }

        // refresh buff/debuff/otros
        $refreshed = false;
        foreach ($unit['effects'] as $idx => $existing) {
            if (is_array($existing) && ($existing['type'] ?? '') === $type) {
                $unit['effects'][$idx] = $fx;
                $refreshed = true;
                break;
            }
        }
        if (!$refreshed) {
            $fx['applied_round'] = $currentRound;
            $unit['effects'][] = $fx;
        }
    }

    /**
     * @param array<string, mixed> $fx
     * @param list<array<string, mixed>> $events
     */
    private function isCatalogEffect(array $fx, array &$events): bool
    {
        $type = (string) ($fx['type'] ?? '');
        if ($type === '' || !isset(self::EFFECT_RULES[$type])) {
            $events[] = ['type' => 'invalid_effect_type', 'effect' => $fx];
            return false;
        }
        $rule = self::EFFECT_RULES[$type];
        if (!empty($rule['duration']) && !array_key_exists('duration', $fx)) {
            $events[] = ['type' => 'invalid_effect_payload', 'reason' => 'missing_duration', 'effect' => $fx];
            return false;
        }
        if (!empty($rule['value']) && !array_key_exists('value', $fx)) {
            $events[] = ['type' => 'invalid_effect_payload', 'reason' => 'missing_value', 'effect' => $fx];
            return false;
        }
        if (!empty($rule['chance']) && !array_key_exists('chance', $fx)) {
            $events[] = ['type' => 'invalid_effect_payload', 'reason' => 'missing_chance', 'effect' => $fx];
            return false;
        }
        return true;
    }

    /**
     * @param array<string, mixed> $unit
     * @param array<string, mixed> $fx
     * @param list<array<string, mixed>> $events
     */
    private function applyCleanse(array &$unit, array $fx, array &$events, int $currentRound): void
    {
        $remove = $fx['remove'] ?? [];
        if (!is_array($remove)) {
            $remove = [];
        }
        $filtered = [];
        foreach ($unit['effects'] as $existing) {
            if (!is_array($existing)) {
                continue;
            }
            if ((int) ($existing['applied_round'] ?? 0) === $currentRound) {
                $filtered[] = $existing;
                continue;
            }
            $t = (string) ($existing['type'] ?? '');
            $isDebuff = $this->isDebuffType($t);
            $isControl = in_array($t, ['stun', 'freeze', 'ability_lock'], true);
            if (in_array('debuff', $remove, true) && $isDebuff) {
                continue;
            }
            if (in_array('control', $remove, true) && $isControl) {
                continue;
            }
            $filtered[] = $existing;
        }
        $unit['effects'] = $filtered;
        $events[] = ['type' => 'cleanse', 'removed' => $remove];
    }

    private function isDebuffType(string $type): bool
    {
        return in_array(
            $type,
            ['weaken', 'damage_amp', 'mind_down', 'focus_down', 'speed_down', 'anti_heal', 'bleed', 'action_efficiency_down', 'energy_block', 'energy_drain', 'random_debuff'],
            true
        );
    }

    /**
     * @param array<string, mixed> $actor
     */
    private function scaledValue(float $base, array $actor, string $scaling): float
    {
        $stat = 0;
        if ($scaling === 'focus') {
            $stat = (int) ($actor['stats']['focus'] ?? 0);
        } elseif ($scaling === 'speed') {
            $stat = (int) ($actor['stats']['speed'] ?? 0);
        } elseif ($scaling === 'luck') {
            $stat = (int) ($actor['stats']['luck'] ?? 0);
        } else {
            $stat = (int) ($actor['stats']['mind'] ?? 0);
        }
        return $base * (1 + $stat / 100.0);
    }

    /**
     * @param array<string, mixed> $target
     */
    private function collectDamageAmp(array $target, float $base): float
    {
        $amp = $base;
        foreach ($target['effects'] ?? [] as $fx) {
            if (is_array($fx) && ($fx['type'] ?? '') === 'damage_amp') {
                $amp *= (1 + (float) ($fx['value'] ?? 0));
            }
        }
        return $amp;
    }

    /**
     * @param array<string, mixed> $attacker
     */
    private function collectWeaken(array $attacker, float $base): float
    {
        $mul = $base;
        foreach ($attacker['effects'] ?? [] as $fx) {
            if (is_array($fx) && ($fx['type'] ?? '') === 'weaken') {
                $mul *= (1 - (float) ($fx['value'] ?? 0));
            }
        }
        return max(0.0, $mul);
    }

    /**
     * @param array<string, mixed> $target
     * @param array<string, mixed> $fx
     */
    private function collectConditionalBonus(array $fx, array $target, float $base): float
    {
        if (($fx['type'] ?? '') === 'bonus_vs_condition') {
            $condition = (string) ($fx['condition'] ?? '');
            if ($condition === 'target_hp_above_70') {
                $hp = (int) ($target['hp'] ?? 0);
                $max = max(1, (int) ($target['hp_max'] ?? $hp));
                if (($hp / $max) > 0.70) {
                    return $base * (1 + (float) ($fx['value'] ?? 0));
                }
            }
        }
        return $base;
    }

    /**
     * @param array<string, mixed> $target
     */
    private function collectDamageReduction(array $target, float $baseMit): float
    {
        $mit = $baseMit;
        foreach ($target['effects'] ?? [] as $fx) {
            if (!is_array($fx)) {
                continue;
            }
            if (($fx['type'] ?? '') === 'damage_reduction') {
                $mit += (float) ($fx['value'] ?? 0);
            }
        }
        return $mit;
    }

    /**
     * @param array<string, mixed> $target
     */
    private function consumeShieldBeforeHp(array &$target, int $damage): int
    {
        if ($damage <= 0) {
            return 0;
        }
        if (!isset($target['effects']) || !is_array($target['effects'])) {
            return $damage;
        }
        foreach ($target['effects'] as $idx => $fx) {
            if (!is_array($fx) || ($fx['type'] ?? '') !== 'shield') {
                continue;
            }
            $shield = (int) ($fx['remaining'] ?? $fx['value'] ?? 0);
            if ($shield <= 0) {
                continue;
            }
            $absorbed = min($shield, $damage);
            $shield -= $absorbed;
            $damage -= $absorbed;
            $target['effects'][$idx]['remaining'] = $shield;
            if ($shield <= 0) {
                unset($target['effects'][$idx]);
            }
            if ($damage <= 0) {
                break;
            }
        }
        $target['effects'] = array_values($target['effects']);
        return $damage;
    }

    /**
     * @param array<string, mixed> $unit
     * @param array<string, mixed> $fx
     * @param list<array<string, mixed>> $events
     */
    private function applyHeal(array &$unit, array $fx, array &$events): void
    {
        $base = (float) ($fx['value'] ?? 0.0);
        $focus = (int) ($unit['stats']['focus'] ?? 0);
        $healAmount = $base * (1 + $focus / 100.0);
        $antiHeal = 0.0;
        foreach ($unit['effects'] ?? [] as $e) {
            if (is_array($e) && ($e['type'] ?? '') === 'anti_heal') {
                $antiHeal += (float) ($e['value'] ?? 0.0);
            }
        }
        $antiHeal = min(CombatCaps::ANTI_HEAL_MAX, max(0.0, $antiHeal));
        $finalHeal = max(0.0, $healAmount * (1 - $antiHeal));
        $before = (int) ($unit['hp'] ?? 0);
        $max = max($before, (int) ($unit['hp_max'] ?? $before));
        $unit['hp'] = min($max, $before + (int) round($finalHeal));
        $events[] = [
            'type' => 'heal',
            'targetUnitId' => (string) ($fx['targetUnitId'] ?? ''),
            'sourceUnitId' => (string) ($fx['sourceUnitId'] ?? ''),
            'value' => (int) round($finalHeal),
        ];
    }

    /**
     * @param array<string, mixed> $unit
     * @param array<string, mixed> $fx
     * @param list<array<string, mixed>> $events
     */
    private function applyShieldRefresh(array &$unit, array $fx, array &$events): void
    {
        $max = max(1, (int) ($unit['hp_max'] ?? $unit['hp'] ?? 1));
        $shieldValue = (int) round($max * (float) ($fx['value'] ?? 0.0));
        if (!isset($unit['effects']) || !is_array($unit['effects'])) {
            $unit['effects'] = [];
        }
        $refreshed = false;
        foreach ($unit['effects'] as $idx => $existing) {
            if (is_array($existing) && ($existing['type'] ?? '') === 'shield') {
                $unit['effects'][$idx]['value'] = $shieldValue;
                $unit['effects'][$idx]['remaining'] = $shieldValue;
                $unit['effects'][$idx]['duration'] = (int) ($fx['duration'] ?? 1);
                $refreshed = true;
                break;
            }
        }
        if (!$refreshed) {
            $fx['value'] = $shieldValue;
            $fx['remaining'] = $shieldValue;
            $unit['effects'][] = $fx;
        }
        $events[] = ['type' => 'shield', 'value' => $shieldValue];
    }

    /**
     * @param array<string, mixed> $unit
     * @param array<string, mixed> $fx
     * @param list<array<string, mixed>> $events
     */
    private function applyEnergyEffect(array &$unit, array $fx, array &$events): void
    {
        $before = (int) ($unit['energy'] ?? 0);
        $delta = (int) ($fx['value'] ?? 0);
        if (($fx['type'] ?? '') === 'energy_drain') {
            $delta = -abs($delta);
        }
        $unit['energy'] = max(0, min(CombatCaps::ENERGY_MAX, $before + $delta));
        $events[] = ['type' => 'energy_effect', 'delta' => $delta, 'after' => (int) $unit['energy']];
    }

    /**
     * Tick de efectos por ronda (regen/bleed, expiración de duraciones).
     *
     * @param array<string, mixed> $state
     * @param list<array<string, mixed>> $events
     */
    public function tickRoundEffects(array &$state, array &$events): void
    {
        if (!isset($state['units']) || !is_array($state['units'])) {
            return;
        }
        foreach ($state['units'] as $uid => &$unit) {
            if (!is_array($unit) || empty($unit['alive'])) {
                continue;
            }

            $hpMax = max(1, (int) ($unit['hp_max'] ?? $unit['hp'] ?? 1));
            $damageAmp = $this->collectDamageAmp($unit, 1.0);
            $antiHeal = 0.0;
            foreach ($unit['effects'] ?? [] as $fx) {
                if (is_array($fx) && ($fx['type'] ?? '') === 'anti_heal') {
                    $antiHeal += (float) ($fx['value'] ?? 0.0);
                }
            }
            $antiHeal = min(CombatCaps::ANTI_HEAL_MAX, max(0.0, $antiHeal));

            foreach ($unit['effects'] ?? [] as $idx => $fx) {
                if (!is_array($fx)) {
                    continue;
                }
                $type = (string) ($fx['type'] ?? '');
                if ($type === 'bleed') {
                    $dot = max(1, (int) round($hpMax * (float) ($fx['value'] ?? 0.0) * $damageAmp));
                    $shielded = $this->consumeShieldBeforeHp($unit, $dot);
                    $unit['hp'] = max(0, (int) ($unit['hp'] ?? 0) - $shielded);
                    $events[] = ['type' => 'bleed_tick', 'unitId' => (string) $uid, 'value' => $shielded];
                } elseif ($type === 'regen') {
                    $base = (float) ($fx['value'] ?? 0.0) * $hpMax;
                    $focus = (int) ($unit['stats']['focus'] ?? 0);
                    $heal = $base * (1 + $focus / 100.0);
                    $heal = max(0, (int) round($heal * (1 - $antiHeal)));
                    $before = (int) ($unit['hp'] ?? 0);
                    $unit['hp'] = min($hpMax, $before + $heal);
                    $events[] = ['type' => 'regen_tick', 'unitId' => (string) $uid, 'value' => ((int) $unit['hp'] - $before)];
                }

                if (isset($fx['duration'])) {
                    $left = (int) $fx['duration'] - 1;
                    if ($left <= 0) {
                        unset($unit['effects'][$idx]);
                    } else {
                        $unit['effects'][$idx]['duration'] = $left;
                    }
                }
            }
            if (isset($unit['effects']) && is_array($unit['effects'])) {
                $unit['effects'] = array_values($unit['effects']);
            }
        }
        unset($unit);
    }
}

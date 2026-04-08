<?php
declare(strict_types=1);

/**
 * Solo (actor, targets[], skill). Sin state global, sin targeting, sin validación de acción.
 */
final class SquadSkillExecutor
{
    /**
     * @param array<string, mixed> $actor
     * @param list<array<string, mixed>> $targets
     * @param array<string, mixed> $skill
     * @return array{events: list<mixed>, hit_success: bool, targets_hit: list<string>, effects: list<mixed>, self_effects: list<mixed>, meta: array<string, mixed>}
     */
    public function execute(array $actor, array $targets, array $skill): array
    {
        $targetsHit = [];
        foreach ($targets as $t) {
            if (isset($t['id'])) {
                $targetsHit[] = (string) $t['id'];
            }
        }

        $base = (float) ($skill['base_power'] ?? 0);
        $canCrit = !empty($skill['can_crit']);
        $luck = (int) ($actor['stats']['luck'] ?? 0);
        $critChance = min(CombatCaps::CRIT_CHANCE_MAX, max(0.0, $luck * 0.002));
        $procChance = $this->effectiveProcChance((float) ($skill['proc_chance'] ?? 0), $luck);
        $damageAmp = 1.0;
        $weaken = 1.0;
        $conditionalBonus = 1.0;
        $hitCount = max(1, (int) ($skill['hit_count'] ?? 1));
        $hitType = (string) ($skill['hit_type'] ?? 'single');
        if ($hitType === 'single') {
            $hitCount = 1;
        }

        $effects = [];
        foreach ($targetsHit as $tid) {
            for ($i = 0; $i < $hitCount; $i++) {
                $crit = $canCrit && ((random_int(0, 9999) / 10000) < $critChance);
                $effects[] = [
                    'type' => 'damage',
                    'sourceUnitId' => (string) ($actor['id'] ?? ''),
                    'targetUnitId' => $tid,
                    'base_power' => $base / $hitCount,
                    'scalingStat' => (string) ($skill['scaling_stat'] ?? 'mind'),
                    'can_crit' => $canCrit,
                    'crit_multiplier' => $crit ? 1.5 : 1.0,
                    'damage_amp' => $damageAmp,
                    'weaken' => $weaken,
                    'conditional_bonus' => $conditionalBonus,
                    'crit' => $crit,
                    'hit_index' => $i,
                    'hit_count' => $hitCount,
                ];
            }
            // Por defecto los efectos secundarios solo en el ultimo hit.
            $payload = $skill['effect_payload'] ?? [];
            if (is_array($payload) && $payload !== []) {
                foreach ($payload as $fx) {
                    if (!is_array($fx)) {
                        continue;
                    }
                    $fx['sourceUnitId'] = (string) ($actor['id'] ?? '');
                    $fx['targetUnitId'] = $tid;
                    $fx['apply_on_hit'] = (int) ($fx['apply_on_hit'] ?? ($hitCount - 1));
                    $fx['proc_roll'] = (random_int(0, 9999) / 10000) < $procChance;
                    $effects[] = $fx;
                }
            }
        }

        return [
            'events' => [],
            'hit_success' => $targetsHit !== [],
            'targets_hit' => $targetsHit,
            'effects' => $effects,
            'self_effects' => [],
            'meta' => [
                'skill_id' => (string) ($skill['id'] ?? ''),
                'energy_gain_bonus' => (int) ($skill['energy_gain_on_use'] ?? 0),
                'passive_energy_gain' => (int) ($skill['passive_energy_gain'] ?? 0),
                'hit_count' => $hitCount,
            ],
        ];
    }

    private function effectiveProcChance(float $baseChance, int $luck): float
    {
        $baseChance = max(0.0, $baseChance);
        $chance = $baseChance * (1 + $luck / 100.0);
        return min(CombatCaps::PROC_CHANCE_MAX, $chance);
    }
}

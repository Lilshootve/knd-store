<?php
declare(strict_types=1);

/**
 * Ejecuta la acción tras validateAction en el engine. Skill/special delegan en SquadSkillExecutor.
 */
final class SquadActionResolver
{
    private SquadSkillExecutor $skillExecutor;
    private KndSquadSkillRegistry $skillRegistry;
    private float $critSoftCapStart;

    public function __construct(
        SquadSkillExecutor $skillExecutor,
        KndSquadSkillRegistry $skillRegistry,
        float $critSoftCapStart = 0.60
    ) {
        $this->skillExecutor = $skillExecutor;
        $this->skillRegistry = $skillRegistry;
        $this->critSoftCapStart = $critSoftCapStart;
    }

    /**
     * @param array<string, mixed> $actor debe incluir 'id'
     * @param array<string, mixed> $action
     * @param list<array<string, mixed>> $targets
     * @return array{events: list<mixed>, hit_success: bool, targets_hit: list<string>, effects: list<mixed>, self_effects: list<mixed>, meta: array<string, mixed>}
     */
    public function execute(array $actor, array $action, array $targets): array
    {
        $kind = (string) ($action['action'] ?? '');
        if ($kind === 'attack') {
            return $this->executeAttack($actor, $targets);
        }
        if ($kind === 'defend') {
            return [
                'events' => [],
                'hit_success' => false,
                'targets_hit' => [],
                'effects' => [],
                'self_effects' => [['type' => 'defend', 'turns' => 1]],
                'meta' => ['action' => 'defend'],
            ];
        }
        if (in_array($kind, ['skill', 'special'], true)) {
            $skillId = (string) ($action['skillId'] ?? '');
            $skill = $this->skillRegistry->get($skillId);
            if (!is_array($skill)) {
                return [
                    'events' => [],
                    'hit_success' => false,
                    'targets_hit' => [],
                    'effects' => [],
                    'self_effects' => [],
                    'meta' => ['error' => 'skill_missing'],
                ];
            }
            return $this->skillExecutor->execute($actor, $targets, $skill);
        }

        return [
            'events' => [],
            'hit_success' => false,
            'targets_hit' => [],
            'effects' => [],
            'self_effects' => [],
            'meta' => ['error' => 'unknown_action'],
        ];
    }

    /**
     * @param list<array<string, mixed>> $targets
     * @return array{events: list<mixed>, hit_success: bool, targets_hit: list<string>, effects: list<mixed>, self_effects: list<mixed>, meta: array<string, mixed>}
     */
    private function executeAttack(array $actor, array $targets): array
    {
        $base = 80.0;
        $luck = (int) ($actor['stats']['luck'] ?? 0);
        // crit_chance = luck * 0.2% con cap duro/soft-cap.
        $critChance = $this->effectiveCritChance($luck * 0.002);

        $hit = false;
        $targetsHit = [];
        $effects = [];
        foreach ($targets as $t) {
            $tid = (string) ($t['id'] ?? '');
            if ($tid === '') {
                continue;
            }
            $isCrit = (random_int(0, 9999) / 10000) < $critChance;
            $critMultiplier = $isCrit ? 1.5 : 1.0;
            $effects[] = [
                'type' => 'damage',
                'sourceUnitId' => (string) ($actor['id'] ?? ''),
                'targetUnitId' => $tid,
                'base_power' => $base,
                'scalingStat' => 'mind',
                'can_crit' => true,
                'crit_multiplier' => $critMultiplier,
                // Orden de multiplicadores: crit * damage_amp * weaken * conditional_bonus.
                'damage_amp' => 1.0,
                'weaken' => 1.0,
                'conditional_bonus' => 1.0,
                'crit' => $isCrit,
            ];
            $targetsHit[] = $tid;
            $hit = true;
        }

        return [
            'events' => [],
            'hit_success' => $hit,
            'targets_hit' => $targetsHit,
            'effects' => $effects,
            'self_effects' => [],
            'meta' => [
                'action' => 'attack',
                'crit_chance_effective' => $critChance,
                'energy_gain_bonus' => 0,
            ],
        ];
    }

    private function effectiveCritChance(float $raw): float
    {
        $c = max(0.0, min(CombatCaps::CRIT_CHANCE_MAX, $raw));
        if ($c <= $this->critSoftCapStart) {
            return $c;
        }
        $excess = $c - $this->critSoftCapStart;
        return $this->critSoftCapStart + $excess * 0.35;
    }
}

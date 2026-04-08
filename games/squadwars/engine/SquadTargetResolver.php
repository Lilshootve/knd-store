<?php
declare(strict_types=1);

/**
 * Resolución de targets + fallback por skill; no compartir con 1v1/3v3 cola.
 */
final class SquadTargetResolver
{
    /**
     * @param array<string, mixed> $actor
     * @param array<string, mixed>|null $action
     * @param array<string, array<string, mixed>> $units
     * @param array<string, mixed>|null $skill
     * @return list<array<string, mixed>> unidades objetivo (con id)
     */
    public function resolve(array $actor, ?array $action, array $units, ?array $skill): array
    {
        if ($action === null) {
            return [];
        }
        $side = (string) ($actor['side'] ?? 'player');
        $opp = $side === 'player' ? 'enemy' : 'player';

        $act = (string) ($action['action'] ?? '');
        if ($act === 'attack') {
            return $this->resolveAttackDefault($units, $opp, $action);
        }

        if (in_array($act, ['skill', 'special'], true) && is_array($skill)) {
            return $this->resolveWithSkillFallback($actor, $action, $units, $opp, $skill);
        }

        if ($act === 'heal') {
            return $this->resolveHealTargets($actor, $action, $units, $side);
        }

        return [];
    }

    public function canResolve(array $actor, ?array $action, array $units, ?array $skill): bool
    {
        $targets = $this->resolve($actor, $action, $units, $skill);
        $act = (string) ($action['action'] ?? '');
        return $targets !== [];
    }

    /**
     * Curación en cadena tras un ataque (mismo actor, mismo coste de energía en validate).
     *
     * @param array<string, array<string, mixed>> $units
     * @return list<array<string, mixed>>
     */
    public function resolveHealTargetsForActor(array $actor, string $healTargetKind, array $units): array
    {
        $side = (string) ($actor['side'] ?? 'player');

        return $this->resolveHealTargets($actor, ['action' => 'heal', 'healTarget' => $healTargetKind], $units, $side);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function resolveAttackDefault(array $units, string $oppSide, array $action): array
    {
        $slotOrder = ['front', 'mid', 'back'];
        $want = isset($action['targetSlot']) ? (string) $action['targetSlot'] : 'front';
        if (!in_array($want, $slotOrder, true)) {
            $want = 'front';
        }
        $try = [$want, ...array_values(array_diff($slotOrder, [$want]))];
        foreach ($try as $slot) {
            $u = $this->findUnitInSlot($units, $oppSide, $slot);
            if ($u !== null) {
                return [$u];
            }
        }
        return [];
    }

    /**
     * @param array<string, mixed> $skill
     * @return list<array<string, mixed>>
     */
    private function resolveWithSkillFallback(array $actor, array $action, array $units, string $oppSide, array $skill): array
    {
        $primary = $this->tryPrimary($action, $units, $oppSide);
        if ($primary !== null) {
            return [$primary];
        }
        $rules = $skill['fallback_rules'] ?? [];
        if (is_string($rules)) {
            $decoded = json_decode($rules, true);
            $rules = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($rules)) {
            $rules = [];
        }
        $chain = [];
        $scope = (string) ($action['targetScope'] ?? '');
        if ($scope !== '' && isset($rules[$scope]) && is_array($rules[$scope])) {
            $chain = $rules[$scope];
        } elseif (isset($rules['default']) && is_array($rules['default'])) {
            $chain = $rules['default'];
        }
        foreach ($chain as $step) {
            $t = $this->interpretFallbackStep($step, $units, $oppSide, $action);
            if ($t !== null) {
                return [$t];
            }
        }
        $fallback = $this->resolveAttackDefault($units, $oppSide, $action);
        if ($fallback !== []) {
            return $fallback;
        }
        $rnd = $this->pickRandomAlive($units, $oppSide);
        return $rnd !== null ? [$rnd] : [];
    }

    private function tryPrimary(array $action, array $units, string $oppSide): ?array
    {
        if (!empty($action['targetUnitId'])) {
            $id = (string) $action['targetUnitId'];
            if (isset($units[$id]) && !empty($units[$id]['alive'])) {
                $u = $units[$id];
                if (($u['side'] ?? '') === $oppSide) {
                    return $this->withId($u, $id);
                }
            }
        }
        if (!empty($action['targetSlot'])) {
            $u = $this->findUnitInSlot($units, $oppSide, (string) $action['targetSlot']);
            if ($u !== null) {
                return $u;
            }
        }
        return null;
    }

    /**
     * @param mixed $step
     */
    private function interpretFallbackStep($step, array $units, string $oppSide, array $action): ?array
    {
        if (is_string($step)) {
            if (str_starts_with($step, 'enemy_')) {
                $slot = substr($step, strlen('enemy_'));
                return $this->findUnitInSlot($units, $oppSide, $slot);
            }
            if ($step === 'lowest_hp') {
                return $this->pickByMetric($units, $oppSide, 'lowest_hp');
            }
            if ($step === 'highest_hp') {
                return $this->pickByMetric($units, $oppSide, 'highest_hp');
            }
            if ($step === 'highest_mind') {
                return $this->pickByMetric($units, $oppSide, 'highest_mind');
            }
            if ($step === 'most_debuffed') {
                return $this->pickByMetric($units, $oppSide, 'most_debuffed');
            }
            if ($step === 'random_alive') {
                return $this->pickRandomAlive($units, $oppSide);
            }
        }
        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findUnitInSlot(array $units, string $side, string $slot): ?array
    {
        foreach ($units as $id => $u) {
            if (!is_array($u) || empty($u['alive'])) {
                continue;
            }
            if (($u['side'] ?? '') === $side && ($u['slot'] ?? '') === $slot) {
                return $this->withId($u, (string) $id);
            }
        }
        return null;
    }

    private function withId(array $unit, ?string $id = null): array
    {
        if ($id !== null) {
            $unit['id'] = $id;
        }
        return $unit;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function pickRandomAlive(array $units, string $side): ?array
    {
        $alive = [];
        foreach ($units as $id => $u) {
            if (!is_array($u) || empty($u['alive'])) {
                continue;
            }
            if (($u['side'] ?? '') !== $side) {
                continue;
            }
            $alive[] = $this->withId($u, (string) $id);
        }
        if ($alive === []) {
            return null;
        }
        return $alive[random_int(0, count($alive) - 1)];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function pickByMetric(array $units, string $side, string $metric): ?array
    {
        $candidates = [];
        foreach ($units as $id => $u) {
            if (!is_array($u) || empty($u['alive']) || ($u['side'] ?? '') !== $side) {
                continue;
            }
            $candidates[] = $this->withId($u, (string) $id);
        }
        if ($candidates === []) {
            return null;
        }
        usort($candidates, static function (array $a, array $b) use ($metric): int {
            if ($metric === 'lowest_hp_ratio') {
                $ma = max(1, (int) ($a['hp_max'] ?? $a['hp'] ?? 1));
                $mb = max(1, (int) ($b['hp_max'] ?? $b['hp'] ?? 1));
                $ra = ((int) ($a['hp'] ?? 0)) / $ma;
                $rb = ((int) ($b['hp'] ?? 0)) / $mb;

                return $ra <=> $rb;
            }
            if ($metric === 'lowest_hp') {
                return ((int) ($a['hp'] ?? 0)) <=> ((int) ($b['hp'] ?? 0));
            }
            if ($metric === 'highest_hp') {
                return ((int) ($b['hp'] ?? 0)) <=> ((int) ($a['hp'] ?? 0));
            }
            if ($metric === 'highest_mind') {
                return ((int) ($b['stats']['mind'] ?? 0)) <=> ((int) ($a['stats']['mind'] ?? 0));
            }
            if ($metric === 'most_debuffed') {
                $ac = is_array($a['effects'] ?? null) ? count($a['effects']) : 0;
                $bc = is_array($b['effects'] ?? null) ? count($b['effects']) : 0;
                return $bc <=> $ac;
            }
            return 0;
        });
        return $candidates[0];
    }

    /**
     * @param array<string, mixed> $actor
     * @param array<string, mixed> $action
     * @param array<string, array<string, mixed>> $units
     * @return list<array<string, mixed>>
     */
    private function resolveHealTargets(array $actor, array $action, array $units, string $allySide): array
    {
        $kind = strtolower(trim((string) ($action['healTarget'] ?? $action['target'] ?? 'self')));
        if ($kind === 'lowest_ally') {
            $pick = $this->pickByMetric($units, $allySide, 'lowest_hp_ratio');

            return $pick !== null ? [$pick] : [$this->withId($actor)];
        }
        if ($kind === 'all_allies') {
            return $this->allAliveInSide($units, $allySide);
        }

        return [$this->withId($actor)];
    }

    /**
     * @param array<string, array<string, mixed>> $units
     * @return list<array<string, mixed>>
     */
    private function allAliveInSide(array $units, string $side): array
    {
        $out = [];
        foreach ($units as $id => $u) {
            if (!is_array($u) || empty($u['alive']) || ($u['side'] ?? '') !== $side) {
                continue;
            }
            $out[] = $this->withId($u, (string) $id);
        }

        return $out;
    }
}

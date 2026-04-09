<?php
declare(strict_types=1);

/**
 * Orquesta fases, timeline estable, energía, cooldowns globales por ronda, muertes.
 */
final class SquadSimEngine
{
    private InitiativeTimeline $initiativeTimeline;
    private SquadTargetResolver $targetResolver;
    private SquadActionResolver $actionResolver;
    private SquadEffectResolver $effectResolver;
    private KndSquadSkillRegistry $skillRegistry;

    public function __construct(
        InitiativeTimeline $initiativeTimeline,
        SquadTargetResolver $targetResolver,
        SquadActionResolver $actionResolver,
        SquadEffectResolver $effectResolver,
        KndSquadSkillRegistry $skillRegistry
    ) {
        $this->initiativeTimeline = $initiativeTimeline;
        $this->targetResolver = $targetResolver;
        $this->actionResolver = $actionResolver;
        $this->effectResolver = $effectResolver;
        $this->skillRegistry = $skillRegistry;
    }

    /**
     * @param array<string, mixed> $state
     */
    public function resolveRound(array &$state): SubmitRoundResultV1
    {
        $roundResolved = (int) ($state['round'] ?? 1);
        $state['phase'] = 'resolving';
        $this->recalculateSynergies($state);

        $units = &$state['units'];
        if (!is_array($units)) {
            $units = [];
        }

        $state['timeline'] = $this->initiativeTimeline->build($units);
        $timelineUsed = $state['timeline'];
        $events = [];

        foreach ($state['timeline'] as $unitId) {
            $unit = $units[$unitId] ?? null;
            if (!is_array($unit) || empty($unit['alive'])) {
                continue;
            }
            $events[] = ['type' => 'actor_turn', 'unitId' => $unitId];
            $unit['id'] = $unitId;

            $action = $state['plannedActions']['actions'][$unitId] ?? null;
            if (!is_array($action)) {
                $action = null;
            }

            if ($this->isActionBlockedByControl($unit, $action)) {
                $events[] = ['type' => 'action_lost_control', 'unitId' => $unitId];
                continue;
            }

            if (!$this->validateAction($unit, $action, $state, $events)) {
                continue;
            }

            /** @var array<string, mixed> $action */
            $skill = null;
            if (in_array((string) ($action['action'] ?? ''), ['skill', 'special'], true)) {
                $skill = $this->skillRegistry->get((string) ($action['skillId'] ?? ''));
            }

            $targets = $this->targetResolver->resolve($unit, $action, $units, $skill);
            $result = $this->actionResolver->execute($unit, $action, $targets);
            $this->appendHybridChainHeal($unit, $action, $units, $result);

            $this->appendActionLog($state, $unitId, $action, $targets, $result);

            $this->effectResolver->applyDamage($result, $state, $events);
            $this->effectResolver->applyOnHit($result, $state, $events);
            $this->effectResolver->applySecondary($result, $state, $events);
            $this->applyEnergy($unitId, $result, $state, $events);
            $this->processDeaths($state, $events);
            $this->fireSynergyTriggers($state, 'on_skill_used', ['unitId' => $unitId], $events);

            if ($this->isBattleOver($state)) {
                $state['phase'] = 'finished';
                $state['winner'] = $this->resolveWinner($state);
                $this->fireSynergyTriggers($state, 'on_kill', [], $events);
                break;
            }
        }

        if (($state['phase'] ?? '') !== 'finished') {
            $this->effectResolver->tickRoundEffects($state, $events);
            $this->processDeaths($state, $events);
            $this->tickCooldownsEndOfRound($state);
            $state['plannedActions'] = ['round' => (int) ($state['round'] ?? 1), 'actions' => []];
            $state['round'] = (int) ($state['round'] ?? 1) + 1;
            $state['phase'] = 'planning';
        }

        return new SubmitRoundResultV1(
            $events,
            $timelineUsed,
            $roundResolved,
            ($state['phase'] ?? '') === 'finished',
            isset($state['winner']) ? (string) $state['winner'] : null,
            (int) ($state['version'] ?? 1),
            (string) ($state['phase'] ?? 'planning'),
        );
    }

    /**
     * @param array<string, mixed> $unit
     * @param array<string, mixed>|null $action
     * @param array<string, mixed> $state
     * @param list<array<string, mixed>> $events
     */
    private function validateAction(array $unit, ?array $action, array $state, array &$events): bool
    {
        if ($action === null) {
            $events[] = ['type' => 'invalid_action', 'reason' => 'missing_action'];
            return false;
        }
        if (empty($unit['alive'])) {
            $events[] = ['type' => 'invalid_action', 'reason' => 'unit_dead'];
            return false;
        }

        if ((string) ($action['action'] ?? '') === 'defend') {
            $events[] = ['type' => 'invalid_action', 'reason' => 'defend_disabled'];
            return false;
        }

        if ((string) ($action['action'] ?? '') === 'heal') {
            $cost = max(0, (int) ($action['energyCost'] ?? 0));
            if ((int) ($unit['energy'] ?? 0) < $cost) {
                $events[] = ['type' => 'invalid_action', 'reason' => 'insufficient_energy'];
                return false;
            }
            $pct = (float) ($action['healPct'] ?? 0.0);
            if ($pct <= 0.0) {
                $events[] = ['type' => 'invalid_action', 'reason' => 'invalid_heal'];
                return false;
            }
        }

        if ((string) ($action['action'] ?? '') === 'attack') {
            $chain = (float) ($action['chainHealPct'] ?? 0.0);
            $cost = max(0, (int) ($action['energyCost'] ?? 0));
            if ($chain > 0.0 || $cost > 0) {
                if ((int) ($unit['energy'] ?? 0) < $cost) {
                    $events[] = ['type' => 'invalid_action', 'reason' => 'insufficient_energy'];
                    return false;
                }
            }
            if ($chain > 1.0) {
                $events[] = ['type' => 'invalid_action', 'reason' => 'invalid_chain_heal'];
                return false;
            }
        }

        $skill = null;
        if (in_array((string) ($action['action'] ?? ''), ['skill', 'special'], true)) {
            $skill = $this->skillRegistry->get((string) ($action['skillId'] ?? ''));
            if (!is_array($skill)) {
                $events[] = ['type' => 'invalid_action', 'reason' => 'skill_not_found'];
                return false;
            }
            $cost = (int) ($skill['energy_cost'] ?? 0);
            if ((int) ($unit['energy'] ?? 0) < $cost) {
                $events[] = ['type' => 'invalid_action', 'reason' => 'insufficient_energy'];
                return false;
            }
            $sid = (string) ($skill['id'] ?? '');
            if ($sid !== '' && (int) ($unit['cooldowns'][$sid] ?? 0) > 0) {
                $events[] = ['type' => 'invalid_action', 'reason' => 'skill_on_cooldown'];
                return false;
            }
        }

        $units = $state['units'] ?? [];
        if (!is_array($units) || !$this->targetResolver->canResolve($unit, $action, $units, $skill)) {
            $events[] = ['type' => 'invalid_action', 'reason' => 'invalid_target'];
            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $unit
     */
    private function isActionBlockedByControl(array $unit, ?array $action): bool
    {
        $best = null;
        $priority = ['stun' => 3, 'freeze' => 2, 'ability_lock' => 1];
        foreach ($unit['effects'] ?? [] as $fx) {
            if (!is_array($fx)) {
                continue;
            }
            $t = (string) ($fx['type'] ?? '');
            if (!isset($priority[$t])) {
                continue;
            }
            if ($best === null || $priority[$t] > $priority[$best]) {
                $best = $t;
            }
        }
        if ($best === null) {
            return false;
        }
        if ($best === 'ability_lock') {
            $kind = (string) ($action['action'] ?? '');
            return in_array($kind, ['ability', 'special', 'skill'], true);
        }
        return true;
    }

    /**
     * @param array<string, mixed> $state
     * @param array<string, mixed> $action
     * @param list<array<string, mixed>> $targets
     * @param array{events?: list<mixed>, hit_success?: bool, targets_hit?: list<string>, effects?: list<mixed>, self_effects?: list<mixed>, meta?: array<string, mixed>} $result
     */
    private function appendActionLog(array &$state, string $unitId, array $action, array $targets, array $result): void
    {
        $finalTargets = [];
        foreach ($targets as $t) {
            if (!empty($t['id'])) {
                $finalTargets[] = (string) $t['id'];
            }
        }
        $state['log'][] = [
            'type' => 'action',
            'actor' => $unitId,
            'action' => $action,
            'final_targets' => $finalTargets,
            'result' => $result,
        ];
    }

    /**
     * @param array{events?: list<mixed>, hit_success?: bool, targets_hit?: list<string>, effects?: list<mixed>, self_effects?: list<mixed>, meta?: array<string, mixed>} $result
     * @param array<string, mixed> $state
     * @param list<array<string, mixed>> $events
     */
    private function applyEnergy(string $unitId, array $result, array &$state, array &$events): void
    {
        if (!isset($state['units'][$unitId])) {
            return;
        }
        $before = (int) ($state['units'][$unitId]['energy'] ?? 0);
        // Orden: turno(+1) -> pasivas -> costo acción -> hit(+1) -> efectos -> clamp
        $gain = 1;
        $gain += (int) ($result['meta']['passive_energy_gain'] ?? 0);
        if (!empty($result['hit_success'])) {
            $gain += 1;
        }
        $gain += (int) ($result['meta']['energy_gain_bonus'] ?? 0);
        $gain = min(CombatCaps::ENERGY_GAIN_TURN_MAX, max(0, $gain)); // cap de ganancia por turno/unidad
        $action = $state['plannedActions']['actions'][$unitId] ?? [];
        if (is_array($action) && in_array((string) ($action['action'] ?? ''), ['skill', 'special'], true)) {
            $skill = $this->skillRegistry->get((string) ($action['skillId'] ?? ''));
            if (is_array($skill)) {
                $cost = (int) ($skill['energy_cost'] ?? 0);
                $state['units'][$unitId]['energy'] = max(0, min(CombatCaps::ENERGY_MAX, $before + $gain - $cost));
                $cd = (int) ($skill['cooldown'] ?? 0);
                if ($cd > 0 && !empty($skill['id'])) {
                    $state['units'][$unitId]['cooldowns'][(string) $skill['id']] = $cd;
                }
                $events[] = [
                    'type' => 'energy',
                    'unitId' => $unitId,
                    'before' => $before,
                    'gain' => $gain,
                    'after' => (int) $state['units'][$unitId]['energy'],
                    'spent' => $cost,
                ];
                return;
            }
        }
        if (is_array($action) && (string) ($action['action'] ?? '') === 'heal') {
            $cost = max(0, (int) ($action['energyCost'] ?? 0));
            $gain = 1;
            $gain += (int) ($result['meta']['passive_energy_gain'] ?? 0);
            if (!empty($result['hit_success'])) {
                $gain += 1;
            }
            $gain += (int) ($result['meta']['energy_gain_bonus'] ?? 0);
            $gain = min(CombatCaps::ENERGY_GAIN_TURN_MAX, max(0, $gain));
            $state['units'][$unitId]['energy'] = max(0, min(CombatCaps::ENERGY_MAX, $before + $gain - $cost));
            $events[] = [
                'type' => 'energy',
                'unitId' => $unitId,
                'before' => $before,
                'gain' => $gain,
                'after' => (int) $state['units'][$unitId]['energy'],
                'spent' => $cost,
            ];
            return;
        }
        $attackFee = 0;
        if (is_array($action) && (string) ($action['action'] ?? '') === 'attack') {
            $attackFee = max(0, (int) ($action['energyCost'] ?? 0));
        }
        $state['units'][$unitId]['energy'] = max(0, min(CombatCaps::ENERGY_MAX, $before + $gain - $attackFee));
        $events[] = [
            'type' => 'energy',
            'unitId' => $unitId,
            'before' => $before,
            'gain' => $gain,
            'after' => (int) $state['units'][$unitId]['energy'],
            'spent' => $attackFee,
        ];
    }

    /**
     * Una sola acción `attack`: daño + curación en cadena (mismo coste de energía, un tick de timeline cliente).
     *
     * @param array<string, mixed> $actor
     * @param array<string, mixed> $action
     * @param array<string, array<string, mixed>> $units
     * @param array{events?: list<mixed>, hit_success?: bool, targets_hit?: list<string>, effects?: list<mixed>, self_effects?: list<mixed>, meta?: array<string, mixed>} $result
     */
    private function appendHybridChainHeal(array $actor, array $action, array $units, array &$result): void
    {
        if ((string) ($action['action'] ?? '') !== 'attack') {
            return;
        }
        $chainPct = (float) ($action['chainHealPct'] ?? 0.0);
        if ($chainPct <= 0.0) {
            return;
        }
        $chainPct = max(0.0, min(1.0, $chainPct));
        $kind = strtolower(trim((string) ($action['chainHealTarget'] ?? 'self')));
        $healTargets = $this->targetResolver->resolveHealTargetsForActor($actor, $kind, $units);
        $srcId = (string) ($actor['id'] ?? '');
        foreach ($healTargets as $ht) {
            $tid = (string) ($ht['id'] ?? '');
            if ($tid === '') {
                continue;
            }
            $maxHp = max(1, (int) ($ht['hp_max'] ?? $ht['hp'] ?? 1));
            $val = (int) round($maxHp * $chainPct);
            if ($val < 1) {
                $val = 1;
            }
            $result['effects'][] = [
                'type' => 'heal',
                'sourceUnitId' => $srcId,
                'targetUnitId' => $tid,
                'value' => $val,
            ];
        }
    }

    /**
     * @param array<string, mixed> $state
     * @param list<array<string, mixed>> $events
     */
    private function processDeaths(array &$state, array &$events): void
    {
        $units = &$state['units'];
        if (!is_array($units)) {
            return;
        }
        foreach ($units as $id => &$u) {
            if (!is_array($u) || empty($u['alive'])) {
                continue;
            }
            if ((int) ($u['hp'] ?? 1) <= 0) {
                $u['alive'] = false;
                // limpiamos efectos al morir para evitar residuos de turno restante.
                $u['effects'] = [];
                $events[] = ['type' => 'death', 'unitId' => (string) $id];
                $this->recalculateSynergies($state);
                $this->fireSynergyTriggers($state, 'on_kill', ['victim' => (string) $id], $events);
            }
        }
        unset($u);
    }

    /**
     * @param array<string, mixed> $state
     */
    private function tickCooldownsEndOfRound(array &$state): void
    {
        $units = &$state['units'];
        if (!is_array($units)) {
            return;
        }
        foreach ($units as &$u) {
            if (!is_array($u) || empty($u['alive'])) {
                continue;
            }
            if (!isset($u['cooldowns']) || !is_array($u['cooldowns'])) {
                continue;
            }
            foreach ($u['cooldowns'] as $sid => $left) {
                $u['cooldowns'][$sid] = max(0, (int) $left - 1);
            }
            $this->tickControlDiminishingReset($u);
        }
        unset($u);
    }

    /**
     * Reset DR de control si no recibe control por 2 turnos.
     *
     * @param array<string, mixed> $unit
     */
    private function tickControlDiminishingReset(array &$unit): void
    {
        $keys = ['stun', 'freeze', 'ability_lock'];
        foreach ($keys as $k) {
            $ageKey = 'control_age_' . $k;
            $stackKey = 'control_stack_' . $k;
            $unit[$ageKey] = (int) ($unit[$ageKey] ?? 0) + 1;
            if ((int) $unit[$ageKey] >= 2) {
                $unit[$stackKey] = 0;
            }
        }
    }

    /**
     * @param array<string, mixed> $state
     */
    private function recalculateSynergies(array &$state): void
    {
        // Pasivas: ampliar con ClassSynergyRules en /shared.
        $state['meta']['synergies_revision'] = (int) ($state['meta']['synergies_revision'] ?? 0) + 1;
    }

    /**
     * @param array<string, mixed> $state
     * @param array<string, mixed> $context
     * @param list<array<string, mixed>> $events
     */
    private function fireSynergyTriggers(array &$state, string $trigger, array $context, array &$events): void
    {
        $events[] = ['type' => 'synergy_trigger', 'trigger' => $trigger, 'context' => $context];
    }

    /**
     * @param array<string, mixed> $state
     */
    private function isBattleOver(array $state): bool
    {
        $units = $state['units'] ?? [];
        if (!is_array($units)) {
            return true;
        }
        $p = $e = false;
        foreach ($units as $u) {
            if (!is_array($u) || empty($u['alive'])) {
                continue;
            }
            if (($u['side'] ?? '') === 'player') {
                $p = true;
            }
            if (($u['side'] ?? '') === 'enemy') {
                $e = true;
            }
        }
        return !$p || !$e;
    }

    /**
     * @param array<string, mixed> $state
     */
    private function resolveWinner(array $state): string
    {
        $units = $state['units'] ?? [];
        $p = false;
        foreach ($units as $u) {
            if (is_array($u) && !empty($u['alive']) && ($u['side'] ?? '') === 'player') {
                $p = true;
                break;
            }
        }
        return $p ? 'player' : 'enemy';
    }
}

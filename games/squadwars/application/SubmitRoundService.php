<?php
declare(strict_types=1);

final class SubmitRoundService
{
    private SquadSimEngine $engine;

    public function __construct(SquadSimEngine $engine)
    {
        $this->engine = $engine;
    }

    /**
     * @param array<string, mixed> $state
     * @param list<array<string, mixed>> $actionsList
     */
    public function submit(array &$state, string $battleToken, int $round, array $actionsList, int $clientStateVersion): SubmitRoundResultV1
    {
        if ($clientStateVersion !== (int) ($state['version'] ?? 1)) {
            throw new InvalidArgumentException('state_out_of_sync');
        }
        if ($round !== (int) ($state['round'] ?? 1)) {
            throw new InvalidArgumentException('round_mismatch');
        }

        $byId = [];
        foreach ($actionsList as $row) {
            if (!is_array($row)) {
                continue;
            }
            $uid = (string) ($row['unitId'] ?? '');
            if ($uid !== '') {
                $byId[$uid] = $row;
            }
        }

        $merged = $this->mergeDefaultActions($state, $byId);
        $state['plannedActions'] = ['round' => $round, 'actions' => $merged];

        $raw = $this->engine->resolveRound($state);
        $state['version'] = (int) ($state['version'] ?? 1) + 1;

        return $raw->withStateVersion((int) $state['version']);
    }

    /**
     * Entrada canónica para API: mantiene contrato sin exponer detalles internos.
     *
     * @param array<string, mixed> $state
     * @param array<string, mixed> $payload
     */
    public function execute(array &$state, array $payload): SubmitRoundResultV1
    {
        $battleToken = (string) ($payload['battle_token'] ?? '');
        $round = (int) ($payload['round'] ?? 0);
        $actions = isset($payload['actions']) && is_array($payload['actions']) ? $payload['actions'] : [];
        $clientStateVersion = (int) ($payload['clientStateVersion'] ?? -1);

        return $this->submit($state, $battleToken, $round, $actions, $clientStateVersion);
    }

    /**
     * @param array<string, mixed> $state
     * @param array<string, array<string, mixed>> $byId
     * @return array<string, array<string, mixed>>
     */
    private function mergeDefaultActions(array $state, array $byId): array
    {
        $units = $state['units'] ?? [];
        if (!is_array($units)) {
            return $byId;
        }
        $out = $byId;
        foreach ($units as $id => $u) {
            if (!is_array($u) || empty($u['alive'])) {
                continue;
            }
            if (isset($out[(string) $id])) {
                continue;
            }
            $side = (string) ($u['side'] ?? 'player');
            if ($side === 'enemy') {
                $out[(string) $id] = [
                    'unitId' => (string) $id,
                    'action' => 'attack',
                    'targetScope' => SquadTargetScope::SLOT_ENEMY,
                    'targetSlot' => 'front',
                ];
            } else {
                $out[(string) $id] = [
                    'unitId' => (string) $id,
                    'action' => 'attack',
                    'targetScope' => SquadTargetScope::SLOT_ENEMY,
                    'targetSlot' => 'front',
                ];
            }
        }
        return $out;
    }
}

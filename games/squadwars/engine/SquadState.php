<?php
declare(strict_types=1);

/**
 * Helpers de normalización de estado; contrato canónico en SquadStateV1.
 */
final class SquadState
{
    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    public static function ensurePlannedActionsShape(array $state): array
    {
        $pa = $state['plannedActions'] ?? [];
        if (!is_array($pa)) {
            $pa = [];
        }
        if (!isset($pa['round']) || !isset($pa['actions']) || !is_array($pa['actions'])) {
            $state['plannedActions'] = [
                'round' => (int) ($state['round'] ?? 1),
                'actions' => is_array($pa['actions'] ?? null) ? $pa['actions'] : [],
            ];
        }
        return $state;
    }
}

<?php
declare(strict_types=1);

/**
 * Contrato state SquadWars v1 (single source of truth).
 * @see docs/KND_COMBAT_DEPENDENCY_MAP.md
 */
final class SquadStateV1
{
    public const MODE = 'squadwars';

    /**
     * @return array<string, mixed>
     */
    public static function emptyShell(string $battleId): array
    {
        return [
            'battleId' => $battleId,
            'mode' => self::MODE,
            'phase' => 'planning',
            'round' => 1,
            'units' => [],
            'plannedActions' => [
                'round' => 1,
                'actions' => [],
            ],
            'timeline' => [],
            'log' => [],
            'winner' => null,
            'version' => 1,
        ];
    }
}

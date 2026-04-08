<?php
declare(strict_types=1);

/**
 * Registro de skills SquadWars (carga DB/config). Stub hasta cablear PDO.
 */
final class KndSquadSkillRegistry
{
    /** @var array<string, array<string, mixed>> */
    private array $cache = [];

    /** @var PDO|null */
    private $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    public function get(string $skillId): ?array
    {
        if ($skillId === '') {
            return null;
        }
        return $this->cache[$skillId] ?? null;
    }

    public function register(string $skillId, array $definition): void
    {
        $errors = $this->validateDefinition($definition);
        if ($errors !== []) {
            throw new InvalidArgumentException('invalid_skill_definition:' . implode('|', $errors));
        }
        $this->cache[$skillId] = $definition;
    }

    /**
     * @param array<string, mixed> $definition
     * @return list<string>
     */
    public function validateDefinition(array $definition): array
    {
        $errors = [];
        $required = ['id', 'type', 'target_scope', 'scaling_stat', 'base_power', 'energy_cost', 'cooldown'];
        foreach ($required as $k) {
            if (!array_key_exists($k, $definition)) {
                $errors[] = 'missing_' . $k;
            }
        }

        $basePower = (float) ($definition['base_power'] ?? 0);
        $type = (string) ($definition['type'] ?? '');
        if ($type === 'attack' && ($basePower < 60 || $basePower > 75)) {
            $errors[] = 'power_budget_attack';
        } elseif ($type === 'skill' && ($basePower < 85 || $basePower > 110)) {
            $errors[] = 'power_budget_skill';
        } elseif ($type === 'special' && ($basePower < 110 || $basePower > 150)) {
            $errors[] = 'power_budget_special';
        }

        $proc = (float) ($definition['proc_chance'] ?? 0);
        if ($proc > 0.75) {
            $errors[] = 'proc_cap_exceeded';
        }

        return $errors;
    }
}

<?php
declare(strict_types=1);

/**
 * Orden global: initiative = speed + rand(1,25) para que speed siga primando pero haya mezcla cuando están parejos;
 * desempate luck, luego moneda (rng inyectable en tests).
 */
final class InitiativeTimeline
{
    /** @var ?callable */
    private $rngInt;

    public function __construct(?callable $rngInt = null)
    {
        $this->rngInt = $rngInt;
    }

    /**
     * @param array<string, array<string, mixed>> $units
     * @return list<string> unitIds ordenados (solo vivos)
     */
    public function build(array $units): array
    {
        $roll = $this->rngInt ?? static fn (int $min, int $max): int => random_int($min, $max);

        $rows = [];
        foreach ($units as $id => $u) {
            if (empty($u['alive'])) {
                continue;
            }
            $speed = (int) ($u['stats']['speed'] ?? 0);
            $luck = (int) ($u['stats']['luck'] ?? 0);
            $init = $speed + $roll(1, 25);
            $rows[] = ['id' => (string) $id, 'init' => $init, 'luck' => $luck];
        }

        usort($rows, static function (array $a, array $b) use ($roll): int {
            if ($a['init'] !== $b['init']) {
                return $b['init'] <=> $a['init'];
            }
            if ($a['luck'] !== $b['luck']) {
                return $b['luck'] <=> $a['luck'];
            }
            return $roll(0, 1) === 0 ? -1 : 1;
        });

        return array_map(static fn (array $r) => $r['id'], $rows);
    }
}

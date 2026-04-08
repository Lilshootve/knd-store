<?php
declare(strict_types=1);

/**
 * Procs basados en Luck (sin esquiva por speed).
 */
final class KndLuckProcResolver
{
    private float $roll01;

    public function __construct(float $roll01)
    {
        $this->roll01 = $roll01;
    }

    public function proc(float $chance01): bool
    {
        $c = max(0.0, min(1.0, $chance01));
        return $this->roll01 < $c;
    }
}

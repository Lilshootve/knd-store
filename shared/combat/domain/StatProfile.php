<?php
declare(strict_types=1);

/**
 * DTO de stats de combate compartido (MindWars / SquadWars).
 * Ampliar cuando se extraigan factories desde includes/mind_wars.php.
 */
final class KndStatProfile
{
    public int $mind;
    public int $focus;
    public int $speed;
    public int $luck;

    public function __construct(int $mind, int $focus, int $speed, int $luck)
    {
        $this->mind = $mind;
        $this->focus = $focus;
        $this->speed = $speed;
        $this->luck = $luck;
    }
}

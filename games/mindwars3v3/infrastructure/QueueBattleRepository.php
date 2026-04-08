<?php
declare(strict_types=1);

/** Repositorio futuro para knd_mw3v3_battles. */
final class QueueBattleRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }
}

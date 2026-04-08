<?php
declare(strict_types=1);

/** Repositorio futuro para knd_mw1v1_battles (cutover desde knd_mind_wars_battles). */
final class DuelBattleRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }
}

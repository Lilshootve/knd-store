<?php
declare(strict_types=1);

require_once __DIR__ . '/../infrastructure/SquadBattleRepository.php';

/**
 * Lectura de estado persistido (knd_squadwars_battles) — implementar al conectar repositorio.
 */
final class GetSquadBattleStateService
{
    private SquadBattleRepository $repository;

    public function __construct(SquadBattleRepository $repository)
    {
        $this->repository = $repository;
    }

    public function getByToken(string $token): ?array
    {
        return $this->repository->findByToken($token);
    }
}

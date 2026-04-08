<?php
declare(strict_types=1);

/**
 * Punto de entrada histórico; la carga real está en includes/mind_wars_arena_bootstrap.php
 * para que las APIs funcionen aunque games/mindwars1v1/ no exista en el servidor.
 */
require_once dirname(__DIR__, 2) . '/includes/mind_wars_arena_bootstrap.php';

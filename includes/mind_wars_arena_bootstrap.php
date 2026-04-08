<?php
declare(strict_types=1);

/**
 * Carga el stack Mind Wars para APIs (1v1 / cola / PvE) sin depender de games/mindwars1v1/
 * en servidores donde esa carpeta no está desplegada.
 */
if (!defined('BASE_PATH')) {
    require_once dirname(__DIR__) . '/config/bootstrap.php';
}

require_once BASE_PATH . '/includes/mind_wars.php';
require_once BASE_PATH . '/includes/mind_wars_combat_actions.php';

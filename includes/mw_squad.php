<?php
declare(strict_types=1);

/**
 * Punto de carga único para el squad legacy: el archivo real vive bajo games/mind-wars-squad.
 * Evita rutas rotas cuando los API hacen require BASE_PATH/includes/mw_squad.php
 */
if (!defined('BASE_PATH')) {
    require_once dirname(__DIR__) . '/config/bootstrap.php';
}
require_once BASE_PATH . '/games/mind-wars-squad/includes/mw_squad.php';

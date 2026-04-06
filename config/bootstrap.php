<?php
/**
 * Raíz del proyecto PHP (en hosting suele ser public_html; en repo: backend/kndstore).
 * Cargar primero desde cualquier script: require_once …/config/bootstrap.php (ajustar ../).
 */
declare(strict_types=1);

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}
if (!defined('KND_ROOT')) {
    define('KND_ROOT', BASE_PATH);
}

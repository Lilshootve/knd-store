<?php
/**
 * KND Store - Bootstrap para rutas portables en hosting compartido.
 * Define KND_ROOT (directorio raíz del proyecto) sin rutas absolutas del servidor.
 * Este archivo debe vivir en includes/, por tanto KND_ROOT = directorio que contiene includes/.
 */
if (defined('KND_ROOT')) {
    return;
}
define('KND_ROOT', dirname(__DIR__));

<?php
declare(strict_types=1);

/**
 * Mind Wars 3v3 lineal (cola / oleadas) — capa modular sobre el mismo state que 1v1.
 */
if (!defined('BASE_PATH')) {
    require_once dirname(__DIR__, 2) . '/config/bootstrap.php';
}

require_once BASE_PATH . '/includes/mind_wars_arena_bootstrap.php';
require_once __DIR__ . '/engine/QueueFormat.php';
require_once __DIR__ . '/engine/PveKnockoutResolver.php';

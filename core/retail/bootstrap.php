<?php
/**
 * KND Retail Module — Bootstrap
 * Carga todos los includes del módulo retail en orden correcto.
 * Incluir este archivo al inicio de cualquier endpoint retail.
 */

require_once __DIR__ . '/../../config/bootstrap.php';
defined('KND_ROOT') or define('KND_ROOT', BASE_PATH);

// Core del sistema KND (ya existentes)
require_once BASE_PATH . '/includes/env.php';
require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/csrf.php';
require_once BASE_PATH . '/includes/rate_limit.php';
require_once BASE_PATH . '/includes/json.php';

// Módulo retail
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/validate.php';
require_once __DIR__ . '/parser.php';
require_once __DIR__ . '/intent_mapper.php';

<?php
/**
 * KND Retail Module — Bootstrap
 * Carga todos los includes del módulo retail en orden correcto.
 * Incluir este archivo al inicio de cualquier endpoint retail.
 */

defined('KND_ROOT') or define('KND_ROOT', dirname(__DIR__, 2));

// Core del sistema KND (ya existentes)
require_once KND_ROOT . '/includes/env.php';
require_once KND_ROOT . '/includes/session.php';
require_once KND_ROOT . '/includes/config.php';
require_once KND_ROOT . '/includes/auth.php';
require_once KND_ROOT . '/includes/csrf.php';
require_once KND_ROOT . '/includes/rate_limit.php';
require_once KND_ROOT . '/includes/json.php';

// Módulo retail
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/validate.php';
require_once __DIR__ . '/parser.php';
require_once __DIR__ . '/intent_mapper.php';

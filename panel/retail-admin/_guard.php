<?php
/**
 * KND Retail Admin — Guard Middleware
 * Incluir al inicio de cada página del dashboard.
 * Verifica sesión + resolución de business_id + rol admin.
 */

defined('KND_ROOT') or define('KND_ROOT', dirname(__DIR__, 2));

require_once KND_ROOT . '/includes/env.php';
require_once KND_ROOT . '/includes/session.php';
require_once KND_ROOT . '/includes/config.php';
require_once KND_ROOT . '/includes/auth.php';
require_once KND_ROOT . '/core/retail/auth.php';
require_once KND_ROOT . '/core/retail/audit.php';
require_once __DIR__ . '/_rbac.php';

// 1. Verificar sesión activa
if (!is_logged_in()) {
    header('Location: /auth.php?next=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// 2. Conectar a DB
$pdo = getDBConnection();
if (!$pdo) {
    http_response_code(503);
    die('<p style="font-family:sans-serif;color:red">Error de base de datos. Intente más tarde.</p>');
}

// 3. Resolver negocio desde sesión (inyecta retail_business_id, retail_role, etc.)
$userId = current_user_id();
$resolved = retail_resolve_business_for_gateway($pdo, $userId);
if (!$resolved) {
    http_response_code(403);
    die('<p style="font-family:sans-serif;color:red">No tienes un negocio asignado. Contacta al administrador del sistema.</p>');
}

// 4. Solo admins pueden acceder al dashboard
if (!retail_is_admin()) {
    http_response_code(403);
    die('<p style="font-family:sans-serif;color:red">Acceso denegado. Se requiere rol admin.</p>');
}

// Variables globales de conveniencia para las páginas
$RETAIL_BIZ    = retail_business();
$RETAIL_BIZ_ID = retail_business_id();
$RETAIL_ROLE   = retail_role();
$RETAIL_USER   = current_username();

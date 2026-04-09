<?php
// GET — catálogo activo de nexus_furniture_catalog (misma fuente que Sanctum).
// Requiere sesión: mismo criterio que api/nexus/sanctum.php (api_require_login).
require_once __DIR__ . '/../../config/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/json.php';
require_once BASE_PATH . '/includes/nexus_furniture_catalog.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('METHOD_NOT_ALLOWED', 'Only GET allowed', 405);
}

api_require_login();

$pdo = getDBConnection();
$uid = current_user_id();
if (!$uid) {
    json_error('AUTH_REQUIRED', 'Invalid session (no user id)', 401);
}

try {
    $catalog = nexus_furniture_catalog_fetch_active($pdo);
    json_success(['catalog' => $catalog]);
} catch (PDOException $e) {
    error_log('furniture_catalog: ' . $e->getMessage());
    json_error('DB_ERROR', 'Failed to load catalog', 500);
}

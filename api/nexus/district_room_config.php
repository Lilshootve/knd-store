<?php
// GET ?district=tesla — JSON para district-room.php (NPCs, tema). Requiere sesión.
require_once __DIR__ . '/../../config/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/json.php';
require_once BASE_PATH . '/includes/nexus_district_room_registry.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('METHOD_NOT_ALLOWED', 'Only GET allowed', 405);
}

if (!is_logged_in()) {
    json_error('UNAUTHORIZED', 'Debes iniciar sesión', 401);
}

$district = isset($_GET['district']) ? strtolower(trim((string)$_GET['district'])) : '';
if ($district === '' || !in_array($district, nexus_district_room_layer_ids(), true)) {
    json_error('INVALID_DISTRICT', 'Distrito no válido o sin sala instanciada', 422);
}

$cfg = nexus_district_room_get($district);
if (!$cfg) {
    json_error('NOT_FOUND', 'Configuración de sala no encontrada', 404);
}

json_success([
    'district_id' => $district,
    'room'        => $cfg,
    'return_hint' => nexus_district_room_entry_url($district),
]);

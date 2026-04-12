<?php
/**
 * Distrito — GLB en Nexus City + transform compartido por todos los usuarios.
 *
 * GET  (sin auth)  → { districts: [{ id, city_glb_url, city_mesh_* }], migration_applied }
 * POST (auth+WB)   → actualiza city_glb_url y/o pos_x,pos_y,pos_z,rot_y,scale (mismas claves que world builder)
 *
 * Lectura completa del mundo: GET /api/nexus/world.php (incluye estos campos en districts[]).
 */
require_once __DIR__ . '/../../config/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/nexus_world_builder_gate.php';
require_once BASE_PATH . '/includes/json.php';

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ── GET: lectura pública (comprueba migración y lista URLs/transforms) ───────
if ($method === 'GET') {
    try {
        $cols = $pdo->query('SHOW COLUMNS FROM nexus_districts')->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        error_log('district_city_mesh GET: ' . $e->getMessage());
        json_success(['districts' => [], 'migration_applied' => false]);
    }
    if (!in_array('city_glb_url', $cols, true)) {
        json_success(['districts' => [], 'migration_applied' => false]);
    }
    try {
        $rows = $pdo->query('
            SELECT id,
                   city_glb_url,
                   city_mesh_pos_x, city_mesh_pos_y, city_mesh_pos_z,
                   city_mesh_rot_y, city_mesh_scale
            FROM nexus_districts
            ORDER BY sort_order ASC, id ASC
        ')->fetchAll(PDO::FETCH_ASSOC);
        json_success(['districts' => $rows, 'migration_applied' => true]);
    } catch (Throwable $e) {
        error_log('district_city_mesh GET list: ' . $e->getMessage());
        json_error('DB_ERROR', 'No se pudo leer distritos', 500);
    }
}

// ── POST: escritura solo World Builder ──────────────────────────────────────
if ($method !== 'POST') {
    json_error('METHOD_NOT_ALLOWED', 'Solo GET o POST', 405);
}

if (!is_logged_in()) {
    json_error('UNAUTHORIZED', 'Debes estar autenticado', 401);
}

$uid = (int) current_user_id();
if ($uid <= 0) {
    json_error('UNAUTHORIZED', 'Sesión sin user id válido', 401);
}

if (!nexus_user_can_world_builder($pdo, $uid)) {
    json_error('FORBIDDEN', 'Solo administradores pueden modificar distritos', 403);
}

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) {
    json_error('BAD_JSON', 'Cuerpo JSON inválido', 400);
}

$districtId = isset($body['district_id']) ? strtolower(trim((string) $body['district_id'])) : '';
if ($districtId === '' || !preg_match('/^[a-z0-9_-]{1,32}$/', $districtId)) {
    json_error('INVALID_DISTRICT', 'district_id inválido', 422);
}

try {
    $cols = $pdo->query('SHOW COLUMNS FROM nexus_districts')->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    json_error('DB_ERROR', 'No se pudo leer esquema de distritos', 500);
}
if (!in_array('city_glb_url', $cols, true)) {
    json_error('MIGRATION_REQUIRED', 'Ejecuta sql/nexus_districts_city_mesh.sql en la base de datos', 503);
}

$check = $pdo->prepare('SELECT 1 FROM nexus_districts WHERE id = ? LIMIT 1');
$check->execute([$districtId]);
if (!$check->fetchColumn()) {
    json_error('NOT_FOUND', 'Distrito no encontrado', 404);
}

/** @return string|null */
function nexus_sanitize_city_glb_url($v): ?string
{
    if ($v === null || $v === '') {
        return null;
    }
    $s = trim((string) $v);
    if (strlen($s) > 512) {
        return null;
    }
    if (strncmp($s, '/assets/', 8) === 0 || strncmp($s, '/games/', 7) === 0) {
        return $s;
    }
    if (preg_match('#^https://[a-z0-9._-]+/#i', $s)) {
        return $s;
    }
    return null;
}

$sets    = [];
$params  = [];

if (array_key_exists('city_glb_url', $body)) {
    $url = nexus_sanitize_city_glb_url($body['city_glb_url']);
    if ($body['city_glb_url'] !== null && $body['city_glb_url'] !== '' && $url === null) {
        json_error('INVALID_URL', 'city_glb_url debe ser /assets/... , /games/... o https://...', 422);
    }
    $sets[] = 'city_glb_url = ?';
    $params[] = $url;
}

foreach (['pos_x' => 'city_mesh_pos_x', 'pos_y' => 'city_mesh_pos_y', 'pos_z' => 'city_mesh_pos_z', 'rot_y' => 'city_mesh_rot_y', 'scale' => 'city_mesh_scale'] as $in => $col) {
    if (!array_key_exists($in, $body)) {
        continue;
    }
    $val = $body[$in];
    if ($val === null) {
        $sets[] = "{$col} = NULL";
        continue;
    }
    if (!is_numeric($val)) {
        json_error('INVALID_FIELD', "Campo numérico inválido: {$in}", 422);
    }
    $f = (float) $val;
    if ($in === 'scale' && ($f <= 0 || $f > 500)) {
        json_error('INVALID_SCALE', 'scale debe estar entre 0 y 500', 422);
    }
    $sets[] = "{$col} = ?";
    $params[] = $f;
}

if (empty($sets)) {
    json_error('EMPTY_PATCH', 'Nada que actualizar', 400);
}

$params[] = $districtId;
$sql = 'UPDATE nexus_districts SET ' . implode(', ', $sets) . ' WHERE id = ?';

try {
    $st = $pdo->prepare($sql);
    $st->execute($params);
} catch (Throwable $e) {
    error_log('district_city_mesh patch: ' . $e->getMessage());
    json_error('DB_ERROR', 'No se pudo guardar', 500);
}

json_success(['district_id' => $districtId, 'updated' => true]);

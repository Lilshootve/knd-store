<?php
// api/nexus/world_builder.php
// CRUD para nexus_world_objects — sólo admins pueden escribir.
// GET  ?action=load         → lista todos los objetos colocados
// POST {action:'place', …}  → inserta uno nuevo
// POST {action:'delete', id} → borra por id
// POST {action:'patch', id, rot_y?, scale?, pos_x?, material_data?, light_data?} → actualiza
require_once __DIR__ . '/../../config/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/nexus_world_builder_gate.php';
require_once BASE_PATH . '/includes/json.php';

$pdo = getDBConnection();

// ── Determinar acción ──
$method = $_SERVER['REQUEST_METHOD'];
$action = null;
$body   = [];

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'load';
} elseif ($method === 'POST') {
    $raw  = file_get_contents('php://input');
    $body = json_decode($raw, true) ?: [];
    $action = $body['action'] ?? null;
} else {
    json_error('METHOD_NOT_ALLOWED', 'Solo GET/POST', 405);
}

// ── Helper: normaliza y valida JSON ──────────────────────────────────────────
function normalize_json_field($value, int $maxLen = 16384): ?string {
    if ($value === null || $value === '') return null;
    if (is_array($value)) {
        $enc = json_encode($value, JSON_UNESCAPED_UNICODE);
        $value = ($enc !== false) ? $enc : null;
    } else {
        $value = (string) $value;
    }
    if ($value === null) return null;
    // Validate JSON
    json_decode($value);
    if (json_last_error() !== JSON_ERROR_NONE) return null;
    // Length guard
    if (strlen($value) > $maxLen) return null;
    return $value;
}

// ── LOAD: público (sin sesión) ───────────────────────────────────────────────
if ($method === 'GET' && $action === 'load') {
    try {
        // Detect whether material_data column exists (migration may not have run yet)
        $cols = $pdo->query("SHOW COLUMNS FROM nexus_world_objects")->fetchAll(PDO::FETCH_COLUMN);
        $hasMaterialData = in_array('material_data', $cols);

        $selectExtra = $hasMaterialData ? ', material_data' : '';

        $rows = $pdo->query("
            SELECT id, item_id, model_url,
                   pos_x, pos_y, pos_z,
                   rot_y, scale,
                   light_data{$selectExtra},
                   created_by, created_at
            FROM nexus_world_objects
            ORDER BY id ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        json_success(['objects' => $rows]);
    } catch (Throwable $e) {
        error_log('world_builder load: ' . $e->getMessage());
        json_success(['objects' => []]);
    }
}

// ── Resto: requiere sesión ───────────────────────────────────────────────────
if (!is_logged_in()) {
    json_error('UNAUTHORIZED', 'Debes estar autenticado', 401);
}
$uid = current_user_id();
if (!$uid) {
    json_error('UNAUTHORIZED', 'Sesión sin user id válido', 401);
}

// ── Acciones de escritura → requieren admin ──────────────────────────────────
if (!nexus_user_can_world_builder($pdo, $uid)) {
    json_error('FORBIDDEN', 'Solo administradores pueden modificar el mundo', 403);
}

// ── PLACE ────────────────────────────────────────────────────────────────────
if ($action === 'place') {
    $item_id      = trim($body['item_id']   ?? '');
    $model_url    = trim($body['model_url'] ?? '');
    $pos_x        = (float)($body['pos_x']  ?? 0);
    $pos_y        = (float)($body['pos_y']  ?? 0);
    $pos_z        = (float)($body['pos_z']  ?? 0);
    $rot_y        = (float)($body['rot_y']  ?? 0);
    $scale        = (float)($body['scale']  ?? 1.0);
    $light_data   = normalize_json_field($body['light_data']    ?? null);
    $material_data = normalize_json_field($body['material_data'] ?? null);

    if ($item_id === '') {
        json_error('VALIDATION', 'item_id es requerido', 422);
    }
    if ($scale < 0.01 || $scale > 20) $scale = 1.0;

    // Detect material_data column
    $cols = $pdo->query("SHOW COLUMNS FROM nexus_world_objects")->fetchAll(PDO::FETCH_COLUMN);
    $hasMaterialData = in_array('material_data', $cols);

    try {
        if ($hasMaterialData) {
            $stmt = $pdo->prepare("
                INSERT INTO nexus_world_objects
                    (item_id, model_url, pos_x, pos_y, pos_z, rot_y, scale,
                     light_data, material_data, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $item_id, $model_url ?: null,
                $pos_x, $pos_y, $pos_z, $rot_y, $scale,
                $light_data, $material_data, $uid
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO nexus_world_objects
                    (item_id, model_url, pos_x, pos_y, pos_z, rot_y, scale, light_data, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $item_id, $model_url ?: null,
                $pos_x, $pos_y, $pos_z, $rot_y, $scale,
                $light_data, $uid
            ]);
        }
        $newId = (int)$pdo->lastInsertId();
        json_success(['id' => $newId]);
    } catch (PDOException $e) {
        error_log('world_builder place: ' . $e->getMessage());
        json_error('DB_ERROR', 'Error al guardar objeto', 500);
    }
    return;
}

// ── DELETE ───────────────────────────────────────────────────────────────────
if ($action === 'delete') {
    $id = (int)($body['id'] ?? 0);
    if ($id <= 0) {
        json_error('VALIDATION', 'id inválido', 422);
    }
    try {
        $stmt = $pdo->prepare("DELETE FROM nexus_world_objects WHERE id = ?");
        $stmt->execute([$id]);
        json_success(['deleted' => $stmt->rowCount() > 0]);
    } catch (PDOException $e) {
        error_log('world_builder delete: ' . $e->getMessage());
        json_error('DB_ERROR', 'Error al borrar objeto', 500);
    }
    return;
}

// ── PATCH (transform + material + light) ────────────────────────────────────
if ($action === 'patch') {
    $id = (int)($body['id'] ?? 0);
    if ($id <= 0) {
        json_error('VALIDATION', 'id inválido', 422);
    }

    // Detect material_data column
    $cols = $pdo->query("SHOW COLUMNS FROM nexus_world_objects")->fetchAll(PDO::FETCH_COLUMN);
    $hasMaterialData = in_array('material_data', $cols);

    // Numeric fields
    $numericAllowed = ['rot_y', 'scale', 'pos_x', 'pos_y', 'pos_z'];
    $sets   = [];
    $params = [];

    foreach ($numericAllowed as $field) {
        if (isset($body[$field])) {
            $sets[]   = "`$field` = ?";
            $params[] = (float)$body[$field];
        }
    }

    // JSON field: light_data
    // NOTE: use array_key_exists (not isset) so explicit null is detected
    if (array_key_exists('light_data', $body)) {
        $val = $body['light_data'];
        if ($val === null || $val === '') {
            $sets[] = '`light_data` = NULL';   // explicit clear
        } else {
            $normalized = normalize_json_field($val);
            if ($normalized !== null) {
                $sets[]   = '`light_data` = ?';
                $params[] = $normalized;
            }
        }
    }

    // JSON field: material_data (only if column exists)
    if ($hasMaterialData && array_key_exists('material_data', $body)) {
        $val = $body['material_data'];
        if ($val === null || $val === '') {
            $sets[] = '`material_data` = NULL';  // explicit clear
        } else {
            $normalized = normalize_json_field($val);
            if ($normalized !== null) {
                $sets[]   = '`material_data` = ?';
                $params[] = $normalized;
            }
        }
    }

    if (empty($sets)) {
        json_error('VALIDATION', 'Ningún campo válido para actualizar', 422);
    }

    $params[] = $id;

    try {
        $stmt = $pdo->prepare(
            "UPDATE nexus_world_objects SET " . implode(', ', $sets) . " WHERE id = ?"
        );
        $stmt->execute($params);
        json_success(['updated' => $stmt->rowCount() > 0]);
    } catch (PDOException $e) {
        error_log('world_builder patch: ' . $e->getMessage());
        json_error('DB_ERROR', 'Error al actualizar objeto', 500);
    }
    return;
}

json_error('INVALID_ACTION', "Acción '$action' no reconocida", 400);

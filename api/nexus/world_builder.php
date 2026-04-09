<?php
// api/nexus/world_builder.php
// CRUD para nexus_world_objects — sólo admins pueden escribir.
// GET  ?action=load         → lista todos los objetos colocados
// POST {action:'place', …}  → inserta uno nuevo
// POST {action:'delete', id} → borra por id
// POST {action:'patch', id, rot_y?, scale?} → actualiza transform
require_once __DIR__ . '/../../config/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/json.php';

$pdo = getDBConnection();

// ── Verificar autenticación ──
if (!is_logged_in()) {
    json_error('UNAUTHORIZED', 'Debes estar autenticado', 401);
}
$uid = (int)$_SESSION['user_id'];

// ── Verificar rol admin ──
// Fuente principal: admin_users (username + active)
// Fallback legado: users.role
function isAdmin(PDO $pdo, int $uid): bool {
    try {
        $u = $pdo->prepare("SELECT username FROM users WHERE id = ? LIMIT 1");
        $u->execute([$uid]);
        $username = $u->fetchColumn();
        if ($username) {
            $a = $pdo->prepare("
                SELECT role
                FROM admin_users
                WHERE username = ?
                  AND active = 1
                LIMIT 1
            ");
            $a->execute([$username]);
            $adminRole = $a->fetchColumn();
            if (in_array($adminRole, ['owner', 'manager', 'support'], true)) {
                return true;
            }
        }
    } catch (PDOException $_) {
        // noop: intenta fallback
    }

    try {
        $s = $pdo->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
        $s->execute([$uid]);
        $role = $s->fetchColumn();
        return in_array($role, ['admin', 'superadmin', 'mod'], true);
    } catch (PDOException $_) {
        return false;
    }
}

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

// ── LOAD (público — se cargan al entrar al Nexus) ──
if ($action === 'load') {
    try {
        $rows = $pdo->query("
            SELECT id, item_id, model_url, pos_x, pos_y, pos_z,
                   rot_y, scale, light_data, created_by, created_at
            FROM nexus_world_objects
            ORDER BY id ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // La tabla puede no existir aún
        error_log('world_builder load: ' . $e->getMessage());
        json_success(['objects' => []]);
        return;
    }
    json_success(['objects' => $rows]);
    return;
}

// ── Acciones de escritura → requieren admin ──
if (!isAdmin($pdo, $uid)) {
    json_error('FORBIDDEN', 'Solo administradores pueden modificar el mundo', 403);
}

// ── PLACE ──
if ($action === 'place') {
    $item_id   = trim($body['item_id']   ?? '');
    $model_url = trim($body['model_url'] ?? '');
    $pos_x     = (float)($body['pos_x']  ?? 0);
    $pos_y     = (float)($body['pos_y']  ?? 0);
    $pos_z     = (float)($body['pos_z']  ?? 0);
    $rot_y     = (float)($body['rot_y']  ?? 0);
    $scale     = (float)($body['scale']  ?? 1.0);

    if ($item_id === '') {
        json_error('VALIDATION', 'item_id es requerido', 422);
    }
    if ($scale < 0.01 || $scale > 20) $scale = 1.0;

    try {
        $stmt = $pdo->prepare("
            INSERT INTO nexus_world_objects
                (item_id, model_url, pos_x, pos_y, pos_z, rot_y, scale, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $item_id,
            $model_url ?: null,
            $pos_x, $pos_y, $pos_z,
            $rot_y, $scale,
            $uid
        ]);
        $newId = (int)$pdo->lastInsertId();
        json_success(['id' => $newId]);
    } catch (PDOException $e) {
        error_log('world_builder place: ' . $e->getMessage());
        json_error('DB_ERROR', 'Error al guardar objeto', 500);
    }
    return;
}

// ── DELETE ──
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

// ── PATCH (actualizar transform sin recolocar) ──
if ($action === 'patch') {
    $id = (int)($body['id'] ?? 0);
    if ($id <= 0) {
        json_error('VALIDATION', 'id inválido', 422);
    }

    // Construir SET dinámico — sólo campos permitidos
    $allowed = ['rot_y', 'scale', 'pos_x', 'pos_y', 'pos_z'];
    $sets = []; $params = [];
    foreach ($allowed as $field) {
        if (isset($body[$field])) {
            $sets[]   = "`$field` = ?";
            $params[] = (float)$body[$field];
        }
    }
    if (empty($sets)) {
        json_error('VALIDATION', 'Ningún campo válido para actualizar', 422);
    }
    $params[] = $id;

    try {
        $stmt = $pdo->prepare("UPDATE nexus_world_objects SET " . implode(', ', $sets) . " WHERE id = ?");
        $stmt->execute($params);
        json_success(['updated' => $stmt->rowCount() > 0]);
    } catch (PDOException $e) {
        error_log('world_builder patch: ' . $e->getMessage());
        json_error('DB_ERROR', 'Error al actualizar objeto', 500);
    }
    return;
}

json_error('INVALID_ACTION', "Acción '$action' no reconocida", 400);

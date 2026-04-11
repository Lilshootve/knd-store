<?php
/**
 * api/admin/world_builder_catalog.php
 * Admin CRUD for nexus_world_builder_catalog (City / World Builder items).
 *
 * GET    — list all items (including inactive if ?all=1)
 * POST   — create (no id) or update (id provided) an item
 * DELETE — soft-delete: set is_active = 0
 */
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../admin/_guard.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

admin_require_login();
admin_require_perm('nexus.catalog.edit');

$pdo    = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

// ── helpers ──────────────────────────────────────────────────────────

function wbc_json_ok(?string $raw): bool {
    if ($raw === null || $raw === '') return true;
    json_decode($raw);
    return json_last_error() === JSON_ERROR_NONE;
}

function wbc_respond(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function wbc_error(string $msg, int $code = 400): void {
    wbc_respond(['ok' => false, 'error' => $msg], $code);
}

// ── GET ───────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $showAll = !empty($_GET['all']);
    $where   = $showAll ? '' : 'WHERE is_active = 1';
    try {
        $st = $pdo->query(
            "SELECT id, item_code, name, category, rarity, model_url,
                    wb_scale, default_light_json, hologram, sort_order, is_active
             FROM nexus_world_builder_catalog
             $where
             ORDER BY sort_order ASC, name ASC"
        );
        $rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
        wbc_respond(['ok' => true, 'items' => $rows]);
    } catch (PDOException $e) {
        error_log('admin/world_builder_catalog GET: ' . $e->getMessage());
        wbc_error('Database error', 500);
    }
}

// ── POST (create / update) ────────────────────────────────────────────
elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) wbc_error('JSON body required');

    $id                 = isset($input['id']) ? (int)$input['id'] : 0;
    $item_code          = trim((string)($input['item_code']          ?? ''));
    $name               = trim((string)($input['name']               ?? ''));
    $category           = trim((string)($input['category']           ?? ''));
    $rarity             = trim((string)($input['rarity']             ?? 'common'));
    $model_url          = trim((string)($input['model_url']          ?? ''));
    $wb_scale           = max(0.001, (float)($input['wb_scale']      ?? 1.0));
    $default_light_json = isset($input['default_light_json']) ? trim((string)$input['default_light_json']) : null;
    $hologram           = isset($input['hologram']) ? (int)(bool)$input['hologram'] : 0;
    $sort_order         = (int)($input['sort_order'] ?? 0);
    $is_active          = isset($input['is_active'])  ? (int)(bool)$input['is_active'] : 1;

    if ($item_code === '') wbc_error('item_code is required');
    if ($name === '')      wbc_error('name is required');
    if ($category === '')  wbc_error('category is required');
    if ($model_url === '') wbc_error('model_url is required');

    $allowed_rarities = ['common','uncommon','rare','epic','legendary'];
    if (!in_array($rarity, $allowed_rarities, true)) wbc_error('Invalid rarity');

    if ($default_light_json !== null && $default_light_json !== '' && !wbc_json_ok($default_light_json)) {
        wbc_error('default_light_json must be valid JSON');
    }
    if ($default_light_json === '') $default_light_json = null;

    try {
        if ($id > 0) {
            $st = $pdo->prepare('
                UPDATE nexus_world_builder_catalog
                SET item_code=?, name=?, category=?, rarity=?, model_url=?,
                    wb_scale=?, default_light_json=?, hologram=?, sort_order=?, is_active=?
                WHERE id=?
            ');
            $st->execute([$item_code, $name, $category, $rarity, $model_url,
                          $wb_scale, $default_light_json, $hologram, $sort_order, $is_active, $id]);
            if ($st->rowCount() === 0) wbc_error('Item not found', 404);
            wbc_respond(['ok' => true, 'updated' => true, 'id' => $id]);
        } else {
            $st = $pdo->prepare('
                INSERT INTO nexus_world_builder_catalog
                    (item_code, name, category, rarity, model_url, wb_scale,
                     default_light_json, hologram, sort_order, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $st->execute([$item_code, $name, $category, $rarity, $model_url,
                          $wb_scale, $default_light_json, $hologram, $sort_order, $is_active]);
            wbc_respond(['ok' => true, 'created' => true, 'id' => (int)$pdo->lastInsertId()], 201);
        }
    } catch (PDOException $e) {
        error_log('admin/world_builder_catalog POST: ' . $e->getMessage());
        if ($e->getCode() == 23000) wbc_error('Duplicate item_code — an item with that code already exists');
        wbc_error('Database error', 500);
    }
}

// ── DELETE (soft) ─────────────────────────────────────────────────────
elseif ($method === 'DELETE') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) wbc_error('id is required');
    try {
        $st = $pdo->prepare('UPDATE nexus_world_builder_catalog SET is_active = 0 WHERE id = ?');
        $st->execute([$id]);
        if ($st->rowCount() === 0) wbc_error('Item not found', 404);
        wbc_respond(['ok' => true, 'disabled' => true, 'id' => $id]);
    } catch (PDOException $e) {
        error_log('admin/world_builder_catalog DELETE: ' . $e->getMessage());
        wbc_error('Database error', 500);
    }
} else {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
}

<?php
/**
 * api/admin/furniture_catalog.php
 * Admin CRUD for nexus_furniture_catalog (Sanctum items).
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

function fc_json_ok(?string $raw): bool {
    if ($raw === null || $raw === '') return true; // nullable field
    json_decode($raw);
    return json_last_error() === JSON_ERROR_NONE;
}

function fc_respond(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function fc_error(string $msg, int $code = 400): void {
    fc_respond(['ok' => false, 'error' => $msg], $code);
}

// ── GET ───────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $showAll = !empty($_GET['all']);
    $where   = $showAll ? '' : 'WHERE is_active = 1';
    try {
        $st = $pdo->query(
            "SELECT id, code, name, category, rarity, width, depth,
                    price_kp, asset_data, is_active
             FROM nexus_furniture_catalog
             $where
             ORDER BY category, price_kp, name"
        );
        $rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
        fc_respond(['ok' => true, 'items' => $rows]);
    } catch (PDOException $e) {
        error_log('admin/furniture_catalog GET: ' . $e->getMessage());
        fc_error('Database error', 500);
    }
}

// ── POST (create / update) ────────────────────────────────────────────
elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) fc_error('JSON body required');

    $id         = isset($input['id']) ? (int)$input['id'] : 0;
    $code       = trim((string)($input['code']     ?? ''));
    $name       = trim((string)($input['name']     ?? ''));
    $category   = trim((string)($input['category'] ?? ''));
    $rarity     = trim((string)($input['rarity']   ?? 'common'));
    $width      = max(1, (int)($input['width']     ?? 1));
    $depth      = max(1, (int)($input['depth']     ?? 1));
    $price_kp   = max(0, (int)($input['price_kp']  ?? 0));
    $asset_data = isset($input['asset_data']) ? trim((string)$input['asset_data']) : null;
    $is_active  = isset($input['is_active'])  ? (int)(bool)$input['is_active'] : 1;

    if ($code === '')     fc_error('code is required');
    if ($name === '')     fc_error('name is required');
    if ($category === '') fc_error('category is required');

    $allowed_rarities = ['common','uncommon','rare','epic','legendary'];
    if (!in_array($rarity, $allowed_rarities, true)) fc_error('Invalid rarity');

    if ($asset_data !== null && $asset_data !== '' && !fc_json_ok($asset_data)) {
        fc_error('asset_data must be valid JSON');
    }
    // Normalise empty → null
    if ($asset_data === '') $asset_data = null;

    try {
        if ($id > 0) {
            // Update
            $st = $pdo->prepare('
                UPDATE nexus_furniture_catalog
                SET code=?, name=?, category=?, rarity=?, width=?, depth=?,
                    price_kp=?, asset_data=?, is_active=?
                WHERE id=?
            ');
            $st->execute([$code, $name, $category, $rarity, $width, $depth,
                          $price_kp, $asset_data, $is_active, $id]);
            if ($st->rowCount() === 0) fc_error('Item not found', 404);
            fc_respond(['ok' => true, 'updated' => true, 'id' => $id]);
        } else {
            // Create
            $st = $pdo->prepare('
                INSERT INTO nexus_furniture_catalog
                    (code, name, category, rarity, width, depth, price_kp, asset_data, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $st->execute([$code, $name, $category, $rarity, $width, $depth,
                          $price_kp, $asset_data, $is_active]);
            fc_respond(['ok' => true, 'created' => true, 'id' => (int)$pdo->lastInsertId()], 201);
        }
    } catch (PDOException $e) {
        error_log('admin/furniture_catalog POST: ' . $e->getMessage());
        if ($e->getCode() == 23000) fc_error('Duplicate code — item with that code already exists');
        fc_error('Database error', 500);
    }
}

// ── DELETE (soft) ─────────────────────────────────────────────────────
elseif ($method === 'DELETE') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) fc_error('id is required');
    try {
        $st = $pdo->prepare('UPDATE nexus_furniture_catalog SET is_active = 0 WHERE id = ?');
        $st->execute([$id]);
        if ($st->rowCount() === 0) fc_error('Item not found', 404);
        fc_respond(['ok' => true, 'disabled' => true, 'id' => $id]);
    } catch (PDOException $e) {
        error_log('admin/furniture_catalog DELETE: ' . $e->getMessage());
        fc_error('Database error', 500);
    }
} else {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
}

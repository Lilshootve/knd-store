<?php
// GET — catálogo 3D del World Builder (tabla nexus_world_builder_catalog).
// Separado de furniture_catalog.php (Sanctum / muebles de habitación).
// Solo usuarios con permiso de World Builder (mismo criterio que world_builder.php escritura).
require_once __DIR__ . '/../../config/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/json.php';
require_once BASE_PATH . '/includes/nexus_world_builder_gate.php';
require_once BASE_PATH . '/includes/nexus_world_builder_catalog.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('METHOD_NOT_ALLOWED', 'Only GET allowed', 405);
}

api_require_login();

$pdo = getDBConnection();
$uid = current_user_id();
if (!$uid) {
    json_error('AUTH_REQUIRED', 'Invalid session (no user id)', 401);
}

if (!nexus_user_can_world_builder($pdo, (int) $uid)) {
    json_error('FORBIDDEN', 'World builder access required', 403);
}

$search = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
$category = isset($_GET['category']) ? trim((string) $_GET['category']) : '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = max(1, min(100, (int) ($_GET['limit'] ?? 12)));

// Marketplace envía page y limit; el panel WB suele hacer GET sin esos parámetros (cache-bust ?v= está bien).
$wantFullCatalog = !isset($_GET['page']) && !isset($_GET['limit'])
    && $search === '' && $category === '';

try {
    if (!nexus_world_builder_catalog_table_exists($pdo)) {
        json_success([
            'catalog' => [],
            'total' => 0,
            'warning' => 'Table nexus_world_builder_catalog missing; run sql/nexus_world_builder_catalog.sql',
        ]);
    }

    if ($wantFullCatalog) {
        $st = $pdo->query(
            "SELECT id, item_code, name, category, rarity, model_url, wb_scale, default_light_json, hologram, sort_order
             FROM nexus_world_builder_catalog
             WHERE is_active = 1
             ORDER BY sort_order ASC, name ASC"
        );
        $raw = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
        $catalog = [];
        foreach ($raw as $r) {
            $catalog[] = nexus_world_builder_catalog_row_to_api($r);
        }
        json_success(['catalog' => $catalog, 'total' => count($catalog)]);
    }

    $result = nexus_world_builder_catalog_fetch_filtered($pdo, $search, $category, $page, $limit);
    json_success([
        'catalog' => $result['rows'],
        'total' => $result['total'],
    ]);
} catch (PDOException $e) {
    error_log('world_builder_catalog: ' . $e->getMessage());
    json_error('DB_ERROR', 'Failed to load builder catalog', 500);
}

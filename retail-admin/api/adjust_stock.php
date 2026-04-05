<?php
/**
 * POST /retail-admin/api/adjust_stock.php
 * Ajuste de stock desde el dashboard admin.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

defined('KND_ROOT') or define('KND_ROOT', dirname(__DIR__, 2));

require_once KND_ROOT . '/includes/env.php';
require_once KND_ROOT . '/includes/session.php';
require_once KND_ROOT . '/includes/config.php';
require_once KND_ROOT . '/includes/auth.php';
require_once KND_ROOT . '/includes/csrf.php';
require_once KND_ROOT . '/includes/json.php';
require_once KND_ROOT . '/retail/auth.php';
require_once KND_ROOT . '/retail/audit.php';
require_once KND_ROOT . '/retail/tools/adjust_stock.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['ok' => false, 'error' => 'POST only']); exit;
}

csrf_guard();
api_require_login();

$pdo = getDBConnection();
if (!$pdo) { http_response_code(500); echo json_encode(['ok' => false, 'error' => 'DB error']); exit; }

$resolved = retail_resolve_business_for_gateway($pdo, current_user_id());
if (!$resolved) { http_response_code(403); echo json_encode(['ok' => false, 'error' => 'No business assigned']); exit; }
if (!retail_is_admin()) { http_response_code(403); echo json_encode(['ok' => false, 'error' => 'Admin required']); exit; }

$productId = (int) ($_POST['product_id'] ?? 0);
$delta     = (int) ($_POST['delta'] ?? 0);
$reason    = trim($_POST['reason'] ?? '');

$result = retail_adjust_stock($pdo, [
    'product_id' => $productId,
    'delta'      => $delta,
    'reason'     => $reason,
]);

if (isset($result['error'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $result['error']]);
} else {
    echo json_encode(['ok' => true, 'data' => $result]);
}

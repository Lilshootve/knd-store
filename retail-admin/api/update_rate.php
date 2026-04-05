<?php
/**
 * POST /retail-admin/api/update_rate.php
 * Actualización de tasa de cambio desde el dashboard admin (AJAX).
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
require_once KND_ROOT . '/retail/tools/update_exchange_rate.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['ok' => false, 'error' => 'POST only']); exit;
}

csrf_guard();
api_require_login();

$pdo = getDBConnection();
if (!$pdo) { http_response_code(500); echo json_encode(['ok' => false, 'error' => 'DB error']); exit; }

$resolved = retail_resolve_business_for_gateway($pdo, current_user_id());
if (!$resolved) { http_response_code(403); echo json_encode(['ok' => false, 'error' => 'No business']); exit; }
if (!retail_is_admin()) { http_response_code(403); echo json_encode(['ok' => false, 'error' => 'Admin required']); exit; }

$payload  = json_decode(file_get_contents('php://input'), true) ?: [];
$currency = strtoupper(trim($payload['currency'] ?? $_POST['currency'] ?? ''));
$rate     = (float) ($payload['rate'] ?? $_POST['rate'] ?? 0);

$result = retail_update_exchange_rate($pdo, ['currency' => $currency, 'rate' => $rate]);

if (isset($result['error'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $result['error']]);
} else {
    echo json_encode(['ok' => true, 'data' => $result]);
}

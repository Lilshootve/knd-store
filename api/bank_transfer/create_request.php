<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once BASE_PATH . '/includes/pricing.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload']);
    exit;
}

$items = $payload['items'] ?? [];
$deliveryType = isset($payload['deliveryType']) ? (string) $payload['deliveryType'] : '';
$customer = isset($payload['customer']) && is_array($payload['customer']) ? $payload['customer'] : [];

if (!is_array($items) || empty($items)) {
    http_response_code(400);
    echo json_encode(['error' => 'No items provided']);
    exit;
}

$itemsDetailed = [];
$subtotal = 0.0;

foreach ($items as $item) {
    $id = isset($item['id']) ? (int) $item['id'] : 0;
    $qty = isset($item['qty']) ? (int) $item['qty'] : 0;
    $variants = isset($item['variants']) && is_array($item['variants']) ? $item['variants'] : null;

    if ($id <= 0 || $qty < 1 || $qty > 10) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid item input']);
        exit;
    }

    $pricingItem = getPricingItemById($id);
    if (!$pricingItem) {
        http_response_code(400);
        echo json_encode(['error' => 'Unknown product id']);
        exit;
    }

    if (!validateItemVariants($pricingItem, $variants)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid variants']);
        exit;
    }

    $unitPrice = (float) $pricingItem['base_price_usd'];
    $lineTotal = $unitPrice * $qty;
    $subtotal += $lineTotal;

    $itemsDetailed[] = [
        'id' => $id,
        'name' => $pricingItem['name'],
        'type' => $pricingItem['type'],
        'qty' => $qty,
        'unit_price' => round($unitPrice, 2),
        'line_total' => round($lineTotal, 2),
        'variants' => $variants,
    ];
}

$shipping = 0.0;
$total = round($subtotal + $shipping, 2);

$orderId = 'KND-' . date('Y') . '-' . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

$record = [
    'order_id' => $orderId,
    'created_at' => date('c'),
    'items' => $itemsDetailed,
    'totals' => [
        'subtotal' => round($subtotal, 2),
        'shipping' => round($shipping, 2),
        'total' => $total,
        'currency' => 'USD',
    ],
    'deliveryType' => $deliveryType,
    'customer' => [
        'name' => $customer['name'] ?? '',
        'whatsapp' => $customer['whatsapp'] ?? '',
        'notes' => $customer['notes'] ?? '',
    ],
    'payment_method' => 'Bank Transfer',
];

require_once BASE_PATH . '/includes/storage.php';
ensure_storage_ready();

$ok = append_json_record(storage_path('bank_transfer_requests.json'), $record);
if (!$ok) {
    http_response_code(500);
    echo json_encode(['error' => 'Storage write error']);
    exit;
}

echo json_encode([
    'ok' => true,
    'order_id' => $orderId,
]);

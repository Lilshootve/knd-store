<?php
require_once __DIR__ . '/../../includes/pricing.php';
require_once __DIR__ . '/../../includes/paypal_config.php';
require_once __DIR__ . '/../../includes/storage.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

ensure_storage_ready();

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload']);
    exit;
}

$items = $payload['items'] ?? [];
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
        'name' => $pricingItem['name'],
        'unit_amount' => [
            'currency_code' => 'USD',
            'value' => number_format($unitPrice, 2, '.', ''),
        ],
        'quantity' => (string) $qty,
    ];
}

$shipping = 0.0;
$total = $subtotal + $shipping;

storage_log('paypal_create_order: attempt', ['total' => $total, 'item_count' => count($itemsDetailed)]);

$accessToken = getPayPalAccessToken();
if (!$accessToken) {
    storage_log('paypal_create_order: auth_failed');
    http_response_code(500);
    echo json_encode(['error' => 'PayPal auth failed']);
    exit;
}

$orderBody = [
    'intent' => 'CAPTURE',
    'purchase_units' => [
        [
            'amount' => [
                'currency_code' => 'USD',
                'value' => number_format($total, 2, '.', ''),
                'breakdown' => [
                    'item_total' => [
                        'currency_code' => 'USD',
                        'value' => number_format($subtotal, 2, '.', ''),
                    ],
                    'shipping' => [
                        'currency_code' => 'USD',
                        'value' => number_format($shipping, 2, '.', ''),
                    ],
                ],
            ],
            'items' => $itemsDetailed,
        ],
    ],
];

$response = paypalApiRequest('POST', '/v2/checkout/orders', $accessToken, $orderBody);

if ($response['status'] < 200 || $response['status'] >= 300) {
    storage_log('paypal_create_order: api_error', ['http_status' => $response['status']]);
    http_response_code(500);
    echo json_encode(['error' => 'PayPal order creation failed']);
    exit;
}

$paypalOrderId = $response['body']['id'] ?? null;
if (!$paypalOrderId) {
    storage_log('paypal_create_order: missing_order_id', ['http_status' => $response['status']]);
    http_response_code(500);
    echo json_encode(['error' => 'Missing PayPal order id']);
    exit;
}

storage_log('paypal_create_order: success', ['paypal_order_id' => $paypalOrderId, 'total' => $total]);

echo json_encode(['id' => $paypalOrderId]);

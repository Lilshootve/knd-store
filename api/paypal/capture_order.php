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

$orderID = isset($payload['orderID']) ? trim((string) $payload['orderID']) : '';
$items = $payload['items'] ?? [];
$deliveryType = isset($payload['deliveryType']) ? (string) $payload['deliveryType'] : '';
$customer = isset($payload['customer']) && is_array($payload['customer']) ? $payload['customer'] : [];

if (!$orderID || !is_array($items) || empty($items)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing order data']);
    exit;
}

storage_log('paypal_capture: attempt', ['paypal_order_id' => $orderID, 'item_count' => count($items)]);

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
$total = $subtotal + $shipping;

$ordersFile = storage_path('orders.json');

$existing = read_json_array($ordersFile);
foreach ($existing as $rec) {
    if (!empty($rec['paypal_order_id']) && $rec['paypal_order_id'] === $orderID) {
        storage_log('paypal_capture: duplicate', ['paypal_order_id' => $orderID]);
        http_response_code(409);
        echo json_encode(['error' => 'Order already captured']);
        exit;
    }
}

$accessToken = getPayPalAccessToken();
if (!$accessToken) {
    storage_log('paypal_capture: auth_failed', ['paypal_order_id' => $orderID]);
    http_response_code(500);
    echo json_encode(['error' => 'PayPal auth failed']);
    exit;
}

$captureResponse = paypalApiRequest('POST', '/v2/checkout/orders/' . rawurlencode($orderID) . '/capture', $accessToken, null);
if ($captureResponse['status'] < 200 || $captureResponse['status'] >= 300) {
    storage_log('paypal_capture: api_error', ['paypal_order_id' => $orderID, 'http_status' => $captureResponse['status']]);
    http_response_code(500);
    echo json_encode(['error' => 'PayPal capture failed']);
    exit;
}

$body = $captureResponse['body'] ?? [];
$ppStatus = $body['status'] ?? '';
$capture = $body['purchase_units'][0]['payments']['captures'][0] ?? [];
$captureStatus = $capture['status'] ?? '';
$amountValue = isset($capture['amount']['value']) ? (float) $capture['amount']['value'] : 0.0;
$currencyCode = $capture['amount']['currency_code'] ?? '';

if ($ppStatus !== 'COMPLETED' && $captureStatus !== 'COMPLETED') {
    storage_log('paypal_capture: not_completed', ['paypal_order_id' => $orderID, 'pp_status' => $ppStatus, 'capture_status' => $captureStatus]);
    http_response_code(400);
    echo json_encode(['error' => 'Capture not completed']);
    exit;
}

if (round($amountValue, 2) !== round($total, 2) || $currencyCode !== 'USD') {
    storage_log('paypal_capture: amount_mismatch', [
        'paypal_order_id' => $orderID,
        'expected' => round($total, 2),
        'got' => $amountValue,
        'currency' => $currencyCode,
    ]);
    http_response_code(400);
    echo json_encode(['error' => 'Amount mismatch']);
    exit;
}

$orderRef = 'KND-' . date('Y') . '-' . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
$customerEmail = trim($customer['email'] ?? '');

$record = [
    'order_ref' => $orderRef,
    'paypal_order_id' => $orderID,
    'status' => 'paid',
    'amount_usd' => number_format(round($total, 2), 2, '.', ''),
    'payer_email' => $body['payer']['email_address'] ?? '',
    'items' => $itemsDetailed,
    'totals' => [
        'subtotal' => round($subtotal, 2),
        'shipping' => round($shipping, 2),
        'total' => round($total, 2),
        'currency' => 'USD',
    ],
    'deliveryType' => $deliveryType,
    'customer' => [
        'name' => $customer['name'] ?? '',
        'whatsapp' => $customer['whatsapp'] ?? '',
        'email' => $customerEmail ?: null,
        'notes' => $customer['notes'] ?? '',
    ],
    'created_at' => date('c'),
    'source' => 'paypal',
];

$persisted = append_json_record($ordersFile, $record);

storage_log('paypal_capture: persist_result', [
    'paypal_order_id' => $orderID,
    'order_ref' => $orderRef,
    'persisted' => $persisted,
    'file' => $ordersFile,
    'file_exists' => file_exists($ordersFile),
    'file_size' => file_exists($ordersFile) ? filesize($ordersFile) : 0,
]);

require_once __DIR__ . '/../../includes/mailer.php';

$trackUrl = 'https://kndstore.com/track-order.php?id=' . urlencode($orderRef);

$itemsList = '';
foreach ($itemsDetailed as $it) {
    $itemsList .= '  - ' . ($it['name'] ?? 'Item') . ' x' . ($it['qty'] ?? 1) . ' — $' . number_format($it['line_total'] ?? 0, 2) . "\n";
}
$emailBody  = "Order Confirmation - KND Store\n";
$emailBody .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
$emailBody .= "Order ID: " . $orderRef . "\n";
$emailBody .= "Total: $" . number_format($total, 2) . " USD\n";
$emailBody .= "Delivery: " . ($deliveryType ?: 'Digital / remote') . "\n\n";
$emailBody .= "Items:\n" . $itemsList . "\n";
$emailBody .= "Track your order:\n" . $trackUrl . "\n\n";
$emailBody .= "Next steps: We will contact you via WhatsApp or email for delivery details.\n\n";
$emailBody .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
$emailBody .= "Support: support@kndstore.com | WhatsApp +58 414-159-2319\n";
$emailBody .= "KND Store — kndstore.com";

if ($customerEmail && filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
    knd_mail($customerEmail, 'Order ' . $orderRef . ' confirmed - KND Store', $emailBody);
}

$opsSecretsFile = __DIR__ . '/../../config/ops_secrets.local.php';
if (file_exists($opsSecretsFile)) {
    $opsSecrets = include $opsSecretsFile;
    $opsEmail = is_array($opsSecrets) ? trim($opsSecrets['ops_email'] ?? '') : '';
    if ($opsEmail && filter_var($opsEmail, FILTER_VALIDATE_EMAIL)) {
        $opsBody = "[PayPal] New order\n\n" . $emailBody . "\n\nCustomer: " . ($customer['name'] ?? '-') . "\nWhatsApp: " . ($customer['whatsapp'] ?? '-');
        knd_mail($opsEmail, 'KND Order ' . $orderRef . ' - PayPal', $opsBody);
    }
}

$response = [
    'ok' => true,
    'order_ref' => $orderRef,
    'redirect' => '/thank-you.php?ref=' . urlencode($orderRef),
];
if (!$persisted) {
    $response['warn'] = 'persist_failed';
    storage_log('paypal_capture: WARNING persist_failed but returning success to client', ['order_ref' => $orderRef]);
}

echo json_encode($response);

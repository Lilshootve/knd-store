<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/_guard.php';
admin_require_login();
admin_require_perm('system.create_test_order');
require_once BASE_PATH . '/includes/storage.php';

header('Content-Type: text/html; charset=utf-8');
ensure_storage_ready();

$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderRef = 'KND-' . date('Y') . '-' . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

    $record = [
        'order_ref' => $orderRef,
        'paypal_order_id' => 'TEST-' . strtoupper(bin2hex(random_bytes(6))),
        'status' => 'paid',
        'amount_usd' => '15.00',
        'payer_email' => 'test@example.com',
        'items' => [
            [
                'id' => 0,
                'name' => 'Test Product A',
                'type' => 'digital',
                'qty' => 1,
                'unit_price' => 9.00,
                'line_total' => 9.00,
                'variants' => null,
            ],
            [
                'id' => 0,
                'name' => 'Test Product B',
                'type' => 'digital',
                'qty' => 1,
                'unit_price' => 6.00,
                'line_total' => 6.00,
                'variants' => null,
            ],
        ],
        'totals' => [
            'subtotal' => 15.00,
            'shipping' => 0.00,
            'total' => 15.00,
            'currency' => 'USD',
        ],
        'deliveryType' => 'Digital / remote',
        'customer' => [
            'name' => 'Test Customer',
            'whatsapp' => '+10000000000',
            'email' => 'test@example.com',
            'notes' => 'Synthetic test order — safe to delete.',
        ],
        'created_at' => date('c'),
        'source' => 'test',
    ];

    $ok = append_json_record(storage_path('orders.json'), $record);
    storage_log('test_order: created', [
        'order_ref' => $orderRef,
        'persisted' => $ok,
        'file' => storage_path('orders.json'),
    ]);

    $result = ['ok' => $ok, 'order_ref' => $orderRef];
}
?>
<!DOCTYPE html>
<html>
<head><title>Test Order — KND Admin</title>
<style>
    body { font-family: 'Courier New', monospace; background: #0a0a0a; color: #e0e0e0; padding: 2rem; }
    h1 { color: #00d4ff; margin-bottom: 1.5rem; }
    .card { background: #111; border: 1px solid #222; padding: 1.5rem; border-radius: 6px; max-width: 480px; }
    .ok { color: #4ade80; }
    .fail { color: #f87171; }
    a { color: #00d4ff; }
    button { background: linear-gradient(135deg, rgba(0,212,255,.15), rgba(255,0,255,.08)); border: 1px solid rgba(0,212,255,.35); color: #fff; padding: .7rem 1.8rem; border-radius: 8px; cursor: pointer; font-size: 1rem; font-family: inherit; }
    button:hover { border-color: rgba(0,212,255,.6); }
    .ref { font-size: 1.3rem; font-weight: bold; color: #fff; letter-spacing: .05em; }
    .links { margin-top: 1rem; display: flex; flex-direction: column; gap: .5rem; }
</style>
</head>
<body>
<h1>Create Test Order</h1>
<p><a href="/admin/orders.php?tab=test">&larr; Admin Panel (Test tab)</a> &nbsp;|&nbsp; <a href="/admin/debug_storage.php">Debug Storage</a></p>

<div class="card">
<?php if ($result): ?>
    <?php if ($result['ok']): ?>
        <p class="ok">Order persisted successfully.</p>
        <p class="ref"><?php echo htmlspecialchars($result['order_ref']); ?></p>
        <div class="links">
            <a href="/track-order.php?id=<?php echo urlencode($result['order_ref']); ?>">View on Track Order page &rarr;</a>
            <a href="/admin/orders.php?tab=test">View in Admin Panel (Test tab) &rarr;</a>
        </div>
    <?php else: ?>
        <p class="fail">Persist failed. Check <code>storage/logs/payments.log</code> and <code>admin/debug_storage.php</code>.</p>
    <?php endif; ?>
    <hr style="border-color:#222; margin:1.5rem 0;">
<?php endif; ?>

    <p>Inserts a synthetic order into <code>orders.json</code> with <code>source=test</code>. No PayPal call is made.</p>
    <form method="post">
        <button type="submit">Create Test Order</button>
    </form>
</div>
</body>
</html>

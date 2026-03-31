<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/storage.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/footer.php';

ensure_storage_ready();

$searchId = isset($_GET['id']) ? trim((string) $_GET['id']) : '';
$order = null;
$source = '';

if ($searchId !== '') {
    $files = [
        'paypal' => storage_path('orders.json'),
        'bank'   => storage_path('bank_transfer_requests.json'),
        'other'  => storage_path('other_payment_requests.json'),
    ];
    foreach ($files as $src => $path) {
        $data = read_json_array($path);
        if (empty($data)) continue;

        if ($src === 'paypal') {
            foreach ($data as $r) {
                $matchRef = isset($r['order_ref']) && strcasecmp($r['order_ref'], $searchId) === 0;
                $matchPP  = isset($r['paypal_order_id']) && strcasecmp($r['paypal_order_id'], $searchId) === 0;
                if ($matchRef || $matchPP) {
                    $order = $r;
                    $source = $src;
                    break 2;
                }
            }
        } else {
            $idKey = 'order_id';
            foreach ($data as $r) {
                if (isset($r[$idKey]) && strcasecmp($r[$idKey], $searchId) === 0) {
                    $order = $r;
                    $source = $src;
                    break 2;
                }
            }
        }
    }
}

$paymentLabels = ['paypal' => 'PayPal', 'bank' => 'Bank Transfer (ACH / Wire)', 'other' => 'WhatsApp (Other)'];
$statusMessages = [
    'pending'            => 'Awaiting confirmation',
    'awaiting_transfer'  => 'Awaiting payment transfer',
    'processing'         => 'Order is being processed',
    'paid'               => 'Payment confirmed. Processing.',
    'completed'          => 'Completed',
    'delivered'          => 'Completed',
    'cancelled'          => 'Cancelled',
    'failed'             => 'Payment failed',
];

$statusClassMap = [
    'pending' => 'status-pending',
    'awaiting_transfer' => 'status-awaiting_transfer',
    'processing' => 'status-processing',
    'paid' => 'status-paid',
    'completed' => 'status-completed',
    'delivered' => 'status-delivered',
    'cancelled' => 'status-cancelled',
    'failed' => 'status-failed',
];

$statusLabelMap = [
    'pending' => 'Pending',
    'awaiting_transfer' => 'Awaiting Transfer',
    'processing' => 'Processing',
    'paid' => 'Paid',
    'completed' => 'Completed',
    'delivered' => 'Delivered',
    'cancelled' => 'Cancelled',
    'failed' => 'Failed',
];

echo generateHeader(t('track.meta.title'), t('track.meta.description'));
?>
<style>
.status-badge { padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; letter-spacing: .4px; display: inline-block; text-transform: capitalize; }
.status-pending { background: #2a2f36; color: #9aa4af; }
.status-awaiting_transfer { background: rgba(243,156,18,.15); color: #f39c12; }
.status-paid { background: rgba(46,204,113,.15); color: #2ecc71; }
.status-completed { background: rgba(39,174,96,.18); color: #27ae60; }
.status-delivered { background: rgba(39,174,96,.22); color: #27ae60; }
.status-processing { background: rgba(52,152,219,.15); color: #3498db; }
.status-cancelled { background: rgba(231,76,60,.18); color: #e74c3c; }
.status-failed { background: rgba(192,57,43,.20); color: #c0392b; }
.status-unknown { background: #1f242b; color: #7f8c8d; }
</style>
<div id="particles-bg"></div>
<?php echo generateNavigation(); ?>

<section class="order-section py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-5">
                <div class="card order-details-card checkout-lux">
                    <div class="card-body order-details-body">
                        <h2 class="mb-4 checkout-info-title"><?php echo t('track.title'); ?></h2>

                        <form method="get" class="mb-4">
                            <label class="order-field-label" for="track-id"><?php echo t('track.label'); ?></label>
                            <div class="d-flex gap-2">
                                <input type="text" name="id" id="track-id" class="order-input flex-grow-1" placeholder="KND-2026-XXXX" value="<?php echo htmlspecialchars($searchId); ?>" required>
                                <button type="submit" class="order-btn-primary" style="width:auto;padding:0.7rem 1.5rem;"><?php echo t('track.search_btn'); ?></button>
                            </div>
                        </form>

                        <?php if ($searchId !== '' && $order): ?>
                        <?php
                            $oid = ($source === 'paypal') ? ($order['order_ref'] ?? '') : ($order['order_id'] ?? '');
                            $date = isset($order['created_at']) ? date('M j, Y — H:i', strtotime($order['created_at'])) : '-';
                            $total = $order['totals']['total'] ?? 0;
                            $currency = $order['totals']['currency'] ?? 'USD';
                            $items = $order['items'] ?? [];
                            $status = $order['status'] ?? ($source === 'paypal' ? 'paid' : 'pending');
                            $statusMsg = $statusMessages[$status] ?? ucfirst($status);
                            $paymentLabel = $paymentLabels[$source] ?? ucfirst($source);
                        ?>
                        <div class="checkout-info-box mb-3">
                            <div class="order-total-row mb-2">
                                <span class="order-total-muted"><?php echo t('track.order_id'); ?></span>
                                <span class="fw-bold"><?php echo htmlspecialchars($oid); ?></span>
                            </div>
                            <div class="order-total-row mb-2">
                                <span class="order-total-muted"><?php echo t('track.date'); ?></span>
                                <span><?php echo htmlspecialchars($date); ?></span>
                            </div>
                            <div class="order-total-row mb-2">
                                <span class="order-total-muted"><?php echo t('track.payment'); ?></span>
                                <span><?php echo htmlspecialchars($paymentLabel); ?></span>
                            </div>
                            <div class="order-total-row mb-2">
                                <span class="order-total-muted"><?php echo t('track.total'); ?></span>
                                <span class="order-total-amount">$<?php echo number_format($total, 2); ?> <?php echo htmlspecialchars($currency); ?></span>
                            </div>
                        </div>

                        <div class="checkout-info-box mb-3">
                            <div class="checkout-info-title mb-2"><?php echo t('track.items'); ?></div>
                            <?php foreach ($items as $it): ?>
                            <div class="d-flex justify-content-between mb-1">
                                <span><?php echo htmlspecialchars($it['name'] ?? 'Item'); ?> <small class="text-muted">x<?php echo (int)($it['qty'] ?? 1); ?></small></span>
                                <span>$<?php echo number_format($it['line_total'] ?? 0, 2); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="checkout-info-box">
                            <div class="checkout-info-title mb-1"><?php echo t('track.status'); ?></div>
                            <span class="status-badge <?php echo $statusClassMap[$status] ?? 'status-unknown'; ?>"><?php echo htmlspecialchars($statusLabelMap[$status] ?? ucfirst($status)); ?></span>
                            <p class="mb-0 mt-2 checkout-info-hint"><?php echo htmlspecialchars($statusMsg); ?></p>
                        </div>

                        <?php elseif ($searchId !== ''): ?>
                        <div class="checkout-info-box text-center">
                            <p class="mb-0"><?php echo t('track.not_found'); ?></p>
                        </div>
                        <?php endif; ?>

                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script src="/assets/js/navigation-extend.js"></script>
<?php echo generateFooter(); echo generateScripts(); ?>

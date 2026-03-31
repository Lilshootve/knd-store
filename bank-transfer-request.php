<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/footer.php';

$orderId = isset($_GET['order']) ? trim((string) $_GET['order']) : '';

$order = null;
if (preg_match('/^KND-\d{4}-\d{4}$/', $orderId)) {
    $storageDir = __DIR__ . '/storage';
    $requestsFile = $storageDir . '/bank_transfer_requests.json';
    if (file_exists($requestsFile)) {
        $data = json_decode(file_get_contents($requestsFile), true);
        if (is_array($data)) {
            foreach ($data as $r) {
                if (isset($r['order_id']) && $r['order_id'] === $orderId) {
                    $order = $r;
                    break;
                }
            }
        }
    }
}

$title = $order ? t('bank_request.title') : 'Order Not Found';
echo generateHeader($title, $order ? 'Request bank details for your order' : '');
?>

<div id="particles-bg"></div>
<?php echo generateNavigation(); ?>

<section class="order-section py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-5">
                <div class="card order-details-card checkout-lux">
                    <div class="card-body order-details-body">
                        <?php if ($order): ?>
                        <h2 class="mb-4 checkout-info-title"><?php echo t('bank_request.title'); ?></h2>

                        <div class="order-section-block mb-4">
                            <div class="order-total-row mb-2">
                                <span class="order-total-muted"><?php echo t('bank_request.order_id'); ?></span>
                                <span class="fw-bold"><?php echo htmlspecialchars($order['order_id']); ?></span>
                            </div>
                            <div class="order-total-row mb-2">
                                <span class="order-total-muted"><?php echo t('bank_request.amount_due'); ?></span>
                                <span class="order-total-amount">$<?php echo number_format($order['totals']['total'] ?? 0, 2); ?> USD</span>
                            </div>
                        </div>

                        <a href="#" id="bank-request-whatsapp" class="order-btn-whatsapp d-block text-center text-decoration-none">
                            <?php echo t('bank_request.whatsapp_btn'); ?>
                        </a>

                        <input type="hidden" id="bt-order-id" value="<?php echo htmlspecialchars($order['order_id']); ?>">
                        <input type="hidden" id="bt-amount" value="<?php echo htmlspecialchars(number_format($order['totals']['total'] ?? 0, 2)); ?>">
                        <input type="hidden" id="bt-name" value="<?php echo htmlspecialchars($order['customer']['name'] ?? ''); ?>">
                        <?php else: ?>
                        <div class="text-center py-4">
                            <p class="text-muted"><?php echo t('bank_request.not_found'); ?></p>
                            <a href="/order.php" class="btn btn-outline-neon"><?php echo t('nav.orders'); ?></a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if ($order): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('bank-request-whatsapp');
    const orderId = document.getElementById('bt-order-id')?.value || '';
    const amount = document.getElementById('bt-amount')?.value || '0.00';
    const name = document.getElementById('bt-name')?.value || '';

    let msg = '🔐 *Bank Transfer Request*%0A%0A';
    msg += '*Order ID:* ' + orderId + '%0A';
    msg += '*Amount:* $' + amount + ' USD%0A';
    if (name) msg += '*Customer Name:* ' + name + '%0A';
    msg += '*Payment Method:* Bank Transfer%0A%0A';
    msg += 'Please share the banking details to complete this transfer.';

    const phone = '584141592319';
    const url = 'https://wa.me/' + phone + '?text=' + msg;

    btn.href = url;
    btn.target = '_blank';
});
</script>
<?php endif; ?>

<?php echo generateFooter(); echo generateScripts(); ?>

<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/footer.php';

$ref = isset($_GET['ref']) ? trim((string) $_GET['ref']) : '';
$safeRef = htmlspecialchars($ref);
?>
<?php echo generateHeader('Thank you - KND Store', 'Your order has been received.'); ?>

<div id="particles-bg"></div>
<?php echo generateNavigation(); ?>

<section class="order-section py-5">
    <div class="container text-center py-5">
        <div class="card knd-card mx-auto" style="max-width: 520px;">
            <div class="card-body py-5 px-4">
                <i class="fas fa-check-circle text-success mb-3" style="font-size: 4rem;"></i>
                <h2 class="mb-3">Payment approved</h2>
                <p class="text-muted mb-4">Your order has been received and payment was successful.</p>

                <?php if ($ref): ?>
                <div class="checkout-info-box mb-4 p-3 text-start" style="background: rgba(0,212,255,.06); border: 1px solid rgba(0,212,255,.15); border-radius: 10px;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted d-block mb-1">Order ID</small>
                            <span class="fw-bold fs-5" id="ty-order-ref"><?php echo $safeRef; ?></span>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-neon" id="ty-copy-btn" title="Copy Order ID" style="min-width:38px;">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>

                <a href="/track-order.php?id=<?php echo urlencode($ref); ?>" class="btn btn-neon-primary w-100 mb-3" style="padding: .85rem 1.5rem; font-weight: 600; font-size: 1rem;">
                    <i class="fas fa-magnifying-glass me-2"></i>Track Order Status
                </a>
                <p class="small text-muted mb-0">You can track your order anytime at <a href="/track-order.php" style="color: var(--knd-accent-cyan, #00d4ff);">/track-order</a></p>
                <?php else: ?>
                <div class="checkout-info-box mb-4 p-3" style="background: rgba(243,156,18,.08); border: 1px solid rgba(243,156,18,.2); border-radius: 10px;">
                    <p class="mb-0"><i class="fas fa-info-circle me-2" style="color: #f39c12;"></i>If you didn't receive an Order ID, please <a href="/contact.php" style="color: var(--knd-accent-cyan, #00d4ff);">contact support</a>.</p>
                </div>
                <?php endif; ?>

                <a href="/" class="btn btn-outline-neon mt-3">Back to Home</a>
            </div>
        </div>
    </div>
</section>

<script>
(function() {
    var btn = document.getElementById('ty-copy-btn');
    if (!btn) return;
    btn.addEventListener('click', function() {
        var ref = document.getElementById('ty-order-ref');
        if (!ref) return;
        var text = ref.textContent.trim();
        navigator.clipboard.writeText(text).then(function() {
            btn.innerHTML = '<i class="fas fa-check"></i>';
            setTimeout(function() { btn.innerHTML = '<i class="fas fa-copy"></i>'; }, 1500);
        }).catch(function() {
            prompt('Copy your Order ID:', text);
        });
    });
})();
</script>

<script src="/assets/js/navigation-extend.js"></script>
<?php echo generateFooter(); echo generateScripts(); ?>

<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
ini_set('display_errors', '0');

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/footer.php';
require_once __DIR__ . '/includes/support_credits.php';

require_login();
require_verified_email();

$csrfToken = csrf_token();
$userId = current_user_id();

$pdo = getDBConnection();
$availablePoints = 0;
$rewards = [];
if ($pdo) {
    release_available_points_if_due($pdo, $userId);
    expire_points_if_due($pdo, $userId);
    $availablePoints = get_available_points($pdo, $userId);

    $stmt = $pdo->query("SELECT * FROM rewards_catalog WHERE is_active = 1 ORDER BY points_cost ASC");
    $rewards = $stmt->fetchAll();
}

$seoTitle = t('rw.page_title', 'Rewards Catalog') . ' | KND Store';
$seoDesc  = t('rw.page_desc', 'Redeem your KND Support Credits for services and rewards.');
echo generateHeader($seoTitle, $seoDesc);
?>

<div id="particles-bg"></div>
<?php echo generateNavigation(); ?>

<section class="hero-section" style="min-height: 100vh; padding-top: 120px; padding-bottom: 60px;">
<div class="container">
    <div class="row mb-4">
        <div class="col">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1 class="glow-text mb-2"><i class="fas fa-gift me-2"></i><?php echo t('rw.title', 'Rewards Catalog'); ?></h1>
                    <p class="text-white-50 mb-0"><?php echo t('rw.subtitle', 'Redeem your available credits for KND services and more.'); ?></p>
                </div>
                <div class="glass-card-neon p-3 text-center" style="min-width: 160px;">
                    <div class="text-white-50 small"><?php echo t('sc.available', 'Available'); ?></div>
                    <div class="fs-3 fw-bold" style="color: var(--knd-neon-blue);" id="rw-available"><?php echo number_format($availablePoints); ?></div>
                    <a href="/support-credits.php" class="btn btn-outline-light btn-sm mt-2"><?php echo t('rw.get_more', 'Get More Credits'); ?></a>
                </div>
            </div>
        </div>
    </div>

    <div class="alert" style="background: rgba(37,156,174,0.08); border: 1px solid rgba(37,156,174,0.2); color: rgba(255,255,255,0.7);">
        <i class="fas fa-info-circle me-2" style="color: var(--knd-neon-blue);"></i>
        <?php echo t('rw.hold_notice', 'Only available credits (after hold period) can be redeemed. Pending credits cannot be used.'); ?>
    </div>

    <?php if (empty($rewards)): ?>
        <div class="text-center py-5">
            <i class="fas fa-box-open fa-3x text-white-50 mb-3"></i>
            <p class="text-white-50"><?php echo t('rw.empty', 'No rewards available at this time. Check back soon!'); ?></p>
        </div>
    <?php else: ?>
    <div class="row g-3">
        <?php foreach ($rewards as $r): ?>
        <div class="col-md-6 col-lg-4">
            <div class="glass-card-neon p-4 h-100 d-flex flex-column sc-reward-card">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="badge" style="background: rgba(37,156,174,0.15); color: var(--knd-neon-blue);"><?php echo htmlspecialchars($r['category']); ?></span>
                    <?php if ($r['stock'] !== null): ?>
                        <span class="badge bg-secondary"><?php echo (int) $r['stock']; ?> <?php echo t('rw.left', 'left'); ?></span>
                    <?php endif; ?>
                </div>
                <h5 class="mb-2"><?php echo htmlspecialchars($r['title']); ?></h5>
                <?php if ($r['description']): ?>
                    <p class="text-white-50 small flex-grow-1"><?php echo htmlspecialchars($r['description']); ?></p>
                <?php else: ?>
                    <div class="flex-grow-1"></div>
                <?php endif; ?>
                <div class="d-flex justify-content-between align-items-center mt-2">
                    <span class="fw-bold" style="color: var(--knd-neon-blue);">
                        <i class="fas fa-coins me-1"></i><?php echo number_format($r['points_cost']); ?> <?php echo t('sc.credits_label', 'credits'); ?>
                    </span>
                    <?php
                    $canAfford = $availablePoints >= (int) $r['points_cost'];
                    $inStock = $r['stock'] === null || (int) $r['stock'] > 0;
                    ?>
                    <button class="btn btn-sm <?php echo ($canAfford && $inStock) ? 'btn-neon-primary' : 'btn-outline-secondary'; ?> sc-redeem-btn"
                        data-reward-id="<?php echo $r['id']; ?>"
                        data-title="<?php echo htmlspecialchars($r['title']); ?>"
                        data-cost="<?php echo $r['points_cost']; ?>"
                        <?php echo (!$canAfford || !$inStock) ? 'disabled' : ''; ?>>
                        <?php if (!$inStock): ?>
                            <?php echo t('rw.out_of_stock', 'Out of Stock'); ?>
                        <?php elseif (!$canAfford): ?>
                            <?php echo t('rw.not_enough', 'Not Enough'); ?>
                        <?php else: ?>
                            <i class="fas fa-check me-1"></i><?php echo t('rw.redeem', 'Redeem'); ?>
                        <?php endif; ?>
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- My Redemptions -->
    <div class="mt-5">
        <div class="glass-card-neon p-4">
            <h5 class="mb-3"><i class="fas fa-receipt me-2"></i><?php echo t('rw.my_redemptions', 'My Redemptions'); ?></h5>
            <div class="table-responsive">
                <table class="table table-sm" style="color: #ccc;">
                    <thead><tr><th>#</th><th><?php echo t('rw.reward', 'Reward'); ?></th><th><?php echo t('sc.credits_label', 'Credits'); ?></th><th><?php echo t('sc.status', 'Status'); ?></th><th><?php echo t('sc.date', 'Date'); ?></th></tr></thead>
                    <tbody>
                    <?php
                    if ($pdo) {
                        $stmt = $pdo->prepare(
                            "SELECT rr.*, rc.title AS reward_title FROM reward_redemptions rr
                             JOIN rewards_catalog rc ON rc.id = rr.reward_id
                             WHERE rr.user_id = ? ORDER BY rr.created_at DESC LIMIT 20"
                        );
                        $stmt->execute([$userId]);
                        $redemptions = $stmt->fetchAll();
                        foreach ($redemptions as $rd):
                    ?>
                        <tr>
                            <td><?php echo $rd['id']; ?></td>
                            <td><?php echo htmlspecialchars($rd['reward_title']); ?></td>
                            <td><?php echo number_format($rd['points_spent']); ?></td>
                            <td>
                                <?php
                                $stColors = ['requested'=>'info','approved'=>'primary','fulfilled'=>'success','rejected'=>'danger','cancelled'=>'secondary'];
                                $stc = $stColors[$rd['status']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?php echo $stc; ?>"><?php echo $rd['status']; ?></span>
                            </td>
                            <td class="small"><?php echo date('M d, Y', strtotime($rd['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</section>

<?php echo generateFooter(); ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function() {
    const CSRF = '<?php echo $csrfToken; ?>';

    document.querySelectorAll('.sc-redeem-btn:not([disabled])').forEach(btn => {
        btn.addEventListener('click', function() {
            const rewardId = this.dataset.rewardId;
            const title = this.dataset.title;
            const cost = this.dataset.cost;

            Swal.fire({
                title: 'Redeem "' + title + '"?',
                html: 'This will spend <strong>' + parseInt(cost).toLocaleString() + ' KP</strong>.<br>This action cannot be undone.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Redeem',
                cancelButtonText: 'Cancel',
                background: '#0a0f1e',
                color: '#e0e0e0',
                confirmButtonColor: '#259cae',
            }).then(result => {
                if (!result.isConfirmed) return;

                const fd = new FormData();
                fd.append('reward_id', rewardId);
                fd.append('csrf_token', CSRF);

                fetch('/api/support-credits/redeem.php', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(data => {
                        if (data.ok) {
                            Swal.fire({
                                title: 'Redeemed!',
                                html: 'You spent <strong>' + data.data.points_spent + ' KP</strong> on "' + data.data.reward_title + '".'
                                    + '<br>Available KP: <strong>' + data.data.available_after + '</strong>',
                                icon: 'success',
                                background: '#0a0f1e',
                                color: '#e0e0e0',
                                confirmButtonColor: '#259cae',
                            }).then(() => location.reload());
                        } else {
                            Swal.fire({
                                title: 'Error',
                                text: data.error?.message || 'Something went wrong.',
                                icon: 'error',
                                background: '#0a0f1e',
                                color: '#e0e0e0',
                            });
                        }
                    })
                    .catch(() => {
                        Swal.fire({
                            title: 'Network Error',
                            text: 'Please try again.',
                            icon: 'error',
                            background: '#0a0f1e',
                            color: '#e0e0e0',
                        });
                    });
            });
        });
    });
})();
</script>

<script src="/assets/js/navigation-extend.js"></script>
<?php echo generateScripts(); ?>

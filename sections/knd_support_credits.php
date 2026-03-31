<?php
/**
 * Support credits / KND Points — aligned with support-credits/knd-points-v2.html
 *
 * Expects in scope: $balance, $availableNet, $csrfToken, $ptsRate, $payments (array)
 */
$packAmounts = [5, 10, 25, 50, 100];
$defaultUsd = 25;

$methodDefs = [
    ['id' => 'paypal', 'icon' => '🅿️', 'label_key' => 'sc.method_label_paypal'],
    ['id' => 'binance_pay', 'icon' => '🪙', 'label_key' => 'sc.method_label_binance'],
    ['id' => 'zinli', 'icon' => '💳', 'label_key' => 'sc.method_label_zinli'],
    ['id' => 'pago_movil', 'icon' => '📱', 'label_key' => 'sc.method_label_pago_movil'],
    ['id' => 'ach', 'icon' => '🏦', 'label_key' => 'sc.method_label_ach'],
];

$kscHistStatusClass = [
    'pending' => 'ksc-hist-status--pending',
    'confirmed' => 'ksc-hist-status--confirmed',
    'rejected' => 'ksc-hist-status--rejected',
    'disputed' => 'ksc-hist-status--disputed',
    'refunded' => 'ksc-hist-status--refunded',
];

$kscCfg = [
    'csrf' => $csrfToken,
    'ptsRate' => (int) $ptsRate,
    'apiUrl' => '/api/support-credits/create_payment.php',
    'i18n' => [
        'processing' => t('sc.processing'),
        'networkError' => t('sc.network_error'),
        'submitDefault' => t('sc.submit_cta'),
        'resultSuccess' => t('sc.result_success_template'),
    ],
];
?>
<script type="application/json" id="ksc-cfg"><?php echo json_encode($kscCfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?></script>

<main class="knd-support-credits-page" id="knd-support-credits">
    <div id="ksc-bg" aria-hidden="true"><canvas id="ksc-bg-canvas"></canvas></div>

    <div class="ksc-page-inner">
        <section class="ksc-hero">
            <div class="ksc-hero-badge">⬡ <?php echo htmlspecialchars(t('sc.hero_badge')); ?></div>
            <h1 class="ksc-hero-title"><?php echo htmlspecialchars(t('sc.title')); ?></h1>
            <p class="ksc-hero-sub"><?php echo htmlspecialchars(t('sc.hero_sub')); ?></p>
        </section>

        <div class="ksc-balance-strip">
            <div class="ksc-bal-card">
                <div class="ksc-bal-label"><?php echo htmlspecialchars(t('sc.pending')); ?></div>
                <div class="ksc-bal-value" data-target="<?php echo (int) ($balance['pending'] ?? 0); ?>"><?php echo number_format((int) ($balance['pending'] ?? 0)); ?></div>
            </div>
            <div class="ksc-bal-card">
                <div class="ksc-bal-label"><?php echo htmlspecialchars(t('sc.available')); ?></div>
                <div class="ksc-bal-value" data-target="<?php echo (int) $availableNet; ?>"><?php echo number_format((int) $availableNet); ?></div>
            </div>
            <div class="ksc-bal-card">
                <div class="ksc-bal-label"><?php echo htmlspecialchars(t('sc.label_total_spent')); ?></div>
                <div class="ksc-bal-value" data-target="<?php echo (int) ($balance['spent_total'] ?? 0); ?>"><?php echo number_format((int) ($balance['spent_total'] ?? 0)); ?></div>
            </div>
            <div class="ksc-bal-card">
                <div class="ksc-bal-label"><?php echo htmlspecialchars(t('sc.expiring')); ?></div>
                <div class="ksc-bal-value" data-target="<?php echo count($balance['expiring_soon'] ?? []); ?>"><?php echo count($balance['expiring_soon'] ?? []); ?></div>
            </div>
        </div>

        <?php if (!empty($balance['expiring_soon'])) : ?>
            <div class="ksc-expiring-alert" role="status">
                <?php echo htmlspecialchars(t('sc.expiring_warning')); ?>
                <?php foreach ($balance['expiring_soon'] as $exp) : ?>
                    <span class="badge bg-warning text-dark ms-1"><?php echo (int) $exp['points']; ?> KP — <?php echo htmlspecialchars(date('M d, Y', strtotime($exp['expires_at']))); ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="ksc-purchase-card">
            <div class="ksc-pc-title"><?php echo htmlspecialchars(t('sc.obtain_credits')); ?></div>

            <label class="ksc-field-label" for="ksc-amount"><?php echo htmlspecialchars(t('sc.select_amount')); ?></label>
            <div class="ksc-amount-grid" id="ksc-packs">
                <?php foreach ($packAmounts as $amt) :
                    $kp = (int) round($amt * $ptsRate);
                    $active = $amt === $defaultUsd ? ' active' : '';
                    ?>
                    <button type="button" class="ksc-amount-chip<?php echo $active; ?>" data-usd="<?php echo (float) $amt; ?>">
                        $<?php echo (int) $amt; ?><span class="ksc-chip-kp"><?php echo number_format($kp); ?> <?php echo htmlspecialchars(t('sc.credits_label')); ?></span>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="ksc-custom-row">
                <span class="ksc-custom-prefix">$</span>
                <input class="ksc-custom-input" type="number" id="ksc-amount" value="<?php echo (int) $defaultUsd; ?>" min="1" max="500" step="0.01" inputmode="decimal">
                <span class="ksc-custom-eq">= <strong id="ksc-kp-preview"><?php echo number_format((int) round($defaultUsd * $ptsRate)); ?></strong> <?php echo htmlspecialchars(t('sc.credits_label')); ?></span>
            </div>

            <label class="ksc-field-label"><?php echo htmlspecialchars(t('sc.payment_method')); ?></label>
            <div class="ksc-method-grid" id="ksc-methods">
                <?php foreach ($methodDefs as $mi => $md) :
                    $act = $mi === 0 ? ' active' : '';
                    ?>
                    <button type="button" class="ksc-method-card<?php echo $act; ?>" data-method="<?php echo htmlspecialchars($md['id']); ?>">
                        <div class="ksc-mc-icon" aria-hidden="true"><?php echo $md['icon']; ?></div>
                        <div class="ksc-mc-name"><?php echo htmlspecialchars(t($md['label_key'])); ?></div>
                    </button>
                <?php endforeach; ?>
            </div>

            <label class="ksc-field-label" for="ksc-notes"><?php echo htmlspecialchars(t('sc.notes_label')); ?></label>
            <input class="ksc-notes-input" type="text" id="ksc-notes" maxlength="200" placeholder="<?php echo htmlspecialchars(t('sc.notes_placeholder')); ?>" autocomplete="off">

            <button type="button" class="ksc-submit-btn" id="ksc-submit"><?php echo htmlspecialchars(t('sc.submit_cta')); ?></button>

            <div id="ksc-result" class="ksc-result" style="display:none;"></div>
        </div>

        <div class="ksc-info-card">
            <div class="ksc-info-title"><?php echo htmlspecialchars(t('sc.how_it_works')); ?></div>
            <div class="ksc-steps">
                <?php for ($si = 1; $si <= 4; $si++) : ?>
                    <div class="ksc-step">
                        <div class="ksc-step-num"><?php echo $si; ?></div>
                        <div class="ksc-step-text"><?php echo htmlspecialchars(t('sc.step' . $si)); ?></div>
                    </div>
                <?php endfor; ?>
            </div>
            <div class="ksc-legal">
                <div class="ksc-legal-title"><?php echo htmlspecialchars(t('sc.legal_title')); ?></div>
                <ul class="ksc-legal-list">
                    <?php for ($li = 1; $li <= 6; $li++) : ?>
                        <li><?php echo htmlspecialchars(t('sc.legal_' . $li)); ?></li>
                    <?php endfor; ?>
                </ul>
            </div>
        </div>

        <div class="ksc-history-card">
            <div class="ksc-hist-title"><?php echo htmlspecialchars(t('sc.my_payments')); ?></div>
            <div class="ksc-hist-table-wrap">
                <table class="ksc-hist-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th><?php echo htmlspecialchars(t('sc.method')); ?></th>
                            <th><?php echo htmlspecialchars(t('sc.amount')); ?></th>
                            <th><?php echo htmlspecialchars(t('sc.credits_label')); ?></th>
                            <th><?php echo htmlspecialchars(t('sc.status')); ?></th>
                            <th><?php echo htmlspecialchars(t('sc.date')); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $p) :
                            $st = (string) ($p['status'] ?? 'pending');
                            $stLabel = t('sc.status_' . $st, strtoupper($st));
                            ?>
                            <tr>
                                <td><?php echo (int) $p['id']; ?></td>
                                <td><?php echo htmlspecialchars((string) ($p['method'] ?? '')); ?></td>
                                <td>$<?php echo number_format((float) ($p['amount_usd'] ?? 0), 2); ?></td>
                                <td class="ksc-hist-kp"><?php echo number_format((int) ($p['points'] ?? 0)); ?></td>
                                <td><span class="ksc-hist-status <?php echo htmlspecialchars($kscHistStatusClass[$st] ?? 'ksc-hist-status--pending'); ?>"><?php echo htmlspecialchars($stLabel); ?></span></td>
                                <td><?php echo htmlspecialchars(date('M d, Y H:i', strtotime($p['created_at'] ?? 'now'))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

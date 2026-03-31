<?php
/**
 * Credits card (balance + Get credits). Use above params panel in Labs shell.
 * Expects $balance in scope.
 */
$balance = isset($balance) ? (int) $balance : 0;
?>
<div class="ln-credits-card ln-credits-card-above-params">
  <div class="ln-credits-label"><?php echo t('labs.balance', 'Balance'); ?></div>
  <div class="ln-credits-value" id="ln-balance"><?php echo number_format($balance); ?> <span class="ln-kp">KP</span></div>
  <a href="/support-credits.php" class="ln-credits-link"><?php echo t('labs.get_credits', 'Get credits'); ?></a>
</div>

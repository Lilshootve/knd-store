<?php
/**
 * KND Insight — bet entry row.
 * Embed: included inside .arena. Lobby shell: included after .arena (below playfield, above history).
 */
?>
          <div class="entry-bar" id="au-entry-bar" data-csrf="<?php echo htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            <span class="entry-label"><?php echo t('au.entry_label', 'BET'); ?></span>
            <div class="entry-chips" id="au-entry-chips" role="group" aria-label="<?php echo htmlspecialchars(t('au.entry_label', 'Entry')); ?>">
              <?php foreach ($au_entry_options as $opt): ?>
              <button type="button" class="entry-chip<?php echo (int)$opt['v'] === 200 ? ' active' : ''; ?>" data-value="<?php echo (int)$opt['v']; ?>"><?php echo htmlspecialchars($opt['label']); ?></button>
              <?php endforeach; ?>
            </div>
            <span class="entry-payout"><?php echo t('au.win_payout', 'WIN'); ?>: <span id="au-payout-preview">340 KP</span></span>
          </div>

<?php
/**
 * KND Insight — game board (embedded in lobby shell or arena embed).
 * Expects: $balance, $history, $au_entry_options, $embed (bool)
 */
?>
    <div class="insight-knd-arena<?php echo $embed ? ' embed-fill' : ' insight-in-shell'; ?>">
      <div id="insight-arena-bg"><div class="bg-divider"></div></div>

      <div class="streak-banner" id="au-streak-banner" aria-hidden="true"></div>

      <div id="insight-endgame" class="insight-endgame-root" role="dialog" aria-modal="true" aria-hidden="true">
        <div id="insight-endgame-bg"></div>
        <div id="insight-endgame-confetti" aria-hidden="true"></div>
        <div id="insight-endgame-panel">
          <div class="eg-tl" id="insight-egtl"></div>
          <div class="eg-box">
            <div class="eg-res" id="insight-eg-result"></div>
            <div class="eg-sub" id="insight-eg-sub"></div>
            <div class="eg-div" id="insight-egd1"></div>
            <div class="eg-sr" id="insight-eg-stats"></div>
            <div class="eg-div" id="insight-egd2"></div>
            <div class="eg-rw" id="insight-eg-rw"></div>
            <p class="eg-q" id="insight-eg-flavor"></p>
            <div class="eg-btns" id="insight-eg-btns">
              <button type="button" class="eg-btn" id="insight-eg-continue"><?php echo t('au.eg_continue', 'Continue'); ?></button>
              <?php if ($embed): ?>
              <button type="button" class="eg-btn sec" id="insight-eg-arena"><?php echo t('au.eg_return_arena', 'Return to Arena'); ?></button>
              <?php endif; ?>
            </div>
          </div>
          <div class="eg-bl" id="insight-egbl"></div>
        </div>
      </div>

      <div class="insight-arena-app">
        <div class="topbar">
          <div class="tb-brand">
            <span class="eye" aria-hidden="true">👁</span>
            <?php echo t('au.title', 'KND INSIGHT'); ?>
          </div>
          <div class="tb-streak" id="au-tb-streak"><?php echo t('au.streak_label', 'STREAK'); ?>: 0</div>
          <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;justify-content:flex-end;">
            <?php if (!$embed): ?>
            <a href="/knd-arena.php" class="tb-back"><?php echo t('arena.back', 'Arena hub'); ?></a>
            <?php endif; ?>
            <div class="tb-balance" title="<?php echo htmlspecialchars(t('au.your_balance', 'Your KND Points')); ?>">
              💰 <span id="au-balance"><?php echo number_format($balance); ?></span> KP
            </div>
          </div>
        </div>

        <div class="arena" id="au-arena">
          <div class="zone zone-under" id="zone-under" role="button" tabindex="0" aria-label="<?php echo htmlspecialchars(t('au.choice_under', 'Under')); ?>">
            <div class="zone-arrow" aria-hidden="true">⬇</div>
            <div class="zone-label" style="color:var(--c);"><?php echo t('au.under', 'UNDER'); ?></div>
            <div class="zone-range">1 — 5</div>
            <div class="zone-odds"><?php echo t('au.zone_odds_under', '50% · 1.7x'); ?></div>
          </div>

          <div class="nexus">
            <div class="nexus-ring r1"></div>
            <div class="nexus-ring r2"></div>
            <div class="nexus-ring r3"></div>
            <div class="dice-shell" id="dice-shell">
              <div class="dice-face">
                <span class="dice-value" id="dice-value">—</span>
              </div>
            </div>
            <div class="dice-status" id="dice-status"><?php echo t('au.ready', 'READY'); ?></div>
          </div>

          <?php if ($embed) {
              include __DIR__ . '/entry_bar.php';
          } ?>

          <div class="zone zone-above" id="zone-above" role="button" tabindex="0" aria-label="<?php echo htmlspecialchars(t('au.choice_above', 'Above')); ?>">
            <div class="zone-arrow" aria-hidden="true">⬆</div>
            <div class="zone-label" style="color:var(--m);"><?php echo t('au.above', 'ABOVE'); ?></div>
            <div class="zone-range">6 — 10</div>
            <div class="zone-odds"><?php echo t('au.zone_odds_above', '50% · 1.7x'); ?></div>
          </div>
        </div>

        <?php if (!$embed) {
            include __DIR__ . '/entry_bar.php';
        } ?>

        <div class="bottombar">
          <span class="history-label"><?php echo t('au.history', 'HISTORY'); ?></span>
          <div class="history-strip" id="au-history-strip">
            <?php foreach ($history as $h): ?>
            <?php
                $au_delta = (int)$h['is_win'] ? (int)$h['payout_points'] : -(int)$h['entry_points'];
            ?>
            <div class="history-dot <?php echo (int)$h['is_win'] ? 'win' : 'lose'; ?>" data-delta-kp="<?php echo (int)$au_delta; ?>">
              <?php echo (int)$h['rolled_value']; ?><span class="hd-choice"><?php echo strtoupper(substr($h['choice'], 0, 1)); ?></span>
            </div>
            <?php endforeach; ?>
          </div>
          <div class="bb-stats">
            <div class="bb-stat">
              <span class="bb-sv win-rate" id="au-bb-winrate">—</span>
              <span class="bb-sl"><?php echo t('au.win_rate_short', 'WIN RATE'); ?></span>
            </div>
            <div class="bb-stat">
              <span class="bb-sv profit" id="au-bb-profit">—</span>
              <span class="bb-sl"><?php echo t('au.net_kp_short', 'NET KP'); ?></span>
            </div>
          </div>
        </div>
      </div>
    </div>

<?php
/** @var array $L lobby payload from mw_build_lobby_data_payload */
$u = $L['user'] ?? [];
$cur = $L['currencies'] ?? [];
$kp = (int) ($cur['knd_points_available'] ?? 0);
$fr = (int) ($cur['fragments_total'] ?? 0);
$xpPct = (int) ($u['xp_fill_pct'] ?? 0);
$ranking = $L['ranking'] ?? [];
$pos = $ranking['estimated_position'] ?? null;
$rankLabel = $pos !== null ? '#' . (int) $pos : '—';
?>
<header class="topbar">
  <a class="tb-logo" href="/index.php" title="KND Store — Inicio">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
      <polygon points="12,2 22,7 22,17 12,22 2,17 2,7"/>
    </svg>
    KND <span style="font-size:10.35px;letter-spacing:2px;color:var(--t3);font-weight:600">GAMES</span>
  </a>

  <div class="tb-identity">
    <div class="tb-avatar" id="tb-avatar-btn" role="button" tabindex="0" title="Avatars">
      <span id="tb-avatar-thumb" class="tb-avatar-inner"></span>
      <div class="av-rarity-ring" id="tb-avatar-ring" style="display:none"></div>
    </div>
    <div class="tb-info">
      <div class="tb-username" id="tb-username"><?php echo htmlspecialchars((string) ($u['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
      <div class="tb-level" id="tb-level">LVL <?php echo (int) ($u['level'] ?? 1); ?> · <?php echo htmlspecialchars($rankLabel, ENT_QUOTES, 'UTF-8'); ?></div>
      <div class="tb-xpbar">
        <div class="tb-xpfill" id="tb-xpfill" style="width:<?php echo max(0, min(100, $xpPct)); ?>%"></div>
      </div>
    </div>
  </div>

  <div class="tb-currency">
    <div class="currency-chip gold-chip" id="cc-coins-chip" title="KND Points">
      <span class="cc-icon">💰</span>
      <span id="cc-coins"><?php echo number_format($kp); ?></span>
    </div>
    <div class="currency-chip gem-chip" id="cc-gems-chip" title="Fragments">
      <span class="cc-icon">💎</span>
      <span id="cc-gems"><?php echo number_format($fr); ?></span>
    </div>
    <div class="currency-chip energy-chip" id="cc-energy-chip" title="Coming soon" style="display:none">
      <span class="cc-icon">⚡</span>
      <span id="cc-energy">—</span>
    </div>
  </div>

  <div class="tb-controls">
    <div class="tb-icon-btn" id="notif-btn" title="Notifications">🔔
      <div class="notif-badge hidden" id="notif-badge">0</div>
    </div>
    <div class="tb-icon-btn" id="settings-btn" title="Settings">⚙️</div>
    <div class="tb-icon-btn" id="profile-btn" title="Profile">👤</div>
  </div>
</header>

<?php
/** @var array $L lobby payload from mw_build_lobby_data_payload */
/** @var string|null $KND_LOBBY_RETURN_URL optional same-site URL from ?return= */
$u = $L['user'] ?? [];
$kndReturn = isset($KND_LOBBY_RETURN_URL) && is_string($KND_LOBBY_RETURN_URL) && $KND_LOBBY_RETURN_URL !== ''
    ? $KND_LOBBY_RETURN_URL
    : null;
$cur = $L['currencies'] ?? [];
$kp = (int) ($cur['knd_points_available'] ?? 0);
$fr = (int) ($cur['fragments_total'] ?? 0);
$xpPct = (int) ($u['xp_fill_pct'] ?? 0);
$ranking = $L['ranking'] ?? [];
$pos = $ranking['estimated_position'] ?? null;
$rankLabel = $pos !== null ? '#' . (int) $pos : '—';
?>
<header class="topbar" data-no-holo-orb>
  <a class="tb-logo" href="/index.php" title="KND Store — Inicio">
    <div class="logo-hex" aria-hidden="true">
      <svg viewBox="0 0 32 32" fill="none" width="32" height="32">
        <polygon points="16,2 28,9 28,23 16,30 4,23 4,9"
          stroke="url(#mw-lobby-hexg)" stroke-width="1.5" fill="var(--accent-action-subtle)"/>
        <polygon points="16,8 23,12 23,20 16,24 9,20 9,12"
          stroke="url(#mw-lobby-hexg2)" stroke-width="1" fill="var(--accent-action-subtle)" opacity="0.6"/>
        <defs>
          <linearGradient id="mw-lobby-hexg" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="var(--accent-action)"/>
            <stop offset="100%" stop-color="var(--accent-primary)"/>
          </linearGradient>
          <linearGradient id="mw-lobby-hexg2" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="var(--accent-primary)"/>
            <stop offset="100%" stop-color="var(--accent-action)"/>
          </linearGradient>
        </defs>
      </svg>
    </div>
    <span class="tb-brand">KND <em style="font-style:normal;font-weight:400;font-size:9px;letter-spacing:3px;color:var(--t3);margin-left:2px">GAMES</em></span>
  </a>
  <?php if ($kndReturn !== null): ?>
  <a class="tb-back-nexus" href="<?php echo htmlspecialchars($kndReturn, ENT_QUOTES, 'UTF-8'); ?>" title="Volver a la sala">← Sala</a>
  <?php endif; ?>

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
    <a class="currency-chip gold-chip" id="cc-coins-chip" href="/support-credits.php" title="KND Points">
      <span class="cc-icon">💰</span>
      <span id="cc-coins"><?php echo number_format($kp); ?></span>
    </a>
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
    <a class="tb-icon-btn" id="profile-btn" href="/my-profile.php" title="Profile">👤</a>
  </div>
</header>

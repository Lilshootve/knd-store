<?php
/** @var array $L */
$sel = $L['selected_avatar'] ?? null;
$name = $sel ? (string) ($sel['name'] ?? 'Avatar') : '—';
$rarity = $sel ? strtoupper((string) ($sel['rarity'] ?? 'common')) : '—';
$cls = 'FIGHTER';
$stats = $L['equipped_mw_stats'] ?? null;
$skills = $L['equipped_mw_skills'] ?? null;
if ($stats === null && $sel && !empty($sel['mw_avatar_id'])) {
    $cls = 'MIND WARS';
} elseif ($skills && !empty($skills['ability_code'])) {
    $cls = strtoupper((string) $skills['ability_code']);
}
$heroUrl = $L['hero_image_url'] ?? null;
$heroModelUrl = $L['hero_model_url'] ?? null;

$mnd = $stats ? max(0, min(100, (int) ($stats['mind'] ?? 0))) : 0;
$fcs = $stats ? max(0, min(100, (int) ($stats['focus'] ?? 0))) : 0;
$spd = $stats ? max(0, min(100, (int) ($stats['speed'] ?? 0))) : 0;
$lck = $stats ? max(0, min(100, (int) ($stats['luck'] ?? 0))) : 0;
?>
<div class="center-col">
  <div class="hero-stage" id="hero-stage">
    <div class="live-preview" id="live-preview"></div>
    <div class="corner-scan cs-tl" aria-hidden="true"></div>
    <div class="corner-scan cs-tr" aria-hidden="true"></div>
    <div class="corner-scan cs-bl" aria-hidden="true"></div>
    <div class="corner-scan cs-br" aria-hidden="true"></div>

    <div class="hero-stats hs-left" aria-hidden="true">
      <div class="stat-pill">
        <span class="sp-label">MIND</span>
        <div class="sp-bar"><div class="sp-fill" style="width:<?php echo (int) $mnd; ?>%;background:#c040ff;box-shadow:0 0 6px #c040ff"></div></div>
        <span class="sp-val" style="color:#c040ff"><?php echo (int) $mnd; ?></span>
      </div>
      <div class="stat-pill">
        <span class="sp-label">FOCUS</span>
        <div class="sp-bar"><div class="sp-fill" style="width:<?php echo (int) $fcs; ?>%;background:#00e5ff;box-shadow:0 0 6px #00e5ff"></div></div>
        <span class="sp-val" style="color:#00e5ff"><?php echo (int) $fcs; ?></span>
      </div>
    </div>
    <div class="hero-stats hs-right" aria-hidden="true">
      <div class="stat-pill">
        <span class="sp-val" style="color:#20e080"><?php echo (int) $spd; ?></span>
        <div class="sp-bar"><div class="sp-fill" style="width:<?php echo (int) $spd; ?>%;background:#20e080;box-shadow:0 0 6px #20e080"></div></div>
        <span class="sp-label">SPEED</span>
      </div>
      <div class="stat-pill">
        <span class="sp-val" style="color:#ffc030"><?php echo (int) $lck; ?></span>
        <div class="sp-bar"><div class="sp-fill" style="width:<?php echo (int) $lck; ?>%;background:#ffc030;box-shadow:0 0 6px #ffc030"></div></div>
        <span class="sp-label">LUCK</span>
      </div>
    </div>

    <div class="hero-holo">
      <div class="hero-rarity-tag" id="hero-rarity-tag"><?php echo htmlspecialchars($rarity, ENT_QUOTES, 'UTF-8'); ?></div>
      <div
        class="hero-avatar-wrap"
        id="hero-avatar-wrap"
        data-hero-image-url="<?php echo $heroUrl ? htmlspecialchars($heroUrl, ENT_QUOTES, 'UTF-8') : ''; ?>"
        data-hero-model-url="<?php echo $heroModelUrl ? htmlspecialchars($heroModelUrl, ENT_QUOTES, 'UTF-8') : ''; ?>"
      >
        <div class="hero-glow-ring" aria-hidden="true"></div>
        <div class="hero-glow-ring2" aria-hidden="true"></div>
        <?php if ($heroUrl): ?>
        <img class="hero-img" src="<?php echo htmlspecialchars($heroUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="" id="hero-avatar-img">
        <?php else: ?>
        <div class="hero-sil" id="hero-avatar-fallback">
          <div class="hs-head"></div>
          <div class="hs-neck"></div>
          <div class="hs-torso"></div>
          <div class="hs-legs">
            <div class="hs-leg"></div>
            <div class="hs-leg"></div>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="hero-platform" aria-hidden="true">
      <div class="hp-ellipse"></div>
      <div class="hp-glow"></div>
    </div>
    <div class="hero-beam" aria-hidden="true"></div>
    <div class="hero-scan" aria-hidden="true"></div>

    <div class="hero-name" id="hero-name"><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></div>
    <div class="hero-class" id="hero-class"><?php echo htmlspecialchars($cls . ' · ' . $rarity, ENT_QUOTES, 'UTF-8'); ?></div>
  </div>

  <div class="battle-zone">
    <div class="battle-subtext" id="battle-subtext">NEXUS ONLINE</div>
    <button type="button" class="battle-btn" id="nexus-link-btn"
            onclick="location.href='/games/arena-protocol/nexus-city.html'">
      <span class="bb-icon">⬡</span> LINK PROTOCOL
    </button>
    <div class="quick-actions">
      <button type="button" class="qa-btn" id="qa-change-avatar">UNITS</button>
      <button type="button" class="qa-btn" id="qa-inventory">🎒 INVENTORY</button>
      <button type="button" class="qa-btn" id="qa-neural-link">🧬 NEURAL LINK</button>
    </div>
  </div>
</div>

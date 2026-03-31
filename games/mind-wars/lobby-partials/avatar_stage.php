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
?>
<div class="center-col">
  <div class="hero-stage" id="hero-stage">
    <div class="live-preview" id="live-preview"></div>
    <div class="hero-glow-ring"></div>
    <div class="hero-glow-ring2"></div>
    <div class="hero-holo">
      <div class="hero-rarity-tag" id="hero-rarity-tag"><?php echo htmlspecialchars($rarity, ENT_QUOTES, 'UTF-8'); ?></div>
      <div
        class="hero-avatar-wrap"
        id="hero-avatar-wrap"
        data-hero-image-url="<?php echo $heroUrl ? htmlspecialchars($heroUrl, ENT_QUOTES, 'UTF-8') : ''; ?>"
        data-hero-model-url="<?php echo $heroModelUrl ? htmlspecialchars($heroModelUrl, ENT_QUOTES, 'UTF-8') : ''; ?>"
      >
        <?php if ($heroUrl): ?>
        <img src="<?php echo htmlspecialchars($heroUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="" id="hero-avatar-img">
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
      <div class="hero-platform">
        <div class="hp-glow"></div>
        <div class="hp-ring"></div>
        <div class="hp-ring2"></div>
      </div>
      <div class="hero-beam"></div>
    </div>
    <div class="hero-name" id="hero-name"><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></div>
    <div class="hero-class" id="hero-class"><?php echo htmlspecialchars($cls . ' · ' . $rarity, ENT_QUOTES, 'UTF-8'); ?></div>
  </div>

  <div class="battle-zone">
    <div class="battle-subtext" id="battle-subtext">READY FOR COMBAT</div>
    <button type="button" class="battle-btn" id="battle-open-mm">
      <span class="bb-icon">⚔</span> BATTLE
    </button>
    <div class="quick-actions">
      <button type="button" class="qa-btn" id="qa-change-avatar">⬡ CHANGE AVATAR</button>
      <button type="button" class="qa-btn" id="qa-inventory">🎒 INVENTORY</button>
      <button type="button" class="qa-btn" id="qa-neural-link">🧬 NEURAL LINK</button>
    </div>
  </div>
</div>

<?php
/**
 * Empty-state content for #labs-result-preview (hex + titles + optional tips).
 * Set before include: $labsStudioPhTitle, $labsStudioPhSub
 * Optional: $labsStudioTipsIcon, $labsStudioTipsLine1, $labsStudioTipsLine2
 */
$title = $labsStudioPhTitle ?? t('labs.studio.empty_title', 'Ready to create?');
$sub = $labsStudioPhSub ?? '';
$tipIcon = $labsStudioTipsIcon ?? 'fa-wand-magic-sparkles';
$tip1 = $labsStudioTipsLine1 ?? null;
$tip2 = $labsStudioTipsLine2 ?? null;
$gSuf = isset($labsStudioGradientSuffix) ? (string) $labsStudioGradientSuffix : '';
?>
<div id="labs-placeholder-tips" class="knd-labs-studio-empty-root">
  <div class="knd-labs-studio-hero">
    <div class="knd-labs-canvas-logo-mark">
      <svg viewBox="0 0 60 60" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <polygon points="30,4 54,18 54,42 30,56 6,42 6,18" stroke="url(#kndStudioHexG1<?php echo htmlspecialchars($gSuf, ENT_QUOTES, 'UTF-8'); ?>)" stroke-width="2" fill="rgba(124,92,255,0.06)"/>
        <polygon points="30,12 46,21 46,39 30,48 14,39 14,21" stroke="url(#kndStudioHexG2<?php echo htmlspecialchars($gSuf, ENT_QUOTES, 'UTF-8'); ?>)" stroke-width="1.5" fill="rgba(0,212,255,0.04)" opacity="0.6"/>
        <polygon points="30,20 38,24.5 38,33.5 30,38 22,33.5 22,24.5" fill="url(#kndStudioHexG3<?php echo htmlspecialchars($gSuf, ENT_QUOTES, 'UTF-8'); ?>)" opacity="0.9"/>
        <defs>
          <linearGradient id="kndStudioHexG1<?php echo htmlspecialchars($gSuf, ENT_QUOTES, 'UTF-8'); ?>" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="#7c5cff"/>
            <stop offset="100%" stop-color="#00d4ff"/>
          </linearGradient>
          <linearGradient id="kndStudioHexG2<?php echo htmlspecialchars($gSuf, ENT_QUOTES, 'UTF-8'); ?>" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="#00d4ff"/>
            <stop offset="100%" stop-color="#7c5cff"/>
          </linearGradient>
          <linearGradient id="kndStudioHexG3<?php echo htmlspecialchars($gSuf, ENT_QUOTES, 'UTF-8'); ?>" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="#7c5cff"/>
            <stop offset="100%" stop-color="#00d4ff"/>
          </linearGradient>
        </defs>
      </svg>
    </div>
    <div class="knd-labs-canvas-title"><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php if ($sub !== ''): ?>
    <div class="knd-labs-canvas-subtitle"><?php echo htmlspecialchars($sub, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
  </div>
  <?php if ($tip1 !== null && $tip1 !== ''): ?>
  <div class="knd-labs-studio-tips labs-placeholder-tips">
    <i class="fas <?php echo htmlspecialchars($tipIcon, ENT_QUOTES, 'UTF-8'); ?> knd-labs-studio-tips-icon" aria-hidden="true"></i>
    <p class="knd-labs-studio-tips-line1"><?php echo htmlspecialchars($tip1, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php if ($tip2 !== null && $tip2 !== ''): ?>
    <p class="knd-labs-studio-tips-line2 mb-0"><?php echo htmlspecialchars($tip2, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

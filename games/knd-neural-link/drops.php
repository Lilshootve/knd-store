<?php
/**
 * KND Neural Link — drops.php (sandbox UI)
 * PRODUCCIÓN: puede integrarse al layout global; rutas relativas a /games/knd-neural-link/
 */
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

require_login();

$userId   = (int) current_user_id();
$username = htmlspecialchars(current_username() ?? 'OPERATOR', ENT_QUOTES, 'UTF-8');
$csrfJs   = json_encode(csrf_token(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$cssPath = __DIR__ . '/assets/drops.css';
$jsPath  = __DIR__ . '/assets/drops.js';
$cssV    = file_exists($cssPath) ? filemtime($cssPath) : 0;
$jsV     = file_exists($jsPath) ? filemtime($jsPath) : 0;
require_once __DIR__ . '/../../includes/favicon_links.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>KND Neural Link</title>
<?php echo generateFaviconLinks(); ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;700;900&family=Rajdhani:wght@300;400;500;600;700&family=Share+Tech+Mono&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/games/knd-neural-link/assets/drops.css?v=<?= (int) $cssV ?>">
</head>
<body>

<div id="drops-bg">
  <canvas id="bg-canvas"></canvas>
  <div class="bg-grid"></div>
  <div class="bg-vignette"></div>
</div>

<div id="screen-flash"></div>
<div id="toast-stack"></div>

<div class="drops-shell">

  <header class="drops-topbar">
    <a href="/games/mind-wars/lobby.php" class="dtb-back">
      <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M10 3L5 8L10 13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
      LOBBY
    </a>
    <div class="dtb-title">
      <span class="dtb-glyph">⬡</span>
      NEURAL LINK
      <span class="dtb-sub">Consciousness interface</span>
    </div>
    <div class="dtb-balance" id="dtb-balance">
      <div class="bal-chip kp-chip" title="KND Points">
        <span class="bal-icon">◇</span>
        <span class="bal-val" id="bal-kp">—</span>
        <span class="bal-unit">KP</span>
      </div>
    </div>
  </header>

  <main class="drops-main">

    <section class="packs-section">
      <div class="section-eyebrow">SELECT LINK BANDWIDTH</div>
      <div class="packs-grid" id="packs-grid">
        <div class="pack-skeleton"></div>
        <div class="pack-skeleton"></div>
        <div class="pack-skeleton"></div>
      </div>
    </section>

    <section class="drop-stage-section">

      <div class="drop-stage" id="drop-stage">

        <div class="stage-idle" id="stage-idle">
          <div class="capsule-wrap" id="capsule-wrap">
            <div class="capsule-outer">
              <div class="capsule-ring r1"></div>
              <div class="capsule-ring r2"></div>
              <div class="capsule-ring r3"></div>
              <div class="capsule-core">
                <div class="capsule-inner-glow"></div>
                <div class="capsule-hex">⬡</div>
              </div>
            </div>
            <div class="capsule-base">
              <div class="cb-ring"></div>
              <div class="cb-ring cb-ring2"></div>
              <div class="cb-beam"></div>
            </div>
          </div>

          <div class="pack-info-display" id="pack-info-display">
            <div class="pid-name" id="pid-name">AWAITING SELECTION</div>
            <div class="pid-desc" id="pid-desc">Choose a link class above</div>
            <div class="pid-rates" id="pid-rates"></div>
          </div>

          <div class="pity-display" id="pity-display">
            <div class="pity-row">
              <span class="pity-label">LEGENDARY STABILIZATION</span>
              <div class="pity-bar"><div class="pity-fill pity-legendary" id="pf-legendary"></div></div>
              <span class="pity-val" id="pv-legendary">0</span>
            </div>
            <div class="pity-row">
              <span class="pity-label">EPIC STABILIZATION</span>
              <div class="pity-bar"><div class="pity-fill pity-epic" id="pf-epic"></div></div>
              <span class="pity-val" id="pv-epic">0</span>
            </div>
          </div>
        </div>

        <div class="stage-opening hidden" id="stage-opening">
          <div class="open-bg-burst" id="open-burst"></div>
          <div class="open-capsule-shatter" id="open-shatter">
            <div class="shard s1"></div><div class="shard s2"></div>
            <div class="shard s3"></div><div class="shard s4"></div>
            <div class="shard s5"></div><div class="shard s6"></div>
          </div>
          <div class="open-label" id="open-label">INITIALIZING…</div>
        </div>

        <div class="stage-result hidden" id="stage-result">
          <div class="result-bg-rays" id="result-rays"></div>

          <div class="result-card" id="result-card">
            <div class="rc-topbar" id="rc-topbar"></div>
            <div class="rc-portrait" id="rc-portrait">
              <div class="rc-scanlines"></div>
              <div class="rc-glow-ring" id="rc-glow-ring"></div>
              <img id="rc-image" src="" alt="" class="rc-img">
              <div class="rc-platform">
                <div class="rcp-ring"></div>
                <div class="rcp-glow"></div>
              </div>
              <div class="rc-beam" id="rc-beam"></div>
            </div>
            <div class="rc-rarity-badge" id="rc-rarity-badge">BASELINE</div>
            <div class="rc-name" id="rc-name">—</div>
            <div class="rc-class" id="rc-class">—</div>
            <div class="rc-stats" id="rc-stats"></div>
            <div class="rc-duplicate hidden" id="rc-duplicate">
              <span class="dup-icon">⟳</span>
              <div>
                <div class="dup-title">SYNC REDUNDANT</div>
                <div class="dup-sub">Energy routed: <span class="dup-ke" id="dup-ke">+0 KE</span></div>
              </div>
            </div>
          </div>

          <div class="result-actions" id="result-actions">
            <button class="ra-btn ra-equip hidden" id="ra-equip" onclick="KNDDrops.equipAvatar()">⬡ SET ACTIVE AVATAR</button>
            <button class="ra-btn ra-again" id="ra-again" onclick="KNDDrops.openAgain()">↺ LINK AGAIN</button>
            <button class="ra-btn ra-return" onclick="KNDDrops.resetToIdle()">← DISENGAGE</button>
          </div>
        </div>

      </div>

      <div class="open-btn-wrap" id="open-btn-wrap">
        <div class="open-btn-cost" id="open-btn-cost"></div>
        <button class="open-btn" id="open-btn" onclick="KNDDrops.openDrop()" disabled>
          <span class="ob-icon">⬡</span>
          <span class="ob-label">INITIATE NEURAL LINK</span>
          <span class="ob-glow"></span>
        </button>
        <div class="open-btn-hint" id="open-btn-hint">Select a link class to continue</div>
      </div>

    </section>

    <section class="history-section">
      <div class="section-eyebrow">RECENT LINKS</div>
      <div class="history-list" id="history-list">
        <div class="history-empty">No links this session</div>
      </div>
    </section>

  </main>
</div>

<div class="equip-modal-backdrop hidden" id="equip-modal">
  <div class="equip-modal">
    <div class="em-top"></div>
    <div class="em-body">
      <div class="em-title">SET AS ACTIVE AVATAR?</div>
      <div class="em-av-name" id="em-av-name">—</div>
      <div class="em-av-class" id="em-av-class">—</div>
      <div class="em-btns">
        <button class="em-confirm" id="em-confirm">⬡ CONFIRM</button>
        <button class="em-cancel" onclick="KNDDrops.closeEquipModal()">CANCEL</button>
      </div>
    </div>
  </div>
</div>

<script>
window.KND_CONFIG = {
  userId:   <?= $userId ?>,
  username: <?= json_encode($username, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
  apiBase:  '/games/knd-neural-link/api',
  csrfToken: <?= $csrfJs ?>,
  equipUrl: '/api/avatar/set_favorite.php',
};
</script>
<script src="/games/knd-neural-link/assets/drops.js?v=<?= (int) $jsV ?>"></script>
</body>
</html>

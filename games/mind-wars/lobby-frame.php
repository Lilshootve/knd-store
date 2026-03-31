<?php
/**
 * Shared Mind Wars–style shell (loading, bg, overlays, topbar, 3 columns, optional bottom nav).
 * Caller must set $LOBBY_CENTER_PARTIAL (path to PHP partial for center column) and
 * $csrfToken, $L, $cssV, $jsV, $mwCardCssV, $mwCardJsV, $levelsCssV before including.
 * Set $LOBBY_BOTTOM_NAV = false before include to hide the bottom nav (Mind Wars lobby).
 */
require_once __DIR__ . '/../../includes/favicon_links.php';
if (empty($LOBBY_CENTER_PARTIAL) || !is_readable($LOBBY_CENTER_PARTIAL)) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Error</title></head><body><p>Invalid lobby layout.</p></body></html>';
    exit;
}
$pageTitle = $LOBBY_PAGE_TITLE ?? 'KND Games — Lobby';
$loadingLogo = $LOBBY_LOADING_LOGO ?? 'MIND WARS';
$shellGame = $LOBBY_SHELL_GAME ?? 'mind-wars';
$extraHead = $LOBBY_EXTRA_HEAD_HTML ?? '';
$showLobbyBottomNav = ($LOBBY_BOTTOM_NAV ?? true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
<?php echo generateFaviconLinks(); ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;600;700;900&family=Rajdhani:wght@300;400;500;600;700&family=Share+Tech+Mono&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/levels.css?v=<?php echo (int) $levelsCssV; ?>">
<link rel="stylesheet" href="/games/mind-wars/lobby.css?v=<?php echo (int) $cssV; ?>">
<link rel="stylesheet" href="/games/mind-wars/mw-avatar-cards.css?v=<?php echo (int) $mwCardCssV; ?>">
<?php
$cfxCss = __DIR__ . '/../../assets/css/cursor-fx.css';
if (is_file($cfxCss)) { ?>
<link rel="stylesheet" href="/assets/css/cursor-fx.css?v=<?php echo (int) filemtime($cfxCss); ?>">
<?php } ?>
<?php echo $extraHead; ?>
</head>
<body>

<div id="mw-portrait-gate" class="mw-portrait-gate" role="dialog" aria-modal="true" aria-labelledby="mw-portrait-gate-title">
  <div class="mw-portrait-gate-inner">
    <div class="mw-portrait-gate-phone" aria-hidden="true">
      <span class="mw-portrait-gate-icon">📱</span>
      <span class="mw-portrait-gate-arrow">↻</span>
    </div>
    <p id="mw-portrait-gate-title" class="mw-portrait-gate-title">Rotate your device</p>
    <p class="mw-portrait-gate-sub">Mind Wars is built for landscape. Turn your phone sideways to see the full lobby and battle HUD.</p>
    <button type="button" class="mw-portrait-gate-skip" onclick="document.documentElement.classList.add('mw-portrait-dismissed')">Continue in portrait</button>
  </div>
</div>

<div id="loading-screen">
  <div class="ls-logo"><?php echo htmlspecialchars($loadingLogo, ENT_QUOTES, 'UTF-8'); ?></div>
  <div class="ls-sub">KND Games</div>
  <div class="ls-bar-wrap">
    <div class="ls-bar"><div class="ls-fill" id="ls-fill"></div></div>
    <div class="ls-msg" id="ls-msg">LOADING…</div>
  </div>
</div>

<div id="bg-layer">
  <canvas id="star-canvas"></canvas>
  <canvas id="grid-canvas" aria-hidden="true"></canvas>
  <canvas id="particle-canvas" aria-hidden="true"></canvas>
  <div class="horizon"></div>
  <div class="persp-floor"></div>
</div>

<div id="knd-cursor-fx-root" class="knd-cursor-fx-root" aria-hidden="true"></div>

<div id="toast-container"></div>

<div class="settings-drawer" id="settings-drawer">
  <div class="sd-hdr">
    <span class="sd-title">⚙ SETTINGS</span>
    <button type="button" class="sd-close" id="settings-close">✕</button>
  </div>
  <div class="sd-body">
    <div class="sd-section">Audio</div>
    <div class="sd-row">
      <span class="sd-label">Sound Effects</span>
      <div class="sd-toggle on" id="sfx-toggle"></div>
    </div>
    <div class="sd-row">
      <span class="sd-label">Music</span>
      <div class="sd-toggle" data-sd-toggle></div>
    </div>
    <div class="sd-section" style="margin-top:8px">Visual</div>
    <div class="sd-row">
      <span class="sd-label">Particles</span>
      <div class="sd-toggle on" data-sd-toggle></div>
    </div>
  </div>
</div>

<div class="mm-backdrop" id="mm-modal">
  <div class="mm-modal">
    <div class="mm-hdr">
      <span class="mm-title" id="mm-title">⚔ SELECT BATTLE MODE</span>
      <button type="button" class="mm-close" id="mm-close">✕</button>
    </div>
    <div class="mm-body">
      <div id="mm-mode-step">
        <div class="mode-selector" id="mode-selector">
          <div class="ms-option selected" data-mode="pvp">
            <div class="ms-ico" style="color:#00e8ff">⚔️</div>
            <div class="ms-name">PvP</div>
            <div class="ms-desc">Ranked 1v1</div>
            <div class="ms-badge" style="background:rgba(255,34,85,.1);border:1px solid rgba(255,34,85,.3);color:var(--red);font-family:var(--FD);font-size:8px;letter-spacing:1px;padding:2px 6px;border-radius:1px">RANKED</div>
          </div>
          <div class="ms-option" data-mode="pve">
            <div class="ms-ico" style="color:#9b30ff">🤖</div>
            <div class="ms-name">PvE</div>
            <div class="ms-desc">vs AI</div>
            <div class="ms-badge" style="background:rgba(0,255,153,.08);border:1px solid rgba(0,255,153,.25);color:var(--green);font-family:var(--FD);font-size:8px;letter-spacing:1px;padding:2px 6px;border-radius:1px">CASUAL</div>
          </div>
          <div class="ms-option" data-mode="ranked">
            <div class="ms-ico" style="color:#ffcc00">🏆</div>
            <div class="ms-name">RANKED</div>
            <div class="ms-desc">Ladder</div>
            <div class="ms-badge" style="background:rgba(255,204,0,.1);border:1px solid rgba(255,204,0,.3);color:var(--gold);font-family:var(--FD);font-size:8px;letter-spacing:1px;padding:2px 6px;border-radius:1px">SEASON</div>
          </div>
        </div>
      </div>
      <div id="mm-pve-format-step" class="hidden">
        <button type="button" class="mm-back-step" id="mm-pve-format-back">← Battle mode</button>
        <div class="mode-selector mode-selector--cols-2" id="pve-format-selector">
          <div class="ms-option selected" data-pve-format="1v1">
            <div class="ms-ico" style="color:#00e8ff">⚔️</div>
            <div class="ms-name">1v1</div>
            <div class="ms-desc">Single avatar vs AI</div>
            <div class="ms-badge" style="background:rgba(0,232,255,.08);border:1px solid rgba(0,232,255,.25);color:var(--c);font-family:var(--FD);font-size:8px;letter-spacing:1px;padding:2px 6px;border-radius:1px">DUEL</div>
          </div>
          <div class="ms-option" data-pve-format="3v3">
            <div class="ms-ico" style="color:#9b30ff">👥</div>
            <div class="ms-name">3v3</div>
            <div class="ms-desc">Squad vs AI waves</div>
            <div class="ms-badge" style="background:rgba(155,48,255,.1);border:1px solid rgba(155,48,255,.28);color:var(--m);font-family:var(--FD);font-size:8px;letter-spacing:1px;padding:2px 6px;border-radius:1px">TEAM</div>
          </div>
        </div>
      </div>
      <div id="mm-pve-3v3-setup-step" class="hidden">
        <button type="button" class="mm-back-step" id="mm-pve-3v3-setup-back">← PvE format</button>
        <p class="mm-pve-setup-hint">Choose three avatars for your squad (tap a slot)</p>
        <div class="mm-pve-setup-slots" id="mm-pve-3v3-slots"></div>
      </div>
      <div id="mm-idle-state">
        <button type="button" class="mm-confirm-btn" id="mm-enter-btn">⚔ ENTER BATTLE</button>
      </div>
      <div id="mm-searching-state" class="hidden">
        <div class="mm-searching">
          <div class="search-anim">
            <div class="sa-ring"></div><div class="sa-ring2"></div><div class="sa-ring3"></div>
            <div class="sa-core">⚔</div>
          </div>
          <div class="search-label">Searching<span class="search-dots"><span>.</span><span>.</span><span>.</span></span></div>
          <div class="search-sub" id="search-timer">00:00</div>
          <button type="button" class="mm-cancel-btn" id="mm-cancel-btn">✕ CANCEL</button>
        </div>
      </div>
    </div>
  </div>
</div>

<div id="mm-pve-team-selector" class="mm-pve-team-selector" aria-hidden="true">
  <div class="mm-pve-team-box">
    <div class="mm-pve-team-head">
      <div class="mm-pve-team-title" id="mm-pve-team-title">SELECT AVATAR</div>
      <button type="button" class="mm-pve-team-close" id="mm-pve-team-close">✕</button>
    </div>
    <div class="mm-pve-team-scroll">
      <div class="lavs-grid mm-pve-team-grid" id="mm-pve-team-grid"></div>
    </div>
  </div>
</div>

<div class="overlay-panel" id="lb-overlay">
  <div class="ov-box">
    <div class="ov-hdr">
      <span class="ov-title">🏆 LEADERBOARD</span>
      <button type="button" class="ov-close" data-close-overlay="lb-overlay">✕</button>
    </div>
    <div class="ov-body" id="lb-full-body"></div>
  </div>
</div>

<div class="overlay-panel" id="av-overlay">
  <div class="ov-box ov-collection-box">
    <div class="ov-hdr">
      <span class="ov-title">⬡ AVATAR COLLECTION</span>
      <button type="button" class="ov-close" data-close-overlay="av-overlay">✕</button>
    </div>
    <div class="ov-body ov-collection-body">
      <div class="lavs-grid" id="av-grid"></div>
    </div>
  </div>
</div>

<div class="overlay-panel" id="notif-overlay">
  <div class="ov-box" style="width:min(94vw,420px)">
    <div class="ov-hdr">
      <span class="ov-title">🔔 NOTIFICATIONS</span>
      <button type="button" class="ov-close" data-close-overlay="notif-overlay">✕</button>
    </div>
    <div class="ov-body" id="notif-body"></div>
  </div>
</div>

<div class="lobby-shell<?php echo $showLobbyBottomNav ? '' : ' lobby-shell--no-bottom-nav'; ?>">
<?php require __DIR__ . '/lobby-partials/topbar.php'; ?>
  <div class="lobby-content">
<?php require __DIR__ . '/lobby-partials/panels_left.php'; ?>
<?php require $LOBBY_CENTER_PARTIAL; ?>
<?php $mwShellGame = $shellGame; require __DIR__ . '/lobby-partials/panels_right.php'; ?>
  </div>
<?php if ($showLobbyBottomNav): ?>
  <nav class="bottom-nav" aria-label="Lobby navigation">
    <div class="bnav-item active" data-nav="lobby">
      <div class="bnav-icon">🏠</div>
      <div class="bnav-label">LOBBY</div>
    </div>
    <div class="bnav-item" data-nav="avatars">
      <div class="bnav-icon">⬡</div>
      <div class="bnav-label">AVATARS</div>
    </div>
    <div class="bnav-item bnav-item--neural-link" data-nav="neural-link" title="KND Neural Link">
      <div class="bnav-icon" aria-hidden="true">🧬</div>
      <div class="bnav-label">NEURAL LINK</div>
    </div>
    <div class="bnav-item" data-nav="leaderboard">
      <div class="bnav-icon">🏆</div>
      <div class="bnav-label">RANKS</div>
    </div>
    <div class="bnav-item" data-nav="inventory">
      <div class="bnav-icon">🎒</div>
      <div class="bnav-label">BAG</div>
    </div>
  </nav>
<?php endif; ?>
</div>

<script>
window.MW_LOBBY_CSRF = <?php echo json_encode($csrfToken, JSON_UNESCAPED_UNICODE); ?>;
window.MW_LOBBY_INITIAL = <?php echo json_encode($L, JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="/games/mind-wars/mw-avatar-card.js?v=<?php echo (int) $mwCardJsV; ?>"></script>
<script src="/games/mind-wars/lobby.js?v=<?php echo (int) $jsV; ?>"></script>
<?php
$cfxJs = __DIR__ . '/../../assets/js/cursor-fx.js';
if (is_file($cfxJs)) { ?>
<script src="/assets/js/cursor-fx.js?v=<?php echo (int) filemtime($cfxJs); ?>" defer></script>
<?php } ?>
<?php if (!empty($LOBBY_EXTRA_SCRIPTS_AFTER_LOBBY_JS)) {
    echo $LOBBY_EXTRA_SCRIPTS_AFTER_LOBBY_JS;
} ?>
</body>
</html>

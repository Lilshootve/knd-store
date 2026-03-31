<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/footer.php';

require_login();

$embed = isset($_GET['embed']) && $_GET['embed'] === '1';

$L = [
    'user' => ['username' => '', 'level' => 1, 'xp_fill_pct' => 0],
    'currencies' => ['knd_points_available' => 0, 'fragments_total' => 0],
    'season' => [],
    'ranking' => [],
    'selected_avatar' => null,
    'hero_image_url' => null,
    'missions' => [],
    'avatars' => [],
    'notifications' => ['unread_count' => 0],
    'online_hint' => 0,
];
if (!$embed) {
    require_once __DIR__ . '/../includes/mw_lobby.php';
    $pdoL = getDBConnection();
    $uidL = (int) current_user_id();
    if ($pdoL && $uidL > 0) {
        try {
            $L = mw_build_lobby_data_payload($pdoL, $uidL);
        } catch (Throwable $e) {
            error_log('knowledge-duel lobby payload: ' . $e->getMessage());
        }
    }
}

$csrfToken = csrf_token();
$seoTitle = 'Neural Duel | KND';
$seoDesc = 'Engage in cognitive combat. Answer correctly, strike the enemy, earn Knowledge Energy and Rank.';
$kdCss = __DIR__ . '/../assets/css/knowledge-duel.css';
$arenaHubCss = __DIR__ . '/../assets/css/arena-hub.css';
$kdShellJs = __DIR__ . '/mind-wars/kd-lobby-shell.js';
$mwCardJs = __DIR__ . '/mind-wars/mw-avatar-card.js';
$lobbyCss = __DIR__ . '/mind-wars/lobby.css';
$mwCardCss = __DIR__ . '/mind-wars/mw-avatar-cards.css';
$levelsCss = __DIR__ . '/../assets/css/levels.css';
$vKd = file_exists($kdCss) ? filemtime($kdCss) : time();
$vKs = file_exists($kdShellJs) ? filemtime($kdShellJs) : 0;
$vMwJ = file_exists($mwCardJs) ? filemtime($mwCardJs) : 0;
$vLo = file_exists($lobbyCss) ? filemtime($lobbyCss) : 0;
$vMw = file_exists($mwCardCss) ? filemtime($mwCardCss) : 0;
$vL = file_exists($levelsCss) ? filemtime($levelsCss) : 0;

$extraHead = '';
$extraHead .= '<link rel="stylesheet" href="/assets/css/knowledge-duel.css?v=' . $vKd . '">' . "\n";
$extraHead .= '<link rel="stylesheet" href="/assets/css/arena-hub.css?v=' . (file_exists($arenaHubCss) ? filemtime($arenaHubCss) : time()) . '">' . "\n";
$extraHead .= '<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;700;900&family=Rajdhani:wght@300;400;500;600;700&family=Share+Tech+Mono&display=swap" rel="stylesheet">' . "\n";

if ($embed) {
    header('Content-Type: text/html; charset=utf-8');
    $kdCssV = file_exists($kdCss) ? filemtime($kdCss) : time();
    ?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($seoTitle); ?></title>
    <?php echo generateFaviconLinks(); ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;700;900&family=Rajdhani:wght@300;400;500;600;700&family=Share+Tech+Mono&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css?v=<?php echo @filemtime(__DIR__ . '/../assets/css/style.css'); ?>">
    <link rel="stylesheet" href="/assets/css/levels.css?v=<?php echo file_exists(__DIR__ . '/../assets/css/levels.css') ? filemtime(__DIR__ . '/../assets/css/levels.css') : 0; ?>">
    <link rel="stylesheet" href="/assets/css/knd-ui.css?v=<?php echo file_exists(__DIR__ . '/../assets/css/knd-ui.css') ? filemtime(__DIR__ . '/../assets/css/knd-ui.css') : 0; ?>">
    <link rel="stylesheet" href="/assets/css/knowledge-duel.css?v=<?php echo $kdCssV; ?>">
    <link rel="stylesheet" href="/assets/css/arena-embed.css?v=<?php echo file_exists(__DIR__ . '/../assets/css/arena-embed.css') ? filemtime(__DIR__ . '/../assets/css/arena-embed.css') : 0; ?>">
</head>
<body class="arena-embed knd-neural">
<div class="arena-embed-inner">
<?php
}

if (!$embed) {
    require_once __DIR__ . '/../includes/favicon_links.php';
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($seoTitle, ENT_QUOTES, 'UTF-8'); ?></title>
<?php echo generateFaviconLinks(); ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;600;700;900&family=Rajdhani:wght@300;400;500;600;700&family=Share+Tech+Mono&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="/assets/css/levels.css?v=<?php echo $vL; ?>">
<link rel="stylesheet" href="/games/mind-wars/lobby.css?v=<?php echo $vLo; ?>">
<link rel="stylesheet" href="/games/mind-wars/mw-avatar-cards.css?v=<?php echo $vMw; ?>">
<link rel="stylesheet" href="/assets/css/knowledge-duel.css?v=<?php echo $vKd; ?>">
</head>
<body>
<div id="toast-container"></div>
<div id="loading-screen">
  <div class="ls-logo">NEURAL DUEL</div>
  <div class="ls-sub">KND Games</div>
  <div class="ls-bar-wrap">
    <div class="ls-bar"><div class="ls-fill" id="ls-fill"></div></div>
    <div class="ls-msg" id="ls-msg">LOADING…</div>
  </div>
</div>
<div id="bg-layer">
  <canvas id="star-canvas"></canvas>
  <div class="horizon"></div>
  <div class="persp-floor"></div>
</div>
<div class="lobby-shell">
<?php require __DIR__ . '/mind-wars/lobby-partials/topbar.php'; ?>
<div class="lobby-content">
<?php require __DIR__ . '/mind-wars/lobby-partials/panels_left.php'; ?>
<div class="center-col kd-neural-center">
<?php
}
?>

<!-- ══════════════════════════════════════════
     NEURAL LINK TUTORIAL MODAL
══════════════════════════════════════════ -->
<div id="kd-tutorial-modal" class="kd-tutorial-modal" role="dialog" aria-labelledby="kd-tutorial-title" aria-modal="true" style="display:none;">
    <div class="kd-tutorial-backdrop"></div>
    <div class="kd-tutorial-content knd-panel">
        <div class="knd-panel-topbar"></div>
        <div class="knd-tutorial-header">
            <span class="knd-tutorial-glyph">⬡</span>
            <h2 id="kd-tutorial-title" class="knd-title">NEURAL LINK PROTOCOL</h2>
            <p class="knd-subtitle">Cognitive Combat Initialization</p>
        </div>
        <ol class="knd-tutorial-steps">
            <li>
                <span class="knd-step-num">01</span>
                <div>
                    <strong>SELECT YOUR AVATAR</strong>
                    <span>Your avatar accumulates Knowledge Energy (KE) through combat. KE triggers level-up sequences.</span>
                </div>
            </li>
            <li>
                <span class="knd-step-num">02</span>
                <div>
                    <strong>CONFIGURE NEURAL PARAMETERS</strong>
                    <span>Select cognitive domain and interference level. Higher difficulty amplifies KE and XP yields.</span>
                </div>
            </li>
            <li>
                <span class="knd-step-num">03</span>
                <div>
                    <strong>EXECUTE COGNITIVE STRIKES</strong>
                    <span>Correct responses deal damage to the neural entity. Incorrect responses degrade your Link Stability.</span>
                </div>
            </li>
            <li>
                <span class="knd-step-num">04</span>
                <div>
                    <strong>HARVEST NEURAL REWARDS</strong>
                    <span>Every session yields XP, KE, and Rank. Sustain the link. Advance your neural architecture.</span>
                </div>
            </li>
        </ol>
        <button type="button" class="knd-btn knd-btn--primary" id="kd-tutorial-close">
            <i class="fas fa-link me-2"></i>INITIATE LINK
        </button>
    </div>
</div>

<!-- ══════════════════════════════════════════
     MAIN SHELL
══════════════════════════════════════════ -->
<section class="knd-duel-section">
<div class="knd-duel-shell">

    <!-- ── HEADER STRIP ── -->
    <div class="knd-duel-topstrip">
        <div class="knd-duel-topstrip__left">
            <span class="knd-logo-glyph">⬡</span>
            <span class="knd-system-label">KND NEURAL DUEL</span>
            <span class="knd-step-chip" id="kd-step-chip" data-step="idle">
                <i class="fas fa-satellite-dish me-1"></i>Idle
            </span>
        </div>
        <div class="knd-duel-topstrip__right">
            <div class="knd-hud-pill" id="kd-level-pill">LVL —</div>
            <div class="knd-hud-pill knd-hud-pill--rank" id="kd-rank-pill">RANK —</div>
            <button type="button" class="knd-icon-btn" id="kd-mute-btn" title="Toggle audio signal" aria-label="Toggle sound">
                <i class="fas fa-volume-up kd-mute-icon"></i>
                <i class="fas fa-volume-mute kd-mute-icon kd-mute-icon--off" style="display:none;"></i>
            </button>
        </div>
    </div>

    <!-- ── CONTENT GRID ── -->
    <div class="knd-duel-grid">

        <!-- ════════════════════════════════
             LEFT COL — Avatar + Stats
        ════════════════════════════════ -->
        <aside class="knd-duel-aside" id="kd-duel-aside">

            <!-- Avatar selector -->
            <div class="knd-panel knd-avatar-panel" id="kd-current-duelist-card">
                <div class="knd-panel-topbar"></div>
                <div class="knd-panel-label">ACTIVE UNIT</div>

                <div class="knd-avatar-showcase" id="kd-current-duelist-frame" data-level="1">
                    <div class="knd-avatar-holo-ring"></div>
                    <img id="kd-current-duelist-thumb" class="knd-avatar-showcase__img" alt="Active Avatar">
                    <div class="knd-avatar-base">
                        <div class="knd-avatar-base__ring"></div>
                        <div class="knd-avatar-base__glow"></div>
                    </div>
                </div>

                <div class="knd-avatar-identity">
                    <div class="knd-avatar-identity__name" id="kd-current-duelist-name">SELECT UNIT</div>
                    <div class="knd-avatar-identity__sub" id="kd-current-duelist-sub">Awaiting synchronization</div>
                </div>

                <div id="kd-avatar-picker-idle" class="knd-avatar-picker">
                    <button type="button" class="knd-btn knd-btn--outline w-100" id="kd-avatar-dropdown-btn" aria-expanded="false">
                        <i class="fas fa-chevron-down me-1"></i>CHANGE UNIT
                    </button>
                    <div class="knd-search-wrap mt-2">
                        <i class="fas fa-search knd-search-icon"></i>
                        <input type="text" class="knd-search-input" id="kd-avatar-search" placeholder="Search unit designation..." autocomplete="off">
                    </div>
                    <div class="knd-picker-menu" id="kd-avatar-dropdown-menu" style="display:none;"></div>
                </div>
            </div>

            <!-- Stat readout -->
            <div class="knd-panel knd-stat-panel">
                <div class="knd-panel-topbar"></div>
                <div class="knd-panel-label">NEURAL READOUT</div>
                <div class="knd-stat-grid">
                    <div class="knd-stat-row">
                        <span class="knd-stat-key">USER XP</span>
                        <span class="knd-stat-val" id="kd-stat-xp">—</span>
                    </div>
                    <div class="knd-stat-row">
                        <span class="knd-stat-key">SYNC LEVEL</span>
                        <span class="knd-stat-val" id="kd-stat-level">—</span>
                    </div>
                    <div class="knd-stat-row">
                        <span class="knd-stat-key">UNIT</span>
                        <span class="knd-stat-val knd-stat-val--sm" id="kd-stat-avatar">—</span>
                    </div>
                    <div class="knd-stat-row">
                        <span class="knd-stat-key">UNIT KE</span>
                        <span class="knd-stat-val" id="kd-stat-ke">—</span>
                    </div>
                    <div class="knd-stat-row">
                        <span class="knd-stat-key">UNIT LVL</span>
                        <span class="knd-stat-val" id="kd-stat-avatar-level">—</span>
                    </div>
                    <div class="knd-stat-row">
                        <span class="knd-stat-key">RANK SCORE</span>
                        <span class="knd-stat-val" id="kd-stat-rank">—</span>
                    </div>
                    <div class="knd-stat-row">
                        <span class="knd-stat-key">POSITION</span>
                        <span class="knd-stat-val knd-stat-pos" id="kd-stat-position">—</span>
                    </div>
                </div>

                <div class="knd-bar-section mt-2">
                    <div class="knd-bar-label">
                        <span>USER SYNC</span>
                        <span id="kd-stat-xp-detail" class="knd-bar-label__val">—</span>
                    </div>
                    <div class="knd-bar-track">
                        <div class="knd-bar-fill knd-bar-fill--xp" id="kd-user-panel-progress" style="width:0%"></div>
                    </div>
                </div>
                <div class="knd-bar-section mt-1">
                    <div class="knd-bar-label">
                        <span>UNIT KE</span>
                        <span id="kd-stat-ke-detail" class="knd-bar-label__val">—</span>
                    </div>
                    <div class="knd-bar-track">
                        <div class="knd-bar-fill knd-bar-fill--ke" id="kd-avatar-panel-progress" style="width:0%"></div>
                    </div>
                </div>
            </div>

            <!-- Avatar grid (hidden, used by JS) -->
            <div id="kd-avatar-list" class="kd-avatar-grid kd-avatar-grid--fallback" style="display:none;"></div>
            <div id="kd-avatar-empty" class="knd-empty-state" style="display:none;">
                <i class="fas fa-unlink mb-2"></i><br>
                No units in inventory.<br>
                <span class="knd-empty-state__sub">Acquire avatars to initiate neural link.</span>
            </div>
            <div id="kd-aguacate-state" class="knd-aguacate-state" style="display:none;" role="alert">
                <div class="knd-aguacate-state__emoji" aria-hidden="true">🥑</div>
                <div class="knd-aguacate-state__title">AGUACATE</div>
                <p class="knd-aguacate-state__text">No Mind Wars portraits available for your inventory. You need an owned avatar linked to <strong>mw_avatars</strong> with a non-empty <strong>image</strong> field.</p>
                <div class="knd-aguacate-state__actions">
                    <a class="knd-btn knd-btn--primary" href="/games/mind-wars/lobby.php">Mind Wars Lobby</a>
                    <a class="knd-btn knd-btn--outline" href="/tools/cards/index.html">Avatar collection</a>
                </div>
            </div>

        </aside>

        <!-- ════════════════════════════════
             CENTER — Arena
        ════════════════════════════════ -->
        <main class="knd-duel-arena" id="kd-battle-card">

            <!-- STEP: IDLE ────────────────── -->
            <div id="kd-step-idle" class="knd-step-panel">
                <div class="knd-idle-display">
                    <div class="knd-idle-orb">
                        <div class="knd-idle-orb__ring r1"></div>
                        <div class="knd-idle-orb__ring r2"></div>
                        <div class="knd-idle-orb__ring r3"></div>
                        <div class="knd-idle-orb__core">
                            <span class="knd-idle-orb__glyph">⬡</span>
                        </div>
                    </div>
                    <div class="knd-idle-text">
                        <div class="knd-idle-text__title">NEURAL LINK SYSTEM</div>
                        <div class="knd-idle-text__sub">Select an active unit and configure neural parameters to begin cognitive combat.</div>
                    </div>
                    <!-- Fighters preview in idle -->
                    <div class="knd-idle-fighters" id="kd-battle-placeholder">
                        <div class="knd-idle-fighter">
                            <div class="knd-avatar-frame knd-avatar-frame--idle" id="kd-player-avatar-frame" data-level="1">
                                <img id="kd-player-avatar" alt="Unit">
                            </div>
                            <div class="knd-idle-fighter__name" id="kd-player-name">SELECT UNIT</div>
                        </div>
                        <div class="knd-idle-vs">VS</div>
                        <div class="knd-idle-fighter">
                            <div class="knd-enemy-avatar-wrap knd-avatar-frame knd-avatar-frame--idle knd-avatar-frame--enemy" id="kd-enemy-avatar">
                                <img id="kd-enemy-avatar-img" class="knd-enemy-img" style="display:none;" alt="Entity">
                                <span class="knd-enemy-fallback"><i class="fas fa-robot"></i></span>
                            </div>
                            <div class="knd-idle-fighter__name" id="kd-enemy-name">AWAITING...</div>
                        </div>
                    </div>
                </div>

                <div class="knd-idle-actions">
                    <button type="button" class="knd-btn knd-btn--primary knd-btn--launch" id="kd-open-category-btn" disabled>
                        <span class="knd-btn__glyph">⬡</span>
                        <span>INITIATE LINK</span>
                        <span class="knd-btn__sweep"></span>
                    </button>
                    <div class="knd-idle-hint" id="kd-idle-hint">Synchronize an avatar unit to proceed</div>
                </div>
            </div>

            <!-- STEP: CATEGORY ─────────────── -->
            <div id="kd-step-category" class="knd-step-panel" style="display:none;">
                <div class="knd-step-header">
                    <div class="knd-step-header__eyebrow">STEP 01 / 02</div>
                    <h4 class="knd-step-header__title">SELECT COGNITIVE DOMAIN</h4>
                    <p class="knd-step-header__sub">Choose the neural knowledge field for this engagement.</p>
                </div>
                <div id="kd-category-grid" class="knd-choice-grid"></div>
                <div class="knd-step-nav">
                    <button type="button" class="knd-btn knd-btn--ghost" id="kd-category-back">
                        <i class="fas fa-chevron-left me-1"></i>ABORT
                    </button>
                    <button type="button" class="knd-btn knd-btn--primary" id="kd-category-continue" disabled>
                        CONFIRM DOMAIN <i class="fas fa-chevron-right ms-1"></i>
                    </button>
                </div>
            </div>

            <!-- STEP: DIFFICULTY ───────────── -->
            <div id="kd-step-difficulty" class="knd-step-panel" style="display:none;">
                <div class="knd-step-header">
                    <div class="knd-step-header__eyebrow">STEP 02 / 02</div>
                    <h4 class="knd-step-header__title">SET INTERFERENCE LEVEL</h4>
                    <p class="knd-step-header__sub">Higher interference = greater neural complexity and amplified rewards.</p>
                </div>
                <div id="kd-difficulty-grid" class="knd-choice-grid knd-choice-grid--difficulty"></div>
                <div class="knd-step-nav">
                    <button type="button" class="knd-btn knd-btn--ghost" id="kd-difficulty-back">
                        <i class="fas fa-chevron-left me-1"></i>BACK
                    </button>
                    <button type="button" class="knd-btn knd-btn--primary" id="kd-start-btn" disabled>
                        <i class="fas fa-link me-1"></i>ENGAGE LINK
                    </button>
                </div>
            </div>

            <!-- STEP: BATTLE ───────────────── -->
            <div id="kd-step-battle" class="knd-step-panel" style="display:none;">

                <!-- Meta strip -->
                <div class="knd-battle-meta">
                    <span class="knd-meta-pill" id="kd-battle-category-pill">Domain</span>
                    <span class="knd-meta-pill knd-meta-pill--diff" id="kd-battle-difficulty-pill">Level</span>
                    <span class="knd-meta-counter" id="kd-question-meta">Query 1 / 5</span>
                </div>

                <!-- Fighter row -->
                <div class="knd-fighters-row">

                    <!-- Player fighter -->
                    <div class="knd-fighter knd-fighter--player">
                        <div class="knd-fighter__label">NEURAL AGENT</div>
                        <div class="knd-avatar-frame knd-avatar-frame--battle" id="kd-player-avatar-battle-frame" data-level="1">
                            <div class="knd-avatar-battle-ring"></div>
                            <img id="kd-player-avatar-battle" class="knd-fighter__img" alt="Agent">
                        </div>
                        <div class="knd-fighter__name" id="kd-player-name-battle">AGENT</div>
                        <!-- Link Stability (player HP) -->
                        <div class="knd-stability-wrap">
                            <div class="knd-stability-label">
                                <span>LINK STABILITY</span>
                                <span class="knd-stability-pct" id="kd-player-hp-pct">100%</span>
                            </div>
                            <div class="knd-stability-track knd-stability-track--player">
                                <div id="kd-player-hp-battle" class="knd-stability-fill knd-stability-fill--player" role="progressbar" style="width:100%;">100 HP</div>
                                <div class="knd-stability-shimmer"></div>
                            </div>
                        </div>
                    </div>

                    <!-- VS divider -->
                    <div class="knd-fighters-vs">
                        <div class="knd-vs-line"></div>
                        <div class="knd-vs-text">VS</div>
                        <div class="knd-vs-line"></div>
                    </div>

                    <!-- Enemy fighter -->
                    <div class="knd-fighter knd-fighter--enemy">
                        <div class="knd-fighter__label">NEURAL ENTITY</div>
                        <div class="knd-enemy-avatar-wrap knd-avatar-frame knd-avatar-frame--battle knd-avatar-frame--enemy" id="kd-enemy-avatar-battle">
                            <div class="knd-avatar-battle-ring knd-avatar-battle-ring--enemy"></div>
                            <img id="kd-enemy-avatar-battle-img" class="knd-enemy-img" style="display:none;" alt="Entity">
                            <span class="knd-enemy-fallback"><i class="fas fa-robot"></i></span>
                        </div>
                        <div class="knd-fighter__name" id="kd-enemy-name-battle">ENTITY</div>
                        <!-- Entity integrity (enemy HP) -->
                        <div class="knd-stability-wrap">
                            <div class="knd-stability-label">
                                <span>ENTITY INTEGRITY</span>
                                <span class="knd-stability-pct" id="kd-enemy-hp-pct">100%</span>
                            </div>
                            <div class="knd-stability-track knd-stability-track--enemy">
                                <div id="kd-enemy-hp-battle" class="knd-stability-fill knd-stability-fill--enemy" role="progressbar" style="width:100%;">100 HP</div>
                                <div class="knd-stability-shimmer"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Question interface -->
                <div class="knd-query-panel">
                    <div class="knd-query-panel__header">
                        <span class="knd-query-label">COGNITIVE QUERY</span>
                        <div class="knd-query-scan"></div>
                    </div>
                    <div class="knd-query-text" id="kd-question-text">Loading neural query...</div>
                </div>

                <!-- Answer options -->
                <div id="kd-options" class="knd-options-grid"></div>

                <!-- Feedback -->
                <div id="kd-feedback" class="knd-feedback" style="display:none;" role="status" aria-live="polite"></div>

            </div>

            <!-- STEP: RESULT ────────────────── -->
            <div id="kd-step-result" class="knd-step-panel" style="display:none;">

                <div class="knd-result-header" id="knd-result-header">
                    <div class="knd-result-glyph" id="knd-result-glyph">⬡</div>
                    <div class="knd-result-title" id="kd-result-title">LINK TERMINATED</div>
                    <div class="knd-result-subtitle" id="knd-result-subtitle">Processing neural output...</div>
                </div>

                <div class="knd-result-rewards" id="kd-result-rewards"></div>

                <div class="knd-progress-block">
                    <div class="knd-bar-label">
                        <span>USER SYNC LEVEL</span>
                        <span class="knd-bar-label__val" id="knd-user-progress-label">—</span>
                    </div>
                    <div class="knd-bar-track">
                        <div class="knd-bar-fill knd-bar-fill--xp" id="kd-user-progress" role="progressbar" style="width:0%"></div>
                    </div>
                </div>
                <div class="knd-progress-block mt-2">
                    <div class="knd-bar-label">
                        <span>UNIT KNOWLEDGE ENERGY</span>
                        <span class="knd-bar-label__val" id="knd-avatar-progress-label">—</span>
                    </div>
                    <div class="knd-bar-track">
                        <div class="knd-bar-fill knd-bar-fill--ke" id="kd-avatar-progress" role="progressbar" style="width:0%"></div>
                    </div>
                </div>

                <div class="knd-result-actions">
                    <button type="button" class="knd-btn knd-btn--primary" id="kd-play-again">
                        <i class="fas fa-rotate-right me-1"></i>RE-ENGAGE
                    </button>
                    <button type="button" class="knd-btn knd-btn--outline" id="kd-change-category">CHANGE DOMAIN</button>
                    <button type="button" class="knd-btn knd-btn--ghost" id="kd-change-avatar">CHANGE UNIT</button>
                </div>
            </div>

        </main>

    </div><!-- end grid -->
</div><!-- end shell -->
</section>

<?php if (!$embed): ?>
</div>
<?php
$mwShellGame = 'knowledge-duel';
require __DIR__ . '/mind-wars/lobby-partials/panels_right.php';
?>
</div>
<nav class="bottom-nav" aria-label="KND Games navigation">
  <a class="bnav-item" href="/games/mind-wars/lobby.php"><div class="bnav-icon">🏠</div><div class="bnav-label">LOBBY</div></a>
  <a class="bnav-item" href="/tools/cards/index.html"><div class="bnav-icon">⬡</div><div class="bnav-label">AVATARS</div></a>
  <a class="bnav-item bnav-item--neural-link" href="/games/knd-neural-link/drops.php" title="KND Neural Link"><div class="bnav-icon" aria-hidden="true">🧬</div><div class="bnav-label">NEURAL LINK</div></a>
  <a class="bnav-item" href="/games/mind-wars/lobby.php"><div class="bnav-icon">🏆</div><div class="bnav-label">RANKS</div></a>
  <a class="bnav-item" href="/games/mind-wars/lobby.php"><div class="bnav-icon">🎒</div><div class="bnav-label">BAG</div></a>
</nav>
</div>
<script>
window.MW_LOBBY_CSRF = <?php echo json_encode($csrfToken, JSON_UNESCAPED_UNICODE); ?>;
window.MW_LOBBY_INITIAL = <?php echo json_encode($L, JSON_UNESCAPED_UNICODE | (defined('JSON_INVALID_UTF8_SUBSTITUTE') ? JSON_INVALID_UTF8_SUBSTITUTE : 0)); ?>;
window.MW_SHELL_GAME = 'knowledge-duel';
</script>
<script src="/games/mind-wars/mw-avatar-card.js?v=<?php echo $vMwJ; ?>"></script>
<script src="/games/mind-wars/kd-lobby-shell.js?v=<?php echo $vKs; ?>"></script>
<?php endif; ?>

<?php if ($embed): ?>
<script src="/assets/js/navigation-extend.js"></script>
<script src="/assets/js/knowledge-duel-audio.js?v=<?php echo file_exists(__DIR__ . '/../assets/js/knowledge-duel-audio.js') ? filemtime(__DIR__ . '/../assets/js/knowledge-duel-audio.js') : time(); ?>"></script>
<script>window.KnowledgeDuelConfig = { csrfToken: <?php echo json_encode($csrfToken); ?> };</script>
<script src="/assets/js/knowledge-duel.js?v=<?php echo file_exists(__DIR__ . '/../assets/js/knowledge-duel.js') ? filemtime(__DIR__ . '/../assets/js/knowledge-duel.js') : time(); ?>" defer></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</div></body></html>
<?php exit; endif; ?>

<script src="/assets/js/navigation-extend.js"></script>
<script src="/assets/js/knowledge-duel-audio.js?v=<?php echo file_exists(__DIR__ . '/../assets/js/knowledge-duel-audio.js') ? filemtime(__DIR__ . '/../assets/js/knowledge-duel-audio.js') : time(); ?>"></script>
<script>
window.KnowledgeDuelConfig = {
    csrfToken: <?php echo json_encode($csrfToken); ?>
};
</script>
<script src="/assets/js/knowledge-duel.js?v=<?php echo file_exists(__DIR__ . '/../assets/js/knowledge-duel.js') ? filemtime(__DIR__ . '/../assets/js/knowledge-duel.js') : time(); ?>" defer></script>
</body>
</html>

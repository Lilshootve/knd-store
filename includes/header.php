<?php
// Header común de KND Store

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/favicon_links.php';

function generateHeader($title = 'KND Store - Tienda Galáctica', $description = 'Tienda digital de servicios y productos tecnológicos con temática gamer y cósmica', $extraHead = '', $lightweight = false) {
    $current_page = basename($_SERVER['PHP_SELF']);

    $favicon = generateFaviconLinks();

    $header = '<!DOCTYPE html>' . "\n";
    $header .= '<html lang="en" data-bs-theme="dark">' . "\n";
    $header .= '<head>' . "\n";
    $header .= '    <meta charset="UTF-8">' . "\n";
    $header .= '    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">' . "\n";
    $header .= '    <title>' . htmlspecialchars($title) . '</title>' . "\n";
    $header .= '    <meta name="description" content="' . htmlspecialchars($description) . '">' . "\n";
    $header .= '    <meta name="robots" content="index, follow">' . "\n";
    $header .= '    <meta name="theme-color" content="#010508">' . "\n";
    $header .= '    <meta name="author" content="KND Store">' . "\n";
    $header .= '    <meta name="keywords" content="knd, store, gaming, technology, digital services, apparel, streetwear, ecommerce">' . "\n";
    if ($extraHead) {
        $header .= $extraHead;
    }
    $header .= $favicon;
    $header .= '    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">' . "\n";
    $header .= '    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css?v=652">' . "\n";
    $header .= '    <link rel="stylesheet" href="/assets/css/style.css?v=' . @filemtime(__DIR__ . '/../assets/css/style.css') . '">' . "\n";
    $header .= '    <link rel="stylesheet" href="/assets/css/levels.css?v=' . (file_exists(__DIR__ . '/../assets/css/levels.css') ? filemtime(__DIR__ . '/../assets/css/levels.css') : 0) . '">' . "\n";
    $header .= '    <link rel="stylesheet" href="/assets/css/knd-ui.css?v=' . (file_exists(__DIR__ . '/../assets/css/knd-ui.css') ? filemtime(__DIR__ . '/../assets/css/knd-ui.css') : 0) . '">' . "\n";
    $header .= '    <link rel="stylesheet" href="/assets/css/mobile-optimization.css">' . "\n";
    $header .= '    <link rel="stylesheet" href="/assets/css/header-dynamic.css?v=' . (file_exists(__DIR__ . '/../assets/css/header-dynamic.css') ? filemtime(__DIR__ . '/../assets/css/header-dynamic.css') : 0) . '">' . "\n";
    $header .= '    <link rel="stylesheet" href="/assets/css/knd-skin.css?v=' . (file_exists(__DIR__ . '/../assets/css/knd-skin.css') ? filemtime(__DIR__ . '/../assets/css/knd-skin.css') : 0) . '">' . "\n";
    $phpSelf = $_SERVER['PHP_SELF'] ?? '';
    $isAdmin = (strpos($phpSelf, '/admin') !== false || strpos($phpSelf, 'admin/') === 0);
    if ($isAdmin) {
        $header .= '    <link rel="stylesheet" href="/assets/css/admin.css?v=' . (file_exists(__DIR__ . '/../assets/css/admin.css') ? filemtime(__DIR__ . '/../assets/css/admin.css') : 0) . '">' . "\n";
    }
    $header .= '    <link rel="manifest" href="/assets/images/site.webmanifest">' . "\n";
    $header .= '    <link rel="preload" href="/assets/js/knd-starfield.js" as="script">' . "\n";
    $header .= '    <link rel="preload" href="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js" as="script">' . "\n";
    $header .= '    <script src="/assets/js/knd-toast.js" defer></script>' . "\n";
    $header .= '</head>' . "\n";
    $bodyClass = 'knd-skin';
    if ($lightweight) {
        $bodyClass .= ' arena-info-page';
    }
    if ($current_page === 'auth.php') {
        $bodyClass .= ' auth-page';
    }
    if ($isAdmin) {
        $bodyClass .= ' admin-page';
    }
    $header .= '<body class="' . $bodyClass . '">' . "\n";
    if (!$lightweight && !$isAdmin) {
        $header .= '<div id="bg" aria-hidden="true"><canvas id="bg-canvas" width="300" height="300"></canvas></div>' . "\n";
    }

    return $header;
}

function generateNavigation() {
    $current_page = basename($_SERVER['PHP_SELF']);
    $phpSelf = $_SERVER['PHP_SELF'] ?? '';

    $arenaActive = in_array($current_page, ['knd-arena.php', 'death-roll-lobby.php', 'death-roll-game.php', 'above-under.php', 'leaderboard.php', 'knowledge-duel.php'], true)
        || strpos($phpSelf, '/games/mind-wars/lobby.php') !== false
        || strpos($phpSelf, '/games/mind-wars/mind-wars-arena.php') !== false;
    $labsActive = in_array($current_page, ['knd-labs.php', 'labs-next.php', 'ai-tools.php', 'labs-3d-lab.php', 'labs-text-to-image.php', 'labs-upscale.php', 'labs-consistency.php', 'labs-character-lab.php', 'labs-texture-lab.php', 'labs-jobs.php', 'labs-job.php'], true)
        || strpos($phpSelf, '/labs/') !== false;

    $ecosystemActive = ($current_page === 'ecosystem.php');

    $accountActive = in_array($current_page, ['order.php', 'track-order.php', 'auth.php', 'support-credits.php', 'rewards.php', 'my-profile.php'], true);

    $drLoggedIn = !empty($_SESSION['dr_user_id']);
    $drUsername = $drLoggedIn ? htmlspecialchars($_SESSION['dr_username'] ?? '') : '';

    $creditsBadgeHtml = '';
    $levelBadgeHtml = '';
    if ($drLoggedIn) {
        $scCacheTTL = 60;
        $scCache = $_SESSION['sc_badge_cache'] ?? null;
        $scAvailable = 0;
        if ($scCache && isset($scCache['ts']) && (time() - $scCache['ts']) < $scCacheTTL) {
            $scAvailable = (int) $scCache['available'];
        } else {
            try {
                $scIncPath = __DIR__ . '/support_credits.php';
                if (file_exists($scIncPath)) {
                    require_once $scIncPath;
                    $scPdo = getDBConnection();
                    if ($scPdo) {
                        $scUid = (int) $_SESSION['dr_user_id'];
                        release_available_points_if_due($scPdo, $scUid);
                        expire_points_if_due($scPdo, $scUid);
                        $scAvailable = get_available_points($scPdo, $scUid);
                    }
                }
            } catch (\Throwable $e) {
                $scAvailable = 0;
            }
            $_SESSION['sc_badge_cache'] = ['ts' => time(), 'available' => $scAvailable];
        }
        $creditsBadgeHtml = '<a href="/support-credits.php" class="sc-nav-badge" title="' . t('nav.credits_badge_tooltip', 'Available KND Points') . '">'
            . '<i class="fas fa-coins"></i> ' . ($scAvailable > 0 ? number_format($scAvailable) : '0')
            . '</a>';

        try {
            $profilePath = __DIR__ . '/knd_profile.php';
            if (file_exists($profilePath)) {
                require_once $profilePath;
                $xpPdo = getDBConnection();
                if ($xpPdo) {
                    $xb = get_xp_badge_data($xpPdo, (int) $_SESSION['dr_user_id']);
                    $tip = t('nav.level', 'Level') . ': ' . $xb['level'] . '/30 · XP: ' . number_format($xb['xp']);
                    if ($xb['is_max']) {
                        $tip .= ' · ' . t('profile.max_level', 'MAX LEVEL');
                    } else {
                        $tip .= ' · ' . t('profile.next', 'XP to next level') . ': ' . number_format($xb['next_in']) . ' · ' . t('nav.xp_progress', 'Progress') . ': ' . $xb['pct'] . '%';
                    }
                    $levelBadgeHtml = '<a href="/my-profile.php" class="lvl-badge" data-level="' . (int) $xb['level'] . '" title="' . htmlspecialchars($tip) . '">Lvl ' . $xb['level'] . '</a>';
                }
            }
        } catch (\Throwable $e) {
            $levelBadgeHtml = '';
        }
    }

    $ctaHref = $drLoggedIn ? '/games/mind-wars/lobby.php' : '/auth.php';
    $ctaLabel = $drLoggedIn ? t('nav.arena', 'KND Arena') : t('nav.enter', 'Enter');

    $nav = '<header class="topbar site-header" id="site-header" role="banner">' . "\n";
    $nav .= '  <div class="topbar-inner">' . "\n";
    $nav .= '    <a class="tb-logo" href="/index.php">' . "\n";
    $nav .= '      <span class="tb-hex" aria-hidden="true"><svg viewBox="0 0 30 30" fill="none" stroke="currentColor" stroke-width="1.5"><polygon points="15,2 28,9 28,21 15,28 2,21 2,9"/></svg></span>' . "\n";
    $nav .= '      <span>KND</span>' . "\n";
    $nav .= '    </a>' . "\n";

    $nav .= '    <nav class="tb-nav-cluster" aria-label="Primary">' . "\n";
    $nav .= '      <a class="tb-link' . ($current_page === 'index.php' ? ' active' : '') . '" href="/index.php">' . t('nav.home') . '</a>' . "\n";
    $nav .= '      <a class="tb-link' . ($arenaActive ? ' active' : '') . '" href="/games/mind-wars/lobby.php">' . t('nav.arena', 'KND Arena') . '</a>' . "\n";
    $nav .= '      <a class="tb-link' . ($labsActive ? ' active' : '') . '" href="/labs">' . t('nav.labs', 'KND Labs') . '</a>' . "\n";
    $nav .= '      <a class="tb-link' . ($ecosystemActive ? ' active' : '') . '" href="/ecosystem.php">' . t('nav.ecosystem', 'Ecosystem') . '</a>' . "\n";
    $nav .= '      <div class="dropdown tb-more-wrap">' . "\n";
    $nav .= '        <button type="button" class="tb-link dropdown-toggle border-0" data-bs-toggle="dropdown" aria-expanded="false" id="kndNavMoreToggle">' . t('nav.more', 'More') . '</button>' . "\n";
    $nav .= '        <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end" aria-labelledby="kndNavMoreToggle">' . "\n";
    $nav .= '          <li><a class="dropdown-item' . ($current_page === 'about.php' ? ' active' : '') . '" href="/about.php">' . t('nav.about') . '</a></li>' . "\n";
    $nav .= '          <li><a class="dropdown-item' . ($current_page === 'contact.php' ? ' active' : '') . '" href="/contact.php">' . t('nav.contact') . '</a></li>' . "\n";
    $nav .= '        </ul>' . "\n";
    $nav .= '      </div>' . "\n";
    $nav .= '    </nav>' . "\n";

    $nav .= '    <div class="tb-end">' . "\n";
    if ($levelBadgeHtml || $creditsBadgeHtml) {
        $nav .= '      <div class="knd-user-badges d-none d-lg-flex">' . $levelBadgeHtml . $creditsBadgeHtml . '</div>' . "\n";
    }
    $nav .= '      <div class="d-none d-lg-flex align-items-center gap-2 knd-dropdown">' . "\n";
    $nav .= '        <div class="knd-user-block">' . "\n";
    $nav .= '          <a id="ordersDropdownToggle" class="knd-dropdown-toggle' . ($accountActive ? ' active' : '') . '" href="javascript:void(0)" role="button" aria-expanded="false">' . "\n";
    $nav .= '            <i class="fas fa-user-circle me-1"></i>';
    $nav .= $drLoggedIn ? $drUsername : t('nav.my_account', 'My Account');
    $nav .= '            <span id="order-count" class="badge rounded-pill bg-primary ms-1" style="display:none;min-width:20px;justify-content:center;align-items:center;"></span>' . "\n";
    $nav .= '            <i class="fas fa-chevron-down knd-dropdown-arrow ms-1"></i>' . "\n";
    $nav .= '          </a>' . "\n";
    $nav .= '        </div>' . "\n";
    $nav .= '        <div id="ordersDropdownMenu" class="knd-dropdown-menu">' . "\n";
    $nav .= '          <span class="knd-dropdown-hint">' . t('nav.orders_hint', 'Orders & account') . '</span>' . "\n";
    $nav .= '          <a class="knd-dropdown-item' . ($current_page === 'order.php' ? ' active' : '') . '" href="/order.php"><i class="fas fa-cart-shopping me-2"></i>' . t('nav.my_orders', 'My Orders') . '</a>' . "\n";
    $nav .= '          <a class="knd-dropdown-item' . ($current_page === 'track-order.php' ? ' active' : '') . '" href="/track-order.php"><i class="fas fa-magnifying-glass me-2"></i>' . t('nav.track_order', 'Track Order') . '</a>' . "\n";
    $nav .= '          <a class="knd-dropdown-item" href="/contact.php"><i class="fas fa-headset me-2"></i>' . t('nav.support', 'Support') . '</a>' . "\n";
    $nav .= '          <div style="border-top:1px solid rgba(255,255,255,0.1);margin:6px 0;"></div>' . "\n";
    if ($drLoggedIn) {
        $nav .= '          <a class="knd-dropdown-item' . ($current_page === 'my-profile.php' ? ' active' : '') . '" href="/my-profile.php"><i class="fas fa-user-shield me-2"></i>' . t('nav.profile', 'My Profile') . '</a>' . "\n";
        $nav .= '          <a class="knd-dropdown-item' . ($current_page === 'support-credits.php' ? ' active' : '') . '" href="/support-credits.php"><i class="fas fa-coins me-2"></i>' . t('nav.credits', 'Credits') . '</a>' . "\n";
        $nav .= '          <a class="knd-dropdown-item' . ($current_page === 'rewards.php' ? ' active' : '') . '" href="/rewards.php"><i class="fas fa-gift me-2"></i>' . t('nav.rewards', 'Rewards') . '</a>' . "\n";
        $nav .= '          <div style="border-top:1px solid rgba(255,255,255,0.1);margin:6px 0;"></div>' . "\n";
        $nav .= '          <a class="knd-dropdown-item" href="/logout.php"><i class="fas fa-sign-out-alt me-2"></i>' . t('nav.logout', 'Logout') . '</a>' . "\n";
    } else {
        $nav .= '          <a class="knd-dropdown-item' . ($current_page === 'auth.php' ? ' active' : '') . '" href="/auth.php"><i class="fas fa-sign-in-alt me-2"></i>' . t('nav.login', 'Login') . ' / ' . t('nav.register', 'Register') . '</a>' . "\n";
    }
    $nav .= '        </div>' . "\n";
    $nav .= '      </div>' . "\n";
    // Logged-in: KND Arena is already in tb-nav-cluster — avoid duplicate CTA beside account/profile.
    if (!$drLoggedIn) {
        $nav .= '      <a class="tb-cta d-none d-lg-inline-flex" href="' . htmlspecialchars($ctaHref) . '">' . htmlspecialchars($ctaLabel) . '</a>' . "\n";
    }

    $nav .= '      <button class="tb-burger d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#kndNavOffcanvas" aria-controls="kndNavOffcanvas" aria-label="Menu">' . "\n";
    $nav .= '        <i class="fas fa-bars"></i>' . "\n";
    $nav .= '      </button>' . "\n";
    $nav .= '    </div>' . "\n";
    $nav .= '  </div>' . "\n";
    $nav .= '</header>' . "\n";

    $nav .= '<div class="offcanvas offcanvas-end text-bg-dark knd-nav-offcanvas" tabindex="-1" id="kndNavOffcanvas" aria-labelledby="kndNavOffcanvasLabel">' . "\n";
    $nav .= '  <div class="offcanvas-header">' . "\n";
    $nav .= '    <h5 class="offcanvas-title" id="kndNavOffcanvasLabel" style="font-family:var(--FD);letter-spacing:3px;">KND</h5>' . "\n";
    $nav .= '    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>' . "\n";
    $nav .= '  </div>' . "\n";
    $nav .= '  <div class="offcanvas-body">' . "\n";
    if ($levelBadgeHtml || $creditsBadgeHtml) {
        $nav .= '    <div class="knd-user-badges d-flex flex-wrap gap-2 mb-3">' . $levelBadgeHtml . $creditsBadgeHtml . '</div>' . "\n";
    }
    $nav .= '    <nav class="knd-movelinks d-flex flex-column" aria-label="Mobile">' . "\n";
    $nav .= '      <a href="/index.php">' . t('nav.home') . '</a>' . "\n";
    $nav .= '      <a href="/games/mind-wars/lobby.php">' . t('nav.arena', 'KND Arena') . '</a>' . "\n";
    $nav .= '      <a href="/labs">' . t('nav.labs', 'KND Labs') . '</a>' . "\n";
    $nav .= '      <a href="/ecosystem.php">' . t('nav.ecosystem', 'Ecosystem') . '</a>' . "\n";
    $nav .= '      <a href="/about.php">' . t('nav.about') . '</a>' . "\n";
    $nav .= '      <a href="/contact.php">' . t('nav.contact') . '</a>' . "\n";
    $nav .= '      <a href="/order.php">' . t('nav.my_orders', 'My Orders') . '</a>' . "\n";
    $nav .= '      <a href="/track-order.php">' . t('nav.track_order', 'Track Order') . '</a>' . "\n";
    $nav .= '      <a href="/my-profile.php">' . t('nav.profile', 'My Profile') . '</a>' . "\n";
    if (!$drLoggedIn) {
        $nav .= '      <a href="' . htmlspecialchars($ctaHref) . '">' . htmlspecialchars($ctaLabel) . '</a>' . "\n";
    }
    if ($drLoggedIn) {
        $nav .= '      <a href="/logout.php">' . t('nav.logout', 'Logout') . '</a>' . "\n";
    } else {
        $nav .= '      <a href="/auth.php">' . t('nav.login', 'Login') . '</a>' . "\n";
    }
    $nav .= '    </nav>' . "\n";
    $nav .= '  </div>' . "\n";
    $nav .= '</div>' . "\n";

    return $nav;
}

/**
 * Fixed admin top bar (call after generateHeader on admin pages when logged in).
 */
function generateAdminBar() {
    if (empty($_SESSION['admin_logged_in'])) {
        return '';
    }
    $u = htmlspecialchars($_SESSION['admin_username'] ?? 'Admin', ENT_QUOTES, 'UTF-8');
    $h = '<header class="knd-admin-topbar" role="navigation">' . "\n";
    $h .= '  <div class="knd-admin-topbar-inner">' . "\n";
    $h .= '    <a class="knd-admin-tb-logo" href="/"><span class="knd-admin-tb-hex" aria-hidden="true">⬡</span> KND</a>' . "\n";
    $h .= '    <span class="knd-admin-tb-title">Admin</span>' . "\n";
    $h .= '    <span class="knd-admin-tb-user">' . $u . '</span>' . "\n";
    $h .= '    <a class="knd-admin-tb-link" href="/admin/">Dashboard</a>' . "\n";
    $h .= '    <a class="knd-admin-tb-cta" href="/admin/?logout=1">Logout</a>' . "\n";
    $h .= '  </div>' . "\n";
    $h .= '</header>' . "\n";
    return $h;
}

// Función para generar el panel de personalización de colores
function generateColorPanel() {
    $panel = '<!-- Panel de Personalización de Colores -->' . "\n";
    $panel .= '<div class="color-panel-toggle" id="colorPanelToggle">' . "\n";
    $panel .= '    <i class="fas fa-palette"></i>' . "\n";
    $panel .= '</div>' . "\n";
    $panel .= '<div class="color-panel-overlay" id="colorPanelOverlay"></div>' . "\n";
    $panel .= '<div class="color-panel-sidebar" id="colorPanelSidebar">' . "\n";
    $panel .= '    <div class="color-panel-header">' . "\n";
    $panel .= '        <h3><i class="fas fa-magic me-2"></i>Customize Colors</h3>' . "\n";
    $panel .= '    </div>' . "\n";
    $panel .= '    <div class="color-panel-content">' . "\n";
    $panel .= '        <div class="color-theme active" data-theme="galactic-blue">' . "\n";
    $panel .= '            <h4>Galactic Blue</h4>' . "\n";
    $panel .= '            <div class="color-preview">' . "\n";
    $panel .= '                <div class="color-swatch" style="background: #259cae;"></div>' . "\n";
    $panel .= '                <div class="color-swatch" style="background: #ae2565;"></div>' . "\n";
    $panel .= '                <div class="color-swatch" style="background: #16213e;"></div>' . "\n";
    $panel .= '            </div>' . "\n";
    $panel .= '            <p>Classic neon blue with electric purple.</p>' . "\n";
    $panel .= '        </div>' . "\n";
    $panel .= '        <div class="color-theme" data-theme="cyber-green">' . "\n";
    $panel .= '            <h4>Cyber Green</h4>' . "\n";
    $panel .= '            <div class="color-preview">' . "\n";
    $panel .= '                <div class="color-swatch" style="background: #66bf5a;"></div>' . "\n";
    $panel .= '                <div class="color-swatch" style="background: #70c4e1;"></div>' . "\n";
    $panel .= '                <div class="color-swatch" style="background: #69bab0;"></div>' . "\n";
    $panel .= '            </div>' . "\n";
    $panel .= '            <p>Organic tech with a hacker-futurist vibe.</p>' . "\n";
    $panel .= '        </div>' . "\n";
    $panel .= '        <div class="color-theme" data-theme="nature-green">' . "\n";
    $panel .= '            <h4>Nature Green</h4>' . "\n";
    $panel .= '            <div class="color-preview">' . "\n";
    $panel .= '                <div class="color-swatch" style="background: #c1eeaf;"></div>' . "\n";
    $panel .= '                <div class="color-swatch" style="background: #6ba166;"></div>' . "\n";
    $panel .= '                <div class="color-swatch" style="background: #145926;"></div>' . "\n";
    $panel .= '            </div>' . "\n";
    $panel .= '            <p>Organic green, balanced and calm.</p>' . "\n";
    $panel .= '        </div>' . "\n";
    $panel .= '        <div class="color-theme" data-theme="fire-red">' . "\n";
    $panel .= '            <h4>Solar Fire</h4>' . "\n";
    $panel .= '            <div class="color-preview">' . "\n";
    $panel .= '                <div class="color-swatch" style="background: #b43b6a;"></div>' . "\n";
    $panel .= '                <div class="color-swatch" style="background: #e67635;"></div>' . "\n";
    $panel .= '                <div class="color-swatch" style="background: #bfce17;"></div>' . "\n";
    $panel .= '            </div>' . "\n";
    $panel .= '            <p>Intense energy with solar and neon accents.</p>' . "\n";
    $panel .= '        </div>' . "\n";
    $panel .= '        <div class="color-theme" data-theme="golden-sun">' . "\n";
    $panel .= '            <h4>Golden Sun</h4>' . "\n";
    $panel .= '            <div class="color-preview">' . "\n";
    $panel .= '                <div class="color-swatch" style="background: #ffea00;"></div>' . "\n";
    $panel .= '                <div class="color-swatch" style="background: #bed322;"></div>' . "\n";
    $panel .= '                <div class="color-swatch" style="background: #321f22;"></div>' . "\n";
    $panel .= '            </div>' . "\n";
    $panel .= '            <p>Cosmic elegance, solar luxury, deep contrast.</p>' . "\n";
    $panel .= '        </div>' . "\n";
    $panel .= '        <div class="color-theme" data-theme="neon-pink">' . "\n";
    $panel .= '            <h4>Neon Pearl</h4>' . "\n";
    $panel .= '            <div class="color-preview">' . "\n";
    $panel .= '                <div class="color-swatch" style="background: #dca1e3;"></div>' . "\n";
    $panel .= '                <div class="color-swatch" style="background: #ffc3a8;"></div>' . "\n";
    $panel .= '                <div class="color-swatch" style="background: #e6ffc9;"></div>' . "\n";
    $panel .= '            </div>' . "\n";
    $panel .= '            <p>Soft tones, elegant futurism, premium look.</p>' . "\n";
    $panel .= '        </div>' . "\n";
    $panel .= '        <div class="color-theme" data-theme="ice-blue">' . "\n";
    $panel .= '            <h4>Ice Blue</h4>' . "\n";
    $panel .= '            <div class="color-preview">' . "\n";
    $panel .= '                <div class="color-swatch" style="background: #07eef2;"></div>' . "\n";
    $panel .= '                <div class="color-swatch" style="background: #24d2db;"></div>' . "\n";
    $panel .= '                <div class="color-swatch" style="background: #000000;"></div>' . "\n";
    $panel .= '            </div>' . "\n";
    $panel .= '            <p>Cyan blue for a glacial feel.</p>' . "\n";
    $panel .= '        </div>' . "\n";
    $panel .= '    </div>' . "\n";
    $panel .= '</div>' . "\n";

    return $panel;
}

?>

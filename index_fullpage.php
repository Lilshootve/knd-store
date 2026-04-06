<?php
require_once __DIR__ . '/config/bootstrap.php';
// Experimental fullpage home (no cambios sobre index.php actual).

require_once BASE_PATH . '/includes/session.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/header.php';
require_once BASE_PATH . '/includes/footer.php';
require_once BASE_PATH . '/includes/products-data.php';

// Galería reutilizando la lógica del home actual
$galleryDir = __DIR__ . '/assets/images/gallery';
$allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
$homeGalleryImages = [];
if (is_dir($galleryDir) && is_readable($galleryDir)) {
    $files = @scandir($galleryDir);
    if ($files !== false) {
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            $path = $galleryDir . DIRECTORY_SEPARATOR . $file;
            if (!is_file($path)) continue;
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (in_array($ext, $allowedExt, true)) {
                $homeGalleryImages[] = '/assets/images/gallery/' . rawurlencode($file);
            }
        }
    }
}
sort($homeGalleryImages);
$homeGalleryImages = array_slice($homeGalleryImages, 0, 16);

// Assets específicos de este home
$fpCss = __DIR__ . '/assets/css/home-fullpage.css';
$fpJs  = __DIR__ . '/assets/js/home-fullpage.js';
$fpVer = ($isLocal ?? false) ? time() : (file_exists($fpCss) ? filemtime($fpCss) : time());
$extraHead  = '<link rel="stylesheet" href="/assets/css/home-fullpage.css?v=' . $fpVer . '">';
$extraHead .= '<script src="/assets/js/home-fullpage.js?v=' . $fpVer . '" defer></script>';

echo generateHeader(t('nav.home'), t('meta.default_description'), $extraHead);
?>

<div id="particles-bg"></div>

<?php echo generateNavigation(); ?>

<!-- navegación por puntos (desktop) -->
<div class="fp-dots" aria-hidden="true">
  <button class="fp-dot is-active" data-fp-target="#fp-hero"><span></span></button>
  <button class="fp-dot" data-fp-target="#fp-labs"><span></span></button>
  <button class="fp-dot" data-fp-target="#fp-gallery"><span></span></button>
  <button class="fp-dot" data-fp-target="#fp-arena"><span></span></button>
  <button class="fp-dot" data-fp-target="#fp-services"><span></span></button>
  <button class="fp-dot" data-fp-target="#fp-apparel"><span></span></button>
</div>

<main class="fp-main">

    <!-- 1. Hero -->
    <section id="fp-hero" class="fp-section fp-hero">
        <div class="fp-section-inner">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-lg-6">
                        <h1 class="hero-title mb-3">
                            <span class="text-gradient">Build Smarter.<br>Perform Better.</span>
                        </h1>
                        <p class="hero-subtitle mb-4">
                            Digital performance services and creative assets for modern builders worldwide.
                        </p>
                        <div class="d-flex flex-wrap gap-3">
                            <a href="#fp-services" class="btn btn-primary btn-lg">
                                <i class="fas fa-bolt me-2"></i>Explore Services
                            </a>
                            <a href="#fp-labs" class="btn btn-outline-neon btn-lg">
                                <i class="fas fa-microscope me-2"></i>Enter KND Labs
                            </a>
                            <a href="/apparel.php" class="btn btn-outline-light btn-lg">
                                <i class="fas fa-tshirt me-2"></i>View Apparel
                            </a>
                        </div>
                    </div>
                    <div class="col-lg-6 text-center mt-4 mt-lg-0">
                        <div class="hero-image">
                            <img src="/assets/images/knd-logo.png" alt="KND Store" class="img-fluid hero-logo">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 2. KND Labs -->
    <section id="fp-labs" class="fp-section fp-labs">
        <div class="fp-section-inner">
            <div class="container">
                <div class="row g-4 align-items-center">
                    <div class="col-lg-6">
                        <p class="home-labs-eyebrow"><?php echo t('nav.labs', 'KND Labs'); ?></p>
                        <h2 class="home-labs-headline mb-3">
                            <span class="glow-text">Create assets in minutes.</span>
                        </h2>
                        <p class="home-labs-desc mb-4">
                            Generate images, upscale, create characters and textures, or turn images into 3D —
                            all powered by AI. Fast, creative, and built for makers.
                        </p>
                        <div class="home-labs-ctas">
                            <a href="/labs" class="btn btn-neon-primary btn-lg">
                                <i class="fas fa-microscope me-2"></i><?php echo t('arena.enter', 'Enter'); ?> KND Labs
                            </a>
                            <a href="/gallery.php" class="btn btn-outline-neon btn-lg">
                                <i class="fas fa-images me-2"></i><?php echo t('home.gallery.view_gallery', 'View Gallery'); ?>
                            </a>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="home-labs-visual">
                            <div class="home-labs-visual-inner">
                                <div class="home-labs-visual-glow" aria-hidden="true"></div>
                                <div class="home-labs-visual-icon">
                                    <i class="fas fa-microscope" aria-hidden="true"></i>
                                </div>
                                <div class="home-labs-visual-card">
                                    <strong>Text-to-Image</strong> · Upscale · Character Lab · Texture Lab · <strong>3D Lab</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 3. Gallery -->
    <section id="fp-gallery" class="fp-section fp-gallery">
        <div class="fp-section-inner">
            <div class="container-fluid px-lg-5">
                <div class="row mb-4">
                    <div class="col-lg-6">
                        <p class="home-gallery-eyebrow"><?php echo t('home.gallery.eyebrow', 'Visual Showcase'); ?></p>
                        <h2 class="home-gallery-preview-title mb-2"><?php echo t('home.gallery.title', 'Curated visuals'); ?></h2>
                        <p class="home-gallery-preview-desc mb-0">
                            A fullscreen grid of visuals from the KND ecosystem. Explore the full gallery for wallpapers and assets.
                        </p>
                    </div>
                    <div class="col-lg-6 text-lg-end mt-3 mt-lg-0">
                        <a href="/gallery.php" class="btn btn-outline-neon">
                            <i class="fas fa-th-large me-2"></i><?php echo t('home.gallery.cta', 'Open full gallery'); ?>
                        </a>
                    </div>
                </div>
                <?php if (!empty($homeGalleryImages)): ?>
                    <div class="fp-gallery-grid">
                        <?php foreach ($homeGalleryImages as $imgSrc):
                            $srcEsc = htmlspecialchars($imgSrc, ENT_QUOTES, 'UTF-8');
                            $alt = basename(parse_url($imgSrc, PHP_URL_PATH));
                            $alt = pathinfo($alt, PATHINFO_FILENAME);
                            $alt = htmlspecialchars(preg_replace('/[-_]/', ' ', $alt), ENT_QUOTES, 'UTF-8');
                        ?>
                            <a href="/gallery.php" aria-label="<?php echo $alt; ?>">
                                <img src="<?php echo $srcEsc; ?>" alt="<?php echo $alt; ?>" loading="lazy">
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- 4. KND Arena -->
    <section id="fp-arena" class="fp-section fp-arena">
        <div class="fp-section-inner">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-10">
                        <div class="glass-card-neon p-4 p-md-5 lastroll-promo-card position-relative overflow-hidden">
                            <span class="badge lastroll-beta-badge">BETA</span>
                            <div class="row align-items-center g-4">
                                <div class="col-md-7">
                                    <h2 class="glow-text mb-2">
                                        <i class="fas fa-gamepad me-2"></i><?php echo t('home.games.title', 'KND Games'); ?>
                                    </h2>
                                    <p class="text-white-50 mb-3">
                                        <?php echo t('home.games.subtitle', 'Play KND LastRoll 1v1, KND Insight, and upcoming modes inside KND Arena.'); ?>
                                    </p>
                                    <ul class="lastroll-bullets mb-4">
                                        <li><i class="fas fa-dice-d20 me-2"></i><?php echo t('home.games.bullet1', 'KND LastRoll — 1v1 Death Roll with real-time rooms'); ?></li>
                                        <li><i class="fas fa-eye me-2"></i><?php echo t('home.games.bullet2', 'KND Insight — predict the number, win KND Points'); ?></li>
                                        <li><i class="fas fa-box-open me-2"></i><?php echo t('home.games.bullet_drop', 'KND Drop Chamber — open capsules, discover seasonal loot'); ?></li>
                                        <li><i class="fas fa-trophy me-2"></i><?php echo t('home.games.bullet3', 'Earn XP, climb the leaderboard, collect seasonal badges'); ?></li>
                                    </ul>
                                    <div class="d-flex flex-wrap gap-3">
                                        <a href="/arena" class="btn btn-neon-primary btn-lg">
                                            <i class="fas fa-play me-2"></i><?php echo t('home.games.enter_arena', 'Enter KND Arena'); ?>
                                        </a>
                                        <a href="/how-knd-arena-works" class="btn btn-outline-light">
                                            <i class="fas fa-info-circle me-2"></i><?php echo t('home.games.how', 'How it works'); ?>
                                        </a>
                                    </div>
                                </div>
                                <div class="col-md-5 text-center">
                                    <div class="lastroll-dice-hero">
                                        <svg width="140" height="140" viewBox="0 0 120 120" aria-hidden="true">
                                            <rect x="10" y="10" width="100" height="100" rx="20" fill="rgba(10,15,30,0.6)" stroke="rgba(37,156,174,0.35)" stroke-width="2"/>
                                            <rect x="16" y="16" width="88" height="88" rx="16" fill="rgba(37,156,174,0.04)"/>
                                            <text x="60" y="72" text-anchor="middle" font-family="Orbitron, monospace" font-size="38" font-weight="900" fill="rgba(37,156,174,0.8)">KND</text>
                                            <circle cx="35" cy="35" r="3" fill="rgba(37,156,174,0.12)"/>
                                            <circle cx="85" cy="55" r="3" fill="rgba(37,156,174,0.12)"/>
                                            <circle cx="35" cy="85" r="3" fill="rgba(37,156,174,0.12)"/>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 5. Services -->
    <section id="fp-services" class="fp-section fp-services">
        <div class="fp-section-inner">
            <div class="container">
                <div class="glass-card-neon p-4 p-md-5">
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <p class="badge bg-primary-subtle text-uppercase mb-2" style="letter-spacing:0.18em;">
                                Digital Performance
                            </p>
                            <h2 class="mb-3">Services built for precision and results.</h2>
                            <p class="text-white-50 mb-4">
                                Make confident decisions before you spend. Remote optimization, consulting, and
                                digital services designed to upgrade performance fast.
                            </p>
                            <a href="/products.php" class="btn btn-neon-primary btn-lg">
                                <i class="fas fa-bolt me-2"></i>Explore Services
                            </a>
                        </div>
                        <div class="col-lg-6">
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <h5 class="mb-2">Digital Consulting</h5>
                                    <p class="small text-white-50 mb-0">
                                        Budget planning, compatibility checks, and build simulations.
                                    </p>
                                </div>
                                <div class="col-sm-6">
                                    <h5 class="mb-2">Remote Technical Services</h5>
                                    <p class="small text-white-50 mb-0">
                                        Installations, performance tuning, and setup optimization.
                                    </p>
                                </div>
                                <div class="col-sm-6">
                                    <h5 class="mb-2 mt-3">Digital Marketplace</h5>
                                    <p class="small text-white-50 mb-0">
                                        Instant assets, guides, and digital drops.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 6. Apparel -->
    <section id="fp-apparel" class="fp-section fp-apparel">
        <div class="fp-section-inner">
            <div class="container">
                <div class="glass-card-neon p-4 p-md-5">
                    <div class="row g-4 align-items-center">
                        <div class="col-lg-6">
                            <p class="badge bg-secondary-subtle text-uppercase mb-2" style="letter-spacing:0.18em;">
                                Apparel
                            </p>
                            <h2 class="mb-3">Apparel is the extension.</h2>
                            <p class="text-white-50 mb-4">
                                CORE essentials and limited drops built for creators who want the KND mindset in the real world.
                            </p>
                            <a href="/apparel.php" class="btn btn-neon-primary btn-lg">
                                <i class="fas fa-tshirt me-2"></i>View Apparel
                            </a>
                        </div>
                        <div class="col-lg-6">
                            <div class="text-center">
                                <img src="/assets/images/apparel-hero.png" alt="KND Apparel" class="img-fluid rounded-4 shadow-lg" onerror="this.style.display='none';">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

</main>

<?php echo generateFooter(); ?>


<?php
/**
 * KND Gallery - Full-width image gallery.
 * Loads images automatically from assets/images/gallery/
 * Reusable section; compatible with Apache and project relative paths.
 */
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0);
    session_start();
}
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/footer.php';

// Ruta absoluta a la carpeta de galería (relativa al script)
$galleryDir = __DIR__ . '/assets/images/gallery';
// Extensiones permitidas (minúsculas para comparación)
$allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
$images = [];

if (is_dir($galleryDir) && is_readable($galleryDir)) {
    $files = @scandir($galleryDir);
    if ($files !== false) {
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $path = $galleryDir . DIRECTORY_SEPARATOR . $file;
            if (!is_file($path)) {
                continue;
            }
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (in_array($ext, $allowedExt, true)) {
                // URL pública: ruta relativa al document root
                $images[] = '/assets/images/gallery/' . rawurlencode($file);
            }
        }
    }
}
// Orden estable para la cuadrícula (por nombre de archivo)
sort($images);

$galleryTitle = defined('KND_GALLERY_TITLE') ? KND_GALLERY_TITLE : 'KND Gallery';
$galleryCss = __DIR__ . '/assets/css/gallery.css';
$galleryJs = __DIR__ . '/assets/js/gallery-lightbox.js';
$extraHead = '<link rel="stylesheet" href="/assets/css/gallery.css?v=' . (file_exists($galleryCss) ? filemtime($galleryCss) : time()) . '">';
$extraHead .= '<script src="/assets/js/gallery-lightbox.js?v=' . (file_exists($galleryJs) ? filemtime($galleryJs) : time()) . '" defer></script>';

echo generateHeader($galleryTitle . ' | KND Store', 'Gallery of visuals and wallpapers.', $extraHead);
?>
<div id="particles-bg"></div>
<?php echo generateNavigation(); ?>

<!-- Gallery Section: full-width, minimal gap, modern grid -->
<section class="knd-gallery-section py-4" aria-label="Gallery">
    <div class="container-fluid knd-gallery-container px-2 px-sm-3">
        <!-- Título centrado arriba -->
        <h1 class="knd-gallery-title text-center mb-4"><?php echo htmlspecialchars($galleryTitle); ?></h1>

        <?php if (empty($images)): ?>
            <p class="text-center text-white-50 py-5">No images in gallery yet.</p>
        <?php else: ?>
            <!-- Grid responsive: 1 col móvil, 2 tablet, 4 desktop. Gutters mínimos (g-1). -->
            <div class="row g-1 g-sm-2 knd-gallery-grid">
                <?php foreach ($images as $src): ?>
                    <?php
                    $srcEsc = htmlspecialchars($src, ENT_QUOTES, 'UTF-8');
                    $alt = basename(parse_url($src, PHP_URL_PATH));
                    $alt = pathinfo($alt, PATHINFO_FILENAME);
                    $alt = htmlspecialchars(preg_replace('/[-_]/', ' ', $alt), ENT_QUOTES, 'UTF-8');
                    ?>
                    <div class="col-12 col-md-6 col-lg-3 knd-gallery-col">
                        <div class="knd-gallery-item rounded overflow-hidden">
                            <img src="<?php echo $srcEsc; ?>"
                                 alt="<?php echo $alt; ?>"
                                 class="knd-gallery-img"
                                 loading="lazy"
                                 data-lightbox-src="<?php echo $srcEsc; ?>">
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Lightbox overlay (JS lo rellena y controla visibilidad) -->
<div id="knd-gallery-lightbox" class="knd-gallery-lightbox" role="dialog" aria-modal="true" aria-label="Image preview" hidden>
    <div class="knd-gallery-lightbox-backdrop"></div>
    <div class="knd-gallery-lightbox-content">
        <button type="button" class="knd-gallery-lightbox-close" aria-label="Close"><i class="fas fa-times"></i></button>
        <img src="" alt="" class="knd-gallery-lightbox-img">
    </div>
</div>

<?php
echo generateFooter();
echo generateScripts();
?>

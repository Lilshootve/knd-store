<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/footer.php';
require_once __DIR__ . '/includes/products-data.php';
require_once __DIR__ . '/includes/product-groups.php';

$creativeProducts = getProductsBySlugs($PRODUCT_GROUPS['digital_asset'], $PRODUCTS, 'creative');

function renderCreativeCard(string $slug, array $product): string {
    $displayImage = $product['imagen'];
    if (isset($product['gallery']) && !empty($product['gallery'])) {
        $firstGallery = reset($product['gallery']);
        $displayImage = $firstGallery;
    }
    $price = getProductPriceValue((int) $product['id'], $product);

    $html = '<div class="col-lg-4 col-md-6 mb-4">';
    $html .= '  <div class="product-card creative-card">';
    $html .= '    <div class="product-image">';
    $html .= '      <img src="/' . htmlspecialchars($displayImage) . '" alt="' . htmlspecialchars($product['nombre']) . '" onerror="this.onerror=null; this.src=\'/' . htmlspecialchars($product['imagen']) . '\';">';
    $html .= '      <div class="product-overlay">';
    $html .= '        <a href="/producto.php?slug=' . htmlspecialchars($slug) . '" class="btn btn-primary"><i class="fas fa-eye me-2"></i>' . t('btn.view_details') . '</a>';
    $html .= '      </div>';
    $html .= '    </div>';
    $html .= '    <div class="product-info">';
    $html .= '      <h5 class="product-title">' . htmlspecialchars($product['nombre']) . '</h5>';
    $html .= '      <p class="product-description">' . $product['descripcion'] . '</p>';
    $html .= '      <div class="product-footer">';
    $html .= '        <span class="product-price">$' . number_format($price, 2) . '</span>';
    $html .= '        <div class="d-flex gap-2">';
    $html .= '          <a href="/producto.php?slug=' . htmlspecialchars($slug) . '" class="btn btn-outline-neon btn-sm btn-details">' . t('btn.view_details') . '</a>';
    $html .= '          <button type="button" class="btn btn-primary btn-sm add-to-order" data-id="' . (int)$product['id'] . '" data-name="' . htmlspecialchars($product['nombre'], ENT_QUOTES, 'UTF-8') . '" data-price="' . number_format($price, 2, '.', '') . '" data-type="' . (isset($product['tipo']) ? htmlspecialchars($product['tipo']) : 'digital') . '">';
    $html .= '            ' . t('btn.add_to_order');
    $html .= '          </button>';
    $html .= '        </div>';
    $html .= '      </div>';
    $html .= '    </div>';
    $html .= '  </div>';
    $html .= '</div>';

    return $html;
}
?>

<?php echo generateHeader('Creative - KND Store', 'Curated creative assets: wallpapers, avatars, icons, and digital drops.'); ?>

<div id="particles-bg"></div>

<?php echo generateNavigation(); ?>

<section class="hero-section hero-catalog-bg">
    <div class="container">
        <div class="row align-items-center min-vh-100">
            <div class="col-12 text-center">
                <h1 class="hero-title">
                    <span class="text-gradient">Creative</span><br>
                    <span class="text-gradient">Identity Systems</span>
                </h1>
                <p class="hero-subtitle">
                    Wallpapers, avatars, icons, and mystery drops built for a clean, galactic identity.
                </p>
            </div>
        </div>
    </div>
</section>

<section class="py-5 bg-dark-epic">
    <div class="container">
        <div class="services-header text-center mb-5">
            <h2 class="section-title">Curated Digital Assets</h2>
            <p class="text-white-50">Expressive, clean, and ready to ship across your brand.</p>
        </div>
        <div class="row">
            <?php foreach ($creativeProducts as $slug => $product): ?>
                <?php echo renderCreativeCard($slug, $product); ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <h2 class="section-title mb-3">Need something custom?</h2>
                <p class="text-white-50 mb-0">
                    If you want a tailored creative direction or a premium asset set, reach out and we will build it with you.
                </p>
            </div>
            <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                <a href="/contact.php" class="btn btn-outline-neon btn-lg">
                    <i class="fas fa-paper-plane me-2"></i> Contact Creative
                </a>
            </div>
        </div>
    </div>
</section>

<script src="/assets/js/navigation-extend.js"></script>

<?php
echo generateFooter();
echo generateScripts();
?>

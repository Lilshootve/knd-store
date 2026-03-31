<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/footer.php';
require_once __DIR__ . '/includes/products-data.php';
require_once __DIR__ . '/includes/product-groups.php';

$consultingProducts = getProductsBySlugs($PRODUCT_GROUPS['consulting'], $PRODUCTS, 'services-consulting');
$remoteProducts = getProductsBySlugs($PRODUCT_GROUPS['remote_service'], $PRODUCTS, 'services-remote');
$marketplaceProducts = getProductsBySlugs($PRODUCT_GROUPS['digital_asset'], $PRODUCTS, 'services-marketplace');

function renderServiceCard(string $slug, array $product): string {
    $producto_tipo = isset($product['tipo']) ? $product['tipo'] : 'digital';
    $displayImage = $product['imagen'];
    if (isset($product['gallery']) && !empty($product['gallery'])) {
        $firstGallery = reset($product['gallery']);
        $displayImage = $firstGallery;
    }
    $price = getProductPriceValue((int) $product['id'], $product);

    $html = '<div class="col-lg-4 col-md-6 mb-4">';
    $html .= '  <div class="product-card">';
    $html .= '    <div class="product-image">';
    if ($producto_tipo === 'service') {
        $html .= '      <div class="service-placeholder" style="height: 250px; display: flex; flex-direction: column; align-items: center; justify-content: center; background: linear-gradient(135deg, rgba(37, 156, 174, 0.1) 0%, rgba(138, 43, 226, 0.1) 100%); border-bottom: 2px solid var(--knd-electric-purple);">';
        $html .= '        <i class="fas fa-wand-magic-sparkles fa-4x text-primary mb-3" style="opacity: 0.6;"></i>';
        $html .= '        <span class="text-white-50 small" style="font-size: 0.85rem;">Original Design Service</span>';
        $html .= '      </div>';
        $html .= '      <div class="product-overlay">';
        $html .= '        <a href="/producto.php?slug=' . htmlspecialchars($slug) . '" class="btn btn-primary"><i class="fas fa-eye me-2"></i>' . t('btn.view_details') . '</a>';
        $html .= '      </div>';
    } else {
        $html .= '      <img src="/' . htmlspecialchars($displayImage) . '" alt="' . htmlspecialchars($product['nombre']) . '" onerror="this.onerror=null; this.src=\'/' . htmlspecialchars($product['imagen']) . '\';">';
        $html .= '      <div class="product-overlay">';
        $html .= '        <a href="/producto.php?slug=' . htmlspecialchars($slug) . '" class="btn btn-primary"><i class="fas fa-eye me-2"></i>' . t('btn.view_details') . '</a>';
        $html .= '      </div>';
    }
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

<?php echo generateHeader('Services - KND Store', 'Digital performance services and remote technical solutions for modern builders.'); ?>

<!-- Particles Background -->
<div id="particles-bg"></div>

<?php echo generateNavigation(); ?>

<!-- Hero Section -->
<section class="hero-section hero-catalog-bg">
    <div class="container">
        <div class="row align-items-center min-vh-100">
            <div class="col-12 text-center">
                <h1 class="hero-title">
                    <span class="text-gradient">Services</span><br>
                    <span class="text-gradient">Built for performance</span>
                </h1>
                <p class="hero-subtitle">
                    Digital performance services and remote technical solutions for modern builders.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- Digital Consulting -->
<section class="services-section py-5 bg-dark-epic" id="consulting">
    <div class="container">
        <div class="services-header text-center mb-5">
            <h2 class="section-title">Digital Consulting</h2>
            <p class="text-white-50">Make the right decisions before you spend.</p>
        </div>
        <div class="row">
            <?php foreach ($consultingProducts as $slug => $product): ?>
                <?php echo renderServiceCard($slug, $product); ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Remote Technical Services -->
<section class="services-section py-5" id="remote-services">
    <div class="container">
        <div class="services-header text-center mb-5">
            <h2 class="section-title">Remote Technical Services</h2>
            <p class="text-white-50">Professional remote setup and optimization.</p>
        </div>
        <div class="row">
            <?php foreach ($remoteProducts as $slug => $product): ?>
                <?php echo renderServiceCard($slug, $product); ?>
            <?php endforeach; ?>
        </div>
        <div class="services-disclaimer mt-4">
            <div class="alert alert-warning bg-dark border-warning mb-0">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Remote services depend on stable internet and device condition. We are not responsible for pre-existing hardware issues or interruptions due to connectivity.
            </div>
        </div>
    </div>
</section>

<!-- Digital Marketplace -->
<section class="services-section py-5 bg-dark-epic" id="marketplace">
    <div class="container">
        <div class="services-header text-center mb-5">
            <h2 class="section-title">Digital Marketplace</h2>
            <p class="text-white-50">Instant digital assets and content.</p>
        </div>
        <div class="row">
            <?php foreach ($marketplaceProducts as $slug => $product): ?>
                <?php echo renderServiceCard($slug, $product); ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<script src="/assets/js/navigation-extend.js"></script>

<?php
echo generateFooter();
echo generateScripts();
?> 
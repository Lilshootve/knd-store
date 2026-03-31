<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/footer.php';
require_once __DIR__ . '/includes/products-data.php';

// Filtrar solo productos service
$serviceProducts = array_filter($PRODUCTS, function($product) {
    return isset($product['tipo']) && $product['tipo'] === 'service';
});

echo generateHeader(t('custom_design.meta.title'), t('custom_design.meta.description'));
?>

<div id="particles-bg"></div>

<?php echo generateNavigation(); ?>

<!-- Hero Section -->
<section class="hero-section hero-custom-design-bg">
    <div class="container">
        <div class="row align-items-center min-vh-100">
            <div class="col-lg-7">
                <h1 class="hero-title">
                    <span class="text-gradient">Custom Design Lab</span><br>
                    <span class="hero-subtitle-mini">Custom design built for you</span>
                </h1>
                <p class="hero-subtitle">
                    Turn your ideas into unique designs. Customizable design services for T-Shirts, Hoodies, and full outfit concepts.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- Service Plans -->
<section class="py-5 bg-dark-epic" id="plans">
    <div class="container">
        <h2 class="section-title text-center mb-5">
            <i class="fas fa-layer-group me-2"></i> <?php echo t('custom_design.plans.title'); ?>
        </h2>
        
        <div class="row">
            <?php foreach ($serviceProducts as $slug => $product): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card bg-dark border-primary h-100 product-card">
                        <!-- Service Visual Placeholder -->
                        <div class="service-placeholder" style="height: 250px; display: flex; flex-direction: column; align-items: center; justify-content: center; background: linear-gradient(135deg, rgba(37, 156, 174, 0.1) 0%, rgba(138, 43, 226, 0.1) 100%); border-bottom: 2px solid var(--knd-electric-purple);">
                            <i class="fas fa-wand-magic-sparkles fa-4x text-primary mb-3" style="opacity: 0.6;"></i>
                            <span class="text-white-50 small" style="font-size: 0.85rem;">Original Design Service</span>
                        </div>
                        <div class="card-body text-center">
                            <h4 class="text-white mb-3"><?php echo htmlspecialchars($product['nombre']); ?></h4>
                            <div class="product-price mb-3">
                                $<?php echo number_format(getProductPriceValue((int) $product['id'], $product), 2); ?>
                            </div>
                            <div class="text-white-50 mb-4" style="min-height: 80px;">
                                <?php echo strip_tags($product['descripcion']); ?>
                            </div>
                            <a href="/producto.php?slug=<?php echo $slug; ?>" class="btn btn-primary w-100">
                                <i class="fas fa-shopping-cart me-2"></i> <?php echo t('custom_design.request_buy'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Brief Form Section -->
<section class="py-5" id="brief-form">
    <div class="container">
        <h2 class="section-title text-center mb-5">
            <i class="fas fa-file-alt me-2"></i> <?php echo t('custom_design.brief.title'); ?>
        </h2>
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card knd-card">
                    <div class="card-header">
                        <h4 class="mb-0">Design Brief</h4>
                    </div>
                    <div class="card-body">
                        <form id="custom-design-brief-form">
                            <div class="mb-3">
                                <label class="form-label">Desired style</label>
                                <input type="text" name="estilo" class="form-control" placeholder="e.g., Minimalist, Futuristic, Anime, etc.">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Preferred colors</label>
                                <input type="text" name="colores" class="form-control" placeholder="e.g., Magenta, Turquoise, Black">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Text/name to include</label>
                                <input type="text" name="texto" class="form-control" placeholder="Text or name you want in the design">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Style references (no characters or brands)</label>
                                <textarea name="referencias" class="form-control" rows="4" placeholder="Describe visual references, styles you like, or links to inspiration images (no protected characters or brands)"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Extra details</label>
                                <textarea name="detalles" class="form-control" rows="3" placeholder="Any extra details you want to specify"></textarea>
                            </div>
                    <div class="alert alert-warning" style="background: rgba(255, 193, 7, 0.1); border-color: #ffc107;">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Intellectual Property & Production:</strong>
                        This service includes <strong>original graphic design</strong> and <strong>made-to-order print production</strong>.
                        We do not create or print designs that reproduce characters, brands, logos, or copyrighted material without verifiable authorization from the rights holder.
                    </div>
                    
                    <p class="text-white-50 small mb-0">
                        If the brief contains unauthorized references, <strong>KND Store</strong> may adjust the creative proposal or reject the order before production,
                        without obligation to replicate the original reference.
                    </p>
                            <button type="button" id="save-brief-btn" class="btn btn-primary w-100">
                                <i class="fas fa-save me-2"></i> Save Brief (will be added to your order)
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- How it works -->
<section class="py-5 bg-dark-epic" id="how-it-works">
    <div class="container">
        <h2 class="section-title text-center mb-5">
            <i class="fas fa-cogs me-2"></i> <?php echo t('custom_design.how_it_works.title'); ?>
        </h2>
        <div class="row">
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="card bg-dark border-primary h-100">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="fas fa-shopping-cart fa-3x text-primary"></i>
                        </div>
                        <h5 class="text-white mb-3">1. Choose the service</h5>
                        <p class="text-white-50 small">Pick the design plan you need.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="card bg-dark border-primary h-100">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="fas fa-file-alt fa-3x text-primary"></i>
                        </div>
                        <h5 class="text-white mb-3">2. Complete the brief</h5>
                        <p class="text-white-50 small">Describe your idea, style, and references.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="card bg-dark border-primary h-100">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="fas fa-palette fa-3x text-primary"></i>
                        </div>
                        <h5 class="text-white mb-3">3. We design</h5>
                        <p class="text-white-50 small">We craft your custom design.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="card bg-dark border-primary h-100">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="fas fa-download fa-3x text-primary"></i>
                        </div>
                        <h5 class="text-white mb-3">4. Digital delivery</h5>
                        <p class="text-white-50 small">Receive your editable files and mockups.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
// Guardar brief en localStorage cuando se hace clic en el botón
document.addEventListener('DOMContentLoaded', function() {
    const saveBriefBtn = document.getElementById('save-brief-btn');
    const briefForm = document.getElementById('custom-design-brief-form');
    
    if (saveBriefBtn && briefForm) {
        saveBriefBtn.addEventListener('click', function() {
            const formData = new FormData(briefForm);
            const brief = {
                estilo: formData.get('estilo') || '',
                colores: formData.get('colores') || '',
                texto: formData.get('texto') || '',
                referencias: formData.get('referencias') || '',
                detalles: formData.get('detalles') || ''
            };
            
            // Guardar en localStorage con una clave específica
            localStorage.setItem('knd_custom_design_brief', JSON.stringify(brief));
            
            // Mostrar notificación
            alert('Brief saved. When you add a design service to your order, this brief will be included automatically.');
            
            // Limpiar formulario
            briefForm.reset();
        });
    }
});
</script>

<script src="/assets/js/navigation-extend.js"></script>

<?php 
echo generateFooter();
echo generateScripts();
?>


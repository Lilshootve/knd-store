<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/footer.php';
?>

<?php echo generateHeader(t('terms.meta.title'), t('terms.meta.description')); ?>

<!-- Particles Background -->
<div id="particles-bg"></div>

<?php echo generateNavigation(); ?>

<!-- Hero Section -->
<section class="hero-section py-5">
    <div class="container">
        <div class="row">
            <div class="col-12 text-center">
                <h1 class="hero-title">
                    <span class="text-gradient"><?php echo t('terms.hero.title_line1'); ?></span><br>
                    <span class="text-gradient"><?php echo t('terms.hero.title_line2'); ?></span>
                </h1>
                <p class="hero-subtitle">
                    <?php echo t('terms.hero.subtitle'); ?>
                </p>
                <div class="mt-3">
                    <span class="badge bg-primary fs-6">
                        <i class="fas fa-file-contract me-2"></i>
                        <?php echo t('terms.last_update.badge', null, ['month_year' => date('F Y')]); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Terms Content -->
<section class="py-5 bg-dark-epic">
    <div class="container">
        <div class="row">
            <div class="col-lg-10 mx-auto">
                <div class="mb-5">
                    <h2 class="section-title mb-4">
                        <i class="fas fa-check-circle me-2"></i>
                        Acceptance of Terms
                    </h2>
                    <p class="text-white mb-3">
                        By accessing or using <strong>kndstore.com</strong>, you agree to these Terms and Conditions. If you do not agree, please do not use our services.
                    </p>
                </div>

                <div class="mb-5">
                    <h2 class="section-title mb-4">
                        <i class="fas fa-cubes me-2"></i>
                        Services & Products
                    </h2>
                    <p class="text-white mb-3">
                        We offer digital services, custom design services, and apparel products. Availability, pricing, and delivery timelines may change without notice.
                    </p>
                </div>

                <div class="mb-5">
                    <h2 class="section-title mb-4">
                        <i class="fas fa-credit-card me-2"></i>
                        Orders & Payments
                    </h2>
                    <ul class="text-white mb-3">
                        <li>Orders are confirmed after payment is verified through approved channels.</li>
                        <li>We do not store sensitive payment information.</li>
                        <li>Pricing is listed in USD unless otherwise stated.</li>
                    </ul>
                </div>

                <div class="mb-5">
                    <h2 class="section-title mb-4">
                        <i class="fas fa-bolt me-2"></i>
                        Digital Delivery
                    </h2>
                    <p class="text-white mb-3">
                        Digital products and services are delivered via email, download links, or remote support channels. Delivery times vary by service complexity.
                    </p>
                </div>

                <div class="mb-5">
                    <h2 class="section-title mb-4">
                        <i class="fas fa-tshirt me-2"></i>
                        Apparel Delivery
                    </h2>
                    <p class="text-white mb-3">
                        Apparel orders require coordinated delivery. We contact you to confirm shipping details, timelines, and any required information.
                    </p>
                </div>

                <div class="mb-5">
                    <h2 class="section-title mb-4">
                        <i class="fas fa-palette me-2"></i>
                        Custom Design Services
                    </h2>
                    <p class="text-white mb-3">
                        Custom design services are tailored to the brief you provide. We may request revisions or clarifications to deliver the best result.
                    </p>
                </div>

                <div class="mb-5">
                    <h2 class="section-title mb-4">
                        <i class="fas fa-shield-alt me-2"></i>
                        Intellectual Property
                    </h2>
                    <p class="text-white mb-3">
                        We do not create or print designs that infringe on third-party copyrights, trademarks, or protected materials without verified authorization.
                    </p>
                </div>

                <div class="mb-5">
                    <h2 class="section-title mb-4">
                        <i class="fas fa-undo me-2"></i>
                        Returns & Refunds
                    </h2>
                    <p class="text-white mb-3">
                        Digital products are non-refundable once delivered. For apparel, returns are evaluated on a case-by-case basis due to customized production. If you experience an issue, contact support promptly.
                    </p>
                </div>

                <div class="mb-5">
                    <h2 class="section-title mb-4">
                        <i class="fas fa-user-shield me-2"></i>
                        Limitation of Liability
                    </h2>
                    <p class="text-white mb-3">
                        KND Store is not liable for indirect or incidental damages related to the use of our services. We provide services as-is and make no warranties beyond those required by law.
                    </p>
                </div>

                <div class="mb-5">
                    <h2 class="section-title mb-4">
                        <i class="fas fa-sync-alt me-2"></i>
                        Changes to Terms
                    </h2>
                    <p class="text-white mb-3">
                        We may update these terms at any time. Continued use of the site after changes means you accept the updated terms.
                    </p>
                </div>

                <div class="mb-5">
                    <h2 class="section-title mb-4">
                        <i class="fas fa-envelope-open-text me-2"></i>
                        Contact
                    </h2>
                    <p class="text-white mb-3">
                        Questions about these terms? Contact us at <strong><?php echo defined('SITE_EMAIL') ? SITE_EMAIL : 'support@kndstore.com'; ?></strong>.
                    </p>
                </div>

                <div class="text-center mt-5 pt-4" style="border-top: 1px solid rgba(138, 43, 226, 0.3);">
                    <p class="text-white mb-2">
                        <i class="fas fa-file-contract me-2"></i>
                        <strong>KND Store</strong> - Knowledge 'N Development
                    </p>
                    <p class="text-white mb-0" style="font-size: 0.9rem;">
                        Last updated: <?php echo date('F Y'); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<script src="/assets/js/navigation-extend.js"></script>

<?php 
echo generateFooter();
echo generateScripts();
?>

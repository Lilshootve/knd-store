<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/footer.php';
?>

<?php echo generateHeader(t('privacy.meta.title'), t('privacy.meta.description')); ?>

<!-- Particles Background -->
<div id="particles-bg"></div>

<?php echo generateNavigation(); ?>

<!-- Hero Section -->
<section class="hero-section py-5">
    <div class="container">
        <div class="row">
            <div class="col-12 text-center">
                <h1 class="hero-title">
                    <span class="text-gradient"><?php echo t('privacy.hero.title_line1'); ?></span><br>
                    <span class="text-gradient"><?php echo t('privacy.hero.title_line2'); ?></span>
                </h1>
                <p class="hero-subtitle">
                    <?php echo t('privacy.hero.subtitle'); ?>
                </p>
                <div class="mt-3">
                    <span class="badge bg-primary fs-6">
                        <i class="fas fa-shield-alt me-2"></i>
                        <?php echo t('privacy.last_update.badge', null, ['month_year' => date('F Y')]); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Privacy Content -->
<section class="py-5 bg-dark-epic">
    <div class="container">
        <div class="row">
            <div class="col-lg-10 mx-auto">
                <div class="mb-5">
                    <h2 class="section-title mb-4">
                        <i class="fas fa-info-circle me-2"></i>
                        Introduction
                    </h2>
                    <p class="text-white mb-3">
                        At <strong>KND Store</strong> (<strong>Knowledge 'N Development</strong>), we take privacy seriously. This policy explains how we collect, use, store, and protect your personal data when you use <strong>kndstore.com</strong> and our services.
                    </p>
                    <p class="text-white mb-3">
                        By using our services, you agree to the practices described here.
                    </p>
                </div>

                <div class="mb-5">
                    <h3 class="text-white mb-3 mt-4">
                        <i class="fas fa-database me-2"></i>
                        Data We Collect
                    </h3>
                    <ul class="text-white mb-3">
                        <li><strong>Identity data:</strong> Full name, alias, or username.</li>
                        <li><strong>Contact data:</strong> Email, phone/WhatsApp, and Discord handle.</li>
                        <li><strong>Transaction data:</strong> Purchases, requested services, payment methods used (we do not store sensitive financial data).</li>
                        <li><strong>Technical data:</strong> IP address, browser type, device, and usage analytics.</li>
                        <li><strong>Communication data:</strong> Messages, support requests, and related info you share with us.</li>
                    </ul>
                    <div class="alert alert-info bg-dark border-primary">
                        <i class="fas fa-lock me-2"></i>
                        <strong>Important:</strong> <strong>KND Store</strong> does <strong>not</strong> store credit card numbers, bank account details, or sensitive financial data. Payments are processed through secure external platforms.
                    </div>
                </div>

                <hr class="my-5" style="border-color: rgba(138, 43, 226, 0.3);">

                <div class="mb-5">
                    <h2 class="section-title mb-4">
                        <i class="fas fa-cogs me-2"></i>
                        How We Use Data
                    </h2>
                    <ul class="text-white mb-3">
                        <li>Process orders and service requests.</li>
                        <li>Deliver digital goods and coordinate physical deliveries.</li>
                        <li>Use design briefs to create custom designs and keep you updated.</li>
                        <li>Provide support, notifications, and account communications.</li>
                        <li>Improve performance, usability, and new features.</li>
                    </ul>
                    <div class="alert alert-success bg-dark border-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Commitment:</strong> We never sell, rent, or share your personal data for marketing without explicit consent.
                    </div>
                </div>

                <hr class="my-5" style="border-color: rgba(138, 43, 226, 0.3);">

                <div class="mb-5">
                    <h2 class="section-title mb-4">
                        <i class="fas fa-credit-card me-2"></i>
                        Payments & Financial Security
                    </h2>
                    <p class="text-white mb-3">
                        Payments are processed through secure third-party providers (Zinli, Binance Pay, PayPal, crypto providers, and bank transfer processors). We do not store payment credentials.
                    </p>
                    <div class="alert alert-warning bg-dark border-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Reminder:</strong> Only use official payment channels. We never request sensitive financial data via email, WhatsApp, or Discord.
                    </div>
                </div>

                <hr class="my-5" style="border-color: rgba(138, 43, 226, 0.3);">

                <div class="mb-5" id="cookies-policy">
                    <h2 class="section-title mb-4">
                        <i class="fas fa-cookie-bite me-2"></i>
                        Cookies & Similar Technologies
                    </h2>
                    <p class="text-white mb-3">
                        Cookies are small text files stored on your device. They help us remember preferences and understand how you use the site.
                    </p>
                    <ul class="text-white mb-3">
                        <li><strong>Necessary:</strong> Required for security, sessions, and core functionality.</li>
                        <li><strong>Preferences:</strong> Save experience settings such as themes.</li>
                        <li><strong>Analytics:</strong> Help us improve performance and usability.</li>
                        <li><strong>Marketing:</strong> Used for personalized content and campaign performance.</li>
                    </ul>
                    <p class="text-white mb-3">
                        Manage your preferences via the banner or the
                        <a href="#" class="knd-cookie-settings-link text-decoration-underline">Cookie Settings</a>
                        link in the footer.
                    </p>
                </div>

                <hr class="my-5" style="border-color: rgba(138, 43, 226, 0.3);">

                <div class="mb-5">
                    <h2 class="section-title mb-4">
                        <i class="fas fa-plug me-2"></i>
                        External Tools
                    </h2>
                    <p class="text-white mb-3">
                        We use third-party tools such as analytics, hosting, CDN, and security services to run and improve the site. These providers may process technical data like IP addresses.
                    </p>
                    <div class="alert alert-warning bg-dark border-warning">
                        <i class="fas fa-external-link-alt me-2"></i>
                        <strong>External links:</strong> We are not responsible for third-party privacy practices. Review their policies when you visit them.
                    </div>
                </div>

                <hr class="my-5" style="border-color: rgba(138, 43, 226, 0.3);">

                <div class="mb-5">
                    <h2 class="section-title mb-4">
                        <i class="fas fa-user-shield me-2"></i>
                        Data Security
                    </h2>
                    <ul class="text-white mb-3">
                        <li><strong>Encryption:</strong> HTTPS/SSL protects data in transit.</li>
                        <li><strong>Access control:</strong> Data access is limited to authorized parties.</li>
                        <li><strong>Server security:</strong> Firewalls and monitoring are in place.</li>
                        <li><strong>Updates:</strong> Systems are regularly patched.</li>
                    </ul>
                    <p class="text-white mb-3">
                        No online transmission is 100% secure, but we work hard to protect your data.
                    </p>
                </div>

                <hr class="my-5" style="border-color: rgba(138, 43, 226, 0.3);">

                <div class="mb-5">
                    <h2 class="section-title mb-4">
                        <i class="fas fa-user-check me-2"></i>
                        Your Rights
                    </h2>
                    <p class="text-white mb-3">
                        Depending on your location, you may have the right to access, correct, delete, object to, or export your data, and withdraw consent at any time.
                    </p>
                    <div class="alert alert-success bg-dark border-success">
                        <i class="fas fa-envelope me-2"></i>
                        <strong>Exercise your rights:</strong> Contact us at <a href="mailto:support@kndstore.com" class="text-primary text-decoration-none">support@kndstore.com</a>. We respond within a reasonable timeframe (usually within <strong>30 days</strong>).
                    </div>
                </div>

                <hr class="my-5" style="border-color: rgba(138, 43, 226, 0.3);">

                <div class="mb-5">
                    <h2 class="section-title mb-4">
                        <i class="fas fa-archive me-2"></i>
                        Data Retention & Transfers
                    </h2>
                    <p class="text-white mb-3">
                        We retain data only as long as needed to deliver services, comply with legal obligations, and resolve disputes. Data may be processed internationally through trusted providers with appropriate safeguards.
                    </p>
                </div>

                <hr class="my-5" style="border-color: rgba(138, 43, 226, 0.3);">

                <div class="mb-5">
                    <h2 class="section-title mb-4">
                        <i class="fas fa-child me-2"></i>
                        Minors
                    </h2>
                    <p class="text-white mb-3">
                        Our services are not intended for children under 13. If we learn that we collected data from a minor, we will remove it.
                    </p>
                </div>

                <hr class="my-5" style="border-color: rgba(138, 43, 226, 0.3);">

                <div class="mb-5">
                    <h2 class="section-title mb-4">
                        <i class="fas fa-sync-alt me-2"></i>
                        Changes to This Policy
                    </h2>
                    <p class="text-white mb-3">
                        We may update this policy to reflect changes in our practices or legal requirements. Significant changes will be communicated on the site.
                    </p>
                </div>

                <hr class="my-5" style="border-color: rgba(138, 43, 226, 0.3);">

                <div class="mb-5">
                    <h2 class="section-title mb-4">
                        <i class="fas fa-envelope-open-text me-2"></i>
                        Contact
                    </h2>
                    <p class="text-white mb-3">
                        For privacy questions or requests, contact us at:
                    </p>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="card bg-dark border-primary h-100">
                                <div class="card-body">
                                    <h5 class="text-white mb-2"><i class="fas fa-envelope me-2"></i>Email</h5>
                                    <p class="text-white-50 mb-0"><?php echo defined('SITE_EMAIL') ? SITE_EMAIL : 'support@kndstore.com'; ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="card bg-dark border-primary h-100">
                                <div class="card-body">
                                    <h5 class="text-white mb-2"><i class="fab fa-discord me-2"></i>Discord</h5>
                                    <p class="text-white-50 mb-0">discord.gg/zjP3u5Yztx</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="card bg-dark border-primary h-100">
                                <div class="card-body">
                                    <h5 class="text-white mb-2"><i class="fab fa-whatsapp me-2"></i>WhatsApp</h5>
                                    <p class="text-white-50 mb-0">+58 414-159-2319</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="card bg-dark border-primary h-100">
                                <div class="card-body">
                                    <h5 class="text-white mb-2"><i class="fas fa-globe me-2"></i>Website</h5>
                                    <p class="text-white-50 mb-0">kndstore.com</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-5 pt-4" style="border-top: 1px solid rgba(138, 43, 226, 0.3);">
                    <p class="text-white mb-2">
                        <i class="fas fa-shield-alt me-2"></i>
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

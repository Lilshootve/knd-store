<?php
// Configuración de sesión ANTES de cargar config.php
if (session_status() == PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0); // Cambiar a 1 en producción con HTTPS
    session_start();
} else {
    // Si la sesión ya está activa, solo la iniciamos
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
}

require_once 'includes/config.php';
require_once 'includes/header.php';
require_once 'includes/footer.php';
?>

<?php echo generateHeader('FAQ', 'Frequently Asked Questions - KND Store. Digital Goods • Apparel • Custom Design Services'); ?>

<!-- Particles Background -->
<div id="particles-bg"></div>

<?php echo generateNavigation(); ?>

<!-- Hero Section -->
<section class="hero-section py-5">
    <div class="container">
        <div class="row">
            <div class="col-12 text-center">
                <h1 class="hero-title">
                    <span class="text-gradient">Frequently</span><br>
                    <span class="text-gradient">Asked Questions</span>
                </h1>
                <p class="hero-subtitle">
                    Find answers to your questions about KND Store
                </p>
            </div>
        </div>
    </div>
</section>

<!-- FAQ Section -->
<section class="faq-section py-5 bg-dark-epic">
    <div class="container">
        <div class="row">
            <div class="col-lg-10 mx-auto">
                <div class="faq-container">
                    
                    <!-- FAQ Item 1 -->
                    <div class="faq-item">
                        <div class="faq-question">
                            <h3><i class="fas fa-brain me-3"></i>What is KND Store?</h3>
                        </div>
                        <div class="faq-answer">
                            <p>KND Store is more than a store. It is a command station for gamers, techies, and digital adventurers who want premium tech, epic gear, and unique experiences. Founded in 1995 (yes, that is our easter egg), we fuse hardware, gaming culture, and future vision in one node.</p>
                        </div>
                    </div>

                    <!-- FAQ Item 2 -->
                    <div class="faq-item">
                        <div class="faq-question">
                            <h3><i class="fas fa-rocket me-3"></i>Do you ship interplanetary?</h3>
                        </div>
                        <div class="faq-answer">
                            <p>Not to Mars yet, but we ship nationally and internationally depending on the product. If your zone is off the radar, reach out via Discord or the contact form to open the route.</p>
                        </div>
                    </div>

                    <!-- FAQ Item 3 -->
                    <div class="faq-item">
                        <div class="faq-question">
                            <h3><i class="fas fa-credit-card me-3"></i>What payment methods do you accept?</h3>
                        </div>
                        <div class="faq-answer">
                            <p>We accept:</p>
                            <ul>
                                <li>Credit and debit cards</li>
                                <li>Mobile payments (Zinli, Wally, Binance Pay)</li>
                                <li>Select cryptocurrencies</li>
                                <li>PayPal, Apple Pay, Google Pay</li>
                                <li>KND points system (coming soon)</li>
                            </ul>
                        </div>
                    </div>

                    <!-- FAQ Item 4 -->
                    <div class="faq-item">
                        <div class="faq-question">
                            <h3><i class="fas fa-coins me-3"></i>Can I pay with crypto?</h3>
                        </div>
                        <div class="faq-answer">
                            <p>Absolutely. We are connected to major chains and crypto payment gateways. Just select the option at checkout.</p>
                        </div>
                    </div>

                    <!-- FAQ Item 5 -->
                    <div class="faq-item">
                        <div class="faq-question">
                            <h3><i class="fas fa-gamepad me-3"></i>Do you sell digital products?</h3>
                        </div>
                        <div class="faq-answer">
                            <p>Yes. We offer game keys, premium software, and downloadable content. Digital products are delivered to your hangar (email or user panel) once payment is complete.</p>
                        </div>
                    </div>

                    <!-- FAQ Item 6 -->
                    <div class="faq-item">
                        <div class="faq-question">
                            <h3><i class="fas fa-clock me-3"></i>How long does delivery take?</h3>
                        </div>
                        <div class="faq-answer">
                            <ul>
                                <li><strong>Digital products:</strong> instant or up to 15 minutes.</li>
                                <li><strong>Domestic physical shipping:</strong> 24 to 72 business hours.</li>
                                <li><strong>International shipping:</strong> 5 to 15 days depending on destination.</li>
                            </ul>
                        </div>
                    </div>

                    <!-- FAQ Item 7 -->
                    <div class="faq-item">
                        <div class="faq-question">
                            <h3><i class="fas fa-tools me-3"></i>Do you offer warranty?</h3>
                        </div>
                        <div class="faq-answer">
                            <p>Yes, all physical products include a manufacturer warranty. If something fails, we open a support channel and resolve it as fast as possible.</p>
                        </div>
                    </div>

                    <!-- FAQ Item 8 -->
                    <div class="faq-item">
                        <div class="faq-question">
                            <h3><i class="fas fa-box me-3"></i>Can I track my order?</h3>
                        </div>
                        <div class="faq-answer">
                            <p>Yes. After purchase, you receive a tracking code or access to your pilot panel to see your order status in real time.</p>
                        </div>
                    </div>

                    <!-- FAQ Item 9 -->
                    <div class="faq-item">
                        <div class="faq-question">
                            <h3><i class="fas fa-satellite me-3"></i>How can I contact support?</h3>
                        </div>
                        <div class="faq-answer">
                            <p>You have multiple contact channels:</p>
                            <ul>
                                <li>Contact form on our website</li>
                                <li>Direct email: support@kndstore.com</li>
                                <li>Discord: discord.gg/zjP3u5Yztx</li>
                            </ul>
                        </div>
                    </div>

                    <!-- FAQ Item 10 -->
                    <div class="faq-item">
                        <div class="faq-question">
                            <h3><i class="fas fa-robot me-3"></i>Who or what is Kael?</h3>
                        </div>
                        <div class="faq-answer">
                            <p>Kael is our internal tactical AI. Not just another chatbot — a logic-driven entity trained to assist, answer precisely, and offer solutions before you ask. If Kael responds... listen.</p>
                        </div>
                    </div>

                    <!-- FAQ Item 11 -->
                    <div class="faq-item">
                        <div class="faq-question">
                            <h3><i class="fas fa-dice me-3"></i>What is Death Roll Chain?</h3>
                        </div>
                        <div class="faq-answer">
                            <p>An experimental project based on cosmic chance, blockchain, and rewards. A minigame in development where you can win tokens, items, or laugh to death. (Coming soon in the /deathroll sector.)</p>
                        </div>
                    </div>

                    <!-- FAQ Item 12 -->
                    <div class="faq-item">
                        <div class="faq-question">
                            <h3><i class="fas fa-crystal-ball me-3"></i>What is next for KND?</h3>
                        </div>
                        <div class="faq-answer">
                            <p>No limits. From launching our own cryptocurrency to becoming a network of interstellar stores with integrated AI, NFT products, and gear with soul. Anything is possible.</p>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</section>

<!-- Call to Action -->
<section class="cta-section py-5">
    <div class="container">
        <div class="row">
            <div class="col-12 text-center">
                <h2 class="section-title">
                    <i class="fas fa-question-circle me-3"></i>
                    Didn’t find your answer?
                </h2>
                <p class="cta-text">
                    If you have a specific question not listed here, reach out directly.
                </p>
                <div class="cta-buttons">
                    <a href="/contact.php" class="btn btn-primary btn-lg me-3">
                        <i class="fas fa-envelope"></i> Contact
                    </a>
                    <a href="https://discord.gg/zjP3u5Yztx" target="_blank" class="btn btn-primary btn-lg">
                        <i class="fab fa-discord"></i> Discord
                    </a>
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
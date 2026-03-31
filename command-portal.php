<?php
// KND Command Portal - Página independiente y autocontenida
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>KND Command Portal | KND Store</title>
    <meta name="description" content="KND Command Portal: entrada premium al ecosistema visual y modular de KND Store.">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Orbitron:wght@500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="assets/css/command-portal.css">
</head>
<body class="knd-portal-body knd-portal-page">
    <div class="knd-portal-app">
        <div class="knd-portal-video-layer" aria-hidden="true">
            <video
                class="knd-portal-video"
                autoplay
                muted
                loop
                playsinline
                preload="auto"
            >
                <source src="/assets/videos/background-video.mp4" type="video/mp4">
            </video>
                </div>

        <div class="knd-portal-content">
            <header class="knd-portal-header">
                <div class="knd-portal-brand">
                    <img
                        src="knd-command-portal/assets/images/knd-logo.png"
                        alt="KND Store logo"
                        class="knd-portal-logo"
                        width="64"
                        height="64"
                    >
                    <div class="knd-portal-brand-copy">
                        <span class="knd-portal-brand-title">KND STORE</span>
                        <span class="knd-portal-brand-subtitle">KNOWLEDGE 'N DEVELOPMENT</span>
                    </div>
                </div>

                <div class="knd-portal-dropdown" data-knd-portal-dropdown>
                    <button
                        class="knd-portal-dropdown-toggle"
                        type="button"
                        aria-haspopup="true"
                        aria-expanded="false"
                        aria-controls="knd-portal-menu"
                        data-knd-portal-toggle
                    >
                        <span>Command Portal</span>
                        <span class="knd-portal-dropdown-caret" aria-hidden="true"></span>
                    </button>

                    <ul class="knd-portal-dropdown-menu" id="knd-portal-menu" role="menu" data-knd-portal-menu>
                        <li><a class="knd-portal-dropdown-item" role="menuitem" href="/labs" data-knd-portal-link="labs">Labs</a></li>
                        <li><a class="knd-portal-dropdown-item" role="menuitem" href="/arena" data-knd-portal-link="arena">Arena</a></li>
                        <li><a class="knd-portal-dropdown-item" role="menuitem" href="/services" data-knd-portal-link="services">Services</a></li>
                        <li><a class="knd-portal-dropdown-item" role="menuitem" href="/apparel" data-knd-portal-link="apparel">Apparel</a></li>

                        <li class="knd-portal-dropdown-divider" role="separator"></li>

                        <li><a class="knd-portal-dropdown-item" role="menuitem" href="/profile" data-knd-portal-link="profile">My Profile</a></li>
                        <li><a class="knd-portal-dropdown-item" role="menuitem" href="/notifications" data-knd-portal-link="notifications">Notifications</a></li>
                        <li><a class="knd-portal-dropdown-item" role="menuitem" href="/points" data-knd-portal-link="points">Points</a></li>

                        <li class="knd-portal-dropdown-divider" role="separator"></li>

                        <li><a class="knd-portal-dropdown-item" role="menuitem" href="/support" data-knd-portal-link="support">Support</a></li>
                        <li><a class="knd-portal-dropdown-item knd-portal-dropdown-item-logout" role="menuitem" href="/logout" data-knd-portal-link="logout">Logout</a></li>
                    </ul>
                </div>
            </header>

            <main class="knd-portal-main">
                <section class="knd-portal-hero">
                    <div class="knd-portal-hero-left">
                        <p class="knd-portal-kicker">PROTOTYPE ENVIRONMENT</p>
                        <p class="knd-portal-subkicker">CREATE • PLAY • COLLECT</p>

                        <h1 class="knd-portal-title">KND COMMAND PORTAL</h1>

                        <p class="knd-portal-description">
                            Esta es la entrada al ecosistema visual y modular de KND Store: una interfaz diseñada para explorar
                            experiencias, herramientas y módulos conectados en un solo sistema.
                        </p>

                        <div class="knd-portal-actions">
                            <a class="knd-portal-btn knd-portal-btn-primary" href="#">SIMULAR ACCESO</a>
                            <a class="knd-portal-btn knd-portal-btn-ghost" href="#">VER SISTEMA VISUAL</a>
                        </div>
                    </div>

                    <aside class="knd-portal-status-card knd-portal-status-panel" aria-label="System Status">
                        <div class="knd-portal-status-header">
                            <span class="knd-portal-status-title">System Status</span>
                            <span class="knd-portal-live-badge">LIVE</span>
                        </div>

                        <ul class="knd-portal-status-list">
                            <li class="knd-portal-status-item"><span>KND Labs</span><strong>Ready</strong></li>
                            <li class="knd-portal-status-item"><span>Arena</span><strong>Idle</strong></li>
                            <li class="knd-portal-status-item"><span>Drops</span><strong>Next window</strong></li>
                            <li class="knd-portal-status-item"><span>Services</span><strong>Online</strong></li>
                            <li class="knd-portal-status-item"><span>Apparel</span><strong>Preview</strong></li>
                            <li class="knd-portal-status-item"><span>Environment</span><strong>Prototype</strong></li>
                        </ul>
                    </aside>
                </section>
            </main>

            <footer class="knd-portal-footer" aria-label="Portal Footer">
                <div class="knd-portal-footer-grid">
                    <section class="knd-portal-footer-col">
                        <h3 class="knd-portal-footer-title">Knowledge</h3>
                        <ul class="knd-portal-footer-links">
                            <li><a href="/faq.php">FAQ</a></li>
                            <li><a href="/contact.php">Contact</a></li>
                            <li><a href="/privacy.php">Privacy</a></li>
                            <li><a href="/game-fairness.php">Game Fairness</a></li>
                            <li><a href="/privacy.php#cookies">Cookies</a></li>
                            <li><a href="/track-order.php">Track Order</a></li>
                            <li><a href="#">Cookie Settings</a></li>
                        </ul>
                    </section>

                    <section class="knd-portal-footer-col">
                        <h3 class="knd-portal-footer-title">Tools</h3>
                        <ul class="knd-portal-footer-links">
                            <li><a href="/labs">KND Labs</a></li>
                        </ul>
                    </section>

                    <section class="knd-portal-footer-col">
                        <h3 class="knd-portal-footer-title">Arena</h3>
                        <ul class="knd-portal-footer-links">
                            <li><a href="/arena">KND Arena</a></li>
                            <li><a href="/how-knd-arena-works">How Arena Works</a></li>
                        </ul>
                    </section>

                    <section class="knd-portal-footer-col">
                        <h3 class="knd-portal-footer-title">Contact</h3>
                        <ul class="knd-portal-footer-contact-list">
                            <li>
                                <span class="knd-portal-footer-icon" aria-hidden="true">✉</span>
                                <span>info@kndstore.com</span>
                            </li>
                            <li>
                                <span class="knd-portal-footer-icon" aria-hidden="true">🎧</span>
                                <span>24/7 Support</span>
                            </li>
                            <li>
                                <span class="knd-portal-footer-icon" aria-hidden="true">◎</span>
                                <span>Discord: KND Store</span>
                            </li>
                        </ul>
                    </section>

                    <section class="knd-portal-footer-col">
                        <h3 class="knd-portal-footer-title">Payment Methods</h3>
                        <ul class="knd-portal-footer-payments">
                            <li><span class="knd-portal-footer-icon" aria-hidden="true">◍</span><span>PayPal</span></li>
                            <li><span class="knd-portal-footer-icon" aria-hidden="true">◈</span><span>Visa</span></li>
                            <li><span class="knd-portal-footer-icon" aria-hidden="true">⬢</span><span>Binance</span></li>
                            <li><span class="knd-portal-footer-icon" aria-hidden="true">▦</span><span>ACH Transfer</span></li>
                        </ul>
                    </section>
                </div>
            </footer>
        </div>
    </div>

    <script src="assets/js/command-portal.js"></script>
</body>
</html>

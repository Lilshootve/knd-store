<?php
// KND Command Portal - Prototipo aislado
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>KND Command Portal — Prototype</title>
    <meta name="description" content="Prototype: KND Command Portal — Create • Play • Collect.">

    <!-- Fuentes (aisladas del proyecto principal) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">

    <!-- Bootstrap (solo para dropdown del portal aislado) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

    <!-- Sistema visual del portal (FASE 1) -->
    <link rel="stylesheet" href="assets/css/portal-reset.css">
    <link rel="stylesheet" href="assets/css/portal-base.css">
    <link rel="stylesheet" href="assets/css/portal-layout.css">
    <link rel="stylesheet" href="assets/css/portal-components.css">
    <link rel="stylesheet" href="assets/css/portal-animations.css">
    <link rel="stylesheet" href="assets/css/portal-responsive.css">
</head>
<body class="portal-body">
    <div class="portal-root">
        <div class="portal-bg-layer" aria-hidden="true">
            <video
                class="portal-global-video"
                src="assets/videos/background.mp4"
                autoplay
                muted
                loop
                playsinline
                preload="auto"
            ></video>
            <div class="portal-bg-overlay"></div>
        </div>

        <div class="portal-ui-layer">
            <!-- Shell general del portal (aún sin hero final de FASE 2) -->
            <header class="portal-shell-header">
                <div class="portal-brand-lockup">
                    <img src="assets/images/knd-logo.png" alt="KND Logo" class="portal-brand-logo" width="68" height="68">
                    <div class="portal-brand-text">
                        <span class="portal-brand-title">KND Store</span>
                        <span class="portal-brand-subtitle">Knowledge ’N Development</span>
                    </div>
                </div>
                <nav class="portal-shell-nav" aria-label="Prototype nav">
                    <div class="knd-portal dropdown">
                        <button
                            class="btn knd-portal-btn dropdown-toggle"
                            type="button"
                            data-bs-toggle="dropdown"
                            aria-expanded="false"
                        >
                            Command Portal
                        </button>

                        <ul class="dropdown-menu dropdown-menu-end knd-portal-menu">
                            <li><a class="dropdown-item" href="/labs">Labs</a></li>
                            <li><a class="dropdown-item" href="/arena">Arena</a></li>
                            <li><a class="dropdown-item" href="/services">Services</a></li>
                            <li><a class="dropdown-item" href="/apparel">Apparel</a></li>

                            <li><hr class="dropdown-divider"></li>

                            <li><a class="dropdown-item" href="/profile">My Profile</a></li>
                            <li><a class="dropdown-item" href="/notifications">Notifications</a></li>
                            <li><a class="dropdown-item" href="/points">Points</a></li>

                            <li><hr class="dropdown-divider"></li>

                            <li><a class="dropdown-item" href="/support">Support</a></li>
                            <li><a class="dropdown-item logout-item" href="/logout">Logout</a></li>
                        </ul>
                    </div>
                </nav>
            </header>

            <main class="portal-shell-main">
            <!-- Escena hero con fondo galáctico multicapa -->
            <section class="portal-section portal-section--hero-placeholder portal-hero-galaxy">
                <div class="portal-section-inner portal-hero-content">
                    <div class="portal-stack gap-lg align-start">
                        <div class="portal-hero-label">
                            <span class="portal-kicker">Prototype Environment</span>
                            <span class="portal-tagline">Create • Play • Collect</span>
                        </div>
                        <h1 class="portal-hero-heading">
                            KND Command Portal
                        </h1>
                        <p class="portal-hero-subcopy">
                            FASE 1 — Sistema visual base: tipografía, paleta, superficies, brillos y HUD listos para la escena inmersiva.
                        </p>
                        <div class="portal-row gap-md">
                            <button type="button" class="portal-btn portal-btn-primary">
                                <span>Simular acceso</span>
                            </button>
                            <button type="button" class="portal-btn portal-btn-ghost">
                                <span>Ver sistema visual</span>
                            </button>
                        </div>
                    </div>

                    <div class="portal-panel portal-panel--glass portal-panel--status">
                        <div class="portal-panel-header">
                            <span class="portal-panel-label">System Status</span>
                            <span class="portal-pill portal-pill-live">LIVE</span>
                        </div>
                        <div class="portal-panel-body portal-grid portal-grid--metrics">
                            <div class="portal-metric">
                                <span class="portal-metric-label">KND Labs</span>
                                <span class="portal-metric-value">Ready</span>
                            </div>
                            <div class="portal-metric">
                                <span class="portal-metric-label">Arena</span>
                                <span class="portal-metric-value">Idle</span>
                            </div>
                            <div class="portal-metric">
                                <span class="portal-metric-label">Drops</span>
                                <span class="portal-metric-value">Next window</span>
                            </div>
                            <div class="portal-metric">
                                <span class="portal-metric-label">Services</span>
                                <span class="portal-metric-value">Online</span>
                            </div>
                            <div class="portal-metric">
                                <span class="portal-metric-label">Apparel</span>
                                <span class="portal-metric-value">Preview</span>
                            </div>
                            <div class="portal-metric">
                                <span class="portal-metric-label">Environment</span>
                                <span class="portal-metric-value">Prototype</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Sección posterior demo: Labs con fondo galáctico suave -->
            <section class="portal-section portal-section-labs-bg">
                <div class="portal-section-inner">
                    <div class="portal-stack gap-md align-start">
                        <div class="portal-hero-label">
                            <span class="portal-kicker">Labs Preview</span>
                            <span class="portal-tagline">KND Labs • Experimental</span>
                        </div>
                        <h2 class="portal-hero-heading portal-hero-heading-sm">
                            Create: Visual & 3D Labs
                        </h2>
                        <p class="portal-hero-subcopy">
                            Espacio de pruebas para prompts, texturas, personajes y 3D conectados al ecosistema KND. Esta sección es una demo visual de fondo galáctico para futuros módulos.
                        </p>
                    </div>
                    <div class="portal-panel portal-panel--glass">
                        <div class="portal-panel-header">
                            <span class="portal-panel-label">Prototype Layer</span>
                            <span class="portal-pill portal-pill-live">Labs</span>
                        </div>
                        <div class="portal-panel-body">
                            <p>
                                Aquí más adelante vivirá un carrusel de experimentos, presets y escenas de KND Labs. Por ahora, el foco es validar el comportamiento del fondo espacial y el contraste del contenido.
                            </p>
                        </div>
                    </div>
                </div>
            </section>
            </main>

            <footer class="portal-shell-footer">
                <span class="portal-footer-text">
                    KND Command Portal Prototype — FASE 1 · Visual System
                </span>
            </footer>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

    <!-- JS del portal (se rellenará en FASE 2–3) -->
    <script src="assets/js/portal-core.js"></script>
    <script src="assets/js/portal-carousel.js"></script>
    <script src="assets/js/portal-parallax.js"></script>
    <script src="assets/js/portal-particles.js"></script>
    <script src="assets/js/portal-nav.js"></script>
</body>
</html>


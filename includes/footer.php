<?php
// KND Store - Footer común

// Función para generar el footer completo
// $skipMarketingShell: app-style pages (e.g. dashboard) — omit marketing footer; keep cookie consent UI.
function generateFooter($skipMarketingShell = false) {
    ob_start();
    if (!$skipMarketingShell) {
    ?>
    <!-- Footer -->
    <footer class="footer knd-footer-shell site-footer-wrap py-4 position-relative z-2">
        <div class="knd-footer-hero">
            <div class="footer-brand">⬡ KND — <?= t('footer.tagline_knd', "Knowledge 'N Development") ?></div>
            <div class="footer-sub"><?= t('footer.tagline_sub', 'Where digital innovation begins') ?></div>
        </div>

        <div class="knd-footer-meta position-relative z-2">
            <p class="knd-footer-meaning">
                <?= defined('KND_MEANING_TEXT')
                    ? KND_MEANING_TEXT
                    : 'KND = <strong>Knowledge ‘N Development</strong> — knowledge turned into fast, secure, innovative digital solutions.' ?>
            </p>
            <p class="knd-footer-legal">
                <a href="/privacy.php"><?= t('footer.navigation.privacy') ?></a>
                <span class="knd-footer-dot" aria-hidden="true">·</span>
                <a href="/privacy.php#cookies-policy"><?= t('footer.navigation.cookies') ?></a>
                <span class="knd-footer-dot" aria-hidden="true">·</span>
                <a href="#" class="knd-cookie-settings-link"><?= t('footer.navigation.cookie_settings') ?></a>
                <span class="knd-footer-dot" aria-hidden="true">·</span>
                <a href="/contact.php"><?= t('footer.navigation.contact') ?></a>
            </p>
            <p class="knd-footer-copy"><?= t('footer.copyright', null, ['year' => date('Y')]) ?></p>
        </div>

        <!-- Efecto de partículas para el footer -->
        <div id="particles-footer" class="position-absolute top-0 left-0 w-100 h-100" style="z-index: 1;"></div>
    </footer>
    <?php
    }
    ?>

    <!-- Cookie Consent Banner & Modal -->
    <div id="knd-cookie-banner" class="knd-cookie-banner">
        <div class="knd-cookie-banner-inner glass-card-neon">
            <div class="knd-cookie-banner-text">
                <h5 class="mb-2" data-knd-cookie-title><?= t('cookie.title') ?></h5>
                <p class="mb-0 small" data-knd-cookie-message><?= t('cookie.message') ?></p>
            </div>
            <div class="knd-cookie-banner-actions">
                <button type="button" id="knd-cookie-reject-all" class="btn btn-sm btn-outline-light me-2"><?= t('cookie.btn.reject_all') ?></button>
                <button type="button" id="knd-cookie-customize" class="btn btn-sm btn-outline-neon me-2"><?= t('cookie.btn.customize') ?></button>
                <button type="button" id="knd-cookie-accept-all" class="btn btn-sm btn-neon-primary"><?= t('cookie.btn.accept_all') ?></button>
            </div>
        </div>
    </div>

    <div id="knd-cookie-modal" class="knd-cookie-modal">
        <div class="knd-cookie-modal-backdrop"></div>
        <div class="knd-cookie-modal-dialog glass-card-neon">
            <div class="knd-cookie-modal-header d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0" data-knd-cookie-title><?= t('cookie.title') ?></h5>
                <button type="button" class="btn btn-sm btn-outline-light" data-knd-cookie-close>&times;</button>
            </div>
            <div class="knd-cookie-modal-body">
                <p class="small mb-3" data-knd-cookie-message><?= t('cookie.message') ?></p>

                <div class="knd-cookie-category mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong><?= t('cookie.category.necessary') ?></strong>
                            <p class="small mb-0 text-white-50"><?= t('cookie.category.necessary_desc') ?></p>
                        </div>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" checked disabled>
                        </div>
                    </div>
                </div>

                <div class="knd-cookie-category mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong><?= t('cookie.category.preferences') ?></strong>
                            <p class="small mb-0 text-white-50"><?= t('cookie.category.preferences_desc') ?></p>
                        </div>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="knd-consent-preferences">
                        </div>
                    </div>
                </div>

                <div class="knd-cookie-category mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong><?= t('cookie.category.analytics') ?></strong>
                            <p class="small mb-0 text-white-50"><?= t('cookie.category.analytics_desc') ?></p>
                        </div>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="knd-consent-analytics">
                        </div>
                    </div>
                </div>

                <div class="knd-cookie-category mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong><?= t('cookie.category.marketing') ?></strong>
                            <p class="small mb-0 text-white-50"><?= t('cookie.category.marketing_desc') ?></p>
                        </div>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="knd-consent-marketing">
                        </div>
                    </div>
                </div>

                <p class="small text-white-50 mt-3 mb-0"><?= t('cookie.note') ?></p>
            </div>

            <div class="knd-cookie-modal-footer mt-3 d-flex flex-column flex-md-row justify-content-between align-items-stretch">
                <button type="button" id="knd-cookie-modal-reject-all" class="btn btn-outline-light mb-2 mb-md-0 flex-fill me-md-2"><?= t('cookie.btn.reject_all') ?></button>
                <button type="button" id="knd-cookie-save-preferences" class="btn btn-neon-primary flex-fill"><?= t('cookie.btn.save_preferences') ?></button>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// Función para generar los scripts comunes
// $appShell: signed-in app surfaces — skip particles, starfield, holo, support widget, XP/confetti fluff.
function generateScripts($appShell = false) {
    $scripts = '';

    $phpSelfNav = $_SERVER['PHP_SELF'] ?? '';
    $isAdminScripts = (strpos($phpSelfNav, '/admin') !== false || strpos($phpSelfNav, 'admin/') === 0);

    if ($appShell) {
        $ds = __DIR__ . '/../assets/js/dashboard-shell.js';
        $dsV = file_exists($ds) ? filemtime($ds) : 0;
        $scripts .= '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>' . "\n";
        $scripts .= '<script src="/assets/js/cookies-consent.js" defer></script>' . "\n";
        $scripts .= '<script src="/assets/js/dashboard-shell.js?v=' . (int) $dsV . '" defer></script>' . "\n";
        $scripts .= '</body>' . "\n";
        $scripts .= '</html>' . "\n";
        return $scripts;
    }

    if (!$isAdminScripts && session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['dr_user_id'])) {
        require_once __DIR__ . '/holo_orb_assets.php';
        ob_start();
        holo_orb_emit_init_script();
        $scripts .= ob_get_clean();
    }

    // Level-up y badge (defer para no bloquear render)
    $scripts .= '<script src="/assets/js/knd-xp-fx.js" defer></script>' . "\n";
    $scripts .= '<script src="/assets/js/level-up.js" defer></script>' . "\n";
    
    // jQuery con preload
    $scripts .= '<script src="https://code.jquery.com/jquery-3.6.0.min.js" defer></script>' . "\n";
    
    // Bootstrap JS con preload
    $scripts .= '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>' . "\n";
    // Account / orders dropdown (knd-dropdown-toggle) — required on all pages using generateScripts()
    $ne = __DIR__ . '/../assets/js/navigation-extend.js';
    $scripts .= '<script src="/assets/js/navigation-extend.js?v=' . (file_exists($ne) ? filemtime($ne) : 0) . '" defer></script>' . "\n";
    
    // Particles.js con preload
    $scripts .= '<script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js" defer></script>' . "\n";
    
    // Header dinámico (scroll: ocultar/mostrar, estado scrolled)
    $scripts .= '<script src="/assets/js/header-dynamic.js" defer></script>' . "\n";
    $sf = __DIR__ . '/../assets/js/knd-starfield.js';
    $scripts .= '<script src="/assets/js/knd-starfield.js?v=' . (file_exists($sf) ? filemtime($sf) : 0) . '" defer></script>' . "\n";
    // Custom JS optimizado
    $scripts .= '<script src="/assets/js/main.js" defer></script>' . "\n";
    
    // Gestor de consentimiento de cookies
    $scripts .= '<script src="/assets/js/cookies-consent.js" defer></script>' . "\n";
    
    // Mobile Optimization JS
    $scripts .= '<script src="/assets/js/mobile-optimization.js" defer></script>' . "\n";
    
    // Confetti (Legendary) - toast/xp-fx/level-up loaded in header for early availability
    $scripts .= '<script src="/assets/js/knd-confetti.js" defer></script>' . "\n";
    
    // Partículas footer (único canvas, deferred para no bloquear render)
    $scripts .= '<script>' . "\n";
    $scripts .= 'var _particlesInited = false;' . "\n";
    $scripts .= 'function initParticles() {' . "\n";
    $scripts .= '  if (document.body && document.body.classList.contains("arena-info-page")) return;' . "\n";
    $scripts .= '  if (_particlesInited) return;' . "\n";
    $scripts .= '  if (typeof particlesJS === "undefined") { setTimeout(initParticles, 150); return; }' . "\n";
    $scripts .= '  var el = document.getElementById("particles-footer");' . "\n";
    $scripts .= '  if (!el) return;' . "\n";
    $scripts .= '  _particlesInited = true;' . "\n";
    $scripts .= '  ' . "\n";
    $scripts .= '  particlesJS("particles-footer", {' . "\n";
    $scripts .= '  particles: {' . "\n";
    $scripts .= '    number: { value: 45, density: { enable: true, value_area: 900 } },' . "\n";
    $scripts .= '    color: { value: ["#22D3EE", "#D6CFC7", "#A1A1A1"] },' . "\n";
    $scripts .= '    shape: {' . "\n";
    $scripts .= '      type: "circle",' . "\n";
    $scripts .= '      stroke: {' . "\n";
    $scripts .= '        width: 0,' . "\n";
    $scripts .= '        color: "#000000"' . "\n";
    $scripts .= '      },' . "\n";
    $scripts .= '      polygon: {' . "\n";
    $scripts .= '        nb_sides: 5' . "\n";
    $scripts .= '      }' . "\n";
    $scripts .= '    },' . "\n";
    $scripts .= '    opacity: { value: 0.65,' . "\n";
    $scripts .= '      random: false,' . "\n";
    $scripts .= '      anim: {' . "\n";
    $scripts .= '        enable: false,' . "\n";
    $scripts .= '        speed: 1,' . "\n";
    $scripts .= '        opacity_min: 0.1,' . "\n";
    $scripts .= '        sync: false' . "\n";
    $scripts .= '      }' . "\n";
    $scripts .= '    },' . "\n";
    $scripts .= '    size: {' . "\n";
    $scripts .= '      value: 3,' . "\n";
    $scripts .= '      random: true,' . "\n";
    $scripts .= '      anim: {' . "\n";
    $scripts .= '        enable: false,' . "\n";
    $scripts .= '        speed: 40,' . "\n";
    $scripts .= '        size_min: 0.1,' . "\n";
    $scripts .= '        sync: false' . "\n";
    $scripts .= '      }' . "\n";
    $scripts .= '    },' . "\n";
    $scripts .= '    line_linked: { enable: true, distance: 130, color: "#22D3EE", opacity: 0.45, width: 1 },' . "\n";
    $scripts .= '    move: { enable: true, speed: 4,' . "\n";
    $scripts .= '      direction: "none",' . "\n";
    $scripts .= '      random: false,' . "\n";
    $scripts .= '      straight: false,' . "\n";
    $scripts .= '      out_mode: "out",' . "\n";
    $scripts .= '      bounce: false,' . "\n";
    $scripts .= '      attract: {' . "\n";
    $scripts .= '        enable: false,' . "\n";
    $scripts .= '        rotateX: 600,' . "\n";
    $scripts .= '        rotateY: 1200' . "\n";
    $scripts .= '      }' . "\n";
    $scripts .= '    }' . "\n";
    $scripts .= '  },' . "\n";
    $scripts .= '  interactivity: { detect_on: "canvas", events: { resize: true } },' . "\n";
    $scripts .= '  retina_detect: true });' . "\n";
    $scripts .= '}' . "\n";
    $scripts .= 'function scheduleParticles() {' . "\n";
    $scripts .= '  if (document.body && document.body.classList.contains("arena-info-page")) return;' . "\n";
    $scripts .= '  var el = document.getElementById("particles-footer");' . "\n";
    $scripts .= '  if (!el) return;' . "\n";
    $scripts .= '  if ("IntersectionObserver" in window) {' . "\n";
    $scripts .= '    var io = new IntersectionObserver(function(entries) {' . "\n";
    $scripts .= '      if (entries[0].isIntersecting) { initParticles(); io.disconnect(); }' . "\n";
    $scripts .= '    }, { rootMargin: "100px", threshold: 0 });' . "\n";
    $scripts .= '    io.observe(el);' . "\n";
    $scripts .= '    setTimeout(function() { if (typeof initParticles === "function") initParticles(); io.disconnect(); }, 3000);' . "\n";
    $scripts .= '  } else {' . "\n";
    $scripts .= '    window.addEventListener("load", function() { setTimeout(initParticles, 400); });' . "\n";
    $scripts .= '  }' . "\n";
    $scripts .= '}' . "\n";
    $scripts .= 'if (document.readyState === "loading") { document.addEventListener("DOMContentLoaded", scheduleParticles); }' . "\n";
    $scripts .= 'else { scheduleParticles(); }' . "\n";
    $scripts .= '</script>' . "\n";
    
    // Panel de personalización de colores
    // Support AI Chat Widget
    $scripts .= '<link rel="stylesheet" href="/assets/css/support-chat.css">' . "\n";
    $scripts .= '<button id="knd-chat-btn" class="knd-chat-btn" title="Support" aria-label="Open support chat"><i class="fas fa-headset"></i></button>' . "\n";
    $scripts .= '<div id="knd-chat-panel" class="knd-chat-panel">' . "\n";
    $scripts .= '  <div class="knd-chat-header">' . "\n";
    $scripts .= '    <div class="knd-chat-header-info"><h4>KND Support</h4><span>AI assistant &bull; 24/7</span></div>' . "\n";
    $scripts .= '    <button class="knd-chat-close" aria-label="Close">&times;</button>' . "\n";
    $scripts .= '  </div>' . "\n";
    $scripts .= '  <div class="knd-chat-messages"></div>' . "\n";
    $scripts .= '  <div class="knd-chat-typing"><span></span><span></span><span></span></div>' . "\n";
    $scripts .= '  <div class="knd-chat-quick">' . "\n";
    $scripts .= '    <button class="knd-chat-quick-btn" data-msg="What payment methods do you accept?">Payment</button>' . "\n";
    $scripts .= '    <button class="knd-chat-quick-btn" data-msg="How long does delivery take?">Delivery</button>' . "\n";
    $scripts .= '    <button class="knd-chat-quick-btn" data-msg="How do I choose the right size?">Sizing</button>' . "\n";
    $scripts .= '    <button class="knd-chat-quick-btn" data-msg="What is your refund policy?">Refunds</button>' . "\n";
    $scripts .= '    <button class="knd-chat-quick-btn" data-msg="How can I reach human support?">Contact</button>' . "\n";
    $scripts .= '  </div>' . "\n";
    $scripts .= '  <div class="knd-chat-input-bar">' . "\n";
    $scripts .= '    <textarea class="knd-chat-input" placeholder="Type a message..." rows="1"></textarea>' . "\n";
    $scripts .= '    <button class="knd-chat-send" aria-label="Send"><i class="fas fa-paper-plane"></i></button>' . "\n";
    $scripts .= '  </div>' . "\n";
    $scripts .= '</div>' . "\n";
    $scripts .= '<script src="/assets/js/support-chat.js" defer></script>' . "\n";

    
    $scripts .= '</body>' . "\n";
    $scripts .= '</html>' . "\n";
    
    return $scripts;
}
?> 
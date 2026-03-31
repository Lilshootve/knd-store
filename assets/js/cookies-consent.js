// KND Store - Gestor de consentimiento de cookies

const KND_COOKIE_CONSENT_KEY = 'knd_cookie_consent_v1';
const KND_COOKIE_CONSENT_MAX_AGE_DAYS = 180;

const KND_COOKIE_TRANSLATIONS = {
    es: {
        title: 'Control de Cookies',
        message: 'Usamos cookies para funciones esenciales y para mejorar tu experiencia. Puedes aceptar, rechazar o personalizar.',
        buttons: {
            acceptAll: 'Aceptar todo',
            rejectAll: 'Rechazar',
            customize: 'Personalizar',
            save: 'Guardar preferencias'
        },
        categories: {
            necessary: 'Necesarias (siempre activas)',
            preferences: 'Preferencias',
            analytics: 'Analíticas',
            marketing: 'Marketing'
        },
        note: 'Puedes cambiar tu decisión en cualquier momento desde “Cookie Settings”.'
    }
    // EN u otros idiomas se pueden añadir aquí más adelante
};

function kndGetLocale() {
    // Por ahora solo ES; estructura lista para multi-idioma
    return 'es';
}

function getCookieConsent() {
    try {
        const raw = localStorage.getItem(KND_COOKIE_CONSENT_KEY);
        if (!raw) return null;
        const data = JSON.parse(raw);
        if (!data || !data.timestamp) return null;
        const now = Date.now();
        const ageDays = (now - data.timestamp) / (1000 * 60 * 60 * 24);
        if (ageDays > KND_COOKIE_CONSENT_MAX_AGE_DAYS) {
            localStorage.removeItem(KND_COOKIE_CONSENT_KEY);
            return null;
        }
        return data;
    } catch (e) {
        console.error('Error leyendo consentimiento de cookies', e);
        return null;
    }
}

function setCookieConsent(consent) {
    const payload = {
        version: '1.0.0',
        timestamp: Date.now(),
        categories: {
            necessary: true,
            preferences: !!consent.categories?.preferences,
            analytics: !!consent.categories?.analytics,
            marketing: !!consent.categories?.marketing
        }
    };
    localStorage.setItem(KND_COOKIE_CONSENT_KEY, JSON.stringify(payload));
    return payload;
}

function hasConsent(category) {
    if (category === 'necessary') return true;
    const consent = getCookieConsent();
    if (!consent || !consent.categories) return false;
    return !!consent.categories[category];
}

function runConsentScripts() {
    try {
        if (hasConsent('analytics') && typeof window.KND_ANALYTICS_INIT === 'function') {
            window.KND_ANALYTICS_INIT();
        }
        if (hasConsent('marketing') && typeof window.KND_MARKETING_INIT === 'function') {
            window.KND_MARKETING_INIT();
        }
    } catch (e) {
        console.error('Error ejecutando scripts condicionados por consentimiento', e);
    }
}

function kndShowCookieBanner() {
    const banner = document.getElementById('knd-cookie-banner');
    if (!banner) return;
    banner.classList.add('knd-cookie-banner-visible');
}

function kndHideCookieBanner() {
    const banner = document.getElementById('knd-cookie-banner');
    if (!banner) return;
    banner.classList.remove('knd-cookie-banner-visible');
}

function kndOpenCookieModal() {
    const modal = document.getElementById('knd-cookie-modal');
    if (!modal) return;
    modal.classList.add('knd-cookie-modal-visible');
}

function kndCloseCookieModal() {
    const modal = document.getElementById('knd-cookie-modal');
    if (!modal) return;
    modal.classList.remove('knd-cookie-modal-visible');
}

function kndApplyConsentToUI(consent) {
    const prefs = consent?.categories || {};
    const prefInput = document.getElementById('knd-consent-preferences');
    const analInput = document.getElementById('knd-consent-analytics');
    const markInput = document.getElementById('knd-consent-marketing');
    if (prefInput) prefInput.checked = !!prefs.preferences;
    if (analInput) analInput.checked = !!prefs.analytics;
    if (markInput) markInput.checked = !!prefs.marketing;
}

function initCookieConsentUI() {
    const banner = document.getElementById('knd-cookie-banner');
    const modal = document.getElementById('knd-cookie-modal');
    if (!banner || !modal) return;

    // Los textos ya vienen traducidos desde PHP en el HTML (data-* attributes)
    // Solo actualizamos si window.I18N está disponible y hay cambios dinámicos
    // Los elementos con data-knd-cookie-title y data-knd-cookie-message ya tienen el texto correcto desde PHP

    const btnAcceptAll = document.getElementById('knd-cookie-accept-all');
    const btnRejectAll = document.getElementById('knd-cookie-reject-all');
    const btnCustomize = document.getElementById('knd-cookie-customize');
    const btnSave = document.getElementById('knd-cookie-save-preferences');
    const btnModalRejectAll = document.getElementById('knd-cookie-modal-reject-all');
    const settingsLinks = document.querySelectorAll('.knd-cookie-settings-link');
    const closeButtons = document.querySelectorAll('[data-knd-cookie-close]');

    function saveConsentFromInputs(opts) {
        const prefInput = document.getElementById('knd-consent-preferences');
        const analInput = document.getElementById('knd-consent-analytics');
        const markInput = document.getElementById('knd-consent-marketing');
        const consent = setCookieConsent({
            categories: {
                preferences: opts?.all ? true : !!prefInput?.checked,
                analytics: opts?.all ? true : !!analInput?.checked,
                marketing: opts?.all ? true : !!markInput?.checked
            }
        });
        kndApplyConsentToUI(consent);
        kndHideCookieBanner();
        kndCloseCookieModal();
        runConsentScripts();
    }

    if (btnAcceptAll) {
        btnAcceptAll.addEventListener('click', () => {
            saveConsentFromInputs({ all: true });
        });
    }

    if (btnRejectAll) {
        btnRejectAll.addEventListener('click', () => {
            const consent = setCookieConsent({
                categories: {
                    preferences: false,
                    analytics: false,
                    marketing: false
                }
            });
            kndApplyConsentToUI(consent);
            kndHideCookieBanner();
            kndCloseCookieModal();
        });
    }

    if (btnModalRejectAll) {
        btnModalRejectAll.addEventListener('click', () => {
            const consent = setCookieConsent({
                categories: {
                    preferences: false,
                    analytics: false,
                    marketing: false
                }
            });
            kndApplyConsentToUI(consent);
            kndHideCookieBanner();
            kndCloseCookieModal();
        });
    }

    if (btnCustomize) {
        btnCustomize.addEventListener('click', () => {
            kndOpenCookieModal();
        });
    }

    if (btnSave) {
        btnSave.addEventListener('click', () => {
            saveConsentFromInputs();
        });
    }

    settingsLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            kndHideCookieBanner();
            kndOpenCookieModal();
        });
    });

    closeButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            kndCloseCookieModal();
        });
    });

    const existingConsent = getCookieConsent();
    if (existingConsent) {
        kndApplyConsentToUI(existingConsent);
        kndHideCookieBanner();
        runConsentScripts();
    } else {
        kndShowCookieBanner();
    }
}

// Inicializar cuando el DOM esté listo
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCookieConsentUI);
} else {
    initCookieConsentUI();
}

// Exponer helpers globales por si se necesitan en otras partes
window.KND_COOKIE = {
    getCookieConsent,
    setCookieConsent,
    hasConsent,
    runConsentScripts,
    openSettings: () => {
        kndOpenCookieModal();
    }
};

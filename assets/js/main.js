// KND Store - JavaScript Principal

// La verificación de Font Awesome ahora se maneja en header.php
// No necesitamos duplicar la funcionalidad aquí

// Helper para traducciones JS
function tJs(key, fallback) {
    return (window.I18N && window.I18N[key]) || fallback || key;
}

// Función para inicializar la aplicación
function initApp() {
    // Inicializar otros componentes
    initScrollEffects();
    initColorPanel();
    initMobileOptimizations();
    initAddToOrderButtons();
    initKndChips();
    initKndGenerateBtn();
}

// Chips: toggle is-active en click (Labs, demos)
function initKndChips() {
    document.querySelectorAll('.knd-chip').forEach(function (chip) {
        chip.addEventListener('click', function () {
            var group = chip.closest('.d-flex');
            if (group) {
                group.querySelectorAll('.knd-chip').forEach(function (c) { c.classList.remove('is-active'); });
            }
            chip.classList.add('is-active');
        });
    });
}

// Generate button feedback (Labs workspace)
function initKndGenerateBtn() {
    var btn = document.getElementById('knd-gen-btn');
    if (!btn) return;
    btn.addEventListener('click', function () {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Generating…';
        setTimeout(function () {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-bolt me-2"></i>Generate';
        }, 2500);
    });
}

// Inicializar botones "Añadir al pedido"
function initAddToOrderButtons() {
    document.querySelectorAll('.add-to-order').forEach(btn => {
        btn.addEventListener('click', function() {
            const item = {
                id: parseInt(this.dataset.id, 10),
                name: this.dataset.name || '',
                price: parseFloat(this.dataset.price || 0),
                type: this.dataset.type || 'digital'
            };
            
            if (item.id && item.name && item.price > 0) {
                addItemToOrder(item);
                updateOrderBadge();
                // Usar clave de traducción para el mensaje
                const messageKey = 'order.toast_added';
                showOrderNotification(messageKey, 'success');
            }
        });
    });
}

// Efectos de scroll
function initScrollEffects() {
    // Scroll suave para enlaces internos
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const href = this.getAttribute('href');
            if (!href || href === '#') return;
            e.preventDefault();
            try {
                const target = document.querySelector(href);
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            } catch(err) {}
        });
    });
    
    // Efecto parallax desactivado para evitar que el hero se sobreponga sobre otras secciones
}

// Panel de personalización de colores
function initColorPanel() {
    const toggle = document.getElementById('colorPanelToggle');
    const sidebar = document.getElementById('colorPanelSidebar');
    const overlay = document.getElementById('colorPanelOverlay');
    
    if (toggle && sidebar && overlay) {
        toggle.addEventListener('click', () => {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
            toggle.classList.toggle('active');
        });
        
        overlay.addEventListener('click', () => {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
            toggle.classList.remove('active');
        });
        
        // Temas de colores
        document.querySelectorAll('.color-theme').forEach(theme => {
            theme.addEventListener('click', () => {
                document.querySelectorAll('.color-theme').forEach(t => t.classList.remove('active'));
                theme.classList.add('active');
                
                const themeName = theme.dataset.theme;
                applyColorTheme(themeName);
            });
        });
    }
}

// Aplicar tema de colores
function applyColorTheme(themeName) {
    const root = document.documentElement;
    
    const themes = {
        'galactic-blue': {
            '--knd-neon-blue': '#259cae',
            '--knd-electric-purple': '#ae2565',
            '--knd-gunmetal-gray': '#2c2c2c'
        },
        'cyber-green': {
            '--knd-neon-blue': '#66bf5a',
            '--knd-electric-purple': '#70c4e1',
            '--knd-gunmetal-gray': '#69bab0'
        },
        'fire-red': {
            '--knd-neon-blue': '#b43b6a',
            '--knd-electric-purple': '#e67635',
            '--knd-gunmetal-gray': '#bfce17'
        },
        'golden-sun': {
            '--knd-neon-blue': '#ffea00',
            '--knd-electric-purple': '#bed322',
            '--knd-gunmetal-gray': '#321f22'
        },
        'neon-pink': {
            '--knd-neon-blue': '#dca1e3',
            '--knd-electric-purple': '#ffc3a8',
            '--knd-gunmetal-gray': '#e6ffc9'
        },
        'nature-green': {
            '--knd-neon-blue': '#c1eeaf',
            '--knd-electric-purple': '#6ba166',
            '--knd-gunmetal-gray': '#145926'
        },
        'ice-blue': {
            '--knd-neon-blue': '#07eef2',
            '--knd-electric-purple': '#24d2db',
            '--knd-gunmetal-gray': '#000000'
        }
    };
    
    if (themes[themeName]) {
        Object.entries(themes[themeName]).forEach(([property, value]) => {
            root.style.setProperty(property, value);
        });
        
        // Guardar preferencia
        localStorage.setItem('knd-color-theme', themeName);
    }
}

// Optimizaciones móviles
function initMobileOptimizations() {
    // Detectar si es móvil
    const isMobile = window.innerWidth <= 768;
    
    if (isMobile) {
        // Reducir animaciones en móvil
        document.body.style.setProperty('--animation-duration', '0.3s');
        
        // Optimizar scroll en móvil
        let ticking = false;
        window.addEventListener('scroll', () => {
            if (!ticking) {
                requestAnimationFrame(() => {
                    // Efectos de scroll optimizados para móvil
                    ticking = false;
                });
                ticking = true;
            }
        });
    }
}

// Cargar tema guardado
function loadSavedTheme() {
    const savedTheme = localStorage.getItem('knd-color-theme');
    if (savedTheme) {
        applyColorTheme(savedTheme);
        
        // Marcar como activo en el panel
        const themeElement = document.querySelector(`[data-theme="${savedTheme}"]`);
        if (themeElement) {
            document.querySelectorAll('.color-theme').forEach(t => t.classList.remove('active'));
            themeElement.classList.add('active');
        }
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    initApp();
    loadSavedTheme();
});

// Inicializar cuando la ventana se cargue completamente
window.addEventListener('load', () => {
    // Font Awesome se verifica en header.php
});

// ====== Gestor de pedido simple (localStorage) ======
const KND_ORDER_KEY = 'knd_order_items';

function loadOrderItems() {
    try {
        const raw = localStorage.getItem(KND_ORDER_KEY);
        if (!raw) return [];
        return JSON.parse(raw);
    } catch (e) {
        console.error('Error leyendo pedido desde localStorage', e);
        return [];
    }
}

function saveOrderItems(items) {
    localStorage.setItem(KND_ORDER_KEY, JSON.stringify(items));
}

function addItemToOrder(item) {
    const items = loadOrderItems();
    const index = items.findIndex(i => i.id === item.id);
    if (index !== -1) {
        items[index].qty += 1;
    } else {
        items.push({ ...item, qty: 1 });
    }
    saveOrderItems(items);
    return items;
}

function getOrderTotal(items) {
    return items.reduce((sum, item) => {
        return sum + (item.price * item.qty);
    }, 0);
}

// Build a safe payload for server-side quoting (no local price usage).
function getOrderItemsForQuote(items) {
    return items.map(item => ({
        id: item.id,
        qty: item.qty,
        variants: item.variants || null
    }));
}

// Actualizar un pequeño indicador (opcional) del número de items en el pedido
function updateOrderBadge() {
    const items = loadOrderItems();
    const totalQty = items.reduce((sum, item) => sum + item.qty, 0);
    
    // Actualizar badge en el navbar
    const badge = document.querySelector('#order-count');
    if (badge) {
        if (totalQty > 0) {
            badge.textContent = totalQty;
            badge.style.display = 'inline-flex';
        } else {
            badge.style.display = 'none';
        }
    }
    
}

// Función para mostrar notificación en la parte inferior izquierda
function showOrderNotification(message, type = 'success') {
    // Eliminar notificación anterior si existe
    const existing = document.getElementById('order-notification');
    if (existing) {
        existing.remove();
    }

    // Crear elemento de notificación
    const notification = document.createElement('div');
    notification.id = 'order-notification';
    notification.className = `order-notification order-notification-${type}`;
    // message puede venir ya traducido desde PHP o ser una clave de window.I18N
    const displayMessage = (window.I18N && window.I18N[message]) ? window.I18N[message] : message;
    notification.innerHTML = `
        <div class="order-notification-content">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-info-circle'} me-2"></i>
            <span>${displayMessage}</span>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Mostrar con animación
    setTimeout(() => {
        notification.classList.add('show');
    }, 10);
    
    // Ocultar después de 3 segundos
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 300);
    }, 3000);
}

// Exportar helpers si hiciera falta en el futuro
window.KND_ORDER = {
    loadOrderItems,
    saveOrderItems,
    addItemToOrder,
    getOrderTotal,
    getOrderItemsForQuote,
    updateOrderBadge,
    showOrderNotification
};

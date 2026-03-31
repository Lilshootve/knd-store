# Auditoría y optimización frontend - rendimiento

## 1. Causas de lentitud identificadas

| Causa | Impacto |
|-------|---------|
| **3 canvas de partículas** (header + footer + particles-bg en móvil) | Alto: hasta ~128 partículas, 3 canvases repintando |
| **Partículas header** en navbar (18 partículas, canvas adicional) | Medio: coste extra en todas las páginas |
| **Init partículas bloqueante** (DOMContentLoaded + load) | Medio: compite con render principal |
| **mobile-optimization.js** inyectando partículas en particles-bg en móvil | Alto: canvas extra fullscreen |
| **knd-xp-fx.js y level-up.js sin defer** | Medio: bloquean parsing |
| **Navbar backdrop-filter: blur(20px)** | Medio: coste de composición |
| **console.log en mobile-optimization** | Bajo: overhead en producción |
| **Interactividad partículas footer** (hover, click) | Bajo: cálculos extra |
| **Partículas footer poco visibles** (opacidad/bordes) | UX: efecto no apreciable |

## 2. Archivos modificados

| Archivo | Cambios |
|---------|---------|
| `includes/footer.php` | Partículas: solo footer, deferred, reducidas, sin header |
| `includes/header.php` | Preload particles.js |
| `assets/css/knd-ui.css` | Footer particles opacity 0.7, canvas positioning, navbar blur 12px |
| `assets/js/mobile-optimization.js` | Quitada inyección particles-bg, quitados console.log |
| `docs/FRONTEND-PERF-AUDIT.md` | Este documento |

## 3. Optimizaciones CSS

- **Navbar**: `backdrop-filter: blur(20px)` → `blur(12px)` para reducir coste de composición
- **Footer particles**: `opacity: 0.5` → `0.7` para mejorar visibilidad
- **#particles-footer canvas**: reglas explícitas de posicionamiento para que el canvas cubra bien el footer

## 4. Optimizaciones JS

- **Partículas header eliminadas**: antes se creaba un canvas en el navbar; ahora solo footer
- **Init partículas diferido**: `requestIdleCallback` (o `setTimeout` tras load) para no competir con el render inicial
- **Partículas footer**: 80 → 45, speed 6 → 4, interactividad (hover/click) desactivada
- **mobile-optimization.js**: eliminada `particlesJS("particles-bg")` en móvil y en conexión lenta
- **knd-xp-fx.js, level-up.js**: añadido `defer`
- **console.log**: eliminados en init de mobile-optimization

## 5. Optimizaciones imágenes/assets

- No modificado (preload de particles.js añadido en header)

## 6. Efectos visuales reducidos/limitados

- **Partículas header**: eliminadas
- **Partículas en particles-bg** (móvil): eliminadas
- **Interactividad footer** (repulse, push): desactivada
- **Navbar blur**: 20px → 12px

## 7. Pendiente de revisar

- **style.css (153 KB)**: posible redundancia con knd-ui.css; requiere auditoría más profunda
- **Font Awesome completo**: ~80 KB; valorar subconjunto de iconos usados
- **Color panel**: se carga en todas las páginas; valorar lazy-load al abrir
- **support-chat**: HTML + CSS + JS en todas las páginas; valorar lazy-load del script
- **jQuery**: uso global; confirmar si se puede sustituir o cargar condicionalmente
- **Lazy loading de imágenes**: aplicar `loading="lazy"` donde proceda

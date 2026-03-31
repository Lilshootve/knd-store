# Header dinámico (scroll-aware)

Comportamiento del header/navbar en toda la web:

- **En el hero (top):** semi transparente, backdrop blur, minimalista.
- **Al hacer scroll:** fondo más sólido (`.header-scrolled`), blur sutil, sombra estable.
- **Scroll hacia abajo:** header se oculta con animación vertical (`.header-hidden` → `translateY(-100%)`).
- **Scroll hacia arriba:** header reaparece.
- **Vuelta al top:** vuelve a estado transparente.

## Dónde está colocado

| Qué | Dónde |
|-----|--------|
| Clase e id del nav | `includes/header.php` → `generateNavigation()`: el `<nav>` tiene `site-header` e `id="site-header"`. |
| CSS | `assets/css/header-dynamic.css` (incluido en `generateHeader()` después de `mobile-optimization.css`). |
| JS | `assets/js/header-dynamic.js` (incluido en `includes/footer.php` → `generateScripts()`). |

No hace falta incluir nada en cada página: el header y el footer son comunes, así que el comportamiento aplica en toda la web.

## Clases CSS utilizadas

- `.site-header` – contenedor del navbar (siempre presente).
- `.header-scrolled` – añadida cuando `scrollY > 80` (fondo sólido).
- `.header-hidden` – añadida al bajar, quitada al subir (oculta con `transform: translateY(-100%)`).

## Archivos modificados / agregados

- **Creados:** `assets/css/header-dynamic.css`, `assets/js/header-dynamic.js`, `docs/HEADER-DYNAMIC.md`.
- **Modificados:** `includes/header.php` (clase + id en nav, link al CSS), `includes/footer.php` (script del header dinámico).

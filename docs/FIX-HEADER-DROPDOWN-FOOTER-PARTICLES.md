# Fix: Header dropdown z-index + Footer partículas

## 1. Dropdown My Account detrás del hero

### Conflicto
- `.navbar` en knd-ui.css tenía `overflow: hidden`, recortando el dropdown al extenderlo fuera del header.
- `position: relative` reemplazaba `position: sticky`, empeorando el apilamiento.

### Archivos
- `assets/css/knd-ui.css`

### Cambios
- `.navbar`: `overflow: hidden` → `overflow: visible` (para no recortar el dropdown).
- `.navbar`: `position: relative` → `position: sticky; top: 0; z-index: 1020`.
- `.knd-dropdown-menu`: añadido `z-index: 1050 !important`.

El dropdown queda visible sobre el hero.

---

## 2. Partículas del footer

### Situación
En knd-ui.css no se definían reglas para `.footer` y `#particles-footer`, y en algunas situaciones el footer perdía `position: relative` / `overflow: hidden`, afectando al canvas de partículas.

### Archivos
- `assets/css/knd-ui.css`

### Cambios
- `.footer`: añadidos `position: relative !important` y `overflow: hidden !important`.
- `.footer::before`: añadido `z-index: 0`.
- `#particles-footer`: reglas explícitas: `position: absolute`, `top/left 0`, `width/height 100%`, `z-index: 1`, `opacity: 0.5`, `pointer-events: none`.

El script de partículas en footer.php sigue funcionando; el canvas vuelve a verse con la red geométrica.

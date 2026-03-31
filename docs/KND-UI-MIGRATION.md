# KND UI - Migración Visual

## Resumen

Migración del estilo visual de las demos al sitio completo. El diseño premium (fondo oscuro, paneles elegantes, partículas sutiles) es ahora el estilo oficial.

## Archivos modificados

| Archivo | Cambio |
|---------|--------|
| `includes/header.php` | Añade knd-ui.css, theme-color #060A12 |
| `includes/footer.php` | Header particles init, colores footer actualizados |
| `assets/js/main.js` | initKndChips, initKndGenerateBtn |
| `assets/js/sw.js` | knd-ui.css en cache |
| `labs-workspace-demo.php` | Usa knd-ui, breadcrumb a index |
| `docs/KND-DEMO-FUTURE.md` | Actualizado (migración) |
| `docs/KND-UI-MIGRATION.md` | Nuevo (este doc) |

## Archivos eliminados

| Archivo | Motivo |
|---------|--------|
| `home-demo.php` | Sustituido por estilo global en index.php |
| `labs-text-to-image-demo.php` | Demos consolidadas |
| `assets/css/knd-demo-future.css` | Migrado a knd-ui.css |
| `assets/js/knd-demo-future.js` | Lógica movida a main.js / footer.php |

## Nuevo sistema CSS: knd-ui.css

### Variables

- `--knd-bg`, `--knd-bg-alt` – Fondos
- `--knd-surface`, `--knd-surface-alt` – Paneles
- `--knd-border`, `--knd-border-mid` – Bordes
- `--knd-accent`, `--knd-accent-soft`, `--knd-cyan`, `--knd-cyan-soft` – Acentos
- `--knd-violet`, `--knd-magenta` – Secundarios
- `--knd-text`, `--knd-text-alt`, `--knd-muted` – Texto
- `--knd-radius`, `--knd-radius-sm` – Bordes redondeados
- `--knd-shadow`, `--knd-spacing`, `--knd-transition` – Utilidades

### Componentes

- `.knd-panel`, `.knd-panel-soft` – Paneles
- `.knd-btn-primary`, `.knd-btn-secondary` – Botones
- `.knd-input`, `.knd-textarea`, `.knd-select` – Formularios
- `.knd-chip`, `.knd-chip-active` – Chips
- `.knd-badge`, `.knd-badge--success/warning/danger` – Badges
- `.knd-card`, `.knd-grid`, `.knd-section`, `.knd-hero` – Layout
- `.knd-divider`, `.knd-muted` – Utilidades
- `.knd-workspace`, `.knd-canvas`, `.knd-showcase-card` – Labs

### Overrides globales

- `body` – Fondo oscuro
- `#particles-bg` – Gradiente
- `.navbar` – Header premium, grid sutil, glow inferior
- `.footer` – Panel cyan/violet
- `.hero-section`, `.hero-title`, `.text-gradient`
- `.btn-neon-primary`, `.btn-primary`, `.btn-outline-neon`
- `.glass-card-neon`, `.product-card`, `.arena-card`, `.labs-tool-card`

## Organización del estilo

1. **Variables en :root** – Paleta y tokens compartidos
2. **Base** – body, particles-bg
3. **Navbar** – Estilo premium global
4. **Footer** – Ajustes de color
5. **Hero y botones** – Overrides para clases existentes
6. **Cards** – glass-card-neon, product-card, etc.
7. **Componentes** – Clases reutilizables (.knd-panel, etc.)
8. **Workspace** – Layout Labs (knd-workspace, knd-canvas)

## Partículas

- **Header**: Div inyectado por JS, 18 partículas, opacidad 0.12
- **Footer**: Existente, colores actualizados a cyan/violet

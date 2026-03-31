# KND Labs Future UI – Notas de diseño

## Decisiones visuales

1. **Paleta sobria**  
   Fondo `#070B14` y paneles en `#0E1628` para contraste sin saturación. Cyan `#35C2FF` y violeta `#8B5CFF` usados solo en acentos y CTAs.

2. **Iluminación contenida**  
   Glow solo en focus de inputs y botón principal. Bordes con `rgba(90, 180, 255, 0.18)` para tech-look sin exceso.

3. **Proporción 70/20/10**  
   - 70% SaaS: tipografía clara, espaciado, jerarquía y contraste.  
   - 20% sci-fi: bordes finos, toques cyan, chips redondeados.  
   - 10% gaming: gradiente sutil en CTA principal.

4. **Componentes ligeros**  
   Sin glassmorphism fuerte ni blur pesado. Paneles con bordes definidos y fondos opacos.

## Clases reutilizables (`.lfu`)

| Clase | Uso |
|-------|-----|
| `.lfu-panel` | Card con fondo `#0E1628` y borde sutil |
| `.lfu-panel-header` | Título interno del panel |
| `.lfu-section-title` | Label pequeño, uppercase |
| `.lfu-input` / `.lfu-textarea` | Inputs con focus cyan suave |
| `.lfu-label` | Label de formulario |
| `.lfu-chip` | Pills de presets (`.active` para seleccionado) |
| `.lfu-btn-primary` | CTA con gradiente cyan–violeta |
| `.lfu-btn-secondary` | Botón outline |
| `.lfu-toggle-switch` | Switch (`.active` = ON) |
| `.lfu-badge` | Badge de estado (`.completed`, `.processing`, `.failed`) |
| `.lfu-divider` | Línea sutil |
| `.lfu-history-item` | Fila de historial |
| `.lfu-aspect-btn` | Botón de ratio (`.active`) |

## Cómo extender al resto del sitio

- **Scope**: todo bajo `.lfu` para no pisar estilos globales.  
- **Añadir `.lfu`** al wrapper de la página que quieras convertir.  
- **CSS**: incluir `labs-future-ui.css` solo en esas rutas.  
- **JS**: `labs-future-ui.js` para chips, toggles, collapse, etc.

## Archivos

- `labs-text-to-image-demo.php` – Página demo
- `assets/css/labs-future-ui.css` – Sistema de estilos
- `assets/js/labs-future-ui.js` – Microinteracciones

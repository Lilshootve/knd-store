# KND Labs - Reorganización Canvas / Workspace

## Resumen

Reestructuración de KND Labs: Canvas como herramienta principal, Upscale y Consistency System con páginas dedicadas y HUD propio.

## Archivos modificados

| Archivo | Cambio |
|---------|--------|
| `knd-labs.php` | Canvas como card principal (MAIN), workflow "Canvas" |
| `labs/text-to-image.php` | Título "Canvas", layout 3 columnas (workspace) |
| `labs/upscale.php` | Layout 3 columnas workspace |
| `labs/consistency.php` | Layout 3 columnas workspace |
| `labs-workspace-demo.php` | Breadcrumb y título "Canvas" |
| `assets/css/knd-labs.css` | .labs-tool-card-main |
| `includes/lang/en.php` | labs.canvas.*, labs.credits, labs.add_credits, labs.main_tool, labs.wf_canvas |
| `includes/lang/es.php` | Idem |

## Páginas resultantes

| Página | Ruta | Descripción |
|--------|------|-------------|
| **Canvas** | `/labs-text-to-image.php` | Herramienta principal de creación (antes Text→Image) |
| **Upscale** | `/labs-upscale.php` | Escalado 2x/4x con HUD propio |
| **Consistency System** | `/labs-consistency.php` | Generación con estilo/personaje consistente |

## Textos visuales actualizados

- Hub: "Text → Image" → "Canvas" (card principal con badge MAIN)
- Workflow: "Text → Image" → "Canvas"
- Título Canvas: "Main AI creation workspace"
- Breadcrumbs: "Canvas"
- Recent jobs: tool text2img → "Canvas"
- Consistency tip: "Text2Img" → "Canvas"
- Consistency no_ref: "Text2Img" → "Canvas"

## Nombres internos (compatibilidad)

- Ruta: `/labs-text-to-image.php` (sin cambiar)
- Backend: `tool=text2img`, `jobType: 'text2img'`
- API: endpoints sin cambios
- Base de datos: columna `tool` sigue siendo `text2img`

## Tools que podrían migrarse después

- **Character Lab** – Mismo patrón workspace
- **Texture Lab** – Mismo patrón
- **Image → 3D** – Ya tiene página propia (triposr-3d.php)

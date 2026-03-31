# Canvas, Upscale, Consistency - Refinamiento UI/UX

## Archivos modificados

| Archivo | Cambios |
|---------|---------|
| `labs/text-to-image.php` | Layout canvas, botón Generate, prompt/dropdowns knd-*, Recent Creations, drawer Details |
| `labs/upscale.php` | Generate abajo, knd-form, Recent Creations, Details drawer |
| `labs/consistency.php` | Generate abajo, knd-form, Recent Creations, Details drawer |
| `assets/css/knd-labs.css` | .labs-gen-btn, .labs-gen-area, .labs-form controls, .knd-details-drawer |
| `assets/css/knd-ui.css` | .knd-showcase-card__placeholder |
| `assets/js/kndlabs.js` | bindViewDetails tool-aware, reuseJobSettings (text2img/consistency) |
| `api/labs/job.php` | scale, upscale_model para upscale; base_prompt, scene_prompt para consistency |

---

## 1. Botón Generate

**Antes:** Dentro del canvas, junto al preview.

**Después:**
- Colocado **debajo** del canvas principal, en su propio contenedor `.labs-gen-area`
- Centrado con el área central
- Estilo sci-fi minimal premium (`.labs-gen-btn`):
  - Fondo oscuro sutil `rgba(6, 10, 18, 0.7)`
  - Borde fino cyan `rgba(53, 194, 255, 0.25)`
  - Glow mínimo en hover
  - Sin gradientes llamativos

---

## 2. Prompt y dropdowns

- **Prompt:** `knd-textarea` + label `knd-label`
- **Negative prompt:** `knd-input`
- **Quality / Model:** `knd-select`
- **Presets:** botones con `knd-chip`
- **Presets negativos:** `knd-chip`
- **Advanced:** selects `knd-select`, inputs `knd-input`
- **IPAdapter / ControlNet:** mismos estilos

En `knd-labs.css`:
- `.labs-form .knd-textarea`, `.knd-input`, `.knd-select` con fondo, borde y focus refinados
- `.labs-form .form-select` con estilos consistentes

---

## 3. Recent Creations

- Sección **debajo del workspace** (full width)
- Grid `.knd-card-grid` con cards `.knd-showcase-card`
- Cada card: preview/thumbnail, tipo (Canvas), estado, fecha/hora, botón **Details**
- Datos desde `$historyJobs` (misma fuente que el sidebar)
- Si no hay creaciones: mensaje "Generate your first image to see it here."

---

## 4. Details (drawer KND HUD)

**Reemplazo del modal Bootstrap** por un drawer lateral tipo HUD:

- **Backdrop** + **Drawer** (slide desde la derecha)
- **Bloque 1 – Config:** prompt, negative prompt, model, quality, aspect ratio, steps, CFG, sampler
- **Bloque 2 – Result:** preview grande, fecha, estado, cost/credits, mensaje de error si aplica
- **Bloque 3 – Acciones:**
  - Send to Upscale (enlace a `/labs-upscale.php?source_job_id=`)
  - Consistency (enlace a `/labs-consistency.php?reference_job_id=&mode=`)
  - Create Variations (mismo enlace Consistency)
  - Download
  - Reuse Settings (rellena el formulario Canvas con los parámetros del job)

**Reuse Settings** llama a `reuseJobSettings(jid)` que obtiene el job y rellena prompt, negative, model, seed, steps, cfg, width, height, sampler, scheduler.

---

## 5. Acciones

| Acción | Estado | Ruta/Acción |
|--------|--------|-------------|
| Send to Upscale | Funcional | `/labs-upscale.php?source_job_id=...` |
| Consistency | Funcional | `/labs-consistency.php?reference_job_id=...&mode=...` |
| Create Variations | Funcional | Mismo enlace que Consistency |
| Download | Funcional | Atributo `download` + URL de imagen |
| Reuse Settings | Funcional | JS rellena el form con datos del job |

No hay placeholders; todas las acciones usan rutas o lógica existente.

---

## Coherencia visual

- Header/footer sin cambios
- HUD y sistema visual KND preservados
- Estilo más refinado y consistente con la demo original

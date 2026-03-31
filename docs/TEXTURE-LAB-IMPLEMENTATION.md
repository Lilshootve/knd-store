# Texture Lab – Diagnóstico e implementación

## 1) Diagnóstico

### Cómo funciona hoy Texture Lab
- **En el shell de Labs (knd-labs.php):** El partial `shell-texture.php` usa el mismo formulario genérico (`labs-t2i-form`) y `KNDLabs.init({ jobType: 'texture_seamless' })`. Al enviar, el JS manda `tool=texture_seamless` a `/api/labs/generate.php`, que **rechaza** la petición porque solo acepta `tool` en `['text2img', 'upscale', 'character']`. Por tanto, **Texture Lab en el app shell actual no puede completar una generación** vía cola ComfyUI.
- **Páginas sueltas (labs/texture-lab.php, ai-tools.php):** Envían a `/api/ai/submit.php` con `type=texture_seamless`. Esos jobs van a **triposr_jobs** y a un callback GPU externo, no al worker ComfyUI ni a **knd_labs_jobs**.
- **Workflow ComfyUI:** `comfyui_workflow_path('texture')` no existe; el `else` devuelve `KND_MASTER_WORKFLOW_API.json`, es decir el mismo que Text2Img. No hay plantilla específica para texturas.
- **Worker:** No hay rama `tool === 'texture'`; si un job con `tool=texture` llegara a la cola, se procesaría como texto-imagen genérico (rama `else`).

### Qué limita el enfoque actual
- Texture Lab no está integrado con la cola de Labs (`knd_labs_jobs` + worker).
- Se usa el mismo pipeline/workflow que Text2Img (cuando llega a ComfyUI) o un flujo distinto (triposr + GPU externo), sin modo seamless ni parámetros específicos de textura.
- Un solo modo (texto); no hay “image to texture” ni “image + prompt” en el flujo Labs.
- Coste y KP no unificados (4 KP en ai.php para texture_seamless; Labs no tiene coste para texture).

### Qué se cambia y por qué
- **API:** Se añade `tool=texture` a `api/labs/generate.php` (allowed tools, coste 10 KP, construcción de payload con modo texture y opción seamless).
- **Workflows:** Se crean plantillas dedicadas: `workflows/texture_api.json` (text-to-texture) y `workflows/texture_img2img_api.json` (image / image+prompt), y se usan desde `comfyui.php` según presencia de imagen.
- **Worker:** Se refactoriza por handlers (por tool) y se añade un handler específico para `texture` (descarga de imagen si aplica, subida a ComfyUI, inyección del workflow correcto).
- **Frontend:** `shell-texture.php` pasa a enviar `tool=texture` al API de Labs, con modos Text / Image / Image+Prompt, switch seamless, presets y panel de parámetros; se mantiene el layout actual de Labs.
- **Visor y reutilización:** Se trata texture como primer ciudadano en el drawer (etiqueta, “Send to Upscale”, etc.) y se deja preparado para reutilizar la textura en otras tools sin tocar el 3D viewer en esta fase.

---

## 2) Plan técnico (resumen)

- **Arquitectura:** Texture Lab como tool más en `knd_labs_jobs` con `tool='texture'`; mismo flujo lease → worker → complete/fail.
- **Workflows:** texture_api.json (solo prompt) y texture_img2img_api.json (con imagen); inyección en `comfyui_inject_workflow` con prefijo seamless cuando aplique.
- **DB:** Sin nuevas tablas; se usa `payload_json` para modo, seamless y parámetros; una sola salida (image_url/output_path) en el MVP.
- **Endpoints:** Solo cambios en `api/labs/generate.php` (tool texture, 10 KP, imagen opcional para texture).
- **Worker:** Refactor por handlers; handler `texture` que prepara imagen si viene en payload y llama a inyección de workflow texture.
- **UI:** shell-texture con modos, seamless, presets, panel derecho y form que envía `tool=texture` a generate.php.
- **3D viewer / same.new:** No se integra en esta fase; la salida de Texture Lab es imagen; el visor 3D sigue para 3D Lab y Character Lab. Se deja la puerta abierta para reutilizar la textura en esos flujos más adelante.

---

## 3) Riesgos y rollback

- **Riesgos:** Cambios en worker (refactor) pueden afectar a text2img/upscale/consistency si hay un fallo en el dispatch. Nuevos JSON de workflow deben existir en servidor y ser válidos para ComfyUI.
- **Rollback:** Revertir commits de esta implementación; en DB no hay migración destructiva; quitar `texture` de allowed tools en generate.php desactiva Texture Lab sin tocar el resto.

---

## 4) Resumen de cambios realizados

### Archivos creados
- `docs/TEXTURE-LAB-IMPLEMENTATION.md` – Diagnóstico y plan.
- `workflows/texture_api.json` – Workflow text-to-texture (SD 1.5, EmptyLatentImage).
- `workflows/texture_img2img_api.json` – Workflow image/image+prompt to texture (LoadImage + VAEEncode + KSampler).

### Archivos modificados
- `api/labs/generate.php` – tool `texture` permitido, 10 KP, validación por modo, params `texture_mode` y `seamless`, subida de imagen para modos image/image_prompt.
- `includes/comfyui.php` – `comfyui_workflow_path($tool, $params)` con rama texture (texture_api vs texture_img2img), inyección de prompt con prefijo seamless y SaveImage prefix para texture.
- `workers/labs_worker.php` – `payload['job_id']` para texture, rama `tool === 'texture'` (sin checkpoint/IPAdapter), copia de salida a `job_X_texture.png`.
- `labs/partials/shell-texture.php` – Reescrito: modos Text/Image/Image+Prompt, seamless, presets, panel de parámetros, form con `tool=texture`, envío a generate.php.
- `assets/js/kndlabs.js` – Validación submit para texture (prompt/image según modo), toolLabel y acciones del drawer para `texture`, Reuse Settings para texture.
- `assets/css/labs-next.css` – Estilo `.texture-mode-chip.active`.
- `knd-labs.php` – Icono recent jobs para tool `texture` (`border-all`), `KND_PRICING.texture.base = 10`.
- `api/labs/job.php` – Campos `texture_mode` y `seamless` en la respuesta para tool texture.
- `includes/lang/en.php` e `includes/lang/es.php` – Claves i18n para Texture Lab (subtitle, generate 10 KP, mode, seamless, etc.).

### Migraciones SQL
- Ninguna. Se usa la tabla `knd_labs_jobs` y la columna `tool`; el valor `texture` se guarda igual que `text2img`/`upscale`. `payload_json` almacena `texture_mode` y `seamless`.

### Decisiones a revisar
- Texture usa checkpoint SD 1.5 en los JSON (`v1-5-pruned-emaonly.safetensors`). Si en tu ComfyUI solo tienes SDXL, hay que cambiar el `ckpt_name` en ambos workflows o usar un mapa de checkpoints como en text2img.
- Image-to-texture: la imagen se sube a ComfyUI en `generate.php` (igual que upscale); el worker no descarga imagen para texture.
- El visor 3D (same.new) no se integra en esta fase; Texture Lab solo entrega imagen. La reutilización en Character Lab / 3D Lab se deja para una fase posterior.

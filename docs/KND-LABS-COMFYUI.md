# KND Labs – Integración ComfyUI

Documentación de la arquitectura y el flujo de KND Labs con una **única instancia ComfyUI** para todos los tools.

## Arquitectura

- **Una sola instancia ComfyUI** (por defecto `http://127.0.0.1:8190`) para:
  - Text2Img
  - Img2Img
  - Texture Lab (texture, texture_image, texture_ultra)
  - Upscale
  - Consistency
  - 3D Lab (3d_fast, 3d_premium)
  - Character Lab

- **Workflows** en el repo: `/workflows/`
- **Cola de jobs**: tabla `knd_labs_jobs`, estado `queued` → worker procesa → `done` / `failed`
- **Worker**: `workers/labs_worker.php` (lease → cargar workflow → inyectar params → POST ComfyUI → polling → complete/fail)

## Configuración

### Rutas de salida (`config/labs.php` o `config/labs.local.php`)

- **COMFY_OUTPUT_DIR**: carpeta donde ComfyUI escribe (opcional). Si el worker corre en la misma máquina y esta ruta está configurada, se lee el archivo de ahí; si no, se obtiene por `/view`. Por defecto vacío.
- **KND_FINAL_IMAGE_DIR**: carpeta final para las imágenes generadas (p. ej. `F:\KND\images` o `F:\KND\output`). El worker copia aquí cada resultado además de guardarlo en storage para la web. Por defecto `F:\KND\images`.
- **LABS_UPLOAD_DIR**: ruta relativa al `storage` del proyecto donde se guardan los outputs para servir por web (p. ej. `uploads/labs`).

### ComfyUI (una sola URL)

- **Config**: `config/comfyui.php`
  - `COMFYUI_BASE_URL`: por defecto `http://127.0.0.1:8190`
  - También se puede usar la variable de entorno `COMFYUI_URL` o `COMFYUI_BASE_URL`

- **Provider (web)**: `includes/comfyui_provider.php`
  - URL local por defecto: `http://127.0.0.1:8190`
  - Se puede sobreescribir desde ajustes (BD) o config

- **Worker**: `workers/worker_config.local.php` o variables de entorno
  - `COMFYUI_BASE` / `COMFYUI_URL`: misma URL de ComfyUI
  - Si no se define, se usa `config/comfyui.php` → `COMFYUI_BASE_URL`

### Ruta local de ComfyUI (ejemplo)

En Windows, la instancia unificada puede estar en:

```
C:\AI\Comfyui3d\Comfyui3d\ComfyUI_windows_portable
```

El servidor ComfyUI debe exponer la API en el puerto configurado (p. ej. 8190). La URL que usa KND Labs es la base del servidor (p. ej. `http://127.0.0.1:8190`).

## Mapeo tool → workflow

| Tool           | Archivo workflow              |
|----------------|-------------------------------|
| text2img       | knd-workflow-api.json         |
| img2img        | knd-workflow-api2.json        |
| texture        | texture_generate_pro.json      |
| texture_image  | texture_from_image_pro.json   |
| texture_ultra  | texture_ultra_pro.json        |
| upscale        | upscale_api.json              |
| consistency    | consistency_api.json         |
| 3d_fast        | 3d_fast.json                  |
| 3d_premium     | 3d_premium.json               |
| character      | knd-workflow-api.json         |

**Fallbacks** si no existe el archivo:

- `3d_fast.json` → `generate fast 3d.json`
- `3d_premium.json` → `3d premium.json`
- `knd-workflow-api2.json` → `knd-workflow-api.json`

## Flujo del worker

1. **Lease**: `POST /api/labs/queue/lease.php` → obtiene un job `queued`.
2. **Validación**: `tool` debe estar en la lista permitida; payload no vacío.
3. **job_id**: se añade `payload['job_id']` para todos los tools.
4. **Imágenes** (si aplica):
   - upscale / img2img / texture_image: si el payload trae `image_url`, el worker descarga y sube a ComfyUI → `image_filename`.
   - consistency: descarga imagen de referencia y sube → `reference_image_filename`.
5. **Dispatcher**: según `tool` se elige el archivo de workflow (tabla anterior).
6. **Cargar workflow**: `workflows/<archivo>.json` → JSON decode.
7. **Inyectar parámetros**: `comfyui_inject_workflow_params($workflow, $payload, $tool)`:
   - prompt, negative_prompt, seed, steps, cfg, width, height
   - image_filename (LoadImage) cuando venga en payload
   - SaveImage → `filename_prefix`: `knd_<tool>/job_<job_id>`
   - upscale: upscale_model
   - consistency: reference_image_filename, model_ckpt
   - texture*: denoise, seamless
8. **Checkpoint / IPAdapter / ControlNet**: solo para text2img, img2img, character (no para upscale, consistency, texture*, 3d_*).
9. **Enviar**: `POST {COMFYUI_BASE}/prompt` con `{ "prompt": workflow }` → se guarda `prompt_id`.
10. **Polling**: `GET {COMFYUI_BASE}/history/{prompt_id}` hasta que haya salida o error/timeout.
11. **Salida**: se obtienen las imágenes de `outputs` → nodos con `images`/`gifs` (filename, subfolder, type).
12. **Obtener imagen**: (1) Si `COMFY_OUTPUT_DIR` está configurado y el archivo existe en disco (subfolder/filename), se lee de ahí. (2) Si no, se obtiene vía `GET {COMFYUI_BASE}/view?filename=...&type=output` (comfyui_fetch_output_image_bytes).
13. **Guardar**: se valida (tamaño > 0, extensión png/jpg/webp). Se escribe en `storage/{LABS_UPLOAD_DIR}/job_<job_id>_<tool>.<ext>` → se envía `output_path` en complete. Si `KND_FINAL_IMAGE_DIR` está definido (p. ej. `F:\KND\images`), se copia también ahí.
14. **Complete**: `POST /api/labs/queue/complete.php` con `job_id`, `comfy_prompt_id`, `image_url`, `output_path` (para que la web sirva desde storage y no dependa de la carpeta por defecto de ComfyUI).

## Cómo añadir nuevos workflows

1. **Crear el JSON** del workflow en ComfyUI y guardarlo en `/workflows/nombre_workflow.json`.
2. **Añadir el tool**:
   - En `includes/comfyui.php`: añadir entrada en `COMFYUI_TOOL_WORKFLOW_MAP` y, si hace falta, lógica en `comfyui_workflow_path()` (fallbacks).
   - En `workers/labs_worker.php`: añadir el tool en `$allowedTools` y un `case` en el `switch` con el nombre del archivo.
   - En `api/labs/generate.php`: añadir el tool en `$allowed`, coste KP y validaciones (prompt, imagen, etc.).
3. **Inyección**: si el nuevo workflow usa nodos estándar (CLIPTextEncode, KSampler, EmptyLatentImage, LoadImage, SaveImage), `comfyui_inject_workflow_params` ya inyecta prompt, negative, seed, steps, cfg, size, image y `filename_prefix`. Si usa nodos específicos, ampliar `comfyui_inject_workflow_params()` en `includes/comfyui.php` para esos nodos.

## Cómo cambiar modelos

- **Checkpoints SDXL (text2img/character/img2img)**:
  - Mapa en `includes/comfyui.php`: `COMFYUI_CHECKPOINT_MAP` y `COMFYUI_SDXL_ALLOWED`.
  - Añadir o cambiar el nombre del `.safetensors` en el mapa y en la allowlist.
  - El usuario puede elegir modelo en el front (payload `model`) o usar el checkpoint por defecto de ajustes (`override_ckpt`).

- **Upscale**: en el workflow se usa `UpscaleModelLoader`; el payload puede enviar `upscale_model` (p. ej. `4x-UltraSharp.pth`). El mapa `COMFYUI_UPSCALE_MODEL_MAP` normaliza nombres legacy.

- **Texture / 3D**: los workflows de texture y 3D suelen llevar el modelo embebido en el JSON; cambiar el modelo implica editar el workflow o añadir parámetros inyectados en `comfyui_inject_workflow_params()` para esos nodos.

## Seguridad

- **API generate**: usuario autenticado (`api_require_login()`), tool en lista permitida, longitud de prompt (máx. 500), validación de imagen (tamaño/resolución) cuando aplica.
- **Worker**: solo procesa jobs obtenidos por lease con token; valida `tool` permitido; no expone datos de usuario al ComfyUI más allá del contenido del job.
- **ComfyUI**: se recomienda exponer solo en localhost o detrás de un proxy con control de acceso; opcionalmente `X-KND-TOKEN` si el servidor ComfyUI lo soporta.

## Endpoints ComfyUI usados

- `POST /upload/image` – subida de imagen (input)
- `POST /prompt` – envío del workflow (body: `{ "prompt": workflow_json, "client_id": "knd-labs" }`)
- `GET /history/{prompt_id}` – estado y salidas del run
- `GET /view?filename=...&type=output` – descarga de imagen generada (si no se usa copia local)

## Archivos clave

- `config/comfyui.php` – URL y timeout ComfyUI
- `includes/comfyui.php` – mapeo tool→workflow, inyección, envío, history
- `includes/comfyui_provider.php` – resolución de URL (local/runpod)
- `workers/labs_worker.php` – cola, dispatcher, carga workflow, inyección, polling, complete/fail
- `api/labs/generate.php` – validación, cobro KP, creación de job
- `workflows/*.json` – definiciones de workflows

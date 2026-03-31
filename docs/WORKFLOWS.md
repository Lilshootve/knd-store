# Workflows - Dónde agregar y cómo cargar

Hay **dos sistemas** que cargan workflows, según cómo esté configurado tu flujo:

---

## 1. PHP Worker (labs: text2img, upscale)

**Usado por:** `workers/labs_worker.php` + `api/labs/generate.php` + `api/labs_upscale_create.php`

**Carpeta:** `workflows/` (raíz del proyecto)

**Mapeo de archivos:**

| Tool        | Archivo principal            | Fallback              |
|-------------|------------------------------|------------------------|
| text2img    | `workflows/knd-workflow-api.json` | `text2img_api.json` → `KND_MASTER_WORKFLOW_API.json` |
| character   | `workflows/knd-workflow-api.json` | (igual que text2img)  |
| upscale     | `workflows/upscale_api.json` | `KND_MASTER_WORKFLOW_UPSCALE.json` |

**Config opcional:** En `config/labs.local.php` o variables de entorno:
```php
define('WORKFLOWS_DIR', '/ruta/custom/workflows');
```

**Para agregar un workflow nuevo:**
1. Pon el `.json` en `workflows/`
2. Edita `includes/comfyui.php` → `comfyui_workflow_path()` para mapear tool → archivo

---

## 2. Comfy-Router (Python)

**Usado por:** `comfy-router` (si llamas POST `/generate` con callbacks)

**Carpeta canónica:** `workflows/` (raíz del repo)

`comfy-router/workflow_loader.py` ahora resuelve por defecto a `../workflows` para mantener una sola fuente de verdad con Labs PHP.

Compatibilidad legacy: si no existe `workflows/`, usa fallback a `comfy-router/workflows/`.

Override opcional por entorno:

```bash
WORKFLOWS_DIR=/ruta/custom/workflows
```

**Mapeo (en `workflow_loader.py`):**

| Job type          | Preset   | Archivo                         |
|-------------------|----------|---------------------------------|
| text2img          | standard | `text2img_standard.json`        |
| text2img          | high     | `text2img_high.json`            |
| upscale           | scale=2  | `upscale_2x.json`               |
| upscale           | scale=4  | `upscale_4x.json`               |
| character_create  | game/anime/realistic | `character_create.json` |
| character_variation | variation | `character_variation.json`    |
| texture_seamless  | seamless | `texture_seamless.json`         |

**Para agregar un workflow nuevo:**
1. Pon el `.json` en `workflows/` (raíz)
2. Edita `comfy-router/workflow_loader.py` → `WORKFLOW_MAP` para añadir la clave

---

## Comprobando cuál usas

- Si usas **labs** (labs-text-to-image.php, labs-upscale.php) → se usa el **PHP Worker** → workflows en `workflows/`
- Si usas **ai-tools** o callbacks a `/api/ai/callback` → puede ser **comfy-router** → workflows en `workflows/` (raíz, misma fuente)
- El `worker_config.local.php` tiene `COMFY_URL` → ahí envía el worker el prompt

---

## Error "prompt_outputs_failed_validation"

Causas habituales:

1. **Modelo no encontrado** – `UpscaleModelLoader` debe usar un modelo que exista en `ComfyUI/models/upscale_models/` (ej. `4x-UltraSharp.pth`).

2. **Imagen de LoadImage inexistente** – El archivo debe estar en `ComfyUI/input/`. Si ComfyUI está en otro servidor, usa subida HTTP (`comfyui_upload_image`) en lugar de copia al disco.

3. **Formato del workflow** – El API de ComfyUI solo usa `class_type` e `inputs`. Se eliminan automáticamente claves como `_meta`.

4. **Ver el workflow que se envía** – Revisa `logs/php-error.log` (hay un preview del body). O añade temporalmente:
   ```php
   file_put_contents(storage_path('logs/last_workflow.json'), json_encode($workflow, JSON_PRETTY_PRINT));
   ```
   antes de `comfyui_run_prompt` para inspeccionar el JSON completo.

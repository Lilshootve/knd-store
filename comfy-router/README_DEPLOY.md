# KND ComfyUI GPU Router - Deployment

Router que recibe jobs de KND AI Asset Creator y los ejecuta en ComfyUI.

## Arquitectura

```
KND Store (PHP) --POST /generate--> Router (:8000) --queue--> Worker
                                                                    |
                                                                    v
                                              ComfyUI (:8188) <-- /prompt, poll /history
                                                                    |
                                                                    v
KND Store <--callback-- Router (descarga resultado, POST callback_url)
```

## Variables de entorno

| Variable | Descripción | Default |
|----------|-------------|---------|
| `AI_CALLBACK_SECRET` | Secret compartido con KND | `""` |
| `COMFYUI_URL` | URL de ComfyUI | `http://127.0.0.1:8188` |
| `ROUTER_BASE_URL` | URL pública del Router (para result_url) | `http://localhost:8000` |
| `MAX_ACTIVE_JOBS` | Máximo de jobs GPU simultáneos | `2` |
| `MAX_QUEUE_LENGTH` | Tamaño máximo de cola | `50` |
| `COMFY_TIMEOUT` | Timeout (s) por job ComfyUI | `600` |
| `CALLBACK_RETRIES` | Reintentos en callback a KND | `3` |
| `CALLBACK_TIMEOUT` | Timeout (s) por intento callback | `30` |
| `DOWNLOAD_TIMEOUT` | Timeout (s) descarga imagen (upscale) | `60` |
| `S3_ENDPOINT` | Opcional: endpoint S3/R2 | - |
| `S3_BUCKET` | Opcional: bucket | - |
| `S3_KEY` | Opcional: access key | - |
| `S3_SECRET` | Opcional: secret key | - |
| `S3_PUBLIC_BASE` | Opcional: URL base pública de objetos | - |

## Ejecución local

### 1. ComfyUI

```bash
# En otra terminal
git clone https://github.com/comfyanonymous/ComfyUI.git
cd ComfyUI
pip install -r requirements.txt
python main.py
# ComfyUI en http://127.0.0.1:8188
```

### 2. Router

```bash
cd comfy-router
pip install -r requirements.txt
export AI_CALLBACK_SECRET="tu-secret"
export ROUTER_BASE_URL="https://gpu.tudominio.com"
python -m uvicorn server:app --host 0.0.0.0 --port 8000
```

### 3. KND config

En `config/ai_secrets.local.php`:

```php
define('AI_GPU_API_URL', 'https://gpu.tudominio.com');
define('AI_CALLBACK_SECRET', 'tu-secret');
```

## Docker

### Opción A: Router solo (ComfyUI en host)

```bash
docker build -t knd-comfy-router .
docker run -p 8000:8000 \
  -e AI_CALLBACK_SECRET="tu-secret" \
  -e ROUTER_BASE_URL="https://gpu.tudominio.com" \
  -e COMFYUI_URL="http://host.docker.internal:8188" \
  knd-comfy-router
```

En Linux usar `--add-host=host.docker.internal:host-gateway` o la IP del host.

### Opción B: Docker Compose (Router + ComfyUI)

```yaml
# docker-compose.yml
services:
  comfyui:
    image: comfyanonymous/comfyui:latest
    ports:
      - "8188:8188"
    volumes:
      - ./ComfyUI/models:/app/ComfyUI/models

  router:
    build: ./comfy-router
    ports:
      - "8000:8000"
    environment:
      COMFYUI_URL: http://comfyui:8188
      ROUTER_BASE_URL: https://gpu.tudominio.com
      AI_CALLBACK_SECRET: tu-secret
    depends_on:
      - comfyui
```

## RunPod / Vast.ai

1. Crear instancia con GPU (RTX 4090, A100, etc.).
2. Instalar ComfyUI + modelos.
3. Ejecutar ComfyUI en :8188 (background).
4. Ejecutar Router en :8000.
5. Exponer puerto 8000 públicamente.
6. Configurar `ROUTER_BASE_URL` con la URL pública de la instancia.
7. En KND: `AI_GPU_API_URL` = `https://tu-instancia.runpod.net` (o la URL que devuelva el puerto 8000).

## Contrato /generate

```json
{
  "job_id": "uuid",
  "type": "text2img|character_create|character_variation|upscale|texture_seamless",
  "payload": {...},
  "callback_url": "https://kndstore.com/api/ai/callback.php",
  "secret": "AI_CALLBACK_SECRET"
}
```

Respuesta 202:

```json
{"ok": true, "job_id": "uuid", "status": "queued"}
```

## Workflows

Ver `workflows/README.md` para exportar workflows desde ComfyUI y reemplazar los placeholders.

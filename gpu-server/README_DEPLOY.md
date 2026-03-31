# KND Store - GPU Server (InstantMesh)

Servidor GPU para generación 3D con InstantMesh. Compatible con la API KND (endpoints `/api/triposr/*`).

## Contrato API (compatible KND)

### POST /generate

Recibe:
```json
{
  "job_id": "uuid",
  "image_url": "https://kndstore.com/api/triposr/image.php?t=uuid",
  "callback_url": "https://kndstore.com/api/triposr/callback.php",
  "secret": "shared_secret"
}
```

Responde `202 Accepted`:
```json
{"ok": true, "job_id": "uuid", "status": "queued"}
```

### GET /models/{job_id}.glb

Sirve el modelo generado (GLB o OBJ).

### GET /health

`{"ok": true, "engine": "instantmesh"}`

---

## Variables de entorno

| Variable | Descripción | Default |
|----------|-------------|---------|
| `GPU_HOST_BASE_URL` | URL base pública del servidor (para model_url en callback) | `http://localhost:8000` |
| `INSTANTMESH_CALLBACK_SECRET` | Secret compartido con KND (preferido) | `""` |
| `TRIPOSR_CALLBACK_SECRET` | Legacy, fallback si INSTANTMESH no está | `""` |
| `MAX_CONCURRENCY` | Máximo de jobs GPU simultáneos | `2` |
| `INSTANTMESH_CONFIG` | Config InstantMesh (relativo a INSTANTMESH_DIR) | `configs/instant-mesh-large.yaml` |
| `INSTANTMESH_DIR` | Ruta al repo InstantMesh | `/app/InstantMesh` |
| `WORKSPACE_DIR` | Directorio base de trabajo | `/workspace` |
| `MODELS_DIR` | Directorio de modelos generados | `/workspace/models` |
| `CALLBACK_RETRIES` | Reintentos en callback | `3` |
| `CALLBACK_TIMEOUT` | Timeout (s) por intento callback | `30` |
| `DOWNLOAD_TIMEOUT` | Timeout (s) descarga imagen | `60` |
| `INSTANTMESH_TIMEOUT` | Timeout (s) proceso InstantMesh | `600` |

---

## Ejecución local

### Requisitos

- Python 3.10+
- CUDA 12.x (GPU NVIDIA)
- InstantMesh clonado e instalado

### Setup

```bash
# Clonar InstantMesh
git clone https://github.com/TencentARC/InstantMesh.git
cd InstantMesh
pip install -r requirements.txt
cd ..

# Instalar deps del GPU server
pip install -r requirements.txt

# Exportar variables
export GPU_HOST_BASE_URL="https://gpu.tudominio.com"
export INSTANTMESH_CALLBACK_SECRET="tu-secret"
export MAX_CONCURRENCY=2
export INSTANTMESH_DIR="$(pwd)/InstantMesh"

# Arrancar
uvicorn server:app --host 0.0.0.0 --port 8000
```

---

## Docker

### Build

```bash
docker build -t knd-gpu-server .
```

### Run

```bash
docker run --gpus all -p 8000:8000 \
  -e GPU_HOST_BASE_URL="https://gpu.tudominio.com" \
  -e INSTANTMESH_CALLBACK_SECRET="tu-secret" \
  -e MAX_CONCURRENCY=2 \
  knd-gpu-server
```

---

## RunPod / Vast.ai

1. Crear instancia con GPU (RTX 4090, A100, etc.).
2. Subir imagen Docker o usar Dockerfile.
3. Exponer puerto 8000.
4. Configurar `GPU_HOST_BASE_URL` con la URL pública de la instancia.
5. En KND `config/triposr_secrets.local.php`:
   ```php
   define('INSTANTMESH_API_URL', 'https://tu-runpod-url.runpod.net/generate');
   define('INSTANTMESH_CALLBACK_SECRET', 'tu-secret');
   ```

---

## Pruebas

### 1. Health

```bash
curl http://localhost:8000/health
```

### 2. Encolar job (KND debe tener imagen subida)

```bash
curl -X POST http://localhost:8000/generate \
  -H "Content-Type: application/json" \
  -d '{
    "job_id": "test-'$(uuidgen)'",
    "image_url": "https://kndstore.com/api/triposr/image.php?t=JOB_UUID_REAL",
    "callback_url": "https://kndstore.com/api/triposr/callback.php",
    "secret": "tu-secret-compartido"
  }'
```

Debe responder `202` con `{"ok":true,"job_id":"...","status":"queued"}`.

### 3. Descargar modelo (tras completar)

```bash
curl -O http://localhost:8000/models/JOB_ID.glb
```

### 4. Prueba de concurrencia

```bash
# Enviar 5 jobs
for i in 1 2 3 4 5; do
  curl -s -X POST http://localhost:8000/generate \
    -H "Content-Type: application/json" \
    -d "{\"job_id\":\"test-$i\",\"image_url\":\"https://...\",\"callback_url\":\"https://...\",\"secret\":\"...\"}"
  echo ""
done
```

Con `MAX_CONCURRENCY=2`, solo 2 jobs corren en paralelo; el resto queda en cola.

---

## Logs

Los logs incluyen `[job_id]` para seguimiento por trabajo.

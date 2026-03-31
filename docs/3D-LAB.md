# 3D Lab

Unified 3D generation: image, text+image, or recent creations. Safe mode only.

## Supported modes

- **Image** – Active. Image → 3D via Hunyuan 3D 2.1 (ComfyUI)
- **Text+Image** – Active. Uses image, prompt stored for future use
- **Recent** – Active. Reuse source image from a previous job
- **Text only** – Soon (no workflow yet)

## Routes

- `GET /labs/3d-lab` or `/labs/3D-Lab.php` → 3D Lab page
- `POST /api/labs/3d-lab/create.php` → Create job
- `GET /api/labs/3d-lab/status.php?id={id}` → Job status
- `GET /api/labs/3d-lab/history.php` → User's recent creations
- `GET /api/labs/3d-lab/download.php?id={id}&format=glb|preview` → Download

## Database

```bash
mysql < sql/knd_labs_3d_jobs.sql
mysql < sql/points_ledger_add_3d_lab.sql
```

## Worker (local PC)

```bash
pip install -r workers/requirements-labs-3d.txt
python workers/labs_3d_worker.py
```

### Env vars

| Variable | Default | Description |
|----------|---------|-------------|
| KND_DB_HOST | 127.0.0.1 | DB host |
| KND_DB_PORT | 3306 | DB port |
| KND_DB_NAME | - | DB name |
| KND_DB_USER | - | DB user |
| KND_DB_PASS | - | DB password |
| WORKER_SLEEP_SECONDS | 5 | Poll interval |
| COMFYUI_3D_URL | http://127.0.0.1:8190 | ComfyUI 3D base URL |
| COMFYUI_3D_OUTPUT_ROOT | C:\AI\Comfyui3d\...\ComfyUI\output | ComfyUI output dir |
| COMFYUI_3D_INPUT_ROOT | C:\AI\Comfyui3d\...\ComfyUI\input | ComfyUI input dir |
| LOCAL_3D_STAGING_DIR | F:\KND\output | Local staging for GLB |
| COMFYUI_3D_WORKFLOW_FAST | generate fast 3d.json | Fast workflow path |
| COMFYUI_3D_WORKFLOW_PREMIUM | 3d premium.json | Premium workflow (High/Ultra) |
| COMFYUI_3D_USE_PREMIUM | 0 | Set 1 to use premium for High/Ultra |
| COMFYUI_POLL_MAX_SECONDS | 600 | Max wait for ComfyUI job |
| PUBLIC_SITE_BASE_URL | https://kndstore.com | Base URL for input download |
| STORAGE_PUBLIC_PREFIX | /storage | Prefix (optional, for path-based URL) |
| LABS_3D_INPUT_URL_TEMPLATE | {base}/api/labs/3d-lab/input.php?id={public_id} | URL template for input download |
| LABS_3D_STALE_MINUTES | 30 | Jobs in `processing` longer = abandoned (marked failed on worker start) |
| **KND_3D_UPLOAD_TOKEN** | - | **Required** when web on hosting + worker local. Same as `WORKER_3D_UPLOAD_TOKEN` in `config/worker_secrets.local.php` on server. Used only for 3D upload endpoint (separate from Text2Img queue token). |

## Architecture

- **Web** (hosting) – 3D Lab page, create/status/history/download API. Input images stored in storage.
- **Worker** (local PC) – Polls DB, downloads input from `input.php`, runs `run_labs_3d_job.py`, **uploads** GLB+preview to hosting
- **ComfyUI 3D** (local PC) – Hunyuan 3D 2.1, port 8190
- **Staging** – `F:\KND\output` (local). Not the public URL. For future publish step.

## Pipeline

1. User uploads image → create.php saves to storage (hosting)
2. Worker leases job, passes `input_image_path`, `public_id`, quality, seed
3. `run_labs_3d_job.py`:
   - Downloads input from `GET /api/labs/3d-lab/input.php?id={public_id}` to temp
   - Copies to ComfyUI input
   - Loads workflow (fast or premium), sets image + seed
   - POSTs to ComfyUI /prompt
   - Polls /history until done
   - Locates GLB in ComfyUI output
   - Copies to storage (local) and staging (F:\KND\output)
4. Worker **uploads** GLB and preview to `POST /api/labs/3d-lab/upload-output.php` (X-KND-3D-WORKER-TOKEN)
5. Worker updates job: status, glb_path, preview_path, meta_json

## Workflows

- **generate fast 3d.json** – Image→3D, Hy3D21ExportMesh, outputs `3D/Hy3D_xxxxx_.glb`
- **3d premium.json** – Remesh + texture; current JSON has no ExportMesh. Use fast until premium is updated.

## Backfill (jobs completed before upload-to-hosting)

For jobs that completed when the worker did not yet upload to hosting, run:

```bash
set KND_3D_UPLOAD_TOKEN=your_3d_upload_token_from_worker_secrets
python workers/upload_3d_output_backfill.py c772774c-cc9e-5ddf-cdbc-5a3b5af43c07 631ecbdc-21c1-5350-9755-3dfcbd817f2e
```

Ensure `config/worker_secrets.local.php` on the server has `WORKER_3D_UPLOAD_TOKEN` set to the same value.

## TODO

- Wireframe, stats, texture toggle in viewer (may need Three.js)
- Premium workflow: add ExportMesh node or equivalent
- Future: publish from `F:\KND\output` to CDN/public URL

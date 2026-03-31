# Character Lab

Full pipeline: prompt/image → concept image → 3D mesh (GLB).

## Routes

- `GET /character-lab` - Main UI page
- `GET /labs/character-lab.php` - Same (via rewrite)
- `POST /api/character-lab/create.php` - Create job
- `GET /api/character-lab/status.php?id={job_id}` - Job status
- `GET /api/character-lab/recent-images.php` - User's recent compatible images
- `GET /api/character-lab/download.php?id={job_id}&format=glb|concept|preview` - Download file

## Database

Run migrations in order:

```bash
mysql < sql/knd_character_lab_jobs.sql
mysql < sql/points_ledger_add_character_lab.sql
```

## Config

Copy `config/character_lab.local.example.php` to `config/character_lab.local.php`:

- `CHARACTER_LAB_KP_COST` - KP per generation (default 25)
- `IMAGE_ENGINE_PROVIDER` - local_comfyui | runpod
- `MODEL3D_ENGINE` - hunyuan3d | triposr | instantmesh
- `CHARACTER_LAB_SAFE_ONLY` - true for safe mode only

## Worker

```bash
python workers/character_lab_worker.py
```

Env: `KND_DB_HOST`, `KND_DB_PORT`, `KND_DB_NAME`, `KND_DB_USER`, `KND_DB_PASS`

## TODOs (endpoint-specific)

1. **ComfyUI concept image**: In `run_character_lab_job.py`, replace placeholder with ComfyUI text2img call using normalized prompt.
2. **Hunyuan3D workflow**: Add ComfyUI workflow JSON for Hunyuan3D-2.1; invoke from worker with concept image as input.
3. **InstantMesh fallback**: When primary 3D fails and mode is image-based, optionally call existing InstantMesh endpoint (feature-flagged).
4. **Image normalization**: For image/text_image/recent_image modes, add optional image-to-image step to match stylized game-ready target.
5. **Refund on early failure**: When job fails before concept_image_path is set, insert `character_lab_refund` into points_ledger (reversal of kp_cost).

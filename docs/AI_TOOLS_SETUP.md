# KND AI Tools Setup

Text→Image, Upscale, Character Lab. Uses the same `triposr_jobs` table and `points_ledger` as Image→3D.

## Database

1. Run the AI extension migration (adds job_type, payload_json, result_json, cost_kp to triposr_jobs):
   ```sql
   source sql/triposr_jobs_alter_ai.sql
   ```

2. Add AI ledger source types:
   ```sql
   source sql/points_ledger_add_ai_jobs.sql
   ```

## Configuration

1. Copy and edit:
   ```
   cp config/ai_secrets.local.example.php config/ai_secrets.local.php
   ```

2. Or reuse InstantMesh config: `AI_GPU_API_URL` and `AI_CALLBACK_SECRET` fall back to `INSTANTMESH_*` if not set.

## GPU Server Contract

POST `{AI_GPU_API_URL}/generate` with JSON:

```json
{
  "job_id": "uuid",
  "type": "text2img|upscale|character_create|character_variation",
  "payload": { ... },
  "callback_url": "https://kndstore.com/api/ai/callback.php",
  "secret": "AI_CALLBACK_SECRET",
  "timestamp": 1234567890
}
```

### Payloads by type

**text2img**
```json
{
  "prompt": "string",
  "mode": "standard|high",
  "width": 1024,
  "height": 1024,
  "seed": null
}
```

**upscale**
```json
{
  "image_url": "https://kndstore.com/api/ai/image.php?t=job_uuid",
  "scale": 2
}
```
or `"scale": 4`

**character_create**
```json
{
  "prompt": "string",
  "style": "game|anime|realistic",
  "seed": null
}
```

**character_variation**
```json
{
  "character_id": "uuid",
  "variation_prompt": "string",
  "type": "pose|outfit|expression"
}
```

### Callback response

On completion, GPU POSTs to `callback_url`:

Success:
```json
{
  "job_id": "uuid",
  "status": "completed",
  "result_url": "https://gpu-server/result.png",
  "result": { "files": [...], "meta": {...} },
  "secret": "...",
  "timestamp": 1234567890,
  "signature": "HMAC-SHA256 hex"
}
```

Failure:
```json
{
  "job_id": "uuid",
  "status": "failed",
  "error_message": "string",
  "secret": "..."
}
```

KND validates `secret` (and optionally `signature`). Downloads from `result_url` (or `model_url`) and stores in `storage/ai/outputs/`.

## ComfyUI Router

See `comfy-router/README_DEPLOY.md` for the ComfyUI-based GPU router (text2img, upscale, character, texture_seamless).

## KP Costs

| Type | Cost |
|------|------|
| text2img (standard) | 3 KP |
| text2img (high) | 6 KP |
| upscale | 5 KP |
| character_create | 15 KP |
| character_variation | 6 KP |

## Limits

- 1 active job per user (pending/processing)
- 10 jobs per hour per user
- 30 jobs per day per user
- Prompt max 500 chars
- Image max 10MB, 4096×4096, MIME validated

## Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/ai/submit.php` | POST | Submit job |
| `/api/ai/status.php` | GET | Poll status |
| `/api/ai/callback.php` | POST | GPU callback |
| `/api/ai/download.php` | GET | Download result |
| `/api/ai/image.php` | GET | Serve input image (t=job_uuid) |

## Image→3D

Unchanged. Endpoints remain at `/api/triposr/*`. Linked from AI tools page.

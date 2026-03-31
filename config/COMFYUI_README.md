# KND Labs - ComfyUI Setup

1. **Database**: Run `sql/knd_labs_jobs.sql` to create the jobs table.

2. **Config**: Edit `config/comfyui.php` and set:
   - `COMFYUI_BASE_URL` = your Runpod/ComfyUI URL (e.g. `https://xxx.runpod.net`)
   - `COMFYUI_TIMEOUT` = 120
   - `COMFYUI_CLIENT_ID` = `knd-labs`

3. **Workflows**: Replace `KND_MASTER_WORKFLOW_API.json` and `KND_MASTER_WORKFLOW_UPSCALE.json` with your exported ComfyUI API workflows if needed. The injector looks for: CLIPTextEncode (prompt/negative), KSampler (seed/steps/cfg), EmptyLatentImage (width/height), LoadImage (for upscale).

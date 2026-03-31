# ComfyUI Workflows for KND AI Router

Placeholder workflows. Replace with real ComfyUI exports.

## Exporting from ComfyUI

1. Open ComfyUI in browser (http://localhost:8188).
2. Load or create your workflow (e.g. text2img with KSampler, CLIPTextEncode, SaveImage).
3. **Save (API Format)**: Click the menu (☰) → Save (API Format) → save as `text2img_standard.json` in this folder.
4. The JSON should look like:
   ```json
   {
     "3": {"class_type": "KSampler", "inputs": {"seed": 0, "steps": 20, ...}},
     "6": {"class_type": "CLIPTextEncode", "inputs": {"text": "placeholder", "clip": ["4", 1]}},
     ...
   }
   ```

## Parameter Injection

The router injects these payload keys into workflows:

| Job Type | Injected | Node/Input |
|----------|----------|------------|
| text2img | prompt, seed, width, height, steps | CLIPTextEncode.text, KSampler.seed, EmptyLatentImage |
| upscale | image (after upload) | LoadImage.image |
| character_create | prompt, seed, style | CLIPTextEncode.text, KSampler |
| character_variation | variation_prompt, character_id | CLIPTextEncode.text |
| texture_seamless | prompt | CLIPTextEncode.text |

**Convention**: The first `CLIPTextEncode` node gets the main prompt. Ensure your workflow has at least one such node.

## Required Models

- **CheckpointLoaderSimple**: e.g. `v1-5-pruned-emaonly.safetensors` or your SD model.
- **UpscaleModelLoader**: `4x-UltraSharp.pth` for upscale workflows.

Place models in ComfyUI's `models/` directories (checkpoints, upscale_models).

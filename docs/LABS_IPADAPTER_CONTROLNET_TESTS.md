# KND Labs – IPAdapter / ControlNet / Upscale Test Checklist

## Manual Test Checklist

### 1. Text2img with no toggles (baseline)
- [ ] Go to /labs/text-to-image.php
- [ ] Enter prompt, leave IPAdapter and ControlNet **off**
- [ ] Click Generate
- [ ] **Expected:** Job completes, image appears. No reference/control images used.

### 2. Text2img with IPAdapter enabled + image
- [ ] Enable "Enable IPAdapter (style reference)"
- [ ] Upload a reference image (style/portrait)
- [ ] Enter prompt, click Generate
- [ ] **Expected:** Job completes, output reflects reference style.

### 3. Text2img with ControlNet enabled + image
- [ ] Enable "Enable ControlNet (pose/edges)"
- [ ] Upload a control image (pose/sketch)
- [ ] Enter prompt, click Generate
- [ ] **Expected:** Job completes, output follows control image structure.

### 4. Upscale default
- [ ] Go to /labs/upscale.php
- [ ] Select 4x (default), upload image, submit
- [ ] **Expected:** Job completes, upscaled image returned.

### 5. Upscale with scale=2
- [ ] Select 2x, upload image, submit
- [ ] **Expected:** Job completes (same workflow; scale stored in payload).

---

## Node Mapping (knd-workflow-api.json)

| Node ID | Class                 | Purpose                         |
|---------|-----------------------|---------------------------------|
| 3       | CheckpointLoaderSimple| Base checkpoint                 |
| 6       | CLIPTextEncode        | Positive prompt                 |
| 9       | CLIPTextEncode        | Negative prompt                 |
| 12      | IPAdapterModelLoader  | IP-Adapter weights              |
| 14      | ControlNetLoader      | ControlNet canny                |
| **15**  | ControlNetApplyAdvanced | Control image + strength/start/end |
| **23**  | IPAdapterAdvanced     | Reference image + weight/start/end |
| **31**  | LoadImage             | ControlNet input image          |
| **32**  | LoadImage             | IPAdapter reference image       |
| 29      | VAEDecode             | First pass decode               |
| 30      | KSampler              | First pass                      |
| 18      | KSampler              | ControlNet pass                 |
| 17      | KSampler              | Refiner                         |
| 20      | UltimateSDUpscale     | Upscale step                    |
| 21      | SaveImage             | Output                          |

When disabled: weight/strength set to 0; placeholder image used for nodes 31/32.

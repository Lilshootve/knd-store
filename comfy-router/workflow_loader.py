"""
KND Store - Workflow loader and parameter injection
Loads JSON from workflows/ and injects payload params into node inputs.
"""

import copy
import json
import logging
import os
from pathlib import Path
from typing import Any

logger = logging.getLogger("workflow_loader")

_DEFAULT_ROOT_WORKFLOWS = Path(__file__).resolve().parent.parent / "workflows"
_LEGACY_LOCAL_WORKFLOWS = Path(__file__).resolve().parent / "workflows"
WORKFLOWS_DIR = Path(os.getenv("WORKFLOWS_DIR", str(_DEFAULT_ROOT_WORKFLOWS))).resolve()
if not WORKFLOWS_DIR.exists():
    WORKFLOWS_DIR = _LEGACY_LOCAL_WORKFLOWS

# Map job type + preset -> workflow filename (without .json)
WORKFLOW_MAP = {
    "text2img_standard": "text2img_standard",
    "text2img_high": "text2img_high",
    "upscale_2": "upscale_2x",
    "upscale_4": "upscale_4x",
    "character_create_game": "character_create",
    "character_create_anime": "character_create",
    "character_create_realistic": "character_create",
    "character_variation": "character_variation",
    "texture_seamless_seamless": "texture_seamless",
}

# Node class_type -> input keys we can inject
INJECT_KEYS = {
    "CLIPTextEncode": ["text"],
    "KSampler": ["seed", "steps", "cfg", "denoise"],
    "KSamplerAdvanced": ["seed", "steps", "cfg"],
    "EmptyLatentImage": ["width", "height"],
    "LoadImage": ["image"],
    "UpscaleModelLoader": [],
    "ImageUpscaleWithModel": [],
}


def _find_workflow_path(job_type: str, payload: dict) -> Path:
    preset = ""
    if job_type == "text2img":
        preset = "high" if payload.get("mode") == "high" else "standard"
    elif job_type == "upscale":
        scale = payload.get("scale", 2)
        preset = "4" if scale == 4 else "2"
    elif job_type == "character_create":
        preset = payload.get("style", "game")
    elif job_type == "character_variation":
        preset = "variation"
    elif job_type == "texture_seamless":
        preset = "seamless"

    key = f"{job_type}_{preset}"
    name = WORKFLOW_MAP.get(key, job_type)
    path = WORKFLOWS_DIR / f"{name}.json"
    if not path.exists():
        fallback = WORKFLOWS_DIR / f"{job_type}.json"
        if fallback.exists():
            return fallback
        raise FileNotFoundError(f"No workflow for {key} or {job_type}: {path}")
    return path


def load_workflow(job_type: str, payload: dict) -> dict:
    """Load workflow JSON and inject payload parameters. Returns API-format workflow dict."""
    path = _find_workflow_path(job_type, payload)
    with open(path, encoding="utf-8") as f:
        wf = json.load(f)

    # ComfyUI API format: {"3": {"class_type": "...", "inputs": {...}}, ...}
    # or full format with nodes array - we support both
    if "nodes" in wf:
        api_wf = _nodes_to_api(wf)
    else:
        api_wf = copy.deepcopy(wf)

    api_wf = _inject_params(api_wf, job_type, payload)
    return api_wf


def _nodes_to_api(wf: dict) -> dict:
    """Convert UI format (nodes array) to API format (id -> {class_type, inputs})."""
    nodes = wf.get("nodes", [])
    result = {}
    for n in nodes:
        nid = str(n.get("id", len(result)))
        ctype = n.get("type", n.get("class_type", ""))
        inputs = dict(n.get("inputs", n.get("widgets_values", [])))
        if isinstance(n.get("inputs"), list):
            for inp in n.get("inputs", []):
                if isinstance(inp, dict) and "name" in inp:
                    inputs[inp["name"]] = inp.get("value", inp.get("link", inp))
        result[nid] = {"class_type": ctype, "inputs": inputs}
    return result


def _inject_params(wf: dict, job_type: str, payload: dict) -> dict:
    """Inject payload values into workflow nodes."""
    wf = copy.deepcopy(wf)
    seed = payload.get("seed")
    if seed is None:
        import random
        seed = random.randint(0, 2**32 - 1)

    prompt = payload.get("prompt", "")
    width = payload.get("width", 1024)
    height = payload.get("height", 1024)
    mode = payload.get("mode", "standard")
    steps = 20 if mode == "standard" else 35
    cfg = 7.5

    injected_positive = False
    for nid, node in wf.items():
        if not isinstance(node, dict):
            continue
        ctype = node.get("class_type", "")
        inputs = node.setdefault("inputs", {})

        if ctype == "CLIPTextEncode" and "text" in inputs and not injected_positive:
            inputs["text"] = prompt
            injected_positive = True
        if ctype in ("KSampler", "KSamplerAdvanced"):
            inputs["seed"] = seed
            inputs["steps"] = steps
            inputs["cfg"] = cfg
        if ctype == "EmptyLatentImage":
            inputs["width"] = width
            inputs["height"] = height
        if ctype == "LoadImage" and "_comfy_image" in payload:
            inputs["image"] = payload["_comfy_image"]
        if ctype == "UpscaleModelLoader":
            m = inputs.get("model_name", "")
            if "RealESRGAN" in str(m) or "x4plus" in str(m) or "x2plus" in str(m):
                inputs["model_name"] = "4x-UltraSharp.pth"
            elif job_type == "upscale":
                inputs["model_name"] = "4x-UltraSharp.pth"

    return wf

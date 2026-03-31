"""
3D Lab - ComfyUI 3D API client.
Submit workflows, poll history, locate output GLB.
"""
from __future__ import annotations

import json
import sys
import time
from pathlib import Path
from typing import Any

# Allow imports when run from project root
_workers_dir = Path(__file__).resolve().parent
if str(_workers_dir) not in sys.path:
    sys.path.insert(0, str(_workers_dir))

import requests

from comfyui_3d_config import (
    COMFYUI_3D_INPUT_ROOT,
    COMFYUI_3D_OUTPUT_ROOT,
    COMFYUI_3D_OUTPUT_SUBFOLDER,
    COMFYUI_3D_FILENAME_PREFIX,
    COMFYUI_3D_URL,
    COMFYUI_POLL_INTERVAL,
    COMFYUI_POLL_MAX_SECONDS,
    COMFYUI_SUBMIT_TIMEOUT,
)


def load_workflow(path: str | Path) -> dict[str, Any]:
    """Load workflow JSON."""
    p = Path(path)
    if not p.is_file():
        raise FileNotFoundError(f"Workflow not found: {path}")
    with open(p, "r", encoding="utf-8") as f:
        return json.load(f)


def prepare_workflow_image(workflow: dict[str, Any], image_filename: str) -> dict[str, Any]:
    """
    Set image input in Hy3D21LoadImageWithTransparency node.
    Node "12" uses inputs.image = filename.
    """
    out = json.loads(json.dumps(workflow))
    for nid, node in out.items():
        if not isinstance(node, dict):
            continue
        if node.get("class_type") == "Hy3D21LoadImageWithTransparency":
            if "inputs" not in node:
                node["inputs"] = {}
            node["inputs"]["image"] = image_filename
            return out
    raise ValueError("Hy3D21LoadImageWithTransparency node not found in workflow")


def prepare_workflow_seed(workflow: dict[str, Any], seed: int | None) -> dict[str, Any]:
    """Set seed in Hy3DMeshGenerator if present."""
    if seed is None:
        return workflow
    import random
    actual_seed = seed if seed > 0 else random.randint(1, 2**53)
    out = json.loads(json.dumps(workflow))
    for nid, node in out.items():
        if not isinstance(node, dict):
            continue
        if node.get("class_type") == "Hy3DMeshGenerator":
            if "inputs" not in node:
                node["inputs"] = {}
            node["inputs"]["seed"] = actual_seed
            return out
    return out


def copy_image_to_comfy_input(source_path: str | Path, filename: str) -> Path:
    """
    Copy image to ComfyUI input folder.
    Returns path to the file in ComfyUI input.
    """
    import shutil
    src = Path(source_path)
    if not src.is_file():
        raise FileNotFoundError(f"Input image not found: {source_path}")
    dest_dir = Path(COMFYUI_3D_INPUT_ROOT)
    dest_dir.mkdir(parents=True, exist_ok=True)
    dest = dest_dir / filename
    shutil.copy2(src, dest)
    return dest


def submit_prompt(workflow: dict[str, Any], base_url: str | None = None) -> str:
    """
    POST workflow to ComfyUI /prompt.
    Returns prompt_id.
    """
    url = (base_url or COMFYUI_3D_URL).rstrip("/") + "/prompt"
    try:
        r = requests.post(
            url,
            json={"prompt": workflow},
            timeout=COMFYUI_SUBMIT_TIMEOUT,
        )
        r.raise_for_status()
        data = r.json()
        pid = data.get("prompt_id")
        if not pid:
            raise ValueError(f"No prompt_id in response: {data}")
        return pid
    except requests.RequestException as e:
        raise RuntimeError(f"ComfyUI submit failed: {e}") from e


def get_history(prompt_id: str, base_url: str | None = None) -> dict[str, Any] | None:
    """GET /history/{prompt_id}. Returns {prompt_id: entry} or None if not found."""
    url = (base_url or COMFYUI_3D_URL).rstrip("/") + "/history/" + prompt_id
    try:
        r = requests.get(url, timeout=30)
        if r.status_code == 404:
            return None
        r.raise_for_status()
        return r.json()
    except requests.RequestException:
        return None


def wait_for_completion(
    prompt_id: str,
    base_url: str | None = None,
    poll_interval: float | None = None,
    max_seconds: int | None = None,
) -> dict[str, Any]:
    """
    Poll /history until job completes or times out.
    When ComfyUI finishes, history entry appears. Status may vary by version.
    """
    interval = poll_interval or COMFYUI_POLL_INTERVAL
    max_s = max_seconds or COMFYUI_POLL_MAX_SECONDS
    url = base_url or COMFYUI_3D_URL
    start = time.monotonic()
    while (time.monotonic() - start) < max_s:
        hist = get_history(prompt_id, url)
        if hist and prompt_id in hist:
            entry = hist[prompt_id]
            status = entry.get("status", {})
            if status.get("status_str") == "error":
                err = status.get("messages", [[]])
                msg = str(err[0]) if err else "Execution error"
                raise RuntimeError(f"ComfyUI failed: {msg}")
            if status.get("completed", True):
                return entry
        time.sleep(interval)
    raise TimeoutError(f"ComfyUI job {prompt_id} did not complete within {max_s}s")


def find_output_glb_in_history(history_entry: dict[str, Any]) -> str | None:
    """
    Extract output filename from history.
    Hy3D21ExportMesh outputs to 3D/Hy3D_xxxxx_.glb
    """
    outputs = history_entry.get("outputs", {})
    for node_id, out in outputs.items():
        if not isinstance(out, dict):
            continue
        # Some nodes use "gifs" or "images"; ExportMesh uses "mesh" or similar
        for key in ("meshes", "mesh", "glb", "filenames"):
            if key in out and isinstance(out[key], list) and out[key]:
                f = out[key][0]
                if isinstance(f, dict) and "filename" in f:
                    return f["filename"]
                if isinstance(f, dict) and "name" in f:
                    return f["name"]
                if isinstance(f, str):
                    return f
    return None


def locate_comfy_output_glb(prompt_id: str, history_entry: dict | None = None) -> Path | None:
    """
    Locate the generated GLB in ComfyUI output folder.
    - If history has output filenames, use those.
    - Else: scan output/3D/ for newest Hy3D_*.glb (fast workflow)
    - Fallback: any .glb in output/3D/ (premium or custom)
    """
    output_root = Path(COMFYUI_3D_OUTPUT_ROOT)
    subfolder = output_root / COMFYUI_3D_OUTPUT_SUBFOLDER
    if not subfolder.is_dir():
        return None

    if history_entry:
        name = find_output_glb_in_history(history_entry)
        if name:
            # filename may be "Hy3D_00001_.glb" or "3D/Hy3D_00001_.glb"
            for candidate in [subfolder / name, subfolder / Path(name).name, output_root / name]:
                if candidate.is_file():
                    return candidate

    glbs = list(subfolder.glob(f"{COMFYUI_3D_FILENAME_PREFIX}_*.glb"))
    if not glbs:
        glbs = list(subfolder.glob("*.glb"))
    if not glbs:
        return None
    return max(glbs, key=lambda p: p.stat().st_mtime)

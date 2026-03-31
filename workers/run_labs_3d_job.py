#!/usr/bin/env python3
"""
3D Lab single-job runner.
Integrates with local ComfyUI 3D (Hunyuan 3D 2.1).
- Loads job from payload (job_id, public_id, input_image_path, quality, seed, etc.)
- Downloads input from remote URL, copies to ComfyUI input
- Submits workflow (fast or premium)
- Polls for completion, locates GLB, copies to storage and staging
"""
from __future__ import annotations

import argparse
import json
import logging
import shutil
import sys
from pathlib import Path

# Ensure workers dir is on path for imports
_workers = Path(__file__).resolve().parent
_project = _workers.parent
if str(_workers) not in sys.path:
    sys.path.insert(0, str(_workers))

import tempfile
import urllib.request
import urllib.error

from comfyui_3d_config import (
    INPUT_DOWNLOAD_URL_TEMPLATE,
    LOCAL_3D_STAGING_DIR,
    WORKFLOW_FAST,
    WORKFLOW_PREMIUM,
)
from comfyui_3d_client import (
    copy_image_to_comfy_input,
    load_workflow,
    locate_comfy_output_glb,
    prepare_workflow_image,
    prepare_workflow_seed,
    submit_prompt,
    wait_for_completion,
)

logging.basicConfig(level=logging.INFO, format="[3d-lab] %(message)s")
log = logging.getLogger(__name__)

STORAGE_OUTPUT = "labs/3d-lab/output"
STORAGE_PREVIEW = "labs/3d-lab/preview"


def _pick_workflow(quality: str) -> str:
    """Map quality to fixed 3D workflows."""
    q = (quality or "").strip().lower()
    if q == "high":
        return WORKFLOW_PREMIUM
    return WORKFLOW_FAST


def _download_input_image(public_id: str, input_image_rel: str) -> Path:
    """
    Download input image from remote hosting. Resolves URL from public_id
    and input_image_rel. Returns path to downloaded temp file.
    """
    url = INPUT_DOWNLOAD_URL_TEMPLATE.format(public_id=public_id)
    log.info("input path from DB: %s", input_image_rel)
    log.info("resolved URL: %s", url)

    suffix = Path(input_image_rel).suffix or ".png"
    tmp = tempfile.NamedTemporaryFile(delete=False, suffix=suffix)
    tmp_path = Path(tmp.name)
    tmp.close()

    try:
        req = urllib.request.Request(url, headers={"User-Agent": "KND-3D-Lab-Worker/1.0"})
        with urllib.request.urlopen(req, timeout=60) as resp:
            data = resp.read()
        tmp_path.write_bytes(data)
        log.info("downloaded to temp: %s", tmp_path)
        return tmp_path
    except urllib.error.HTTPError as e:
        raise RuntimeError(f"Download failed HTTP {e.code}: {url}") from e
    except urllib.error.URLError as e:
        raise RuntimeError(f"Download failed: {e.reason}") from e
    except Exception as e:
        if tmp_path.exists():
            tmp_path.unlink(missing_ok=True)
        raise


def run(payload: dict) -> dict:
    job_id = payload.get("job_id")
    public_id = str(payload.get("public_id", "unknown"))
    input_image_rel = payload.get("input_image_path")
    quality = str(payload.get("quality", "Standard"))
    seed_raw = payload.get("seed")
    seed = int(seed_raw) if seed_raw is not None and str(seed_raw).strip() else None

    if not input_image_rel:
        return {"ok": False, "error": "No input image"}

    project_root = _project
    tmp_path = None

    # Download from remote URL (web on hosting, worker local)
    try:
        tmp_path = _download_input_image(public_id, input_image_rel)
    except Exception as e:
        return {"ok": False, "error": str(e)}

    # Copy to ComfyUI input with unique filename
    suffix = Path(input_image_rel).suffix or ".png"
    comfy_filename = f"3dlab_{public_id[:8]}{suffix}"
    try:
        copy_image_to_comfy_input(tmp_path, comfy_filename)
        log.info("copied to ComfyUI input: %s", comfy_filename)
    except Exception as e:
        if tmp_path and tmp_path.exists():
            tmp_path.unlink(missing_ok=True)
        return {"ok": False, "error": f"Could not copy image to ComfyUI: {e}"}
    finally:
        if tmp_path and tmp_path.exists():
            tmp_path.unlink(missing_ok=True)

    workflow_path = _pick_workflow(quality)
    try:
        workflow = load_workflow(workflow_path)
    except FileNotFoundError as e:
        return {"ok": False, "error": str(e)}

    workflow = prepare_workflow_image(workflow, comfy_filename)
    workflow = prepare_workflow_seed(workflow, seed)

    try:
        prompt_id = submit_prompt(workflow)
        log.info("submitted prompt_id=%s workflow=%s", prompt_id, Path(workflow_path).name)
    except Exception as e:
        return {"ok": False, "error": f"ComfyUI submit failed: {e}"}

    try:
        history = wait_for_completion(prompt_id)
        log.info("job completed prompt_id=%s", prompt_id)
    except TimeoutError as e:
        return {"ok": False, "error": str(e)}
    except RuntimeError as e:
        return {"ok": False, "error": str(e)}

    glb_src = locate_comfy_output_glb(prompt_id, history)
    if not glb_src or not glb_src.is_file():
        return {"ok": False, "error": "Could not locate output GLB"}

    # Destinations
    storage_output = project_root / "storage" / STORAGE_OUTPUT
    storage_preview = project_root / "storage" / STORAGE_PREVIEW
    staging_dir = Path(LOCAL_3D_STAGING_DIR)

    storage_output.mkdir(parents=True, exist_ok=True)
    storage_preview.mkdir(parents=True, exist_ok=True)
    staging_dir.mkdir(parents=True, exist_ok=True)

    glb_storage = storage_output / f"{public_id}.glb"
    glb_staging = staging_dir / f"{public_id}.glb"

    try:
        shutil.copy2(glb_src, glb_storage)
    except Exception as e:
        return {"ok": False, "error": f"Could not copy GLB to storage: {e}"}

    try:
        shutil.copy2(glb_src, glb_staging)
        log.info("staged to %s", glb_staging)
    except Exception as e:
        log.warning("staging copy failed (non-fatal): %s", e)

    glb_rel = f"{STORAGE_OUTPUT}/{public_id}.glb"
    preview_path = None
    prev_rel = None

    meta = {
        "prompt_id": prompt_id,
        "workflow": Path(workflow_path).name,
        "quality": quality,
        "staging_path": str(glb_staging),
    }

    return {
        "ok": True,
        "glb_path": str(glb_storage),
        "glb_path_rel": glb_rel,
        "preview_path": None,
        "preview_path_rel": prev_rel,
        "staging_path": str(glb_staging),
        "meta": meta,
    }


def main() -> int:
    p = argparse.ArgumentParser()
    p.add_argument("--payload", required=True)
    args = p.parse_args()
    payload = json.loads(args.payload)
    result = run(payload)
    print(json.dumps(result))
    return 0 if result.get("ok") else 1


if __name__ == "__main__":
    raise SystemExit(main())

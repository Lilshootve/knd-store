#!/usr/bin/env python3
"""
Character Lab single-job runner.

Phase A: Concept image
  - text: ComfyUI text2img with normalized prompt
  - image / text_image / recent_image: use input or run image-to-image normalization
  - TODO: Wire to ComfyUI / Hunyuan3D endpoints per project config

Phase B: 3D generation
  - Primary: Hunyuan3D-2.1 via ComfyUI workflow
  - Fallback: TripoSR (image-only) or InstantMesh (legacy)
  - TODO: Implement actual Hunyuan3D ComfyUI workflow invocation

Outputs JSON to stdout:
{
  "ok": true,
  "concept_path": "/abs/path/to/concept.png",
  "glb_path": "/abs/path/to/mesh.glb",
  "preview_path": "/abs/path/to/thumb.webp"
}
"""

from __future__ import annotations

import argparse
import json
import shutil
from pathlib import Path


def run_job(payload: dict) -> dict:
    public_id = str(payload["public_id"])
    mode = str(payload.get("mode", "text"))
    prompt = str(payload.get("prompt_sanitized", ""))
    category = str(payload.get("category", "human"))
    input_path = payload.get("input_image_path")
    engine_image = str(payload.get("engine_image", "comfyui"))
    engine_3d = str(payload.get("engine_3d", "hunyuan3d"))

    project_root = Path(__file__).resolve().parents[1]
    storage = project_root / "storage" / "labs" / "character-lab"
    concept_dir = storage / "concept"
    mesh_dir = storage / "mesh"
    thumbs_dir = storage / "thumbs"

    concept_dir.mkdir(parents=True, exist_ok=True)
    mesh_dir.mkdir(parents=True, exist_ok=True)
    thumbs_dir.mkdir(parents=True, exist_ok=True)

    concept_path = concept_dir / f"{public_id}_concept.png"
    glb_path = mesh_dir / f"{public_id}.glb"
    thumb_path = thumbs_dir / f"{public_id}.webp"

    # Phase A: Concept image
    if mode in ("image", "text_image", "recent_image") and input_path:
        input_abs = project_root / "storage" / input_path.replace("\\", "/")
        if input_abs.exists() and input_abs.is_file():
            shutil.copy2(input_abs, concept_path)
        else:
            return {"ok": False, "error": "Input image not found"}
    else:
        # TODO: Call ComfyUI text2img with normalized prompt
        # For now create minimal 1x1 PNG placeholder so pipeline can be tested
        concept_path.write_bytes(
            b"\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x02\x00\x00\x00\x90wS\xde\x00\x00\x00\x0cIDATx\x9cc\xf8\x0f\x00\x00\x01\x01\x00\x05\x18\xd8N\x00\x00\x00\x00IEND\xaeB`\x82"
        )

    # Phase B: 3D generation
    # TODO: Invoke Hunyuan3D-2.1 ComfyUI workflow with concept_path as input
    # For now create placeholder GLB so download/viewer can be tested
    glb_path.write_bytes(b"glTF")
    thumb_path.write_bytes(b"RIFFxxxxWEBPVP8 ")

    return {
        "ok": True,
        "concept_path": str(concept_path),
        "glb_path": str(glb_path),
        "preview_path": str(thumb_path),
    }


def main() -> int:
    parser = argparse.ArgumentParser(description="Run one Character Lab job")
    parser.add_argument("--payload", required=True, help="JSON payload")
    args = parser.parse_args()

    payload = json.loads(args.payload)
    result = run_job(payload)
    print(json.dumps(result))
    return 0 if result.get("ok") else 1


if __name__ == "__main__":
    raise SystemExit(main())

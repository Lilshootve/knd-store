#!/usr/bin/env python3
"""
Single-job runner for InstantMesh.

Expected JSON input:
{
  "job_id": 123,
  "public_id": "uuid",
  "input_path": "e:/repo/kndstore/storage/labs/instantmesh/input/uuid.png",
  "remove_bg": true,
  "seed": 42,
  "output_format": "glb"
}

Outputs JSON to stdout:
{
  "ok": true,
  "preview_path": "...",
  "glb_path": "...",
  "obj_path": "..."
}
"""

from __future__ import annotations

import argparse
import json
import os
import random
from pathlib import Path


def _touch_file(path: Path, content: bytes) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_bytes(content)


def run_job(payload: dict) -> dict:
    public_id = str(payload["public_id"])
    output_format = str(payload.get("output_format", "glb")).lower()

    project_root = Path(__file__).resolve().parents[1]
    output_dir = project_root / "storage" / "labs" / "instantmesh" / "output"
    thumbs_dir = project_root / "storage" / "labs" / "instantmesh" / "thumbs"

    # TODO: Replace placeholder generation with real InstantMesh invocation.
    # This stub intentionally creates deterministic placeholder files so backend
    # integration can be tested before GPU infra is connected.
    seed = int(payload.get("seed", 42))
    random.seed(seed)

    preview_path = thumbs_dir / f"{public_id}.webp"
    _touch_file(preview_path, b"RIFFxxxxWEBPVP8 ")

    glb_path = None
    obj_path = None

    if output_format in ("glb", "both"):
        glb_path = output_dir / f"{public_id}.glb"
        _touch_file(glb_path, b"glTF")

    if output_format in ("obj", "both"):
        obj_path = output_dir / f"{public_id}.obj"
        _touch_file(obj_path, b"# InstantMesh OBJ placeholder\n")

    return {
        "ok": True,
        "preview_path": str(preview_path),
        "glb_path": str(glb_path) if glb_path else None,
        "obj_path": str(obj_path) if obj_path else None,
    }


def main() -> int:
    parser = argparse.ArgumentParser(description="Run one InstantMesh job")
    parser.add_argument("--payload", required=True, help="JSON payload for the job")
    args = parser.parse_args()

    payload = json.loads(args.payload)
    result = run_job(payload)
    print(json.dumps(result))
    return 0 if result.get("ok") else 1


if __name__ == "__main__":
    raise SystemExit(main())

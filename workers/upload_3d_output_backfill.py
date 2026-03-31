#!/usr/bin/env python3
"""
Backfill: Upload existing 3D Lab output files to hosting.
Use for jobs that completed before the worker had upload-to-hosting.

Usage:
  python workers/upload_3d_output_backfill.py <public_id> [public_id2 ...]

Env: KND_3D_UPLOAD_TOKEN (required), PUBLIC_SITE_BASE_URL (default https://kndstore.com)
"""
from __future__ import annotations

import os
import sys
from pathlib import Path

import requests

PROJECT_ROOT = Path(__file__).resolve().parents[1]
STORAGE_OUTPUT = "labs/3d-lab/output"
STORAGE_PREVIEW = "labs/3d-lab/preview"
UPLOAD_BASE = os.getenv("PUBLIC_SITE_BASE_URL", "https://kndstore.com").rstrip("/")
WORKER_3D_UPLOAD_TOKEN = os.getenv("KND_3D_UPLOAD_TOKEN", "").strip()


def upload_one(public_id: str) -> bool:
    glb_path = PROJECT_ROOT / "storage" / STORAGE_OUTPUT / f"{public_id}.glb"
    if not glb_path.is_file():
        print(f"  GLB not found: {glb_path}")
        return False
    preview_path = None
    for ext in (".webp", ".png", ".jpg"):
        p = PROJECT_ROOT / "storage" / STORAGE_PREVIEW / f"{public_id}{ext}"
        if p.is_file():
            preview_path = p
            break
    url = f"{UPLOAD_BASE}/api/labs/3d-lab/upload-output.php"
    headers = {"X-KND-3D-WORKER-TOKEN": WORKER_3D_UPLOAD_TOKEN}
    files = [
        ("public_id", (None, public_id)),
        ("_worker_3d_token", (None, WORKER_3D_UPLOAD_TOKEN)),
        ("glb", (f"{public_id}.glb", open(glb_path, "rb"), "model/gltf-binary")),
    ]
    fds = [files[1][1][1]]
    if preview_path:
        ext = preview_path.suffix
        mime = "image/webp" if ext == ".webp" else ("image/png" if ext == ".png" else "image/jpeg")
        fo = open(preview_path, "rb")
        fds.append(fo)
        files.append(("preview", (f"{public_id}{ext}", fo, mime)))
    try:
        r = requests.post(url, headers=headers, files=files, timeout=120)
        r.raise_for_status()
        data = r.json()
        if data.get("ok"):
            print(f"  OK: {public_id}")
            return True
        print(f"  FAIL: {data.get('error', 'Unknown')}")
        return False
    except Exception as e:
        print(f"  ERROR: {e}")
        return False
    finally:
        for fd in fds:
            try:
                fd.close()
            except Exception:
                pass


def main() -> int:
    if not WORKER_3D_UPLOAD_TOKEN:
        print("ERROR: Set KND_3D_UPLOAD_TOKEN (same as WORKER_3D_UPLOAD_TOKEN in config/worker_secrets.local.php)")
        return 1
    ids = [a for a in sys.argv[1:] if a.strip()]
    if not ids:
        print("Usage: python workers/upload_3d_output_backfill.py <public_id> [public_id2 ...]")
        return 1
    ok = 0
    for pid in ids:
        print(f"Uploading {pid}...")
        if upload_one(pid.strip()):
            ok += 1
    print(f"Done: {ok}/{len(ids)} uploaded")
    return 0 if ok == len(ids) else 1


if __name__ == "__main__":
    raise SystemExit(main())

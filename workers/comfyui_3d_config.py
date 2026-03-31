"""
3D Lab - ComfyUI 3D configuration.
Environment variables override defaults.
"""
from __future__ import annotations

import os
from pathlib import Path

_ROOT = Path(__file__).resolve().parents[1]


def _pick_existing_path(candidates: list[str], fallback: str) -> str:
    for c in candidates:
        try:
            if c and Path(c).exists():
                return c
        except Exception:
            pass
    return fallback

COMFYUI_3D_URL = os.getenv("COMFYUI_3D_URL", "http://127.0.0.1:8188")
# Output folder for GLB (e.g. F:\KND\output\3D with Hy3D_00006_.glb); may be symlinked from ComfyUI output
_default_output_root = _pick_existing_path(
    [
        r"C:\AI\Comfyui3d\Comfyui3d\ComfyUI_windows_portable\ComfyUI\output",
        r"F:\KND\output",
    ],
    r"C:\AI\Comfyui3d\Comfyui3d\ComfyUI_windows_portable\ComfyUI\output",
)

COMFYUI_3D_OUTPUT_ROOT = os.getenv("COMFYUI_3D_OUTPUT_ROOT", _default_output_root)
COMFYUI_3D_INPUT_ROOT = os.getenv(
    "COMFYUI_3D_INPUT_ROOT",
    r"C:\AI\Comfyui3d\Comfyui3d\ComfyUI_windows_portable\ComfyUI\input",
)
LOCAL_3D_STAGING_DIR = os.getenv("LOCAL_3D_STAGING_DIR", r"F:\KND\output")

WORKFLOW_FAST = os.getenv(
    "COMFYUI_3D_WORKFLOW_FAST",
    str(_ROOT / "comfy-router" / "workflows" / "generate fast 3d.json"),
)
WORKFLOW_PREMIUM = os.getenv(
    "COMFYUI_3D_WORKFLOW_PREMIUM",
    str(_ROOT / "comfy-router" / "workflows" / "3d premium.json"),
)

COMFYUI_3D_OUTPUT_SUBFOLDER = "3D"
COMFYUI_3D_FILENAME_PREFIX = "Hy3D"

COMFYUI_SUBMIT_TIMEOUT = int(os.getenv("COMFYUI_SUBMIT_TIMEOUT", "30"))
COMFYUI_POLL_INTERVAL = float(os.getenv("COMFYUI_POLL_INTERVAL", "3.0"))
COMFYUI_POLL_MAX_SECONDS = int(os.getenv("COMFYUI_POLL_MAX_SECONDS", "600"))

# Remote input download (web on hosting, worker local)
PUBLIC_SITE_BASE_URL = os.getenv("PUBLIC_SITE_BASE_URL", "https://kndstore.com").rstrip("/")
STORAGE_PUBLIC_PREFIX = os.getenv("STORAGE_PUBLIC_PREFIX", "/storage/").rstrip("/") or "/storage"
# Input API: /api/labs/3d-lab/input.php?id={public_id} (no auth; public_id is secret)
INPUT_DOWNLOAD_URL_TEMPLATE = os.getenv(
    "LABS_3D_INPUT_URL_TEMPLATE",
    f"{PUBLIC_SITE_BASE_URL}/api/labs/3d-lab/input.php?id={{public_id}}",
)

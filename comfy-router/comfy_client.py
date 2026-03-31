"""
KND Store - ComfyUI API client
Submit workflows, poll history, get output file paths.
"""

import json
import logging
import time
from pathlib import Path
from typing import Any

import requests

logger = logging.getLogger("comfy_client")

COMFY_BASE = "http://127.0.0.1:8188"
DEFAULT_TIMEOUT = 600  # 10 min
POLL_INTERVAL = 1.0


def _url(path: str) -> str:
    return f"{COMFY_BASE.rstrip('/')}{path}"


def set_base_url(url: str) -> None:
    global COMFY_BASE
    COMFY_BASE = url.rstrip("/")


def queue_prompt(workflow: dict, timeout: int = 30) -> dict:
    """POST workflow to ComfyUI /prompt. Returns {prompt_id, number} or raises."""
    r = requests.post(
        _url("/prompt"),
        json={"prompt": workflow},
        timeout=timeout,
        headers={"Content-Type": "application/json"},
    )
    r.raise_for_status()
    data = r.json()
    if "error" in data:
        raise RuntimeError(data.get("node_errors") or data["error"])
    return {"prompt_id": data["prompt_id"], "number": data.get("number", 0)}


def get_history(prompt_id: str, timeout: int = 10) -> dict | None:
    """GET /history/{prompt_id}. Returns history dict or None if not ready."""
    r = requests.get(_url(f"/history/{prompt_id}"), timeout=timeout)
    r.raise_for_status()
    data = r.json()
    return data.get(prompt_id)


def wait_for_completion(
    prompt_id: str,
    timeout_sec: float = DEFAULT_TIMEOUT,
    poll_interval: float = POLL_INTERVAL,
) -> dict:
    """Poll history until completed or failed. Returns history entry."""
    deadline = time.time() + timeout_sec
    while time.time() < deadline:
        hist = get_history(prompt_id)
        if hist:
            status = hist.get("status", {})
            if status.get("completed"):
                return hist
            if status.get("status_str") == "error" or status.get("messages"):
                return hist
        time.sleep(poll_interval)
    raise TimeoutError(f"ComfyUI did not complete in {timeout_sec}s")


def get_output_files(history_entry: dict, files_base_url: str | None = None) -> list[dict]:
    """
    Extract output file info from history. Returns list of
    {"kind": "image"|"model", "filename": str, "subfolder": str, "type": str, "url": str}
    files_base_url: Router base URL (e.g. http://localhost:8000). We append /files?params.
    """
    base = (files_base_url or COMFY_BASE).rstrip("/")
    use_router = files_base_url is not None
    outputs = history_entry.get("outputs", {})
    result = []
    for node_id, out in outputs.items():
        if "images" in out:
            for img in out["images"]:
                filename = img.get("filename", "")
                subfolder = img.get("subfolder", "")
                ftype = img.get("type", "output")
                params = f"filename={filename}&type={ftype}"
                if subfolder:
                    params += f"&subfolder={subfolder}"
                url = f"{base}/files?{params}" if use_router else f"{base}/view?{params}"
                result.append({
                    "kind": "image",
                    "filename": filename,
                    "subfolder": subfolder,
                    "type": ftype,
                    "url": url,
                })
        if "gifs" in out:
            for gif in out["gifs"]:
                filename = gif.get("filename", "")
                subfolder = gif.get("subfolder", "")
                ftype = gif.get("type", "output")
                params = f"filename={filename}&type={ftype}"
                if subfolder:
                    params += f"&subfolder={subfolder}"
                url = f"{base}/files?{params}" if use_router else f"{base}/view?{params}"
                result.append({
                    "kind": "image",
                    "filename": filename,
                    "subfolder": subfolder,
                    "type": ftype,
                    "url": url,
                })
    return result


def upload_image(image_url: str, dest_filename: str, timeout: int = 60) -> str:
    """Download image from URL and upload to ComfyUI /upload/image. Returns ComfyUI filename."""
    import io
    r = requests.get(image_url, timeout=timeout, stream=True)
    r.raise_for_status()
    data = r.content
    ext = "jpg"
    if "png" in image_url.lower():
        ext = "png"
    elif "webp" in image_url.lower():
        ext = "webp"
    upload_name = dest_filename if "." in dest_filename else f"{dest_filename}.{ext}"
    files = {"image": (upload_name, io.BytesIO(data), f"image/{ext}")}
    data_form = {"overwrite": "true"}
    ru = requests.post(_url("/upload/image"), files=files, data=data_form, timeout=timeout)
    ru.raise_for_status()
    res = ru.json()
    return res.get("name", upload_name)

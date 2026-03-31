"""
KND Store - ComfyUI Router Worker
Processes jobs from queue: load workflow, run ComfyUI, callback to KND.
"""

import hashlib
import hmac
import json
import logging
import os
import time
from pathlib import Path

import requests

from comfy_client import (
    set_base_url as set_comfy_url,
    queue_prompt,
    wait_for_completion,
    get_output_files,
    upload_image,
)
from workflow_loader import load_workflow

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(name)s: %(message)s",
)
logger = logging.getLogger("worker")

# Env
COMFY_URL = os.environ.get("COMFYUI_URL", "http://127.0.0.1:8188")
ROUTER_BASE_URL = os.environ.get("ROUTER_BASE_URL", "http://localhost:8000")
CALLBACK_SECRET = os.environ.get("AI_CALLBACK_SECRET") or os.environ.get("INSTANTMESH_CALLBACK_SECRET", "")
CALLBACK_RETRIES = int(os.environ.get("CALLBACK_RETRIES", "3"))
CALLBACK_TIMEOUT = int(os.environ.get("CALLBACK_TIMEOUT", "30"))
COMFY_TIMEOUT = int(os.environ.get("COMFY_TIMEOUT", "600"))
DOWNLOAD_TIMEOUT = int(os.environ.get("DOWNLOAD_TIMEOUT", "60"))

# Base URL for building result URLs: Router serves /files (proxies to ComfyUI /view)
ROUTER_FILES_BASE = ROUTER_BASE_URL.rstrip("/")


def _log(job_id: str, msg: str, level: str = "info") -> None:
    getattr(logger, level, logger.info)("[%s] %s", job_id, msg)


def _hmac_sign(secret: str, timestamp: str, body: str) -> str:
    message = timestamp + body
    return hmac.new(secret.encode(), message.encode(), hashlib.sha256).hexdigest()


def _do_callback(
    job_id: str,
    callback_url: str,
    secret: str,
    status: str,
    result: dict | None = None,
    result_url: str | None = None,
    error_message: str | None = None,
    meta: dict | None = None,
) -> bool:
    """POST to KND callback. Sends secret + HMAC signature (timestamp + body)."""
    payload = {
        "job_id": job_id,
        "status": status,
        "secret": secret,
    }
    if result_url:
        payload["result_url"] = result_url
    if result:
        payload["result"] = result
    if error_message:
        payload["error_message"] = error_message
    if meta:
        payload["meta"] = meta

    timestamp = str(int(time.time()))
    payload["timestamp"] = timestamp
    body = json.dumps(payload, separators=(",", ":"))
    if secret:
        payload["signature"] = _hmac_sign(secret, timestamp, body)

    for attempt in range(1, CALLBACK_RETRIES + 1):
        try:
            r = requests.post(
                callback_url,
                json=payload,
                timeout=CALLBACK_TIMEOUT,
                headers={"Content-Type": "application/json"},
            )
            if 200 <= r.status_code < 300:
                _log(job_id, f"callback ok (attempt {attempt})")
                return True
            _log(job_id, f"callback HTTP {r.status_code} (attempt {attempt})", "warning")
        except Exception as e:
            _log(job_id, f"callback error (attempt {attempt}): {e}", "warning")
        time.sleep(2)
    return False


def _upload_to_s3(local_path: Path, job_id: str, filename: str) -> str | None:
    """Upload file to S3/R2 if env vars set. Returns public URL or None."""
    endpoint = os.environ.get("S3_ENDPOINT")
    bucket = os.environ.get("S3_BUCKET")
    key = os.environ.get("S3_KEY")
    secret = os.environ.get("S3_SECRET")
    public_base = os.environ.get("S3_PUBLIC_BASE", "").rstrip("/")
    if not all([endpoint, bucket, key, secret]):
        return None
    try:
        import boto3
        from botocore.config import Config
        client = boto3.client(
            "s3",
            endpoint_url=endpoint,
            aws_access_key_id=key,
            aws_secret_access_key=secret,
            config=Config(signature_version="s3v4"),
        )
        s3_key = f"ai/{job_id}/{filename}"
        client.upload_file(str(local_path), bucket, s3_key)
        if public_base:
            return f"{public_base}/{s3_key}"
        return f"{endpoint.rstrip('/')}/{bucket}/{s3_key}"
    except Exception as e:
        logger.warning("S3 upload failed: %s", e)
        return None


def _process_job(task: dict) -> None:
    """Process a single job synchronously."""
    job_id = task["job_id"]
    job_type = task["type"]
    payload = task.get("payload", {})
    callback_url = task["callback_url"]
    secret = task.get("secret") or CALLBACK_SECRET
    start = time.time()

    set_comfy_url(COMFY_URL)

    try:
        workflow = load_workflow(job_type, payload)
    except FileNotFoundError as e:
        _log(job_id, str(e), "error")
        _do_callback(job_id, callback_url, secret, "failed", error_message=str(e))
        return
    except Exception as e:
        _log(job_id, f"workflow load error: {e}", "error")
        _do_callback(job_id, callback_url, secret, "failed", error_message=str(e))
        return

    # For upscale: download and upload input image to ComfyUI
    if job_type == "upscale" and "image_url" in payload:
        try:
            comfy_filename = upload_image(payload["image_url"], f"{job_id}_input", DOWNLOAD_TIMEOUT)
            payload["_comfy_image"] = comfy_filename
            # Re-load workflow with updated payload (workflow may need LoadImage node)
            workflow = load_workflow(job_type, payload)
        except Exception as e:
            _log(job_id, f"image upload failed: {e}", "error")
            _do_callback(job_id, callback_url, secret, "failed", error_message="Could not load input image")
            return

    try:
        res = queue_prompt(workflow)
        prompt_id = res["prompt_id"]
    except Exception as e:
        _log(job_id, f"ComfyUI queue failed: {e}", "error")
        _do_callback(job_id, callback_url, secret, "failed", error_message=str(e))
        return

    try:
        hist = wait_for_completion(prompt_id, timeout_sec=COMFY_TIMEOUT)
    except TimeoutError as e:
        _log(job_id, str(e), "error")
        _do_callback(job_id, callback_url, secret, "failed", error_message=str(e))
        return
    except Exception as e:
        _log(job_id, f"ComfyUI error: {e}", "error")
        _do_callback(job_id, callback_url, secret, "failed", error_message=str(e))
        return

    status = hist.get("status", {})
    if status.get("status_str") == "error" or status.get("messages"):
        err = "; ".join(str(m) for m in status.get("messages", [])[:3]) or "ComfyUI execution failed"
        _do_callback(job_id, callback_url, secret, "failed", error_message=err)
        return

    files = get_output_files(hist, ROUTER_FILES_BASE)
    if not files:
        _do_callback(job_id, callback_url, secret, "failed", error_message="No output files")
        return

    took_ms = int((time.time() - start) * 1000)
    first_url = files[0]["url"]
    result = {
        "files": [{"kind": f["kind"], "url": f["url"], "filename": f["filename"]} for f in files],
        "meta": {"engine": "comfyui", "took_ms": took_ms, "prompt_id": prompt_id},
    }

    _do_callback(
        job_id,
        callback_url,
        secret,
        "completed",
        result=result,
        result_url=first_url,
        meta=result["meta"],
    )

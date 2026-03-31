"""
KND Store - GPU Worker (InstantMesh)
Background job processor with concurrency limit.
"""

import os
import asyncio
import logging
import subprocess
import time
from pathlib import Path
from concurrent.futures import ThreadPoolExecutor

import requests

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(name)s: %(message)s",
)
logger = logging.getLogger("worker")

# Configuration from env
WORKSPACE = Path(os.environ.get("WORKSPACE_DIR", "/workspace"))
JOBS_DIR = WORKSPACE / "jobs"
MODELS_DIR = Path(os.environ.get("MODELS_DIR", "/workspace/models"))
GPU_HOST = os.environ.get("GPU_HOST_BASE_URL", "http://localhost:8000")
CALLBACK_SECRET = os.environ.get("INSTANTMESH_CALLBACK_SECRET") or os.environ.get("TRIPOSR_CALLBACK_SECRET", "")
MAX_CONCURRENCY = int(os.environ.get("MAX_CONCURRENCY", "2"))
INSTANTMESH_DIR = Path(os.environ.get("INSTANTMESH_DIR", "/app/InstantMesh"))
INSTANTMESH_CONFIG = os.environ.get("INSTANTMESH_CONFIG", "configs/instant-mesh-large.yaml")
CALLBACK_RETRIES = int(os.environ.get("CALLBACK_RETRIES", "3"))
CALLBACK_TIMEOUT = int(os.environ.get("CALLBACK_TIMEOUT", "30"))
DOWNLOAD_TIMEOUT = int(os.environ.get("DOWNLOAD_TIMEOUT", "60"))
INSTANTMESH_TIMEOUT = int(os.environ.get("INSTANTMESH_TIMEOUT", "600"))

# Queue and semaphore (initialized on start)
_queue: asyncio.Queue | None = None
_semaphore: asyncio.Semaphore | None = None
_executor = ThreadPoolExecutor(max_workers=max(1, MAX_CONCURRENCY))




def _log(job_id: str, msg: str, level="info"):
    log_fn = getattr(logger, level, logger.info)
    log_fn("[%s] %s", job_id, msg)


def _download_image(job_id: str, image_url: str, dest_path: Path) -> bool:
    try:
        r = requests.get(image_url, timeout=DOWNLOAD_TIMEOUT, stream=True)
        r.raise_for_status()
        with open(dest_path, "wb") as f:
            for chunk in r.iter_content(chunk_size=8192):
                f.write(chunk)
        return True
    except Exception as e:
        _log(job_id, "download failed: %s" % e, "error")
        return False


def _run_instantmesh(job_id: str, input_path: Path, output_dir: Path) -> Path | None:
    """Run InstantMesh. Returns path to output OBJ or None on failure."""
    config_path = INSTANTMESH_DIR / INSTANTMESH_CONFIG
    if not config_path.exists():
        config_path = INSTANTMESH_DIR / "configs" / "instant-mesh-large.yaml"
    if not config_path.exists():
        _log(job_id, "InstantMesh config not found: %s" % config_path, "error")
        return None

    run_py = INSTANTMESH_DIR / "run.py"
    if not run_py.exists():
        _log(job_id, "InstantMesh run.py not found: %s" % run_py, "error")
        return None

    cmd = [
        "python", str(run_py),
        str(config_path),
        str(input_path),
        "--output_path", str(output_dir),
        "--save_video",
    ]
    _log(job_id, "running: %s" % " ".join(cmd))

    try:
        result = subprocess.run(
            cmd,
            cwd=str(INSTANTMESH_DIR),
            timeout=INSTANTMESH_TIMEOUT,
            capture_output=True,
            text=True,
        )
        if result.returncode != 0:
            _log(job_id, "InstantMesh failed: %s" % result.stderr, "error")
            return None

        # InstantMesh outputs to: {output_dir}/{config_name}/meshes/{input_basename}.obj
        config_name = Path(INSTANTMESH_CONFIG).stem
        mesh_dir = output_dir / config_name / "meshes"
        input_basename = input_path.stem
        obj_path = mesh_dir / f"{input_basename}.obj"

        if not obj_path.exists():
            _log(job_id, "output OBJ not found: %s" % obj_path, "error")
            return None

        return obj_path
    except subprocess.TimeoutExpired:
        _log(job_id, "InstantMesh timeout after %ds" % INSTANTMESH_TIMEOUT, "error")
        return None
    except Exception as e:
        _log(job_id, "InstantMesh error: %s" % e, "error")
        return None


def _obj_to_glb(obj_path: Path, glb_path: Path) -> bool:
    """Convert OBJ to GLB using trimesh. Returns False if conversion fails (caller will use OBJ)."""
    try:
        import trimesh
        mesh = trimesh.load(str(obj_path), file_type="obj", process=False)
        if isinstance(mesh, trimesh.Scene):
            mesh = mesh.dump(concatenate=True)
        mesh.export(str(glb_path), file_type="glb")
        return True
    except Exception as e:
        logger.warning("obj_to_glb failed: %s (will serve OBJ)", e)
        return False


def _do_callback(job_id: str, callback_url: str, secret: str, status: str, **kwargs) -> bool:
    payload = {"job_id": job_id, "status": status, "secret": secret, **kwargs}
    for attempt in range(1, CALLBACK_RETRIES + 1):
        try:
            r = requests.post(
                callback_url,
                json=payload,
                timeout=CALLBACK_TIMEOUT,
                headers={"Content-Type": "application/json"},
            )
            if r.status_code >= 200 and r.status_code < 300:
                _log(job_id, "callback ok (attempt %d)" % attempt)
                return True
            _log(job_id, "callback HTTP %d (attempt %d)" % (r.status_code, attempt), "warning")
        except Exception as e:
            _log(job_id, "callback error (attempt %d): %s" % (attempt, e), "warning")
    return False


def _process_job_sync(job_id: str, image_url: str, callback_url: str, secret: str):
    """Synchronous job processing (runs in executor)."""
    start = time.time()
    job_dir = JOBS_DIR / job_id
    job_dir.mkdir(parents=True, exist_ok=True)

    # Detect extension from URL
    ext = ".jpg"
    if ".png" in image_url.lower() or "png" in image_url.lower():
        ext = ".png"
    elif ".webp" in image_url.lower():
        ext = ".webp"
    input_path = job_dir / f"input{ext}"

    if not _download_image(job_id, image_url, input_path):
        _do_callback(
            job_id, callback_url, secret,
            status="failed",
            error_message="Could not download image",
        )
        return

    output_dir = job_dir / "outputs"
    output_dir.mkdir(exist_ok=True)

    obj_path = _run_instantmesh(job_id, input_path, output_dir)
    if not obj_path:
        _do_callback(
            job_id, callback_url, secret,
            status="failed",
            error_message="InstantMesh processing failed",
        )
        return

    MODELS_DIR.mkdir(parents=True, exist_ok=True)
    glb_path = MODELS_DIR / f"{job_id}.glb"

    if _obj_to_glb(obj_path, glb_path):
        model_ext = "glb"
        model_path = glb_path
    else:
        model_ext = "obj"
        import shutil
        model_path = MODELS_DIR / f"{job_id}.obj"
        shutil.copy2(obj_path, model_path)

    took_ms = int((time.time() - start) * 1000)
    model_url = f"{GPU_HOST.rstrip('/')}/models/{job_id}.{model_ext}"

    _do_callback(
        job_id, callback_url, secret,
        status="completed",
        model_url=model_url,
        meta={
            "engine": "instantmesh",
            "took_ms": took_ms,
            "config": INSTANTMESH_CONFIG,
        },
    )


async def _process_job(job_id: str, image_url: str, callback_url: str, secret: str):
    async with _semaphore:
        loop = asyncio.get_event_loop()
        await loop.run_in_executor(
            _executor,
            _process_job_sync,
            job_id,
            image_url,
            callback_url,
            secret or CALLBACK_SECRET,
        )


async def _worker_loop():
    global _queue, _semaphore
    _queue = asyncio.Queue()
    _semaphore = asyncio.Semaphore(MAX_CONCURRENCY)
    logger.info("Worker started, max_concurrency=%d", MAX_CONCURRENCY)

    while True:
        try:
            task = await _queue.get()
            if task is None:
                break
            job_id, image_url, callback_url, secret = task
            try:
                await _process_job(job_id, image_url, callback_url, secret)
            except Exception as e:
                logger.exception("[%s] worker error: %s", job_id, e)
                _do_callback(
                    job_id, callback_url, secret or CALLBACK_SECRET,
                    status="failed",
                    error_message=str(e),
                )
            finally:
                _queue.task_done()
        except asyncio.CancelledError:
            break
        except Exception as e:
            logger.exception("worker loop error: %s", e)


def enqueue_job(job_id: str, image_url: str, callback_url: str, secret: str):
    if _queue is None:
        raise RuntimeError("Worker not started. Call start_worker() first.")
    _queue.put_nowait((job_id, image_url, callback_url, secret))


def start_worker():
    """Start background worker. Call from main before uvicorn."""
    asyncio.create_task(_worker_loop())

"""
KND Store - ComfyUI GPU Router
FastAPI on :8000, POST /generate, queue + worker, ComfyUI on :8188.
"""

import asyncio
import logging
import os
from contextlib import asynccontextmanager
from concurrent.futures import ThreadPoolExecutor

from fastapi import FastAPI, HTTPException, Request
from fastapi.responses import JSONResponse, Response, StreamingResponse
from pydantic import BaseModel, Field

from worker import _process_job, _do_callback

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(name)s: %(message)s",
)
logger = logging.getLogger("server")

# Env
CALLBACK_SECRET = os.environ.get("AI_CALLBACK_SECRET") or os.environ.get("INSTANTMESH_CALLBACK_SECRET", "")
MAX_ACTIVE_JOBS = int(os.environ.get("MAX_ACTIVE_JOBS", "2"))
MAX_QUEUE_LENGTH = int(os.environ.get("MAX_QUEUE_LENGTH", "50"))
COMFYUI_URL = os.environ.get("COMFYUI_URL", "http://127.0.0.1:8188")

_queue: asyncio.Queue | None = None
_semaphore: asyncio.Semaphore | None = None
_executor: ThreadPoolExecutor | None = None


class GenerateRequest(BaseModel):
    job_id: str = Field(..., min_length=1, max_length=64)
    type: str = Field(..., description="text2img|character_create|character_variation|upscale|texture_seamless")
    payload: dict = Field(default_factory=dict)
    callback_url: str = Field(..., min_length=1)
    secret: str = Field(default="")


def _validate_secret(secret: str) -> bool:
    return bool(CALLBACK_SECRET) and secret == CALLBACK_SECRET


def _validate_type(t: str) -> bool:
    return t in ("text2img", "character_create", "character_variation", "upscale", "texture_seamless")


def _validate_payload(t: str, p: dict) -> str | None:
    if t == "text2img":
        if not p.get("prompt"):
            return "prompt required"
    elif t == "upscale":
        if not p.get("image_url"):
            return "image_url required"
    elif t == "character_create":
        if not p.get("prompt"):
            return "prompt required"
    elif t == "character_variation":
        if not p.get("character_id") or not p.get("variation_prompt"):
            return "character_id and variation_prompt required"
    elif t == "texture_seamless":
        if not p.get("image_url") and not p.get("prompt"):
            return "image_url or prompt required"
    return None


async def _worker_loop() -> None:
    global _queue, _semaphore, _executor
    _queue = asyncio.Queue(maxsize=MAX_QUEUE_LENGTH)
    _semaphore = asyncio.Semaphore(MAX_ACTIVE_JOBS)
    _executor = ThreadPoolExecutor(max_workers=max(1, MAX_ACTIVE_JOBS))
    loop = asyncio.get_event_loop()
    logger.info("Worker started, MAX_ACTIVE_JOBS=%d, MAX_QUEUE_LENGTH=%d", MAX_ACTIVE_JOBS, MAX_QUEUE_LENGTH)

    while True:
        try:
            task = await _queue.get()
            if task is None:
                break
            async with _semaphore:
                try:
                    await loop.run_in_executor(_executor, _process_job, task)
                except Exception as e:
                    logger.exception("[%s] worker error: %s", task.get("job_id"), e)
                    _do_callback(
                        task["job_id"],
                        task["callback_url"],
                        task.get("secret") or CALLBACK_SECRET,
                        "failed",
                        error_message=str(e),
                    )
                finally:
                    _queue.task_done()
        except asyncio.CancelledError:
            break
        except Exception as e:
            logger.exception("worker loop: %s", e)


def enqueue_job(task: dict) -> None:
    if _queue is None:
        raise RuntimeError("Worker not started")
    try:
        _queue.put_nowait(task)
    except asyncio.QueueFull:
        raise HTTPException(status_code=503, detail="Queue full")


@asynccontextmanager
async def lifespan(app: FastAPI):
    asyncio.create_task(_worker_loop())
    yield
    if _queue:
        await _queue.put(None)


app = FastAPI(title="KND ComfyUI GPU Router", version="1.0.0", lifespan=lifespan)


@app.post("/generate")
async def generate(req: GenerateRequest):
    """Enqueue job. Returns 202 immediately."""
    if not _validate_secret(req.secret):
        raise HTTPException(status_code=403, detail="Invalid secret")

    if not _validate_type(req.type):
        raise HTTPException(
            status_code=400,
            detail=f"type must be one of: text2img, character_create, character_variation, upscale, texture_seamless",
        )

    err = _validate_payload(req.type, req.payload or {})
    if err:
        raise HTTPException(status_code=400, detail=err)

    try:
        enqueue_job({
            "job_id": req.job_id,
            "type": req.type,
            "payload": req.payload or {},
            "callback_url": req.callback_url,
            "secret": req.secret,
        })
    except HTTPException:
        raise
    except Exception as e:
        logger.exception("enqueue: %s", e)
        raise HTTPException(status_code=500, detail="Enqueue failed")

    return JSONResponse(
        status_code=202,
        content={"ok": True, "job_id": req.job_id, "status": "queued"},
    )


@app.get("/files")
async def serve_file(
    request: Request,
    filename: str = "",
    subfolder: str = "",
    type: str = "output",
):
    """Proxy to ComfyUI /view for output files."""
    import requests as req
    if not filename:
        raise HTTPException(status_code=400, detail="filename required")
    safe = "".join(c for c in filename if c.isalnum() or c in "-_.")
    if safe != filename:
        raise HTTPException(status_code=400, detail="Invalid filename")
    params = {"filename": filename, "type": type}
    if subfolder:
        params["subfolder"] = subfolder
    url = f"{COMFYUI_URL.rstrip('/')}/view?"
    url += "&".join(f"{k}={v}" for k, v in params.items())
    try:
        r = req.get(url, timeout=60, stream=True)
        r.raise_for_status()
        return StreamingResponse(
            r.iter_content(chunk_size=8192),
            media_type=r.headers.get("Content-Type", "application/octet-stream"),
        )
    except Exception as e:
        logger.warning("proxy /view: %s", e)
        raise HTTPException(status_code=502, detail="ComfyUI unavailable")


@app.get("/health")
async def health():
    return {"ok": True, "engine": "comfyui", "queue_max": MAX_QUEUE_LENGTH}


if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8000)

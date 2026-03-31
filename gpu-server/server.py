"""
KND Store - GPU Server (InstantMesh)
Compatible with KND TripoSR API contract.
POST /generate -> 202 Accepted, enqueue job
GET /models/{job_id}.glb -> serve generated model
"""

import os
import asyncio
import logging
from contextlib import asynccontextmanager
from pathlib import Path

from fastapi import FastAPI, HTTPException
from fastapi.responses import JSONResponse, FileResponse
from pydantic import BaseModel, Field

from worker import enqueue_job, start_worker, MODELS_DIR

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(name)s: %(message)s",
)
logger = logging.getLogger("server")


@asynccontextmanager
async def lifespan(app: FastAPI):
    start_worker()
    yield
    # shutdown: cancel worker tasks if needed


app = FastAPI(title="KND GPU Server (InstantMesh)", version="1.0.0", lifespan=lifespan)


class GenerateRequest(BaseModel):
    job_id: str = Field(..., min_length=1, max_length=64)
    image_url: str = Field(..., min_length=1)
    callback_url: str = Field(..., min_length=1)
    secret: str = Field(default="", description="Shared secret for callback auth")


@app.post("/generate")
async def generate(req: GenerateRequest):
    """Enqueue a mesh generation job. Returns 202 immediately."""
    try:
        enqueue_job(
            job_id=req.job_id,
            image_url=req.image_url,
            callback_url=req.callback_url,
            secret=req.secret,
        )
    except Exception as e:
        logger.exception("enqueue failed: %s", e)
        raise HTTPException(status_code=500, detail="Enqueue failed")

    return JSONResponse(
        status_code=202,
        content={"ok": True, "job_id": req.job_id, "status": "queued"},
    )


@app.get("/models/{filename:path}")
async def serve_model(filename: str):
    """Serve generated model (GLB or OBJ) by filename."""
    # Security: only allow alphanumeric, hyphen, underscore in filename
    safe = "".join(c for c in filename if c.isalnum() or c in "-_.")
    if safe != filename:
        raise HTTPException(status_code=400, detail="Invalid filename")

    path = MODELS_DIR / filename
    if not path.exists() or not path.is_file():
        raise HTTPException(status_code=404, detail="Model not found")

    if path.suffix.lower() == ".glb":
        media_type = "model/gltf-binary"
    elif path.suffix.lower() == ".obj":
        media_type = "text/plain"
    else:
        media_type = "application/octet-stream"

    return FileResponse(path, media_type=media_type, filename=filename)


@app.get("/health")
async def health():
    """Health check."""
    return {"ok": True, "engine": "instantmesh"}


if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8000)

#!/usr/bin/env python3
"""
3D Lab queue worker (DB polling).
Follows the same architecture as instantmesh_worker.py.

Pipeline:
- Lease job from knd_labs_3d_jobs
- Run run_labs_3d_job.py (downloads input from web, ComfyUI 3D, copies GLB to storage + staging)
- Upload GLB and preview to hosting (web on hosting, worker local)
- Update status, paths, meta

Env: KND_DB_HOST, KND_DB_PORT, KND_DB_NAME, KND_DB_USER, KND_DB_PASS
      WORKER_SLEEP_SECONDS (default 5)
      LABS_3D_STALE_MINUTES (default 30)
      KND_3D_UPLOAD_TOKEN (required for upload to hosting; 3D-only, separate from Text2Img)
      PUBLIC_SITE_BASE_URL (default https://kndstore.com)
"""
from __future__ import annotations

import json
import os
import subprocess
import sys
import time
from pathlib import Path

import requests

from _db_common import db_connect, ensure_connection, import_db_driver, to_rel_storage

PROJECT_ROOT = Path(__file__).resolve().parents[1]
UPLOAD_BASE = os.getenv("PUBLIC_SITE_BASE_URL", "https://kndstore.com").rstrip("/")
WORKER_3D_UPLOAD_TOKEN = os.getenv("KND_3D_UPLOAD_TOKEN", "").strip()
STALE_MINUTES = max(5, int(os.getenv("LABS_3D_STALE_MINUTES", "30")))


def _is_remote_site() -> bool:
    """True if web is on hosting (not localhost); then GLB must be uploaded for download to work."""
    base = UPLOAD_BASE.lower()
    return "localhost" not in base and "127.0.0.1" not in base


def _log(msg: str) -> None:
    print(f"[labs-3d-worker] {msg}")


def recover_stale_jobs(conn) -> int:
    """Reset jobs stuck in 'processing' for too long. Returns count reset."""
    with conn.cursor() as cur:
        cur.execute(
            """
            UPDATE knd_labs_3d_jobs
            SET status = 'failed',
                error_message = COALESCE(error_message, 'Job abandoned (worker timeout)'),
                completed_at = NOW(),
                updated_at = NOW()
            WHERE status = 'processing'
              AND processing_started_at < DATE_SUB(NOW(), INTERVAL %s MINUTE)
            """,
            (STALE_MINUTES,),
        )
        n = cur.rowcount
        conn.commit()
    if n > 0:
        _log(f"recovered {n} stale job(s) (processing > {STALE_MINUTES} min)")
    return n


def lease_next_job(conn):
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT id, public_id, input_image_path, quality, advanced_params_json, mode
            FROM knd_labs_3d_jobs
            WHERE status = 'queued'
            ORDER BY created_at ASC
            LIMIT 1
            FOR UPDATE
            """
        )
        row = cur.fetchone()
        if not row:
            conn.rollback()
            return None

        cur.execute(
            """
            UPDATE knd_labs_3d_jobs
            SET status = 'processing', processing_started_at = NOW(), updated_at = NOW()
            WHERE id = %s
            """,
            (row["id"],),
        )
        conn.commit()
        return row


def _mark_failed(conn, job_id, err_msg: str):
    with conn.cursor() as cur:
        cur.execute(
            """
            UPDATE knd_labs_3d_jobs
            SET status = 'failed', error_message = %s, completed_at = NOW(), updated_at = NOW()
            WHERE id = %s
            """,
            (err_msg[:65535], job_id),
        )
    conn.commit()


def _upload_output_to_hosting(public_id: str, glb_path: Path, preview_path: Path | None) -> str | None:
    """Upload GLB and preview to hosting. Returns error message or None on success."""
    if not WORKER_3D_UPLOAD_TOKEN:
        return "KND_3D_UPLOAD_TOKEN not set (required for upload to hosting)"
    glb_path = Path(glb_path)
    if not glb_path.is_file():
        return f"GLB file not found: {glb_path}"
    url = f"{UPLOAD_BASE}/api/labs/3d-lab/upload-output.php"
    headers = {"X-KND-3D-WORKER-TOKEN": WORKER_3D_UPLOAD_TOKEN}
    fds = []
    try:
        files = [
            ("public_id", (None, public_id)),
            ("_worker_3d_token", (None, WORKER_3D_UPLOAD_TOKEN)),
            ("glb", (f"{public_id}.glb", open(glb_path, "rb"), "model/gltf-binary")),
        ]
        fds.append(files[1][1][1])
        if preview_path and Path(preview_path).is_file():
            ext = Path(preview_path).suffix or ".webp"
            mime = "image/webp" if ext == ".webp" else ("image/png" if ext == ".png" else "image/jpeg")
            fo = open(preview_path, "rb")
            fds.append(fo)
            files.append(("preview", (f"{public_id}{ext}", fo, mime)))
        r = requests.post(url, headers=headers, files=files, timeout=120)
        r.raise_for_status()
        data = r.json()
        return None if data.get("ok") else data.get("error", "Upload failed")
    except Exception as e:
        return str(e)
    finally:
        for fd in fds:
            try:
                fd.close()
            except Exception:
                pass


def process_job(conn, job: dict):
    if not job.get("input_image_path"):
        _mark_failed(conn, job["id"], "Image-to-3D mode required; text-only not supported yet")
        return conn

    run_script = PROJECT_ROOT / "workers" / "run_labs_3d_job.py"
    if not run_script.exists():
        _mark_failed(conn, job["id"], "Worker script missing")
        return conn

    seed = None
    if job.get("advanced_params_json"):
        try:
            adv = json.loads(job["advanced_params_json"])
            if isinstance(adv, dict) and adv.get("seed"):
                seed = int(adv["seed"])
        except (json.JSONDecodeError, TypeError, ValueError):
            pass

    payload = {
        "job_id": job["id"],
        "public_id": job["public_id"],
        "input_image_path": job.get("input_image_path"),
        "quality": job.get("quality") or "Standard",
        "seed": seed,
    }

    proc = subprocess.run(
        [sys.executable, str(run_script), "--payload", json.dumps(payload)],
        capture_output=True,
        text=True,
        cwd=str(PROJECT_ROOT),
        check=False,
    )

    conn = ensure_connection(conn, _pym)

    if proc.returncode != 0:
        err = (proc.stderr or proc.stdout or "3D Lab runner failed").strip()
        _mark_failed(conn, job["id"], err)
        return conn

    try:
        out = json.loads(proc.stdout.strip() or "{}")
    except json.JSONDecodeError:
        _mark_failed(conn, job["id"], "Invalid runner JSON output")
        return conn

    if not out.get("ok"):
        err = str(out.get("error", "Unknown"))
        _mark_failed(conn, job["id"], err)
        return conn

    glb_abs = out.get("glb_path")
    prev_abs = out.get("preview_path")
    if glb_abs:
        if _is_remote_site() and not WORKER_3D_UPLOAD_TOKEN:
            _mark_failed(
                conn,
                job["id"],
                "Worker upload required. Set KND_3D_UPLOAD_TOKEN so results are uploaded to the server (download will work).",
            )
            return conn
        if WORKER_3D_UPLOAD_TOKEN:
            err = _upload_output_to_hosting(job["public_id"], Path(glb_abs), Path(prev_abs) if prev_abs else None)
            if err:
                _log(f"upload to hosting failed: {err}")
                _mark_failed(conn, job["id"], f"Generated but upload to hosting failed: {err}")
                return conn

    glb_rel = out.get("glb_path_rel")
    prev_rel = out.get("preview_path_rel")
    if not glb_rel and out.get("glb_path"):
        try:
            glb_rel = to_rel_storage(out["glb_path"], PROJECT_ROOT)
        except Exception:
            pass
    if not prev_rel and out.get("preview_path"):
        try:
            prev_rel = to_rel_storage(out["preview_path"], PROJECT_ROOT)
        except Exception:
            pass

    meta_json = None
    if out.get("meta"):
        try:
            meta_json = json.dumps(out["meta"])
        except (TypeError, ValueError):
            pass

    with conn.cursor() as cur:
        cur.execute(
            """
            UPDATE knd_labs_3d_jobs
            SET status = 'completed',
                glb_path = COALESCE(%s, glb_path),
                preview_path = COALESCE(%s, preview_path),
                meta_json = COALESCE(%s, meta_json),
                completed_at = NOW(),
                updated_at = NOW()
            WHERE id = %s
            """,
            (glb_rel, prev_rel, meta_json, job["id"]),
        )
    conn.commit()
    return conn


def main() -> int:
    global _pym
    _pym = import_db_driver()
    sleep_seconds = max(1, int(os.getenv("WORKER_SLEEP_SECONDS", "5")))

    conn = db_connect(_pym)

    _log("started")
    recover_stale_jobs(conn)

    try:
        while True:
            try:
                conn = ensure_connection(conn, _pym)
                job = lease_next_job(conn)
                if not job:
                    time.sleep(sleep_seconds)
                    continue

                _log(f"processing job #{job['id']} ({job['public_id']})")
                conn = process_job(conn, job)

            except KeyboardInterrupt:
                _log("stopped")
                break
            except Exception as exc:
                _log(f"error: {exc}")
                try:
                    conn = ensure_connection(conn, _pym)
                except Exception:
                    conn = db_connect(_pym)
                time.sleep(sleep_seconds)

    finally:
        try:
            conn.close()
        except Exception:
            pass

    return 0


_pym = None

if __name__ == "__main__":
    raise SystemExit(main())

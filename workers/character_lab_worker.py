#!/usr/bin/env python3
"""
Character Lab queue worker (DB polling).

Pipeline:
  Phase A: concept image (ComfyUI text2img or image normalization)
  Phase B: 3D generation (Hunyuan3D primary, TripoSR/InstantMesh fallback)

Environment variables:
- KND_DB_HOST, KND_DB_PORT, KND_DB_NAME, KND_DB_USER, KND_DB_PASS
- WORKER_SLEEP_SECONDS (optional, default 3)
"""

from __future__ import annotations

import json
import os
import subprocess
import sys
import time
from pathlib import Path


def _import_db_driver():
    try:
        import pymysql  # type: ignore
        return pymysql
    except Exception as exc:
        raise RuntimeError("pymysql required. pip install pymysql") from exc


def _db_connect(pymysql_module):
    return pymysql_module.connect(
        host=os.getenv("KND_DB_HOST", "127.0.0.1"),
        port=int(os.getenv("KND_DB_PORT", "3306")),
        user=os.getenv("KND_DB_USER", ""),
        password=os.getenv("KND_DB_PASS", ""),
        database=os.getenv("KND_DB_NAME", ""),
        autocommit=False,
        charset="utf8mb4",
        cursorclass=pymysql_module.cursors.DictCursor,
    )


def lease_next_job(conn):
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT id, public_id, mode, prompt_raw, prompt_sanitized, category,
                   input_image_path, engine_image, engine_3d
            FROM knd_character_lab_jobs
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
            UPDATE knd_character_lab_jobs
            SET status = 'image_generating', processing_started_at = NOW(), updated_at = NOW()
            WHERE id = %s
            """,
            (row["id"],),
        )
        conn.commit()
        return row


def _to_rel_storage(path: str, project_root: Path) -> str:
    p = Path(path).resolve()
    storage_root = (project_root / "storage").resolve()
    try:
        rel = p.relative_to(storage_root)
        return str(rel).replace("\\", "/")
    except ValueError:
        return path


def process_job(conn, job: dict, project_root: Path):
    run_script = project_root / "workers" / "run_character_lab_job.py"

    payload = {
        "job_id": int(job["id"]),
        "public_id": str(job["public_id"]),
        "mode": str(job.get("mode", "text")),
        "prompt_sanitized": str(job.get("prompt_sanitized", "")),
        "category": str(job.get("category", "human")),
        "input_image_path": job.get("input_image_path"),
        "engine_image": str(job.get("engine_image", "comfyui")),
        "engine_3d": str(job.get("engine_3d", "hunyuan3d")),
    }

    proc = subprocess.run(
        [sys.executable, str(run_script), "--payload", json.dumps(payload)],
        capture_output=True,
        text=True,
        cwd=str(project_root),
        check=False,
    )

    if proc.returncode != 0:
        err = (proc.stderr or proc.stdout or "Character Lab runner failed").strip()
        _mark_failed(conn, job["id"], err)
        return

    try:
        result = json.loads(proc.stdout.strip() or "{}")
    except json.JSONDecodeError:
        result = {"ok": False, "error": "Invalid runner JSON output"}

    if not result.get("ok"):
        err = str(result.get("error") or "Character Lab failed")
        _mark_failed(conn, job["id"], err)
        return

    concept_rel = _to_rel_storage(result["concept_path"], project_root) if result.get("concept_path") else None
    glb_rel = _to_rel_storage(result["glb_path"], project_root) if result.get("glb_path") else None
    thumb_rel = _to_rel_storage(result["preview_path"], project_root) if result.get("preview_path") else None

    status = "mesh_ready" if glb_rel else "partial_success"
    with conn.cursor() as cur:
        cur.execute(
            """
            UPDATE knd_character_lab_jobs
            SET status = %s,
                concept_image_path = COALESCE(%s, concept_image_path),
                mesh_glb_path = COALESCE(%s, mesh_glb_path),
                preview_thumb_path = COALESCE(%s, preview_thumb_path),
                completed_at = NOW(),
                updated_at = NOW()
            WHERE id = %s
            """,
            (status, concept_rel, glb_rel, thumb_rel, job["id"]),
        )
    conn.commit()


def _mark_failed(conn, job_id, err_msg: str):
    with conn.cursor() as cur:
        cur.execute(
            """
            UPDATE knd_character_lab_jobs
            SET status = 'failed', error_message = %s, completed_at = NOW(), updated_at = NOW()
            WHERE id = %s
            """,
            (err_msg[:65535], job_id),
        )
    conn.commit()


def main() -> int:
    sleep_seconds = max(1, int(os.getenv("WORKER_SLEEP_SECONDS", "3")))
    project_root = Path(__file__).resolve().parents[1]

    pymysql_mod = _import_db_driver()
    conn = _db_connect(pymysql_mod)

    print("[character-lab-worker] started")
    while True:
        try:
            job = lease_next_job(conn)
            if not job:
                time.sleep(sleep_seconds)
                continue

            print(f"[character-lab-worker] processing job #{job['id']} ({job['public_id']})")
            process_job(conn, job, project_root)
        except KeyboardInterrupt:
            print("\n[character-lab-worker] stopped")
            break
        except Exception as exc:
            print(f"[character-lab-worker] error: {exc}")
            time.sleep(sleep_seconds)

    try:
        conn.close()
    except Exception:
        pass
    return 0


if __name__ == "__main__":
    raise SystemExit(main())

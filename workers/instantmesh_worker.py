#!/usr/bin/env python3
"""
InstantMesh queue worker (DB polling).

MVP behavior:
- Pull one queued job from knd_labs_instantmesh_jobs
- Mark as processing
- Execute workers/run_instantmesh_job.py
- Persist output paths / status

Environment variables:
- KND_DB_HOST
- KND_DB_PORT (optional, default 3306)
- KND_DB_NAME
- KND_DB_USER
- KND_DB_PASS
- WORKER_SLEEP_SECONDS (optional, default 3)
"""

from __future__ import annotations

import json
import os
import subprocess
import sys
import time
from pathlib import Path

from _db_common import db_connect, ensure_connection, import_db_driver, to_rel_storage


def lease_next_job(conn):
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT id, public_id, source_image_path, remove_bg, seed, output_format
            FROM knd_labs_instantmesh_jobs
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
            UPDATE knd_labs_instantmesh_jobs
            SET status = 'processing', processing_started_at = NOW(), updated_at = NOW()
            WHERE id = %s
            """,
            (row["id"],),
        )
        conn.commit()
        return row




def process_job(conn, job: dict, project_root: Path, pymysql_mod):
    run_script = project_root / "workers" / "run_instantmesh_job.py"
    input_abs = (project_root / "storage" / str(job["source_image_path"])).resolve()

    payload = {
        "job_id": int(job["id"]),
        "public_id": str(job["public_id"]),
        "input_path": str(input_abs),
        "remove_bg": bool(int(job.get("remove_bg", 1))),
        "seed": int(job.get("seed", 42)),
        "output_format": str(job.get("output_format", "glb")),
    }

    proc = subprocess.run(
        [sys.executable, str(run_script), "--payload", json.dumps(payload)],
        capture_output=True,
        text=True,
        cwd=str(project_root),
        check=False,
    )

    conn = ensure_connection(conn, pymysql_mod)

    if proc.returncode != 0:
        err = (proc.stderr or proc.stdout or "InstantMesh runner failed").strip()
        with conn.cursor() as cur:
            cur.execute(
                """
                UPDATE knd_labs_instantmesh_jobs
                SET status = 'failed', error_message = %s, completed_at = NOW(), updated_at = NOW()
                WHERE id = %s
                """,
                (err[:65535], job["id"]),
            )
        conn.commit()
        return conn

    try:
        result = json.loads(proc.stdout.strip() or "{}")
    except json.JSONDecodeError:
        result = {"ok": False, "error": "Invalid runner JSON output"}

    if not result.get("ok"):
        err = str(result.get("error") or "InstantMesh failed")
        with conn.cursor() as cur:
            cur.execute(
                """
                UPDATE knd_labs_instantmesh_jobs
                SET status = 'failed', error_message = %s, completed_at = NOW(), updated_at = NOW()
                WHERE id = %s
                """,
                (err[:65535], job["id"]),
            )
        conn.commit()
        return conn

    preview_rel = to_rel_storage(result["preview_path"], project_root) if result.get("preview_path") else None
    glb_rel = to_rel_storage(result["glb_path"], project_root) if result.get("glb_path") else None
    obj_rel = to_rel_storage(result["obj_path"], project_root) if result.get("obj_path") else None

    with conn.cursor() as cur:
        cur.execute(
            """
            UPDATE knd_labs_instantmesh_jobs
            SET status = 'completed',
                preview_image_path = %s,
                output_glb_path = %s,
                output_obj_path = %s,
                completed_at = NOW(),
                updated_at = NOW()
            WHERE id = %s
            """,
            (preview_rel, glb_rel, obj_rel, job["id"]),
        )
    conn.commit()
    return conn


def main() -> int:
    sleep_seconds = max(1, int(os.getenv("WORKER_SLEEP_SECONDS", "3")))
    project_root = Path(__file__).resolve().parents[1]

    pymysql_mod = import_db_driver()
    conn = db_connect(pymysql_mod)

    print("[instantmesh-worker] started")
    while True:
        try:
            job = lease_next_job(conn)
            if not job:
                time.sleep(sleep_seconds)
                continue

            print(f"[instantmesh-worker] processing job #{job['id']} ({job['public_id']})")
            conn = ensure_connection(conn, pymysql_mod)
            conn = process_job(conn, job, project_root, pymysql_mod)
        except KeyboardInterrupt:
            print("\n[instantmesh-worker] stopped")
            break
        except Exception as exc:
            print(f"[instantmesh-worker] error: {exc}")
            time.sleep(sleep_seconds)

    try:
        conn.close()
    except Exception:
        pass
    return 0


if __name__ == "__main__":
    raise SystemExit(main())

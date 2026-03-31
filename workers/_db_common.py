"""
Shared DB helpers for KND workers (InstantMesh, 3D Lab, Character Lab).
- Connection with env-based config
- ensure_connection: ping/reconnect to avoid "MySQL server has gone away"
- to_rel_storage: normalize paths for storage
"""
from __future__ import annotations

import os
from pathlib import Path
from typing import TYPE_CHECKING

if TYPE_CHECKING:
    from typing import Any

    import pymysql


def import_db_driver():
    try:
        import pymysql  # type: ignore
        return pymysql
    except ImportError as exc:
        raise RuntimeError("pymysql required. pip install pymysql") from exc


def db_connect(pymysql_module) -> "pymysql.Connection":
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


def ensure_connection(conn, pymysql_module):
    """
    Ensure connection is alive. Reconnect on OperationalError (MySQL gone away).
    Returns a valid connection (possibly new).
    """
    try:
        conn.ping(reconnect=True)
        return conn
    except Exception:
        pass
    try:
        conn.close()
    except Exception:
        pass
    return db_connect(pymysql_module)


def to_rel_storage(path: str, project_root: Path) -> str:
    """Convert absolute path to storage-relative (forward slashes)."""
    p = Path(path).resolve()
    storage_root = (project_root / "storage").resolve()
    try:
        rel = p.relative_to(storage_root)
        return str(rel).replace("\\", "/")
    except ValueError:
        return path

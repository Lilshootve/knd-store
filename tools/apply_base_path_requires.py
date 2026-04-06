#!/usr/bin/env python3
"""
Codemod: require_once __DIR__ . '/../../includes/foo.php' (any depth)
  -> require_once BASE_PATH . '/includes/foo.php'
and prepend require_once __DIR__ . '/.../config/bootstrap.php' when missing.

Regex matches '/(\\.\\./)*includes/' after the opening quote (PHP always uses a
leading slash). Re-run is safe once paths are migrated (0 files changed).

Run: python backend/kndstore/tools/apply_base_path_requires.py
"""
from __future__ import annotations

import re
import sys
from pathlib import Path

KNDSTORE = Path(__file__).resolve().parent.parent

# Skip subtrees that are not app PHP or are already canonical
SKIP_DIR_NAMES = {"vendor", "node_modules", ".git"}

# Match __DIR__ . '/../../includes/file.php' or __DIR__ . '/includes/file.php' (note leading / after quote)
DIR_INCLUDES_RE = re.compile(
    r"__DIR__\s*\.\s*(['\"])/(?:\.\./)*includes/([^'\"]+)\1"
)


def bootstrap_suffix(depth: int) -> str:
    if depth == 0:
        return "config/bootstrap.php"
    return "/".join([".."] * depth) + "/config/bootstrap.php"


def bootstrap_depth_for_file(path: Path) -> int:
    rel = path.resolve().relative_to(KNDSTORE.resolve())
    return len(rel.parent.parts)


def insert_bootstrap_after_opening(content: str, boot_line: str) -> str:
    lines = content.splitlines(keepends=True)
    if not lines:
        return content
    if not lines[0].startswith("<?php"):
        return content
    i = 1
    while i < len(lines) and lines[i].strip() == "":
        i += 1
    # declare(strict_types=1);
    if i < len(lines):
        stripped = lines[i].lstrip()
        if stripped.startswith("declare") and "strict_types" in stripped:
            i += 1
            while i < len(lines) and lines[i].strip() == "":
                i += 1
    # /** docblock */
    if i < len(lines) and lines[i].lstrip().startswith("/**"):
        while i < len(lines) and "*/" not in lines[i]:
            i += 1
        i += 1
        while i < len(lines) and lines[i].strip() == "":
            i += 1
    lines.insert(i, boot_line)
    return "".join(lines)


def process_file(path: Path) -> bool:
    try:
        text = path.read_text(encoding="utf-8")
    except OSError:
        return False
    if "includes/" not in text or "__DIR__" not in text:
        return False
    if not DIR_INCLUDES_RE.search(text):
        return False

    depth = bootstrap_depth_for_file(path)
    boot_suffix = bootstrap_suffix(depth)
    boot_line = f"require_once __DIR__ . '/{boot_suffix}';\n"

    new_text = DIR_INCLUDES_RE.sub(
        lambda m: f"BASE_PATH . '/includes/{m.group(2)}'", text
    )
    if new_text == text:
        return False

    if "config/bootstrap.php" not in new_text:
        new_text = insert_bootstrap_after_opening(new_text, boot_line)
    path.write_text(new_text, encoding="utf-8", newline="")
    return True


def main() -> int:
    changed = 0
    for p in sorted(KNDSTORE.rglob("*.php")):
        if any(part in SKIP_DIR_NAMES for part in p.parts):
            continue
        if process_file(p):
            print(p.relative_to(KNDSTORE))
            changed += 1
    print(f"Updated {changed} files.", file=sys.stderr)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())

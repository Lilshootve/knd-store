#!/usr/bin/env python3
"""Scale font-size declarations (~15%) in CSS/PHP/HTML/JS. Skips vw/vh units inside values.

Do not run twice on the same files (values would scale again). For new assets only, pass paths:
  py -3 scripts/scale_font_sizes.py path/to/new.css
"""
import re
import sys
from pathlib import Path

SCALE = 1.15

ROOT = Path(__file__).resolve().parents[1]

FILES = [
    "games/mind-wars/lobby.css",
    "games/mind-wars/mw-avatar-cards.css",
    "games/knd-neural-link/assets/drops.css",
    "games/mind-wars/mind-wars-arena.php",
    "games/mind-wars/lobby.html",
    "games/mind-wars/lobby.js",
    "games/knd-neural-link/assets/drops.js",
    "games/mind-wars/lobby.php",
    "games/mind-wars/lobby-partials/topbar.php",
    "games/mind-wars/lobby-partials/panels_right.php",
    "games/mind-wars.php",
    "assets/css/mind-wars.css",
    "assets/css/arena-hub.css",
    "assets/css/knowledge-duel.css",
    "tools/cards/index.html",
    "games/mind-wars/mind-wars-arena.html",
    "games/mind-wars/update-arena.html",
]


CAP_NUM = r"(\d+\.\d+|\d+\.|\.\d+|\d+)"


def fmt_num(n_str: str) -> str:
    v = float(n_str) * SCALE
    if abs(v - round(v)) < 0.06:
        return str(int(round(v)))
    s = f"{v:.2f}".rstrip("0").rstrip(".")
    return s


def scale_value(val: str) -> str:
    val = re.sub(
        rf"{CAP_NUM}px\b",
        lambda m: f"{fmt_num(m.group(1))}px",
        val,
    )
    val = re.sub(
        rf"{CAP_NUM}rem\b",
        lambda m: f"{fmt_num(m.group(1))}rem",
        val,
    )
    return val


def process(content: str) -> str:
    # html{font-size:16px} — no semicolon before }
    content = re.sub(
        rf"(font-size\s*:\s*){CAP_NUM}(px|rem)(\s*\}})",
        lambda m: f"{m.group(1)}{fmt_num(m.group(2))}{m.group(3)}{m.group(4)}",
        content,
        flags=re.I,
    )
    # font-size: ... ;
    def semi(m):
        return m.group(1) + scale_value(m.group(2)) + ";"

    content = re.sub(r"(font-size\s*:\s*)([^;]+);", semi, content, flags=re.I)
    return content


def main(argv):
    todo = argv if argv else FILES
    for rel in todo:
        path = ROOT.joinpath(*rel.split("/"))
        if not path.exists():
            print(f"skip missing: {rel}", file=sys.stderr)
            continue
        raw = path.read_text(encoding="utf-8")
        out = process(raw)
        if out != raw:
            path.write_text(out, encoding="utf-8", newline="\n")
            print(f"updated {rel}")
        else:
            print(f"unchanged {rel}")


if __name__ == "__main__":
    main(sys.argv[1:])

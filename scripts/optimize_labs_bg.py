#!/usr/bin/env python3
"""Convert background images to optimized WebP (max 1920px, quality 70)."""
from pathlib import Path
from PIL import Image
import sys

MAX_WIDTH = 1920
QUALITY = 70  # 65-75 range
BASE = Path(__file__).resolve().parent.parent / "assets" / "images"

def optimize(name: str) -> int:
    """Convert {name}.png to {name}.webp. Returns 0 on success."""
    src = BASE / f"{name}.png"
    dest = BASE / f"{name}.webp"
    if not src.exists():
        print(f"Error: {src} not found")
        return 1
    img = Image.open(src)
    if img.mode in ("RGBA", "P"):
        img = img.convert("RGB")
    w, h = img.size
    if w > MAX_WIDTH:
        ratio = MAX_WIDTH / w
        img = img.resize((MAX_WIDTH, int(h * ratio)), Image.Resampling.LANCZOS)
    img.save(dest, "WEBP", quality=QUALITY, method=6)
    size_kb = dest.stat().st_size / 1024
    print(f"Saved {dest} ({size_kb:.1f} KB)")
    return 0

def main():
    names = sys.argv[1:] if len(sys.argv) > 1 else ["background-lab", "background-arena"]
    for n in names:
        if optimize(n) != 0:
            return 1
    return 0

if __name__ == "__main__":
    exit(main())

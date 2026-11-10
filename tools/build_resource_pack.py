#!/usr/bin/env python3
"""Extract or rebuild VivaPet's Bedrock resource pack.

Usage:
  python tools/build_resource_pack.py --extract
  # Edit files under resources/pack/
  python tools/build_resource_pack.py --build

Python 3 only; no third-party packages are required.
"""
from __future__ import annotations

import argparse
import os
import tempfile
import zipfile
from pathlib import Path, PurePosixPath

ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / "resources" / "VivaPet.mcpack"
PACK_DIR = ROOT / "resources" / "pack"
REQUIRED = (
    "manifest.json",
    "entity/fox.entity.json",
    "entity/dragon.entity.json",
    "entity/owl.entity.json",
    "entity/rabbit.entity.json",
    "models/fox.geo.json",
    "models/dragon.geo.json",
    "models/owl.geo.json",
    "models/rabbit.geo.json",
)


def safe_member(name: str) -> bool:
    path = PurePosixPath(name)
    return not path.is_absolute() and ".." not in path.parts and "\\" not in name


def extract_pack() -> None:
    if not SOURCE.is_file():
        raise FileNotFoundError(f"Original resource pack not found: {SOURCE}")
    if PACK_DIR.exists() and any(PACK_DIR.iterdir()):
        raise FileExistsError(
            f"{PACK_DIR} already contains files. Back them up or move the folder before extracting."
        )
    PACK_DIR.mkdir(parents=True, exist_ok=True)
    with zipfile.ZipFile(SOURCE) as archive:
        bad = archive.testzip()
        if bad:
            raise RuntimeError(f"Corrupt archive member: {bad}")
        for member in archive.infolist():
            if not safe_member(member.filename):
                raise RuntimeError(f"Unsafe path in archive: {member.filename}")
        archive.extractall(PACK_DIR)
    print(f"Extracted source assets to {PACK_DIR}")


def build_pack() -> None:
    if not PACK_DIR.is_dir():
        raise FileNotFoundError(
            f"Source assets do not exist: {PACK_DIR}. Run with --extract first."
        )

    files = sorted(path for path in PACK_DIR.rglob("*") if path.is_file())
    relative_files = [path.relative_to(PACK_DIR).as_posix() for path in files]
    missing = [name for name in REQUIRED if name not in relative_files]
    if missing:
        raise RuntimeError("Resource pack is missing: " + ", ".join(missing))

    for filename in relative_files:
        if not safe_member(filename):
            raise RuntimeError(f"Unsafe source path: {filename}")

    SOURCE.parent.mkdir(parents=True, exist_ok=True)
    fd, temporary = tempfile.mkstemp(prefix="VivaPet-", suffix=".mcpack", dir=SOURCE.parent)
    os.close(fd)
    try:
        with zipfile.ZipFile(temporary, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
            for path in files:
                archive.write(path, path.relative_to(PACK_DIR).as_posix())
        with zipfile.ZipFile(temporary, "r") as archive:
            bad = archive.testzip()
            if bad:
                raise RuntimeError(f"Built pack failed integrity check at: {bad}")
        os.replace(temporary, SOURCE)
    finally:
        if os.path.exists(temporary):
            os.unlink(temporary)

    print(f"Built {SOURCE} ({SOURCE.stat().st_size} bytes)")


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    group = parser.add_mutually_exclusive_group(required=True)
    group.add_argument("--extract", action="store_true", help="extract the bundled .mcpack into resources/pack/")
    group.add_argument("--build", action="store_true", help="rebuild resources/VivaPet.mcpack from resources/pack/")
    args = parser.parse_args()
    if args.extract:
        extract_pack()
    else:
        build_pack()


if __name__ == "__main__":
    main()

#!/usr/bin/env python3
"""Build the optional server-assets ZIP. Never creates a resource pack."""
from __future__ import annotations
import argparse, json, os, tempfile, zipfile
from pathlib import Path, PurePosixPath

ROOT=Path(__file__).resolve().parents[1]
ASSET_DIR=ROOT/"assets"/"server"
DEFAULT_OUTPUT=ROOT/"build"/"VivaPet-Server-Assets.zip"

def safe_member(name: str) -> bool:
    path=PurePosixPath(name)
    return not path.is_absolute() and ".." not in path.parts and "\\" not in name

def build(output: Path) -> None:
    output.parent.mkdir(parents=True,exist_ok=True)
    required=["README.txt","config.yml","rank-permissions.txt","pet-catalog.json"]
    missing=[name for name in required if not (ASSET_DIR/name).is_file()]
    if missing: raise FileNotFoundError("Missing assets: "+", ".join(missing))
    catalog=json.loads((ASSET_DIR/"pet-catalog.json").read_text(encoding="utf-8"))
    if catalog.get("requires_resource_pack") is not False: raise RuntimeError("Assets must not require a resource pack")
    if int(catalog.get("pet_count",-1)) != len(catalog.get("pets",[])): raise RuntimeError("Pet catalogue count mismatch")
    fd,temp_name=tempfile.mkstemp(prefix="VivaPet-assets-",suffix=".zip",dir=output.parent)
    os.close(fd)
    try:
        with zipfile.ZipFile(temp_name,"w",compression=zipfile.ZIP_DEFLATED,compresslevel=9) as archive:
            for name in required:
                member=f"VivaPet-Server-Assets/{name}"
                if not safe_member(member): raise RuntimeError("Unsafe ZIP member: "+member)
                archive.write(ASSET_DIR/name,member)
        with zipfile.ZipFile(temp_name,"r") as archive:
            if archive.testzip() is not None: raise RuntimeError("ZIP integrity check failed")
            if any(item.filename.lower().endswith(".mcpack") for item in archive.infolist()): raise RuntimeError("Unexpected .mcpack")
        Path(temp_name).replace(output)
    finally:
        Path(temp_name).unlink(missing_ok=True)
    print(f"Built {output} ({output.stat().st_size} bytes)")

def main() -> None:
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--output",type=Path,default=DEFAULT_OUTPUT)
    build(parser.parse_args().output.resolve())

if __name__=="__main__": main()

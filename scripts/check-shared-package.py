#!/usr/bin/env python3
"""Standalone release archive integrity and web-root boundary checks."""
import argparse
import hashlib
import json
import re
import sys
from pathlib import PurePosixPath
from zipfile import ZipFile


def check(path: str) -> None:
    with ZipFile(path) as archive:
        members = set(archive.namelist())
        required = {
            "public_html/index.php", "public_html/.htaccess",
            "public_html/assets/vendor/tabler/tabler.min.css",
            "public_html/assets/vendor/tabler-icons/tabler-icons.min.css",
            "public_html/assets/vendor/bootstrap/css/bootstrap.min.css",
            "public_html/assets/vendor/bootstrap/js/bootstrap.bundle.min.js",
            "vendor/autoload.php", "app/Controllers/Install.php", "shared-core-rescue.php", "shared-release.json",
            "src/Core/Install/SharedHostSetup.php", ".env",
            "README-SHARED-HOSTING.md", "public_html/assets/themes/mcms-default/css/theme.css",
        }
        missing = required - members
        if missing:
            raise ValueError(f"Missing release files: {sorted(missing)}")
        for directory in ("writable/cache/", "writable/logs/", "writable/session/", "writable/backups/", "writable/modules-data/installed/", "writable/themes-data/installed/"):
            if directory not in members:
                raise ValueError(f"Writable directory missing from ZIP: {directory}")
        for name in members:
            segments = PurePosixPath(name).parts
            if name.startswith("/") or ".." in segments or "\\" in name:
                raise ValueError(f"Unsafe archive member: {name}")
            if name.startswith("public_html/"):
                rel = name[len("public_html/"):]
                if rel.startswith(("app/", "src/", "vendor/", "writable/", "modules/")):
                    raise ValueError(f"Internal directory exposed: {name}")
                if rel.endswith(".php") and rel != "index.php":
                    raise ValueError(f"Unexpected executable PHP file: {name}")
        metadata = json.loads(archive.read("shared-release.json"))
        if metadata.get("format") != "mcms-shared-release-v1":
            raise ValueError("Invalid Core release manifest")
        for name, digest in metadata["files"].items():
            if name not in members or hashlib.sha256(archive.read(name)).hexdigest() != digest:
                raise ValueError(f"Unverified Core release file: {name}")
        env = archive.read(".env").decode()
        if "MCMS_INSTALL_MODE = shared" not in env or "MCMS_DB_PASSWORD" in env:
            raise ValueError("Incorrect shared installer environment")
        entry = archive.read("public_html/index.php").decode()
        if "'../app/Config/Paths.php'" not in entry:
            raise ValueError("Front controller lost its private app path")
    print(f"Shared hosting release structure OK: {len(members)} files")


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("archive")
    args = parser.parse_args()
    try:
        check(args.archive)
    except (ValueError, OSError) as error:
        print(error, file=sys.stderr)
        sys.exit(1)

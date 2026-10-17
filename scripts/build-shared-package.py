#!/usr/bin/env python3
"""Build a ready-to-upload shared hosting ZIP on CI/developer machine.

The hosting customer never needs Composer, Node, Docker or a shell.
"""
from __future__ import annotations

import argparse
import os
import re
import shutil
import subprocess
import tempfile
from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile

REPO = Path(__file__).resolve().parents[1]
SOURCE_FOLDERS = ("app", "src", "modules", "themes", "catalog")
SOURCE_FILES = ("composer.json", "composer.lock", "LICENSE", "THIRD_PARTY_NOTICES.md")
NODE_PACKAGES = ("@tabler/core@1.6.1", "@tabler/icons-webfont@3.48.0", "bootstrap@5.3.8")


def run(*argv: str, cwd: Path) -> None:
    subprocess.run(argv, cwd=cwd, check=True)


def build(output: Path) -> Path:
    version_php = (REPO / "src/Core/Version.php").read_text()
    match = re.search(r"const VERSION\s*=\s*'([0-9]+\.[0-9]+\.[0-9]+)'", version_php)
    if not match:
        raise RuntimeError("Unable to read the mCMS Core release version")
    version = match.group(1)
    archive = output / f"mcms-{version}-shared-hosting.zip"
    output.mkdir(parents=True, exist_ok=True)
    if archive.exists():
        raise FileExistsError(f"Package already exists: {archive}")

    with tempfile.TemporaryDirectory(prefix="mcms-shared-") as temporary:
        scratch = Path(temporary)
        stage = scratch / "package"
        stage.mkdir()
        for name in SOURCE_FOLDERS:
            shutil.copytree(REPO / name, stage / name)
        for name in SOURCE_FILES:
            shutil.copy2(REPO / name, stage / name)
        shutil.copytree(REPO / "public", stage / "public_html")
        shutil.copy2(REPO / "docs/SHARED_HOSTING.md", stage / "README-SHARED-HOSTING.md")
        (stage / ".env").write_text(
            "CI_ENVIRONMENT = production\nMCMS_INSTALL_MODE = shared\n",
            encoding="utf-8",
        )

        for name in (
            "cache", "logs", "session", "backups", "modules-data",
            "themes-data", "uploads", "modules-data/installed",
            "modules-data/data", "themes-data/installed", "themes-data/data",
        ):
            (stage / "writable" / name).mkdir(parents=True, exist_ok=True)
        (stage / "writable" / "index.html").write_text("", encoding="utf-8")
        # Composer dependencies are bundled: php/composer is not needed on hosting.
        run(
            "composer", "install", "--no-dev", "--prefer-dist",
            "--no-interaction", "--no-progress", "--no-scripts",
            "--optimize-autoloader", cwd=stage,
        )
        node_dir = scratch / "ui"
        node_dir.mkdir()
        run("npm", "init", "-y", cwd=node_dir)
        run(
            "npm", "install", "--save-exact", "--ignore-scripts",
            "--omit=optional", "--no-audit", "--no-fund",
            *NODE_PACKAGES, cwd=node_dir,
        )
        modules = node_dir / "node_modules"
        vendor_assets = stage / "public_html/assets/vendor"
        static_files = {
            "@tabler/core/dist/css/tabler.min.css": "tabler/tabler.min.css",
            "@tabler/core/dist/js/tabler.min.js": "tabler/tabler.min.js",
            "@tabler/icons-webfont/dist/tabler-icons.min.css": "tabler-icons/tabler-icons.min.css",
            "bootstrap/dist/css/bootstrap.min.css": "bootstrap/css/bootstrap.min.css",
            "bootstrap/dist/js/bootstrap.bundle.min.js": "bootstrap/js/bootstrap.bundle.min.js",
        }
        for source, destination in static_files.items():
            target = vendor_assets / destination
            target.parent.mkdir(parents=True, exist_ok=True)
            shutil.copy2(modules / source, target)
        shutil.copytree(
            modules / "@tabler/icons-webfont/dist/fonts",
            vendor_assets / "tabler-icons/fonts",
        )
        for theme in (stage / "themes").iterdir():
            assets = theme / "assets"
            if assets.is_dir():
                shutil.copytree(
                    assets, stage / "public_html/assets/themes" / theme.name,
                    dirs_exist_ok=True,
                )

        with ZipFile(archive, "x", compression=ZIP_DEFLATED, compresslevel=6) as zipfile:
            for file in sorted(stage.rglob("*")):
                if file.is_symlink():
                    raise RuntimeError(f"Package must not include symlinks: {file}")
                if file.is_dir():
                    zipfile.write(file, file.relative_to(stage).as_posix() + "/")
                elif file.is_file():
                    relative = file.relative_to(stage).as_posix()
                    if relative.startswith("public_html/") and (
                        relative.endswith(".php") and relative != "public_html/index.php"
                    ):
                        raise RuntimeError(f"Unexpected public PHP file: {relative}")
                    zipfile.write(file, relative)

        print(f"Built {archive} ({archive.stat().st_size:,} bytes)")
    return archive


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--output", default=str(REPO / "dist"))
    args = parser.parse_args()
    build(Path(args.output).resolve())


if __name__ == "__main__":
    main()

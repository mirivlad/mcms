#!/usr/bin/env python3
"""Build a ready-to-upload shared hosting ZIP on CI/developer machine.

The hosting customer never needs Composer, Node, Docker or a shell.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import os
import re
import shutil
import subprocess
import tempfile
from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile

REPO = Path(__file__).resolve().parents[1]
SOURCE_FOLDERS = ("app", "src", "modules", "themes", "catalog")
SOURCE_FILES = ("composer.json", "composer.lock", "LICENSE", "THIRD_PARTY_NOTICES.md", "shared-core-rescue.php")
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
        shutil.copytree(REPO / "docs/guide", stage / "manual")
        # Keep developer reference links usable when the manuals are opened
        # offline, without relying on files in the Git source checkout.
        reference = stage / "manual/reference"
        reference.mkdir()
        for name in ("ARCHITECTURE.md", "MODULES.md", "THEMES.md", "EXTENSIONS.md"):
            shutil.copy2(REPO / "docs" / name, reference / name)
        for lang in ("en", "ru"):
            index = stage / "manual" / lang / "README.md"
            index.write_text(index.read_text(encoding="utf-8").replace(
                "../../ARCHITECTURE.md", "../reference/ARCHITECTURE.md"
            ).replace("../../MODULES.md", "../reference/MODULES.md")
             .replace("../../THEMES.md", "../reference/THEMES.md")
             .replace("../../EXTENSIONS.md", "../reference/EXTENSIONS.md"), encoding="utf-8")
        readme = stage / "README-SHARED-HOSTING.md"
        readme.write_text(
            "# mCMS standalone installation\n\n"
            "English instructions: [Administrator guide](manual/en/README.md).\n\n"
            "Русская инструкция: [Руководство администратора](manual/ru/README.md).\n\n"
            + readme.read_text(encoding="utf-8").replace("](guide/", "](manual/"),
            encoding="utf-8",
        )
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

        # Hash every executable/static Core file as an immutable release unit.
        # The installer verifies this before entering maintenance. The admin
        # still has to trust the package publisher (hashes are not signatures).
        core_roots = ("app/", "src/", "modules/", "themes/", "catalog/", "vendor/", "manual/", "public_html/assets/")
        release_files = {}
        for file in sorted(stage.rglob("*")):
            if file.is_file():
                name = file.relative_to(stage).as_posix()
                if name.startswith(core_roots):
                    release_files[name] = hashlib.sha256(file.read_bytes()).hexdigest()
        (stage / "shared-release.json").write_text(
            json.dumps({"format": "mcms-shared-release-v1", "version": version,
                        "files": release_files}, indent=2, sort_keys=True) + "\n",
            encoding="utf-8",
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

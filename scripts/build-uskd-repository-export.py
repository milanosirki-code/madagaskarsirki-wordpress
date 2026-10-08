#!/usr/bin/env python3
from __future__ import annotations

import argparse
import hashlib
import json
import shutil
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

FILES = {
    "integrations/uskd/AGENTS.md": "AGENTS.md",
    "integrations/uskd/README.md": "README.md",
    "integrations/uskd/homepage-378-dynamic-events-ready.html": "backups/homepage-378-dynamic-events-ready.html",
    "integrations/uskd/live-homepage-378-before-dynamic-events-2026-10-01.html": "backups/live-homepage-378-before-dynamic-events-2026-10-01.html",
    "integrations/uskd/live-page-392.html": "backups/live-page-392.html",
    "integrations/uskd/page-392-shortcode-ready.html": "backups/page-392-shortcode-ready.html",
    "integrations/uskd/uskd-madagaskar-events/README.md": "wp-content/plugins/uskd-madagaskar-events/README.md",
    "integrations/uskd/uskd-madagaskar-events/uskd-madagaskar-events.php": "wp-content/plugins/uskd-madagaskar-events/uskd-madagaskar-events.php",
    "docs/USKD_MADAGASKAR_EVENTS_DEPLOYMENT.md": "docs/USKD_MADAGASKAR_EVENTS_DEPLOYMENT.md",
}

def sha256(path: Path) -> str:
    h = hashlib.sha256()
    with path.open("rb") as fh:
        for chunk in iter(lambda: fh.read(1024 * 1024), b""):
            h.update(chunk)
    return h.hexdigest()

def write_target_validator(dst: Path) -> None:
    text = """#!/usr/bin/env python3
import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
MANIFEST = ROOT / "deployment-manifest.json"
PLUGIN = ROOT / "wp-content/plugins/uskd-madagaskar-events/uskd-madagaskar-events.php"

manifest = json.loads(MANIFEST.read_text(encoding="utf-8"))
plugin_text = PLUGIN.read_text(encoding="utf-8")

header = re.search(r"^\\s*\\* Version:\\s*([0-9.]+)\\s*$", plugin_text, re.MULTILINE)
constant = re.search(r"USKD_MDG_EVENTS_VERSION',\\s*'([^']+)'", plugin_text)

assert header, "plugin Version header not found"
assert constant, "USKD_MDG_EVENTS_VERSION constant not found"
assert manifest["plugin_version"] == header.group(1) == constant.group(1)
assert manifest["source_path"] == "wp-content/plugins/uskd-madagaskar-events"
assert manifest["live_page"]["backup_path"] == "backups/live-page-392.html"
assert manifest["shortcode"] == "uskd_madagaskar_events"

excluded = set(manifest["display_contract"]["exclude"])
assert {"price", "checkout_link", "woocommerce_order_data"} <= excluded
assert (ROOT / manifest["live_page"]["backup_path"]).is_file()

assert "shortcode_atts" in plugin_text
assert "array_slice" in plugin_text
assert "woocommerce" not in plugin_text.lower()
assert "checkout" not in plugin_text.lower()

print("USKD standalone deployment contract OK:", manifest["plugin_version"])
"""
    path = dst / "tests/validate-uskd-madagaskar-events.py"
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(text, encoding="utf-8")

def write_target_workflow(dst: Path) -> None:
    text = """name: Build USKD Madagaskar Events

on:
  workflow_dispatch:
  pull_request:
    paths:
      - 'wp-content/plugins/uskd-madagaskar-events/**'
      - 'deployment-manifest.json'
      - 'tests/validate-uskd-madagaskar-events.py'
      - '.github/workflows/build-uskd-madagaskar-events.yml'
  push:
    branches:
      - main
    paths:
      - 'wp-content/plugins/uskd-madagaskar-events/**'
      - 'deployment-manifest.json'
      - 'tests/validate-uskd-madagaskar-events.py'
      - '.github/workflows/build-uskd-madagaskar-events.yml'

permissions:
  contents: read

jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          coverage: none
      - name: Lint plugin PHP
        run: find wp-content/plugins/uskd-madagaskar-events -type f -name '*.php' -print0 | xargs -0 -n1 php -l
      - name: Validate deployment contract
        run: python tests/validate-uskd-madagaskar-events.py
      - name: Build ZIP and checksum
        shell: bash
        run: |
          set -euo pipefail
          rm -rf build
          mkdir -p build/uskd-madagaskar-events
          cp -R wp-content/plugins/uskd-madagaskar-events/. build/uskd-madagaskar-events/
          (
            cd build
            zip -r -q uskd-madagaskar-events.zip uskd-madagaskar-events
            sha256sum uskd-madagaskar-events.zip > uskd-madagaskar-events.zip.sha256
          )
      - uses: actions/upload-artifact@v4
        with:
          name: uskd-madagaskar-events
          path: |
            build/uskd-madagaskar-events.zip
            build/uskd-madagaskar-events.zip.sha256
          retention-days: 30
          if-no-files-found: error
"""
    path = dst / ".github/workflows/build-uskd-madagaskar-events.yml"
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(text, encoding="utf-8")

def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("output", type=Path)
    args = parser.parse_args()
    dst = args.output.resolve()

    if dst.exists():
        shutil.rmtree(dst)
    dst.mkdir(parents=True)

    for src_rel, dst_rel in FILES.items():
        src = ROOT / src_rel
        out = dst / dst_rel
        out.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(src, out)

    manifest = json.loads((ROOT / "integrations/uskd/deployment-manifest.json").read_text(encoding="utf-8"))
    manifest["source_path"] = "wp-content/plugins/uskd-madagaskar-events"
    manifest["live_page"]["backup_path"] = "backups/live-page-392.html"
    manifest["repository_split"] = {
        "target_repository": "milanosirki-code/uskdernegi-wordpress",
        "exported_from": "milanosirki-code/madagaskarsirki-wordpress",
        "issue": 57,
        "status": "seed_ready_repo_not_created",
    }
    (dst / "deployment-manifest.json").write_text(
        json.dumps(manifest, ensure_ascii=False, indent=2) + "\n",
        encoding="utf-8",
    )

    write_target_validator(dst)
    write_target_workflow(dst)

    exported = []
    for path in sorted(p for p in dst.rglob("*") if p.is_file()):
        exported.append({
            "path": str(path.relative_to(dst)),
            "sha256": sha256(path),
            "bytes": path.stat().st_size,
        })

    export_manifest = {
        "schema_version": 1,
        "target_repository": "milanosirki-code/uskdernegi-wordpress",
        "source_repository": "milanosirki-code/madagaskarsirki-wordpress",
        "issue": 57,
        "files": exported,
    }
    (dst / "repository-export-manifest.json").write_text(
        json.dumps(export_manifest, ensure_ascii=False, indent=2) + "\n",
        encoding="utf-8",
    )

    print(f"USKD repository seed built: {dst} ({len(exported)} files before export manifest)")

if __name__ == "__main__":
    main()

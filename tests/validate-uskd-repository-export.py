#!/usr/bin/env python3
from __future__ import annotations

import json
import subprocess
import tempfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
BUILDER = ROOT / "scripts/build-uskd-repository-export.py"

with tempfile.TemporaryDirectory() as td:
    out = Path(td) / "uskdernegi-wordpress"
    subprocess.run(["python", str(BUILDER), str(out)], check=True)

    required = [
        "AGENTS.md",
        "README.md",
        "deployment-manifest.json",
        "repository-export-manifest.json",
        "backups/live-page-392.html",
        "backups/homepage-378-dynamic-events-ready.html",
        "wp-content/plugins/uskd-madagaskar-events/uskd-madagaskar-events.php",
        "wp-content/plugins/uskd-madagaskar-events/README.md",
        "docs/USKD_MADAGASKAR_EVENTS_DEPLOYMENT.md",
        "tests/validate-uskd-madagaskar-events.py",
        ".github/workflows/build-uskd-madagaskar-events.yml",
    ]
    for rel in required:
        assert (out / rel).is_file(), f"missing export file: {rel}"

    manifest = json.loads((out / "deployment-manifest.json").read_text(encoding="utf-8"))
    assert manifest["source_path"] == "wp-content/plugins/uskd-madagaskar-events"
    assert manifest["live_page"]["backup_path"] == "backups/live-page-392.html"
    assert manifest["repository_split"]["target_repository"] == "milanosirki-code/uskdernegi-wordpress"
    assert manifest["display_contract"]["include"] == ["city", "date", "venue", "sessions"]
    assert {"price", "checkout_link", "woocommerce_order_data"} <= set(manifest["display_contract"]["exclude"])

    plugin = (out / "wp-content/plugins/uskd-madagaskar-events/uskd-madagaskar-events.php").read_text(encoding="utf-8")
    assert "Version: 0.2.0" in plugin
    assert "USKD_MDG_EVENTS_VERSION', '0.2.0'" in plugin
    assert "$event['price']" not in plugin
    assert 'woocommerce_' not in plugin.lower()
    assert 'wc_get_' not in plugin.lower()
    assert 'add_to_cart' not in plugin.lower()
    assert '/checkout' not in plugin.lower()

    export_manifest = json.loads((out / "repository-export-manifest.json").read_text(encoding="utf-8"))
    paths = {row["path"] for row in export_manifest["files"]}
    assert "backups/live-page-392.html" in paths
    assert "wp-content/plugins/uskd-madagaskar-events/uskd-madagaskar-events.php" in paths

    subprocess.run(["python", str(out / "tests/validate-uskd-madagaskar-events.py")], check=True)

print("USKD standalone repository export contract OK")

#!/usr/bin/env python3
import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
MANIFEST = ROOT / "integrations/uskd/deployment-manifest.json"
PLUGIN = ROOT / "integrations/uskd/uskd-madagaskar-events/uskd-madagaskar-events.php"

manifest = json.loads(MANIFEST.read_text(encoding="utf-8"))
plugin_text = PLUGIN.read_text(encoding="utf-8")

header = re.search(r"^\s*\* Version:\s*([0-9.]+)\s*$", plugin_text, re.MULTILINE)
constant = re.search(r"USKD_MDG_EVENTS_VERSION',\s*'([^']+)'", plugin_text)

assert header, "plugin Version header not found"
assert constant, "USKD_MDG_EVENTS_VERSION constant not found"
assert manifest["plugin_version"] == header.group(1) == constant.group(1), (
    f"version mismatch: manifest={manifest['plugin_version']} "
    f"header={header.group(1)} constant={constant.group(1)}"
)

assert manifest["shortcode"] == "uskd_madagaskar_events"
excluded = set(manifest["display_contract"]["exclude"])
assert "price" in excluded
assert "checkout_link" in excluded

backup = ROOT / manifest["live_page"]["backup_path"]
assert backup.is_file(), f"missing rollback backup: {backup}"

examples = manifest.get("shortcode_examples", {})
if manifest["plugin_version"] >= "0.2.0":
    assert 'limit="2"' in examples.get("homepage", "")
    assert "shortcode_atts" in plugin_text
    assert "array_slice" in plugin_text

print(
    "USKD deployment contract OK:",
    manifest["plugin_version"],
    manifest["shortcode"],
)

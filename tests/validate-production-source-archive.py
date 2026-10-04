#!/usr/bin/env python3
"""Validate archived source identities and syntax only; never execute WordPress or gateways."""
import argparse
import hashlib
import json
import pathlib
import re
import shutil
import subprocess
import tempfile

def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--lint", action="store_true")
    args = parser.parse_args()
    root = pathlib.Path(__file__).resolve().parents[1]
    manifest = json.loads((root / "docs/STAGE4_SOURCE_INVENTORY_20261004.json").read_text())
    snippets = manifest["active_snippets"]
    assert len(snippets) == 43 and len({s["id"] for s in snippets}) == 43
    assert all(s["id"] not in (117, 119) for s in snippets)
    assert len(manifest["inactive_snippets"]) == 77
    assert manifest["production"]["production_deployment"] is False
    assert manifest["production"]["diagnostic119_restored_passive"] is True
    assert not any(f["path"].endswith("schools.csv") for f in manifest["archived_files"])
    checked = linted = 0
    if args.lint and not shutil.which("php"):
        raise RuntimeError("PHP is required for --lint")
    with tempfile.TemporaryDirectory(prefix="mdg-source-lint-") as tmp:
        for index, entry in enumerate(manifest["archived_files"]):
            path = root / entry["path"]
            raw = path.read_bytes()
            assert hashlib.sha256(raw).hexdigest() == entry["sha256"], entry["path"]
            text = raw.decode("utf-8")
            assert not re.search(r"-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----", text), entry["path"]
            checked += 1
            if args.lint and (entry["kind"] == "snippet_body" or path.suffix == ".php"):
                payload = raw
                if entry["kind"] == "snippet_body" and not text.lstrip().startswith("<?php"):
                    payload = b"<?php\n" + raw
                temp = pathlib.Path(tmp) / (str(index) + ".php")
                temp.write_bytes(payload)
                result = subprocess.run(["php", "-l", str(temp)], capture_output=True, text=True)
                if result.returncode:
                    raise RuntimeError(entry["path"] + ": " + result.stdout + result.stderr)
                linted += 1
    family = (root / "wp-content/plugins/madagaskar-aile-paketi-22/madagaskar-aile-paketi-22.php").read_text()
    assert "family_2_2" in family and "mmc_family_price_for_event" in family
    assert re.search(r"Version:\s*1\.1\.3", family)
    ai = (root / "wp-content/plugins/madagaskar-ai-abilities/madagaskar-ai-abilities.php").read_text()
    assert re.search(r"Version:\s*0\.7\.0", ai)
    school = (root / "wp-content/plugins/madagaskar-okul-tanitim/madagaskar-okul-tanitim.php").read_text()
    assert re.search(r"Version:\s*1\.7\.9", school)
    print(json.dumps({"hashes_verified": checked, "php_files_linted": linted,
                      "active_snippets": len(snippets), "diagnostics_in_production": 0,
                      "main_ahead_versions_preserved": True, "production_deployment": False}))
if __name__ == "__main__":
    main()

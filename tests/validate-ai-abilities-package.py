#!/usr/bin/env python3
import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "wp-content/plugins/madagaskar-ai-abilities"
BOOTSTRAP = PLUGIN / "madagaskar-ai-abilities.php"
MANIFEST = PLUGIN / "migration-manifest.json"

manifest = json.loads(MANIFEST.read_text(encoding="utf-8"))
bootstrap = BOOTSTRAP.read_text(encoding="utf-8")

source_version = manifest["plugin"]["source_version"]

header = re.search(r"^\s*\*\s*Version:\s*([^\s]+)", bootstrap, re.MULTILINE)
constant = re.search(r"define\(\s*'MDG_AI_ABILITIES_VERSION'\s*,\s*'([^']+)'\s*\)", bootstrap)

assert header, "Plugin Version header not found"
assert constant, "MDG_AI_ABILITIES_VERSION constant not found"
assert header.group(1) == source_version, (
    f"Plugin header version {header.group(1)} != manifest {source_version}"
)
assert constant.group(1) == source_version, (
    f"Plugin constant version {constant.group(1)} != manifest {source_version}"
)

pairs = re.findall(
    r"'([^']+)'\s*=>\s*'modules/([^']+\.php)'",
    bootstrap,
)
module_map = dict(pairs)
assert module_map, "No module map entries found"

for slug, filename in module_map.items():
    path = PLUGIN / "modules" / filename
    assert path.is_file(), f"Missing module file for {slug}: {path}"

expected_enabled = manifest["production_known_enabled_modules"]
for slug in expected_enabled:
    assert slug in module_map, f"Known production module missing from plugin map: {slug}"

pending = manifest["migration_order"]
seen_modules = set()
seen_ids = set()
for item in pending:
    slug = item["module"]
    sid = item["snippet_id"]
    assert slug in module_map, f"Pending migration module not in plugin map: {slug}"
    assert slug not in seen_modules, f"Duplicate migration module: {slug}"
    assert sid not in seen_ids, f"Duplicate migration snippet id: {sid}"
    seen_modules.add(slug)
    seen_ids.add(sid)
    assert item.get("smoke"), f"No smoke tests defined for {slug}"
    assert item.get("risk") in {"low", "medium", "high"}, f"Invalid risk for {slug}"

print(
    f"AI abilities package OK: v{source_version}, "
    f"{len(module_map)} modules, {len(pending)} pending migrations"
)

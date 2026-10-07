"""Static guard for Kommo unified family-package fallback."""
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
text = (ROOT / "docs/code-snippets/mdg-kommo-unified-source-v2.php").read_text(encoding="utf-8")

assert "mdg_kommo_unified_v2_family_fallback" in text
assert "mmc_mdg_event_bridge" in text
assert "mdg_family_package_22_v1" in text
assert "'family_2_2'" in text
assert "'capacity_units' => 4" in text
assert "mdg_kommo_unified_v2_family_fallback( $program_id, $tickets )" in text
print("PASS: Kommo unified source includes family-package fallback contract.")

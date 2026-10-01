#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
MODULE = ROOT / "wp-content/plugins/madagaskar-ai-abilities/modules/legacy-mdg-mmc-preview.php"

text = MODULE.read_text(encoding="utf-8")

assert "madagaskar/legacy-mdg-mmc-migration-preview" in text
assert "'readonly' => true" in text
assert "'destructive' => false" in text
assert "'idempotent' => true" in text
assert "'no_write' => true" in text

for forbidden in [
    "MMC_Program_Service::create_program(",
    "MMC_Venue_Service::quick_confirm_master_venue(",
    "MMC_Event_Service::ensure_event_for_program(",
    "MMC_Event_Service::add_session(",
    "MMC_MDG_Bridge_Service::link(",
    "$wpdb->insert(",
    "$wpdb->update(",
    "$wpdb->delete(",
]:
    assert forbidden not in text, f"Forbidden write call found: {forbidden}"

print("Legacy MDG -> MMC preview is read-only and write-call free.")

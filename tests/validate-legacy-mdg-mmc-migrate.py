#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
MODULE = ROOT / "wp-content/plugins/madagaskar-ai-abilities/modules/legacy-mdg-mmc-migrate.php"
text = MODULE.read_text(encoding="utf-8")

required = [
    "START TRANSACTION",
    "COMMIT",
    "ROLLBACK",
    "MIGRATE_LEGACY_MDG_TO_MMC",
    "MMC_Program_Service::create_program",
    "MMC_Venue_Service::quick_confirm_master_venue",
    "MMC_Event_Service::add_session",
    "MMC_Sales_Service::save_mapping",
    "MMC_MDG_Bridge_Service::link",
    "MMC_Sales_Service::mapping_coverage",
    "MMC_MDG_Bridge_Service::status",
    "safe_for_later_write",
    "create_program_then_bridge",
]
for item in required:
    assert item in text, f"Missing migration safety/control marker: {item}"

for forbidden in [
    "wp_delete_post(",
    "wc_create_order(",
    "wp_insert_post(",
    "MDG_Events::create",
    "MDG_Sessions::create",
]:
    assert forbidden not in text, f"Forbidden replacement/destructive sales operation found: {forbidden}"

assert text.count("START TRANSACTION") == 1
assert text.count("COMMIT") == 1
assert text.count("ROLLBACK") == 1

print("Controlled legacy MDG -> MMC migration module safety checks OK.")

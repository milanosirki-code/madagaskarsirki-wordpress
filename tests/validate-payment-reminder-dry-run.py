#!/usr/bin/env python3
from pathlib import Path
import sys

p = Path("docs/live-captures/2026-10-07/snippet-103-payment-reminder-dry-run.php")
s = p.read_text(encoding="utf-8")

required = {
    "dry_run_function": "ms_oh_dry_run_report",
    "cancelled_program_gate": "EVENT_CANCELLED",
    "sales_closed_gate": "SALES_CLOSED",
    "session_started_gate": "SESSION_STARTED",
    "product_unavailable_gate": "PRODUCT_UNAVAILABLE",
    "replacement_paid_gate": "REPLACEMENT_PAID",
    "already_reminded_gate": "ALREADY_REMINDED",
    "origin_gate": "NOT_CHECKOUT",
    "wait_gate": "WAITING",
    "dry_run_mode": "'mode'=>'DRY_RUN'",
    "real_send_zero": "'real_send_count'=>0",
    "v4_sales_lock": "_mdg_v371_sales_closed",
    "canonical_phone": "ms_oh_telefon",
}

for name, needle in required.items():
    if needle not in s:
        raise SystemExit(f"missing required dry-run contract: {name} -> {needle}")

for forbidden in (
    "wp_remote_post(",
    "wp_remote_request(",
    "curl_exec(",
    "send_sms",
    "send_whatsapp",
    "kommo.com/api/v4",
):
    if forbidden in s:
        raise SystemExit(f"forbidden real-send primitive found in live dry-run capture: {forbidden}")

if "[DENEME MODU]" not in s:
    raise SystemExit("dry-run log marker missing")

print("OK: live payment-reminder capture preserves dry-run safety contract and contains no known send primitive.")

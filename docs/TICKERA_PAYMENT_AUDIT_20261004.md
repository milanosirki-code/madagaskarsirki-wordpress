## Stage 3 evidence — 2026-10-04
104 published Tickera instances associated with 43 orders lacking recorded payment date. The fixed original cohort spans 18 days; all orders are failed/PayTR with empty recorded paid date and transaction ID. These fields are not an independent PayTR financial reconciliation.

| Exclusive classification | Count |
|---|---:|
| RECORD_ONLY | 0 |
| QR_GENERATED | 104 |
| CHECKIN_CAPABLE | 0 |
| CHECKED_IN | 0 |
| UNKNOWN | 0 |

QR_GENERATED means a real ticket_code exists and the native resolver resolves it to the same instance; no QR bitmap rendering/delivery was tested. All 104 resolve correctly but the exact currently registered native paid filter returns false. Core check-in consequently returns error 11 before its attendance writer. No customer check-in endpoint was called. None has tc_checkins history or a recorded Pass. Zero means no recorded native check-in, not independent proof about physical admission.

## Proven implementation and chronology
Native WooCommerce/Tickera Bridge 1.7.7, not MMC, is the pre-payment producer:
- woocommerce_new_order_item → TC_WooCommerce_Bridge::create_order_ticket_instances, priority 11, bridge-for-woocommerce.php:1902–2048.
- woocommerce_store_api_checkout_update_order_from_request → create_order_ticket_instances_from_store_api, priority 10, :993–1070.
- Creation inserts publish plus ticket_code without a paid guard.
71 instance creation timestamps equal order creation; 31 are +1 second; 2 are +26 seconds. These timestamps and the isolated reproduction corroborate pre-payment creation; historical deployment/version attribution remains unavailable.

Native Tickera 3.6.0.6 Tickera\TC_Checkin_API::ticket_checkin (:643–852) calls tickera_order_is_paid (:694–705). Legacy alias tc_order_is_paid runs Bridge::tc_modify_order_is_paid priority 10 (:4527–4538), accepting processing/completed and rejecting failed/pending/cancelled. It does not independently inspect date_paid/transaction or authenticate PayTR callback. No registered pre-check-in override exists in any current clean/tc_/tickera_ alias. History writer is only after this guard (:789). Checkinera AJAX checkinera_check_in proxies /tc-api/{api_key}/check_in/{checksum}/; this route was not invoked for customers.

## Separate state
WooCommerce order exists: true. Recorded payment: absent. Tickera instance/code: exists. Current native check-in capability: false. MMC ledger: 56 rows, net quantity/capacity/amount zero for the cohort.

## Reproduction and tests
Exact captured native creator, filter aliases and verifier methods with synthetic PHP/Woo storage; no production order, live check-in or network gateway call.
PHP 8.4: 25 assertions pass, no PHP warning/fatal. Pending/failed/cancelled produce early code but verifier rejects without history write. Synthetic paid processing accepts; repeated MDG payment callbacks and processing/completed transitions leave one existing instance. Family components and canonical family_2_2 mapping retain capacity_units=4. Existing synthetic paid ticket remains accepted.
Commit 344b9452acefce24a3759e64e3a3fadbe9821675.
Actions: https://github.com/milanosirki-code/madagaskarsirki-wordpress/actions/runs/37172718605 (contract), https://github.com/milanosirki-code/madagaskarsirki-wordpress/actions/runs/37172718564 (PHP syntax).
Limit: contract reproduction is not a deployed full WordPress staging checkout. Repeated checkout creation itself is not claimed idempotent. No real failed PayTR callback or successful sandbox payment was performed. Production PayTR test flag is no; successful-payment reproduction unavailable without real charge.

## Risk and patch decision
MEDIUM: early code generation/data integrity, with current native payment gate rejecting all 104. No evidence supports HIGH/CRITICAL or 104 usable/free tickets.
A verifier security patch is not justified by this cohort; no separate fix branch/PR or production patch was created. Payment-first instance deferral remains a data-integrity follow-up requiring real WordPress staging, attendee/cart metadata preservation and retry coverage before any adapter. The current status-only gate is not an end-to-end guarantee of provider-confirmed payment in every possible status transition.
Issue remains open for that follow-up and PayTR callback verification. Audit/tests tracked by draft PR #111.
No legacy order/ticket mutation, invalidation, cleanup or production deployment. Diagnostic #119 passive, code_error null; #117 remains passive.

## Source provenance / rollback
{
  "bridge_sha256": "4255248a877e6a9e5e99d3280145ddc2df0a42e3767e55aa28f73d0d51d5ed68",
  "core_api_sha256": "8dd19c77ae53c49defa025c0ccf50b11c8d1ea1bf26b1ddf264d5df272bb95de",
  "functions_sha256": "e0fbc32a6a94204d5552318f333568747c27c1198b576a150da41a956235b510",
  "captured_utc": "2026-10-04T01:30:57+00:00",
  "limitation": "Minimal exact live method excerpts; synthetic WP/Woo persistence and gateway. This is not a real PayTR integration or deployed staging site."
}

Changed repository files: docs/code-snippets/stage2-runtime-audit.php (read-only native source/gate diagnostic), tests/tickera-payment/native-excerpts.json, tests/tickera-payment/reproduction.php, .github/workflows/tickera-payment-contract.yml, this report and CODEX_PROJECT_STATE.md. No executable production plugin file changed. Rollback: retain #117/#119 passive; revert audit/test commits if required. No customer data rollback is needed.

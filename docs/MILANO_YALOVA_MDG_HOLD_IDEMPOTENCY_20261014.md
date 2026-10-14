# Milano Yalova — canonical MDG capacity hold double-finalization fix

**2026-10-14 — DRAFT only. Not deployed to staging or production.**

## Critical root cause
In current production-source `class-mdg-capacity.php`, `commit_order()` first reads held rows without locking, then unconditionally increments `sold_units` for every row in that stale result and writes status `sold` by ID. Two PayTR callbacks/other order-processing hooks executing concurrently can both read the same held row and both increment sales before the status write. `release_hold()` likewise performs a stale pre-transaction status read and blind subtraction, allowing an expiry/cancellation to race with payment settlement.

## Patch
This draft changes *only* MDG_Capacity::commit_order and release_hold. In each per-hold SQL transaction:

1. Make `UPDATE wp_mdg_holds ... WHERE id=? AND status='held' AND order_id/session_id/units match` an atomic claim, changing status to `sold` (for payment) or validated terminal release state. The affected row count **must be exactly 1**.
2. Only the winner adjusts `wp_mdg_sessions` and requires `held_units >= claimed_units`. Payment uses `held_units -= units; sold_units += units`; release only reduces held. No `GREATEST` masking double-subtractions.
3. Commit the hold claim and capacity update together. On any failure roll back both; return false instead of incorrectly reporting payment settlement success.
4. Repeat/stale callbacks can select the old row, but the UPDATE will affect zero; they must never adjust units.
5. Existing `released/expired` and other valid terminal release statuses remain accepted, but `sold`/`held` statuses cannot be fabricated via release_hold.

The fix **does not** itself authenticate PayTR notifications, create a Woo order, or guarantee only one ticket/PDF. The Woo payment gateway must still be responsible for authenticated receipt and `WC_Order::payment_complete()` under a durable idempotent callback, consistent with one central ledger and no cross-brand duplication.

## QA / release gate
Run `php tests/mdg-capacity-idempotency/regression.php` in independent CI. It checks successful 4-unit 2+2 sale, repeat callback, stale read, release-after-sale refusal, expiry vs late callback, rollback on session mutation failures, recovery and preservation of independent sessions. Before touching any customer tickets: production database table engines must be InnoDB; the source patch must be tested on CENTRAL STAGING, with no payments and no emails. Verify no other plugins write directly to the holds table and identify all release statuses in use. Inspect all Woo/MDG/Tickera event hooks. This is a shared MDG class and changes must not be merged/deployed on the central live site without broader Madagaskar regression and rollback.

**Do NOT claim Milano paid checkout ready or open Yalova sales from this PR.**

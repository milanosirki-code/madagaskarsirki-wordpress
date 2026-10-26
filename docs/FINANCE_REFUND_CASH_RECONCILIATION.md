# Manual refund cash reconciliation

The existing editor intentionally refuses automatic sources and ticket refund rows. Keep that protection. A reviewed manual refund cash disclosure can now be corrected through two administrator-only Finance abilities without creating an order refund, bank payment, income or expense row.

## Private plan and preview

Keep approved plans in the non-autoload `mdg_v5_refund_cash_review_plans` option, keyed by a unique opaque `plan_id`. Each plan contains `record_id`, canonical `event_id` and `event_uuid`, `expected_hash`, integer `amount_cents`, target `notes`, `reason`, private `evidence_ref`, `confirmed_by`, and `confirmed_at`. The expected hash is `mdg_ai_v5_refund_cash_hash()` of the complete current row. Production plans, financial figures, operator details and evidence do not belong in git.

Require the recorded operator's explicit confirmation of the accounting total before preparing a plan. Native Woo refund objects alone do not prove that the gateway returned money. Never use this action to initiate or repeat an actual refund.

Run `madagaskar/finance-refund-cash-preview` with the plan ID. It always forces preview, even if an extra operation reaches the PHP callback. The write ability `madagaskar/finance-refund-cash-reconcile` accepts only the plan ID and `apply` or `rollback`; API callers cannot choose an amount, source row or note. Both `manage_options` and `manage_woocommerce` are required, in addition to the existing Finance table checks.

## Mutation and safeguards

Only a program-scoped manual ticket refund cash record can match a plan. Current event UUID and full before-image must agree. Source document prefixes owned by automatic importers remain blocked. Any payment or settlement tracking, a failed tracking read, a non-InnoDB table or a busy source lock refuses the action.

The mutation uses the same advisory lock as the Finance Records editor, a row lock and a database transaction. Only amount, notes and update time change. The complete before/after image and evidence reference are inserted into native finance history in the same transaction. An audit or commit failure rolls back. A retry verifies the recorded after-image and returns `already_applied`; it does not write a second audit or amount.

The existing generic edit, delete, payment, order and gateway paths are unchanged. Refund cash remains separate from operating cost through the current shared UI/API calculation.

## Rollback

Back up the current source and reviewed row before deploying. The API rollback restores the original three mutable fields only if the current complete row still equals this plan's audited after-image and no payment/settlement tracking exists. It creates a separate rollback audit. A subsequent retry returns `already_rolled_back`; reapplying that plan is refused. Later user changes are preserved. Do not restore the whole expense table or use the unrelated event-identity migration rollback.

For code rollback, restore only this module's verified previous source. Re-read the restored file and run the site health check. Restoring code does not undo an accounting correction; review data and source rollback separately.

## Validation

`tests/finance/refund-cash.php` uses synthetic values and checks preview, permissions, plan ownership, source classification, UUID, stale rows, locking, engines, tracking failures, write/audit/commit rollback, retry, exact restoration and concurrent edits. It runs in the existing PHP 7.4 finance workflow alongside the web, discount, allocation, record and identity contracts. Before production apply, verify a read-only live preview and the intended native refund total. After apply, verify the sole changed row, audit, unchanged operating cost/profit and unaffected protected sources. Do not claim a real payment or physical QR test.

# Operations Faz 1 — Issue #150

## Completed prerequisite
PR #149 merged at `19825eb1f1fc0029c37065532c4104a6c11f1613`; Issue #148 CLOSED/completed. Live raffle bootstrap/reporting hashes match merged main. Raffle domain tables unchanged; Drive canonical project record updated and read back.

## Source and scope
Existing MMC_Operations_Service and MMC_Operations_Admin extended; existing tasks/resources/checklist/schedule/finance remain authoritative. No new table, schema version, provider integration, messages, payment writes, production backfill or personnel assignment.

## Schema/current state
See OPERATIONS_V1_SCHEMA.json and OPERATIONS_V1_BEFORE.json. Thirteen programs/plans; 559 checklist rows; 33 schedules; 141 open tasks. Nine future programs; four historical, including one cancelled. No program currently operations/show_day.
Task schema supports module, priority, assigned_user_id, due_at, completed_at, JSON metadata and timestamps. Status open/completed/cancelled. Existing tasks UI and CRUD/read abilities retained. No source_key column or unique task identity index: metadata source_key plus per-program MySQL advisory lock protects initialization. Completion audit uses task_status_changed in mmc_logs.
Actual program lifecycle: preparation, region_analysis, venue_research, allocation_request, allocation_pending, venue_confirmed, venue_payment, event_setup, sales_prep, sales_open, promotion, operations, show_day, financial_close, deposit_refund, completed, cancelled. Status setter validates names but has no strict transition matrix. Plan draft is not a program status.

## Existing automations preserved
Explicit ensure; venue/event/session/readiness log hooks; checklist initialization; event schedule sync; ready transition; post-show completed -> financial_close -> finance tasks; operation-mode accommodation rules; Issue #121 read-only contracts.

## Changes
- 13 high-level templates (5 preparation, 4 show day, 4 post-show). Earlier phases retain one planning task; full templates only operations/show_day. Finance tasks retained.
- Stable operations_v1.* identities; completed/cancelled/manual matching tasks retained; no reset of dates, owners or completion.
- Serialized initialization/schedule sync; lock failure fails closed. Hook flag released in finally. Cancelled/completed programs do not initialize/generate tasks; terminal log hooks skip.
- One aggregate readiness query: canonical event, first/next session, venue, plan/mode, checklist percentage/problems, evidence-based resource/lodging gaps, open/high/overdue tasks, next action. No per-program query loop.
- Upcoming default/archive filter, show-day marker/next session/doors; existing detailed sales/occupancy read unchanged.
- Explicit capability/nonce/POST-only preparation button; GET read-only.
- System schedule changes preserve manual notes, owners, status and rows. Deleted sessions/cleared system milestones removed; manual rows retained. Unchanged sync avoids timestamp churn.
- Overnight/multi-city lodging required; daytrip N/A; completion preserved.
- Before render exposed existing null textarea PHP deprecations; safe string rendering fixes only Operations admin.

## Tests
87 assertions in isolated database fixture using actual service/admin, including Issue #121's 29. Missing/existing reads/render, initialize twice, stable keys, no owner/deadline assignment, cancelled-task preservation, modes, session add/change/delete, manual state, unchanged sync, lock failure/release, one-query readiness, no-write preview, ready/post-show/finance.
PHP syntax and repo CI required before deploy. No real order/payment/message test.

## Backfill preview / decisions
Preview only; no production task writes. T-7/T-3/T-1 are PROPOSED_DEFAULT requiring later manager decision; no dates/staff auto-assigned. Cancelled #2 has pre-existing open tasks: preserve, review separately; never bulk cancel manual work.

## Deployment / rollback
Pending CI and guarded live verification. Only service/admin: old-hash gate, exact target hashes, backups, atomic replacement, read-back, opcache invalidation, restore on failure. MMC_DB_VERSION unchanged (live/current 1.3.7), so upgrade backfill not triggered. Restore both before files if smoke/health fails. Temporary #117 restored original/passive in finally.

## Status
Implementation/synthetic tests complete; production and final GitHub/Drive evidence pending. Issue #105 OPEN/SEND pending; #82 OPEN/PARKED.

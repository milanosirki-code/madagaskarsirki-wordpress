# Operations Faz 1 — Issue #150

## Completed prerequisite
PR #149 merged at `19825eb1f1fc0029c37065532c4104a6c11f1613`; Issue #148 CLOSED/completed. Live raffle bootstrap/reporting hashes match merged main. Raffle domain tables unchanged; Drive canonical project record updated and read back.

## Source and scope
Existing MMC_Operations_Service and MMC_Operations_Admin extended; existing tasks/resources/checklist/schedule/finance remain authoritative. No new table, schema version, provider integration, messages, payment writes, production backfill or personnel assignment.

## Schema/current state
See OPERATIONS_V1_SCHEMA.json and OPERATIONS_V1_BEFORE.json. Thirteen programs/plans; 559 checklist rows; 33 schedules; 141 open tasks. Nine future programs; four historical, including one cancelled. No program currently operations/show_day.
Task schema supports module, priority, assigned_user_id, due_at, completed_at, JSON metadata and timestamps. Status open/completed/cancelled. Existing dashboard task counters and CRUD/read abilities retained; no standalone task CRUD admin screen was found. Operations now lists existing operation/finance tasks read-only; task writes remain in existing task abilities. No source_key column or unique task identity index: metadata source_key plus per-program MySQL advisory lock protects initialization. Completion audit uses task_status_changed in mmc_logs.
Actual program lifecycle: preparation, region_analysis, venue_research, allocation_request, allocation_pending, venue_confirmed, venue_payment, event_setup, sales_prep, sales_open, promotion, operations, show_day, financial_close, deposit_refund, completed, cancelled. Status setter validates names but has no strict transition matrix. Plan draft is not a program status.

## Existing automations preserved
Explicit ensure; venue/event/session/readiness log hooks; checklist initialization; event schedule sync; ready transition; post-show completed -> financial_close -> finance tasks; operation-mode accommodation rules; Issue #121 read-only contracts.

## Changes
- 13 high-level templates (5 preparation, 4 show day, 4 post-show). Earlier phases retain one planning task; full templates only operations/show_day. Finance tasks retained.
- Stable operations_v1.* identities; completed/cancelled/manual matching tasks retained; no reset of dates, owners or completion.
- Serialized initialization/schedule sync; lock failure fails closed. Hook flag released in finally. Cancelled/completed programs do not initialize/generate tasks; terminal log hooks skip.
- One aggregate readiness query: canonical event, first/next session, venue, plan/mode, checklist percentage/problems, evidence-based resource/lodging gaps, open/high/overdue tasks, next action. No per-program query loop.
- Existing operation/finance task read table with status, priority, owner ID, due/completion time; no extra task CRUD system.
- Upcoming default/archive filter, show-day marker/next session/doors; existing detailed sales/occupancy read unchanged.
- Explicit capability/nonce/POST-only preparation button; GET read-only.
- System schedule changes preserve manual notes, owners, status and rows. Deleted sessions/cleared system milestones removed; manual rows retained. Unchanged sync avoids timestamp churn.
- Overnight/multi-city lodging required; daytrip N/A; completion preserved.
- Before render exposed existing null textarea PHP deprecations; safe string rendering fixes only Operations admin.

## Tests
88 assertions in isolated database fixture using actual service/admin, including Issue #121's 29. Missing/existing reads/render, initialize twice, stable keys, no owner/deadline assignment, cancelled-task preservation, modes, session add/change/delete, manual state, unchanged sync, lock failure/release, one-query readiness, no-write preview, ready/post-show/finance.
PHP syntax and repo CI required before deploy. No real order/payment/message test.

## Backfill preview / decisions
Preview only; no production task writes. T-7/T-3/T-1 are PROPOSED_DEFAULT requiring later manager decision; no dates/staff auto-assigned. Cancelled #2 has pre-existing open tasks: preserve, review separately; never bulk cancel manual work.

## Deployment / rollback
Controlled deploy verified below. Only service/admin: old-hash gate, exact target hashes, backups, atomic replacement, read-back, opcache invalidation, restore on failure. MMC_DB_VERSION unchanged (live/current 1.3.7), so upgrade backfill not triggered. Restore both before files if smoke/health fails. Temporary #117 restored original/passive in finally.

## Status
Implementation, CI, production verification complete; final archive publication recorded below. Issue #105 OPEN/SEND pending; #82 OPEN/PARKED.

## Final production verification — 2026-10-05
- PR #151: https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/151 . Production source commit `4345bf351c2f0a7a014fb09c344c52ef08f6e494`; all 8 applicable CI workflows PASS. Ready-for-review transition follows final evidence commit; merge is a later review step.
- Exactly two MMC include files deployed; service SHA256 `bf266cacdcc5a5aa29aad87f64dd9c08dba57a202e93f9cde72cea98b7d0b07d`; admin `5f0d544afac7047d686c83f7205d888cbbfe18b6c48c0cee86316fb5eeedafd7`. Both read back MATCH candidate/GitHub source. Plugin/database versions untouched; migration/backfill not triggered.
- 18 domain table fingerprints BEFORE=AFTER, including programs, plans, tasks, checklist, schedule, resources, logs, events, sessions, venue/finance data, Kommo profiles and all raffle tables. No production domain data changed.
- Three native Operations abilities successful for future #10, historical #9, cancelled #2. Their response hashes match before. Missing-plan fixture masks only SELECT results (no real record alteration); all three reads and explicit-create-only render pass. SQL write guard recorded zero domain-write attempts.
- Readiness: 9 upcoming programs, one query, 5.46ms. Full authenticated server-side render: 17–18 queries, 10.82–13.45ms across current/history/cancelled scope; no PHP warning/fatal. This is render verification, not a browser visual review.
- Preview: all 13 plans/checklists/legacy planning tasks already present; current-stage would-create counts all zero. Thirteen-task expansion occurs on a future explicit/logged operations/show_day transition, never a GET or this rollout. Canonical schedule updates remain explicit; preview does not claim to mutate/synchronize them.
- Final health 16 OK / 0 warning / 0 critical. Global snippets 125 total / 45 active / code_error 0. Temporary #117 byte-exact original, passive, separate GET read-back verified.
- Rollback not needed. Host before-file backups retained; local original source files included in the Drive evidence archive. To roll back: verify current target hashes, restore admin then service using atomic replace, invalidate opcache, check exact before hashes (`b40a95f8...` service / `2b5a8b09...` admin), rerun guarded smoke/health. No database rollback.
- Drive lifecycle report: https://drive.google.com/file/d/1-DLRn11SFIaOqhGMlohKZuWzK4mQcDMc/view . Final ZIP/source/tests/schema/evidence and canonical project state are updated/read back before task completion.

## ensure_plan call inventory
VALID_WRITE: explicit admin POST prepare/save plan/resource assignment/manual schedule/sync; AI plan-ensure/plan-update; lifecycle venue/event/session/readiness hooks and operations/show_day transition. Existing bootstrap backfill is behind schema upgrade; schema remains 1.3.7 and it was not invoked. Readiness/preview/summary/three read abilities/admin GET: no ensure call (INVALID_READ 0).

## Remaining decisions
Operations phase 2: manager-approved deadlines (T-7/T-3/T-1 remain PROPOSED_DEFAULT), responsible users, any notification policy, and review of old cancelled-program tasks. No WhatsApp/Kommo/SMS, payments, school-field changes, automatic assignment or retroactive operational records. Issue #150 remains open pending PR #151 review/merge; #105 SEND decision pending and #82 PARKED remain untouched.

Final source/test/schema/evidence/rollback Drive archive: https://drive.google.com/file/d/1gWzrQovHHM9JyQF6eT0x-3T2gIUlY9HI/view . No secrets, customer phone/email or raw domain rows are included.

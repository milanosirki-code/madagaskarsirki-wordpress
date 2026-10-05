# Operations Phase3 — exact legacy adoption / controlled backfill

Phase3A issue155 / branch `codex/operations-task-adoption-v3` / PR156. Phase2 PR153 merged as `41bf2fa59d56c400e20435f5df0d56ad45ff9e27`; live service/admin exact main MATCH, Issue152 completed. Phase3B requires manager-selected programs; no adoption or backfill during Phase3A deployment/smoke.

## Policy / template map
One existing service/admin/task table; no migration, cron sync, new provider or duplicate edit/apply endpoint. Canonical 13 templates remain `MMC_Operations_Service::task_templates()` with `operations_v1.<key>` keys: plan, transport, crew, equipment, venue_entry, handover_in, technical, box_office, briefing, inventory, handover_out, reconcile, return. Byte-exact title equality, no trim/fuzzy/case folding; duplicate count includes all Operations task statuses in the same program/title, so historical duplicate slots require review.

Classification order: WRONG_MODULE; CLOSED; missing/closed/finance program AMBIGUOUS; non-template CUSTOM; invalid JSON AMBIGUOUS; already managed tracked separately; DUPLICATE; custom notes/metadata CUSTOM; otherwise ADOPTABLE. JSON object provenance may contain only exact matching source_key/phase/template_version1/system_generated=false; any other key/value fails closed. Existing safe metadata merged; custom metadata untouched. Structural ADOPTABLE is distinct from apply eligibility: explicit writes require active/future operations/show_day and non-cancelled event.

## Two separate explicit steps
A. Capability+nonce+POST+program scope+selected task IDs+stale snapshot guard. Existing program lock, InnoDB engine guard, transaction, program/task row locks, byte CAS of existing task columns and materialized duplicate-count SQL guard. Only `metadata` changes; title/due/owner/status/priority/created_at/updated_at/notes/completed_at unchanged. No task/checklist/plan/log insert. Metadata: source_key, system_generated=true, phase, template_version1, adopted_from_legacy=true, adopted_at, adoption_policy=operations_v3_exact_title. Existing metadata merged. Any stale/custom/duplicate selection aborts entire transaction. Repeat adoption of managed selection returns0 changes.
B. Phase2 preview/explicit program-scoped POST apply remains separate. Hypothetical post-adoption preview uses cloned rows only. Canonical deadlines/owner policy unchanged; no invented offset/end time, blank/verified-managed fields only, manual overrides preserved. No global all-program apply. No finance/cancelled/completed/past writes.

## Read-only views
Existing Operations page adds program-scoped managed/legacy/due-assignable/owner-assignable/duplicate cards, exact-match classification and post-adoption simulation. Custom tasks remain Manual Task. GET dashboard/preview/native three abilities SELECT-only; existing capability model retained. Cancelled program open tasks inventoried only, no automatic cancel. External notification policy candidates remain read-only (high-priority overdue, due today, show-day unresolved problem, unassigned high-priority); provider/message/log count0.

## Verification and deployment
209 isolated assertions pass including121/Phase1/Phase2 regressions. Additional real MySQL transaction/CAS/metadata-only/idempotency/adopt-then-separate-backfill/My Tasks tests run only against ephemeral operations_fixture. CI must pass before source-hash-gated atomic service/admin deploy. Guarded GET smoke includes all program previews, domain SQL write guard, provider-call guard, PHP warnings, before/after22 domain fingerprints, source read-back, health and global Code Snippets errors. Temporary snippet117 restored exact original/passive, separate GET verification; no _fields on writes.

Production task inventory baseline141/open141/system_generated0/due0/owner0; live classification and final evidence are recorded after guarded deployment, not inferred. Production adoption0/backfill0. No real program/task tests that mutate production.

## Rollback
Keep exact Phase2 service/admin sources (main41bf2fa) and current before hashes: service47ab9f4e80792c0eb2d0228c778a7e93494622aaaa0744541141535d4099c853; adminc12273c74baf68dd5b84e4eb3fdc6f7984a4449713a1f5c07bed90d3d34f87fe. Atomic restore both files if post-deploy checks fail; invalidate opcache and recheck fingerprints/health. No domain rollback required in3A. Rollback originals included in Drive ZIP; no secrets/customer data archived.

GitHub source/tests/policy/preview/before-after/deployment/project state and existing Drive project folder archive must be read-back/hash verified before completion. Phase3A final verification below; Phase3B and external provider policy not enabled.

## Final live verification — 2026-10-05
- PR156/source f2b16e1ba9e7d4228a5bda952c36b72d2589f928; CI8/8 PASS;209 isolated and36 real MySQL fixture assertions including prior13. MySQL races/transactions run only in ephemeral CI.
- Live service4c4f608c7f889a8ddb5a5fc1723931da435f3f7ddef09daed607426e67476e1c / admin e7563f392caeb2a150a792c2ee4b2adc764727d0296a889ea72c99cd57e69619: byte/read-back MATCH source. Only2 files deployed; DB1.3.7 unchanged.
-141 total/open/unmanaged legacy tasks:13 Operations,128 other modules (including4 finance).12 structurally ADOPTABLE Operations planning tasks;1 AMBIGUOUS closed/cancelled program; DUPLICATE0/CUSTOM0/CLOSED0/WRONG_MODULE128. Structural eligibility does not override program/date/stage scope: all13 program contexts currently apply-ineligible. WOULD_ADOPT0; adoption-simulated WOULD_SET_DUE0 / OWNER0. No program lifecycle promoted.
- Cancelled program open tasks18:1 Operations template,17 other-module tasks; read-only inventory with IDs/due/owner/created_at in AFTER evidence. No cancel/delete/merge.
- Actual adoption0 / backfill0;22 domain table fingerprints exact BEFORE=AFTER; SQL write attempts0, provider/messages0, PHP warning/fatal0. Existing three native read abilities and legacy response hashes MATCH.
- My Tasks remains0 in production (assigned0); synthetic actual adopt then separate apply makes My Tasks1 and manual override remains protected.
- Authenticated server-render smoke succeeded (not browser visual review):29–30 queries,21.52–39.94ms across future/history/cancelled; alert aggregate1 query. No N+1 added per task.
- Final health16/16 critical0 warning0; current global snippets126 total/46 active/code_error0; temporary117 restored exact original/passive, separate GET verified. Current API count recorded rather than cached45-active health narrative. Snippets not toggled to reconcile counts.
- External notification policy preview only: HIGH priority overdue, due today, show-day unresolved problem, unassigned high-priority tasks; no provider/message/notification-log action. Current overdue/today0; active/future high-priority unassigned9.
- Rollback not needed. Exact main41bf2fa Phase2 originals and host backups preserved; no domain rollback.
- GitHub Issue155 remainsOPEN pending PR156 review/merge; Phase3A production complete, Phase3B not run.
- Drive report https://drive.google.com/file/d/1S06R1zQZ2kd7VRVqjigvwQ9sYtgVBr4k/view ; source/tests/policy/preview/before-after/deploy/rollback ZIP https://drive.google.com/file/d/18Mn-YUwt8wQJLZEka3wtTM-bdv3cWri6/view ; canonical project state updated. Final raw-byte/hash read-back required.

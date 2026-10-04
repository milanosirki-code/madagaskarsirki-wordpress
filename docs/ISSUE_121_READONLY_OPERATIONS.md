# Issue #121 — Read-only Operations Domain Side Effects

## Root cause

Three read-only abilities shared `MMC_Operations_Service::summary()`. That method called `ensure_plan()`, so a GET/read could conditionally initialize or repair operations domain state.

Old chain:

`ability -> callback -> summary() -> ensure_plan() -> domain writes`

The reachable domain records were:

- `mmc_operation_plans`: plan creation, including draft/status and created/updated timestamps.
- `mmc_operation_checklist`: missing default rows; the accommodation rule can also update the lodging row status and timestamp.
- `mmc_tasks`: missing operations task creation.
- `mmc_logs`: operations-plan creation audit record.

No customer or real operation payload is stored in this document.

## Fix

`summary()` now reads plan existence with `get_plan()` and performs only aggregate SELECTs. It adds `plan_exists` without removing the prior summary keys.

The Operations admin GET/render path also uses `get_plan()` rather than `ensure_plan()`. When a plan does not exist, the page renders an explicit **Plan Oluştur / Hazırla** POST action instead of initializing during GET.

New read chain:

`ability -> callback -> get_plan()/checklist()/summary() -> SELECT-only aggregation`

The ability registration remains read-only for:

- `madagaskar/operations-plan-get`
- `madagaskar/operations-summary`
- `madagaskar/operations-checklist-list`

The explicit `madagaskar/operations-plan-ensure` contract remains write/destructive and idempotent.

## ensure_plan() inventory

VALID_WRITE callers retained:

- event-driven operations seeding in `on_program_logged()`
- explicit/backfill initialization in `backfill_existing_programs()`
- `save_plan()`
- `assign_resource()`
- `sync_schedule_from_event()`
- `add_schedule_item()`
- explicit AI `operations-plan-ensure`
- explicit AI `operations-plan-update`
- explicit admin POST `mmc_ops_ensure_plan`

INVALID_READ removed:

- `summary()`
- Operations admin GET/render

NEEDS_REVIEW in Issue #121 scoped callers: none.

## Regression evidence

CI runs PHP syntax checks plus `tests/mmc-integrity/operations-readonly-121.php`. The actual operations service and actual callbacks are executed against an instrumented synthetic DB. The test passed 29 assertions covering missing-plan read, existing-plan read, explicit initialization and second-run idempotency.

On production, a non-#9 existing-plan fixture was read through all three abilities. Full plan/checklist/task/log fingerprints and the plan `updated_at` were identical before and after.

Because production had no safe naturally missing-plan fixture, no real plan was deleted or fabricated. A request-local query-masking guard made the three domain read tables appear empty while blocking any operations-domain INSERT/UPDATE/DELETE before SQL. Result: `plan=null`, `plan_exists=false`, zero summary, empty checklist, zero write attempts and zero warnings. The same guard rendered the missing-plan Operations UI and confirmed the explicit initialize button.

Explicit initialization was not executed against real production data. The CI fixture proves it still creates plan/checklist/task/log on first use and does not duplicate them on the second call.

## Production deployment

Branch: `codex/fix-readonly-operations-121`

PR: #126 — Remove operations initialization side effects from read-only abilities

Tested production-code head: `74f54bcbe81689981ad0c03a62bf8bc4a87f9eb4`

Live source readback matches the CI-tested service/admin blobs recorded in `ISSUE_121_DEPLOYMENT_EVIDENCE.json`.

Final health: 16/16 OK. Active snippet `code_error`: 0. Targeted Operations renders: no warning/fatal observed. Temporary deployment/test helpers were deactivated and trashed.

## 59 read-only final classification

- SAFE: 51
- SIDE_EFFECT_TECHNICAL: 8
- SIDE_EFFECT_DOMAIN: 0
- NEEDS_REVIEW: 0

This is an incremental regression over the same 59-read-only inventory used by Issue #115; the 56 unaffected callback paths were unchanged, and the three Issue #121 paths were re-evaluated statically and dynamically.

Kommo dynamic sources, sales/tickets, Issue #112, Program #9 data, family-package behavior and unrelated operations refactors were not changed.

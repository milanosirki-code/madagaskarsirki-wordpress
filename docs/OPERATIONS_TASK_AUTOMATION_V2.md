# Operations Phase 2 — Issue #152

## Phase 1 closure
PR #151 reviewed, CI8/8 green, merged at `a7883d8e4643a884f26dd33c15451646041cee7e`. Live service/admin byte-exact MATCH merged main. Issue #150 CLOSED/completed. GitHub closure comment and canonical Drive project record updated/read back before Phase2.

## Baseline / provenance
141 total/open tasks; 0 marked system_generated, 141 unmarked/protected as manual; no deadlines/owners/overdue. This classification does not claim every legacy task was manually created: provenance cannot be inferred from a matching title. Owner coverage 13/13 set and valid WordPress users; no current operations/show_day program. Existing tables/DB version unchanged. See OPERATIONS_V2_BEFORE.json and Phase1 schema reports.

## Canonical deadline policy v2
No T-7/T-3/T-1, midnight inferred from program date, or guessed end time.

| Template | Ordered anchors |
|---|---|
| plan | plan.departure_at → plan.venue_entry_at → first session |
| transport | departure_at |
| crew / equipment | departure_at → venue_entry_at |
| venue_entry / handover_in | venue_entry_at |
| technical | rehearsal_at → setup_start_at → first session |
| box_office | doors_open_at → first session minus stored event.door_open_minutes |
| briefing | doors_open_at → rehearsal_at |
| inventory / reconcile | teardown_end_at → explicitly confirmed last-session end |
| handover_out | teardown_end_at |
| return | return_at |

All timestamps validated in WordPress local timezone. Current mmc_sessions has no end_at/duration field: generated schedule +60 minutes is not confirmed, so that fallback stays NULL. Program date establishes future eligibility only; it never creates a deadline clock. Configured door_open_minutes is actual event data, not a new offset policy.

## Manual protection / explicit apply
Only strict system_generated=true, recognized operations_v1.* template, module operations, open task without completed_at, active/future operations/show_day program, noncancelled event. Finance excluded. Existing priority/status/completed_at/notes untouched; metadata extensions preserved.
Metadata: due_source, due_policy_version=2, due_managed=operations_v2, due_last_value, assignment_source=program_owner, assignment_last_user_id. Compare current value to the last automation-written value; divergence, including manual clearing, is preserved. Existing task_updated log hooks mark explicit same-value/clearing edits as manual override. No duplicate task edit endpoint.
Owner fallback only valid program.owner_user_id; manual owner wins. Owner changes can update only matching automation-managed values. Invalid/missing owner never clears an existing assignment. Missing canonical anchor retains the old date pending manager review.
Preview is read-only. Apply is program-scoped admin POST, mmc_manage_operations capability and nonce; per-program lock plus compare-and-swap on status/completion/metadata/due/owner protects concurrent manual edits. Atomic SQL scope guard also excludes a program cancelled during apply and refuses stale/deleted program-owner assignment. Double apply produces no new write/log. No site-wide apply or automatic update of existing tasks on deploy, GET, canonical change or owner change. New system task INSERTs can be enriched in eligible future operations/show_day stage only.

## Read-only MMC alerts / ownership view
One joined query for cards/filters: overdue, due today, next48h, no deadline, mine and high priority; only active/future open Operations tasks. Past/cancelled/completed/finance excluded. Today includes overdue tasks due today; future48h includes today's upcoming tasks, so counters may overlap. Computed state does not alter DB status. Owner shown as existing user ID, no extra customer data. Existing Operations capability model retained; no new staff/role system.
Show-day adds time remaining alongside next session/doors/readiness/problems/resources/tasks. Existing sales read path unchanged. Summary adds compatible overdue/due_today/upcoming/unassigned/next_due fields, no read initialization.
External notification policy is preview-only counts; no email/SMS/WhatsApp/Kommo calls, automation, notification log or provider SDK.

## Tests
171 isolated assertions including Phase1's88: all13 anchors/fallbacks, invalid/date-only/no-end data; new system INSERT enrichment; manual/auto dates; plan/session/owner changes; same-value/clear manual overrides; metadata notes; stage/cancellation/history/closed/finance exclusion; valid owner; CAS race; capability/POST/nonce denials; double apply; overdue/today/48h/no-deadline/mine/high filters; one-query cards; read-only preview/render/native callback regression; no notification writes/calls. Production apply never used.

## Deployment / rollback
Pending CI and guarded deployment. Only existing Operations service/admin change; MMC_DB_VERSION remains1.3.7, no migration/backfill. Snapshot old source hashes and22 domain tables, then atomic exact-hash-gated replacement and read-back. Rollback both exact before files, invalidate opcache, repeat guarded reads and health. Temporary117 always restored original/passive; no _fields on write.
GitHub branch codex/operations-task-automation-v2, separate draft PR; source/tests/policy/preview/evidence/project state and rollback originals archived in existing Drive project folder. Final verification recorded below.

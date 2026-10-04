# Codex Project State — Madagaskar Sirki
Updated: 2026-10-04, Stage8. Historical Stage6/7 entries retain checkpoint status; final Stage8 section supersedes pending statements. Previous full historical state remains in [audit branch / PR111](https://github.com/milanosirki-code/madagaskarsirki-wordpress/blob/codex/current-system-audit-20261003/docs/CODEX_PROJECT_STATE.md). That audit PR was not modified by this production fix. This state file did not exist on main8a6659d; the current task creates the Stage6 canonical update without importing unrelated audit sources.

## Issue #114 — gross revenue semantics
- Root cause: wp-content/plugins/madagaskar-management-center/includes/class-mmc-sales-service.php, MMC_Sales_Service::summary(), unfiltered SUM(gross_amount) from mmc_sales_ledger WHERE event_id=%d. Ledger gross_amount stores nominal mapped line total including unpaid attempts.
- Affected metric: summary.gross_revenue consumed by mdg_ai_mmc_sales_summary / madagaskar/mmc-sales-summary, MMC_Sales_Admin::render and MMC_Dashboard_Service::program_row.
- Old semantics: all nominal mapped line totals, independent of payment.
- Corrected semantics: original tax-inclusive mapped line amount with native WooCommerce paid_at evidence, excluding failed/cancelled/pending/checkout-draft. Refunded paid original amounts stay gross; existing refund-adjusted net stays unchanged.
- Separate nominal_order_value retains the old all-row total. No ledger/order backfill, deletion, mapping redesign or synchronization write.
- Payment evidence: live39 processing PayTR orders have native is_paid=true and date paid, but empty transaction IDs. Failed4 have neither payment signal. No transaction-ID requirement; no completed-only gate.
- Branch: codex/fix-gross-revenue-114.
- Production patch commit:374470e84673882f5e74f1c89067c6efb42e750d. Tests/workflow commit477e9e46eec6f386aecce8147afaac69fdde9413. CI-validated deployment helper commit efacaa460051233060fdff15adb01651f8925e14. Documentation head is discoverable in this file's GitHub history (no self-referential SHA).
- PR:[118](https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/118), separate from audit111.
- Tests:79 assertions actual service/SQL with synthetic SQLite on PHP8.4; processing/completed, failed/pending/cancelled/on-hold/draft, full/partial refunds, mixed orders, callback/status retry idempotency, family_2_2 unit4, empty event.
- efacaa4 CI: Plugins37202923577, ChangedPHP37202923590, contract37202923567, archive37202923565, reconciliation37202923653 all PASS.
- Production deployment:2026-10-04T12:42:52Z, atomic replacement of sales service file only.
- Original Git blob1af29700f0281f64c1d490fc7398b0408b812684 / SHA2568af41d4457cc8fe911d63124bd6adfc0905f5096134b0d3e31580ef349834a77; fresh pre-write verified.
- Corrected live Git blob8d635658d0feebc5d63a2ba1d937eeec6cd2dda7 / SHA2563fb93c6e28489b30fbd837181aef87217d65b0cd5a6997524f1fb278e45456df; atomic readback and next-request hash verified.
- Event9 before47,000 TRY gross -> after42,000 TRY gross; failed nominal5,000 excluded, preserved in nominal47,000. Net42,000, capacity107, ticket107, order43, refund0 unchanged.
- Dashboard and actual read-only ability both42,000; sales UI server-render successful, no warning/fatal. Final independent ability after removing helper returned same.
- Final health16/16, code_error0, snippets120/43active; temporary119 body restored byte-exact/passive/clean. No permanent snippet change.
- family_2_2=2adult+2child, capacity_units4 preserved and regression checked.
- MMC stays1.3.47. AI0.7.0/family1.1.3 not deployed. #112/#115/payment sandbox/other menus untouched.
- Issue #114 CLOSED as completed at 2026-10-04T12:49:26Z after final live verification; evidence comment5980100912.
- Rollback:not required; guarded reverse replacement restores original sales file; no customer/ledger data rollback.
- Remaining risk: historical missing/stale native paid-date evidence not repaired; no browser visual test/new gateway test. PR118 review/merge remains; only deployed sales file is ahead of main until merge.
- Evidence:[ISSUE_114_GROSS_REVENUE.md](ISSUE_114_GROSS_REVENUE.md), [ISSUE_114_DEPLOYMENT_EVIDENCE.json](ISSUE_114_DEPLOYMENT_EVIDENCE.json).

Next:Issue #115 read-only integrity profile metadata side effect.

Archive report verified in project Drive folder: https://drive.google.com/file/d/1NUfyWYPob7aSi2aPOSMeb6K3AqBMY1li/view?usp=drivesdk


## Stage7 — PR118 merged; Issue115 read-only integrity (2026-10-04)

- PR118 reviewed and merged; new main SHA4d5c7052acaff5cf4c4b5507298c4571f0f6be8b. Fresh live sales source matches main blob8d635658d0feebc5d63a2ba1d937eeec6cd2dda7 exactly.
- Issue115 affected ability: madagaskar/program-integrity-checks (GET, readonly). Callback mdg_ai_integrity_checks → MMC_Integrity_Service::checks → MMC_Kommo_Service::status_bridge_preview → ensure_profile → wpdb::update.
- Side-effect type: persistent domain CRM/source profile metadata in custom mmc_kommo_profiles, NOT user_meta. Program/profile9; updated event_id/source_url/source_hash/search_keywords/ai_source_status/updated_at; missing profiles could also create profile/templates.
- Root cause: preview called an unconditional refreshing helper; timestamp-bearing dynamic source text could change persisted source hashes on reads. No write was needed for stage-preview decisions.
- Minimum patch: existing get_profile SELECT plus in-memory missing-lead object. Explicit sync/write paths retain ensure_profile. Dynamic-source architecture, sales/capacity/family and other modules unchanged.
- Branch: codex/fix-readonly-integrity-115. Production patch commit acbf2d71f19b796fb3d192b890969f6051035c3e. CI-validated head55dc3b56eb40fe1415a449eafa3d41b986cbcff2. PR120: https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/120 (draft review; separate from111/118).
- Tests: PHP syntax; actual-service contract19 assertions; original-code negative control reproduces forbidden update. Five applicable CI workflows green.
- Guarded live reproduction: two actual ability executions both attempted the six-column UPDATE; blocked BEFORE SQL. All13 profiles unchanged, aggregate SHA25613817bf96338fdf95c3a93fa745b77e1780d89dea391839be657ddd69594bdb9; profile9 updated_at2026-10-04 08:32:20.
- Production deploy2026-10-04T13:15:04Z, only includes/class-mmc-kommo-service.php. Git blob10418343eb9cc162e6c463a37f70bd040e9d5f07 →10cd9085aef52462aa68312c08c995297dedff87; SHA25641c61b1e6fb1cdb7c6a8a6ca4f66c0ef673cd99f9916a9817421f1ac8db288a3; atomic and next-request readback verified.
- Post-deploy actual GET ability called twice successfully:13OK/4warnings/0critical; all13 persistent profiles and updated_at unchanged. Health smoke16/16. Kommo source freshness warning kept separate.
- 59-read-only static/manual regression scan: SAFE48, SIDE_EFFECT_TECHNICAL8, SIDE_EFFECT_DOMAIN3, NEEDS_REVIEW0 in callback scope. Operations plan-get/summary/checklist-list have conditional domain writes, tracked separately; no operations patch in115.
- Live guarded smoke: first40 successful without SQL-write attempts/PHP warnings; remaining19 and final temporary snippet119 restoration pending connector quota recovery. Issue115 remains OPEN until final verification.
- Rollback: exact-hash reverse single-file patch available, not needed. No business-data mutation for reproduction/deployment. Snippet119 must finish restored byte-exact/passive.
- Evidence: docs/ISSUE_115_READONLY_INTEGRITY.md and docs/ISSUE_115_READONLY_SCAN.json; initial issue evidence comment5980411256. Final deployment JSON/Drive archive will follow final cleanup.
- Remaining risk: separate operations/admin GET patterns; cache warming documented; third-party hooks/concurrent cron outside callback guard. PR120 production source is on branch/live pending review merge.
- Next requested stage: Program9 school/field linkage and target validation. No #112/payment sandbox/school repair/family1.1.3/AI0.7.0/other-module refactor performed.


Stage7 quota checkpoint:
- WPVibe explicitly blocks every additional call until about2026-10-04 15:20UTC. No banked reset used without explicit user request.
- CI head d2db7238723958dc22ac13d335d119095c7b16a8 (including20-call max batched diagnostics) passes all5 applicable workflows; that diagnostic batch update was BLOCKED, not applied live.
- Live production patch remains verified; temporary119 initial diagnostic helper remains ACTIVE. Exact original18606-char code and original passive metadata retained privately under /workspace/issue115/private/snippet119-original.json, not committed. Cleanup is priority after recovery.
- Remaining19 guarded callbacks, final source/profile snapshot,119 exact restoration/readback, global code_error and independent post-cleanup health/integrity/sales reads are pending. Health last observed16/16; do not claim a final global code_error recheck or full59 live smoke.
- Separate conditional read-only operations writes tracked in Issue121 (https://github.com/milanosirki-code/madagaskarsirki-wordpress/issues/121); no operations fix in115.
- Issue115 remains OPEN, verified production fix but incomplete cleanup/regression stage. Quota evidence comment5980626008; sanitized artifact docs/ISSUE_115_DEPLOYMENT_EVIDENCE.json. PR120 remains draft review, not merged.
- Drive archive verified: https://drive.google.com/file/d/1hqNCNLVrVQuXyBWC7Z3bEiXDkVDtN5Aw/view?usp=drivesdk (same archive is updated with the recovery checkpoint).
- Resume within115 before the requested Program9 school/field linkage and target validation stage.


# Stage8 — Issue115 closure and Program9 read-only audit
Verified 2026-10-04 UTC. This final checkpoint supersedes Stage7 quota-pending status.

## Issue115 CLOSED / completed
PR118 was merged at main4d5c7052acaff5cf4c4b5507298c4571f0f6be8b. PR120 was reviewed, diagnostics source removed, all five applicable CI checks passed at head23282049fbe091e54185b9dba8b5a0337fbce16f, and merged.
New main: d51db2441a2e08b66a06d3249509092b5aba4565.
Production class-mmc-kommo-service.php matches main: Git blob10cd9085aef52462aa68312c08c995297dedff87; SHA25641c61b1e6fb1cdb7c6a8a6ca4f66c0ef673cd99f9916a9817421f1ac8db288a3.
Root cause: readonly GET madagaskar/program-integrity-checks → mdg_ai_integrity_checks → MMC_Integrity_Service::checks → status_bridge_preview → ensure_profile → persistent UPDATE in mmc_kommo_profiles. Patch uses get_profile and in-memory missing profile; explicit writes/sync retain ensure_profile.
Snippet119 exact original code/metadata restored, passive, clean. Temporary117 also restored exact/passive/clean. Global120 snippets,43 active,code_error0.
Remaining19 guarded actual callbacks succeeded without SQL write attempts or PHP warnings; combined59/59. Classification SAFE48, TECHNICAL8, DOMAIN3, NEEDS_REVIEW0. The three conditional domain paths were exercised only with initialized safe fixtures/SQL guard; their conditions remain separately tracked in OPEN Issue121.
Two actual native integrity GETs succeeded. All13 profile source_hash/updated_at unchanged; aggregate SHA25613817bf96338fdf95c3a93fa745b77e1780d89dea391839be657ddd69594bdb9 before=after. Profile9 updated_at2026-10-04 08:32:20 unchanged. Required response preserved (13OK,4 existing diagnostic warnings,0critical); these diagnostic warnings are distinct from PHP warnings0/fatals0.
Health16/16. Salesgross42000 TRY, nominal47000, net42000,capacity107; family_2_2=2adult+2child/capacity_units4. No rollback required. Closure evidence comment5982175559.
Limits: guarded callback SQL observation does not promise unrelated cron/third-party hooks are globally write-free; technical caching and Issue121 remain separate.

## Program9 identity — exact ID relations
Program9 PRG-2026-ANK-YENIMA-001: Madagaskar Sirki – Yenimahalle; Ankara/Yenimahalle;2026-10-04; sales_open.
MMCevent9 → active canonical bridge8 → MDGevent7. Venue source mdg, venue11: Yenimahalle Spor Kompleksi Salonu. Venue11 must be joined in MDG namespace, not MMC venue table.
Sessions60/61/62:12:00/14:00/16:00 Europe/Istanbul (DB09:00/11:00/13:00 UTC). WooCommerce published variable parents2277/2280/2283, six active child/adult mappings and Tickera event2275 agree. Native sales mapping39 paid orders/67items/107units/net42000.
Legacy mmc_events.mdg_event_id=NULL is not a missing canonical relation: bridge8 owns mapping. No backfill proposed.

## School / field semantics
Fresh active Okul Tanıtım plugin version1.7.9.
Primary school pool wp_mad_okul_tanitim:349 Ankara/Yenimahalle records,347 Bekliyor +2 Adres Eksik. All unassigned: legacyprogram_id0,mmc_program_idNULL,assigned_user_id0,personnel empty,no last visit.
Program9 materialized target-school rows0; assigned0; visited0; pending assigned unfinished0; visits0; routes0; field staff0. Explicit target-district rows0; region source falls back to home district.
Management numeric TARGET is NOT DEFINED. UI Hedef Okul0 represents materialized rows, not an approved goal. Pool349 is not target349 or pending349 for Program9.
Legacy school-program7 source_event_id7 matches MDG7 exactly, but mmc_program_idNULL and route_program_venue_idNULL. No duplicate matching legacy program.
Checks found0 school/program/user orphans,0 wrong district/program/visit relations,0 duplicate pool hashes/name-address groups,0 program-target duplicates and0 stale relations in Program9 scope.

## UI versus abilities and state preservation
Native field-summary,field-targets,region-program-summary,mdg-bridge-status and selected-venue check were read.
Guarded native server renders: MMC Field cards0targets/0visits; School list349; School dashboard349pool/1district/0MMCvisits/0assignedstaff. Same-scope UI and abilities agree.
Final renders returned no SQL-write attempts or PHP warnings; school-program/pool/target/visit and13profile snapshots identical before/after. No form submission, Kommo refresh, source sync or domain update.
One initial harness lacked the admin template library; corrected harness rerun succeeded. This was not a production UI defect.

## Classification / proposed correction
CORRECT: canonical event/venue/WooCommerce mapping and regional school source.
MISSING_RELATION: legacy school-program7 → MMCprogram9 bridge (one record).
WRONG_RELATION0; DUPLICATE0; STALE0 observed in scope.
TARGET_MISMATCH: no proven numeric mismatch; explicit management goal is absent.
NEEDS_DECISION: management target, selected school subset, promotion districts, field personnel and schedule. Two missing addresses are pool quality findings, not automatic assignments.
Proposed minimal diff: wp_mad_okul_programlar.id7.mmc_program_id NULL → 9. Preserve source_event_id7, title/date/venue/coordinates/route pointer and all school/visit/assignment rows. Not applied.
Do not invoke ensure_mmc_bridge blindly: it also rewrites derived fields and updates linked school rows. Review a controlled one-row bridge operation separately.
Follow-up: https://github.com/milanosirki-code/madagaskarsirki-wordpress/issues/124 (OPEN).
School/field production changes: NONE. Issue121 not fixed. No payment sandbox, AI0.7.0/family1.1.3 deployment or unrelated refactor.


Final concurrent-main check: main advanced independently to6935f7e1009b9118583911b44ed26cfbe4e71862 through unrelated PR123; class-mmc-kommo-service.php still has blob10cd9085aef52462aa68312c08c995297dedff87 and matches verified live source. PR125 is documentation-only (branch codex/program9-school-field-audit-20261004, initial head ee1047ec4a34637198ddd5d8cd564f43440e1bd5). Its read-only integrity CI passed. Verified Drive archive: https://drive.google.com/file/d/1nKhMpHqxmTuVlx3j8opnvA59ntR3xsF1/view?usp=drivesdk

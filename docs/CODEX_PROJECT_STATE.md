# Codex Project State — Madagaskar Sirki
Updated: 2026-10-04, Stage6. Previous full historical state remains in [audit branch / PR111](https://github.com/milanosirki-code/madagaskarsirki-wordpress/blob/codex/current-system-audit-20261003/docs/CODEX_PROJECT_STATE.md). That audit PR was not modified by this production fix. This state file did not exist on main8a6659d; the current task creates the Stage6 canonical update without importing unrelated audit sources.

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

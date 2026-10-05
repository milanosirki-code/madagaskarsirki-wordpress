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

## Stage8 — Issue #121 read-only operations side effects removed (2026-10-04)

- Issue #121 affected exactly three read-only abilities: `madagaskar/operations-plan-get`, `madagaskar/operations-summary`, `madagaskar/operations-checklist-list`.
- Root cause: all three reached `MMC_Operations_Service::summary()`, which called `ensure_plan()`. That helper can create a missing plan, seed missing checklist rows, ensure an operations task, update the accommodation checklist rule, and log plan creation.
- Old read chain: `ability -> callback -> summary() -> ensure_plan() -> plan/checklist/task/log writes`.
- Corrected read chain: `ability -> callback -> get_plan()/checklist()/summary() -> SELECT-only aggregation`.
- Minimum production patch: `summary()` no longer calls `ensure_plan()`; it reads `get_plan()` and adds `plan_exists`. Existing response keys remain. Ability registrations remain read-only.
- Operations admin GET/render now uses `get_plan()`. A missing plan renders an explicit `Plan Oluştur / Hazırla` POST action; GET no longer initializes domain state.
- Explicit write paths retain `ensure_plan()`: event seeding/backfill, plan save/update, resource assignment, schedule sync/add, explicit AI ensure/update and explicit admin initialize.
- Branch: `codex/fix-readonly-operations-121`. Production-code tested head: `74f54bcbe81689981ad0c03a62bf8bc4a87f9eb4`. PR #126: https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/126.
- CI before documentation: five applicable workflows PASS. Issue121 contract: 29 assertions; missing/existing reads write-free; explicit initialize preserved and idempotent.
- Production deploy completed 2026-10-04. Only `class-mmc-operations-service.php` and `class-mmc-operations-admin.php` changed. Service live blob `bc7fc782657c255a3eaff71536203b8611047e1c`, SHA256 `b40a95f88d830b7e5fc8a4ece39c03ec51379e2bef532202a5f4369fbaaaf1e3`. Admin live blob `43a2f40d80e3f60feddfa228ef3e22dae9ef5f67`, SHA256 `2b5a8b09645f91760c71488aa1eb46a7fdeb37ddeea853446b61698a9fdde152`.
- Existing-plan live smoke: all three actual read abilities returned correct data; full plan/checklist/task/log fingerprints and plan `updated_at` were byte/logically unchanged before/after.
- Missing-plan live smoke used request-local SELECT masking plus a pre-SQL domain-write guard because no safe natural missing-plan production fixture existed. It returned plan null, `plan_exists=false`, zero summary, empty checklist, zero domain write attempts, zero warnings, and rendered the explicit initialize button. No production plan was deleted or fabricated.
- Explicit initialize was not executed against a real production program. The actual-service synthetic CI fixture proves first-run plan/checklist/task/log creation and second-run no-duplicate idempotency.
- Operations UI: existing plan renders plan/checklist/summary; missing-plan guarded render shows explicit initialize. Both targeted renders had zero captured warnings and zero write attempts.
- Final read-only classification over the same 59-item inventory: SAFE51, SIDE_EFFECT_TECHNICAL8, SIDE_EFFECT_DOMAIN0, NEEDS_REVIEW0.
- Final live health:16/16 OK. Active Code Snippets `code_error`:0. Temporary Issue121 deploy/test helpers were deactivated and trashed with `code_error=null`.
- Program #9 data was not modified. Kommo dynamic sources, sales/tickets, Issue #112, family-package code and unrelated operations refactors were not changed.
- Evidence: `docs/ISSUE_121_READONLY_OPERATIONS.md`, `docs/ISSUE_121_READONLY_SCAN.json`, `docs/ISSUE_121_DEPLOYMENT_EVIDENCE.json`.
- Next requested stage after Issue #121 closure: Kommo dynamic program source system.


## Issue #82 — Kommo unified sources checkpoint (2026-10-05)

- Branch `codex/kommo-unified-source-82`, based on main `f37d2ec9b2079eccd1f53884a15230a381754032`.
- Canonical live source is active snippet110/V2, historical snippet70/v1.2.0 passive; hourly HTML sync still comes from automation plugin0.1.1.
- Eight canonical active events; program1578/1950 and location1394/5000 characters. Past Yenimahalle excluded.
- Reproduced hidden-source defect: valid configured URL responds404 with canonical body/8articles. Proposed minimum fix sets200 only after authorization. Original endpoint test fails, patched four-case contract and PHP syntax pass.
- Production patch NOT deployed: current Kommo source backups/attachments and real retrieval tests require an attached authorized Kommo browser, unavailable in this session. Remote inventory GET returned404; no speculative update requests/source writes/deletion.
- Current source total/agent attachment classifications remain UNKNOWN; historic15-source count is not treated as current. URL reindex unconfirmed, manual-update limitation preserved.
- Temporary117 restored original/passive with exact read-back. Final122snippets/43active/code_error0; fresh health16/16.
- Issue82 OPEN. Evidence and remaining closure gates: `docs/ISSUE_82_KOMMO_SOURCE_CHECKPOINT.md`. Issue121 remains completed; no unrelated module/domain changes.

## Issue #82 — HTTP status fix deployed (2026-10-05, Europe/Istanbul)

- Explicit Stage2 request authorizes the isolated HTTP fix without waiting for remote retrieval; Issue82 must remain OPEN.
- PR127 reviewed, ready and MERGED; code merge main `d074e115abc546a74a2955a6017ca4e20aaa2d00`. Three head CI checks succeeded.
- Active snippet110 canonical V2 changed only by status_header(200) after authorization. Before SHA256 `292e0c542a3f185119d28ba5639e1786d5bdf0fecaac8fdba3c4fc86e47956dd`; after `787ba5a41c6ff1b07dff3048cbe9230a06612725910cc92717bfbe74e795f73b`; live/readback/main candidate body MATCH. Exact pre-deploy metadata/code backup retained privately. Historical production archive remains immutable.
- Actual hidden-URL HTTP: valid404→200; invalid404→404; missing404→404. Unrelated /bilet-al/200→200 with identical body hash.
- Authenticated admin GET invokes real serve callback with invalid token and hidden URI: HTTP200, admin true,8articles,0capturedwarnings; no session creation/capability stub. A separate authenticated browser request was not available.
- Events, program text and location text unchanged byte/logically;8events,1578/1950 and1394/5000characters. Past Yenimahalle absent. Dynamic rendered minute is normalized for HTML hash comparison; normalized SHA256 `b85c70b42d43a6fb9f7c99858ce35ee70de5814e1d3f51e8d66ce3bf8c0d1d03` unchanged.
- Final health16/16; snippets122total/43active/code_error0. Snippet70 remains passive; temporary117 restored original/passive, verified exact. No targeted PHP warning/fatal; global PHP logs were not audited.
- Kommo browser unavailable. Inventory, attachments, source limit and stale-source classification remain unknown; reindex UNCONFIRMED; real AI preview/context tests NOT RUN. No source writes/creation/deletion, no customer messages.
- Issue82 remains OPEN. Evidence: `docs/ISSUE_82_HTTP_DEPLOYMENT_EVIDENCE.json`. Result: HTTP PATCH DEPLOYED / KOMMO REMOTE VERIFICATION PENDING AUTHORIZED BROWSER. Rollback not needed.

## Issue #129 — Sirk Çekilişi replacement slots (2026-10-05)

- PR128 documentation-only review/CI completed and merged; main `dd466cf475ee09ed2289037ceaa1d0560462fbe2`. Issue82 parked OPEN; no Kommo source action.
- Located live base plugin Madagaskar_Cekilis_V2 v3.3.0 (`madagaskar-cekilis`, native POST forms/Meta Instagram v26), and active snippet106 replacement1.0.0. Both live owners match repository source hashes.
- Root cause: snippet106 current_screen GET auto-redraw plus GET manual action, conditional current-winner exclusion and no historical audit exclusion.
- Candidate1.1.0 (`docs/code-snippets/madagaskar-raffle-replacement.php`) replaces automatic GET with manual POST/capability/nonce + slot occupant snapshots. Atomic campaign lock/transaction replaces only disqualified slots; all prior/current usernames/comment IDs excluded. Verified slots preserved; shortages/errors fully roll back.
- Existing Meta import/frozen eligibility pool/comment weighting and original secure seed/ranking unchanged. No new JS needed. Base plugin unchanged; existing audit schema retained.
- Branch `codex/fix-raffle-replacement-draw`; issue129. PHP syntax/35 actual-service fixture assertions PASS, including reload/double/stale, shortage rollback, permissions and read-only render. Live initial snapshot:7campaigns/2590comments/14draws/1audit; four InnoDB tables. No real campaign test write.
- Production deployment pending CI/review; planned target snippet106 only, exact backup/read-back and guarded UI/hash/health verification. Evidence: `docs/ISSUE_129_RAFFLE_REPLACEMENT.md`.

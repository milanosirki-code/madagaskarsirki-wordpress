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

Issue129 deployment checkpoint:
- Production patch verified active106/version1.1.0 with exact body hash `a1425dbdae1077c18214111eba17dac179f7ac20fe818c0ed74317515ca1d26e` and candidate read-back MATCH. Source updates required explicit native activation; initial deactivation detected and original state restored before final successful staged deployment.
- PR130/code CI head67d2248:4/4green;35actual-service synthetic assertions plus negative history-exclusion control. Original plugin3.3.0 unchanged.
- Guarded actual admin render success;0warning/fatal/SQLwrite attempts. All7campaign/2590comment/14draw/1audit fingerprints unchanged; no live disqualified fixture/action created.
- Final health16/16;snippets122total/43active/code_error0.117original/passive restored exact. Evidence docs/ISSUE_129_RAFFLE_DEPLOYMENT_EVIDENCE.json. PR130 awaits review/merge; Issue129 tracks this final source review.
- Issue82 remains parked OPEN; no Kommo source operations.
# Issue116 — current-main session-start cutoff (production verified)

- PR130 merged; Issue129 completed. Main base `51f899faebe9ae92778770a659a34c1f43b14043`; live raffle106/main match. Real replacement POST not run (no disqualified slot); synthetic fixture evidence retained.
- Branch `codex/fix-session-sales-cutoff-current-main`. Five ticket-plugin live sources exactly matched main; reviewed old PR117 diff transferred with native WooCommerce product/cart guards added.
- Rule: valid UTC `start_at <= now` closes sales; all four layers covered, including active list snippet30. Existing holds/payment completion and family/sales semantics preserved.
- PR131 / production commit `75cbc888a1cd0e01a7f7c96a9a8ef11ff8b29725`:six CI checks pass. Five plugin sources and snippet30 deployed/read-back exact. Guarded expired/future/cart/pre-payment/render/list smoke passes with no SQL writes; temporary117 restored original/passive.
- Sales gross42,000 / nominal47,000 / net42,000 / capacity107 unchanged; family capacity4. Global snippets125/45active/code_error0. Health15 OK/1 unrelated Code Snippets review warning (125/124/61), critical0; do not claim16/16. See `docs/ISSUE_116_DEPLOYMENT_EVIDENCE.json`.
- Latest main observed after concurrent PRs: `51126fdae33fde209542960b67c33c9b5d93a526`. Old PR117 superseded by131 after equivalent and native-product behavior verified; Issue116 production acceptance complete. Kommo82 remains OPEN/PARKED.
# PR131 merge / health inventory reconciliation — 2026-10-05

- PR131 merged to `7c95b0cab1fa8b682aa1d8eb0c7a5f288e3c3a43`; five live ticket-plugin files match merged source hashes. Snippet30 source body matches with PHP-tag/trailing-line-ending normalization. Sales42,000/47,000/42,000/capacity107/family4 retained; no real payment/order test.
- Health15OK/1warning: references125/124/61 are Code Snippets IDs and all KEEP_ACTIVE production sources. False global-function collisions are separate class render/plan methods. Inventory47vsAPI45 is trash active=-1 for121/122, not cache. Scope-aware analysis/active===1 patch underway; no business snippet changes.
- Dedicated branch `codex/fix-snippet-inventory-health`; Issue105 DRY-RUN safety follows health completion; real send0. GitHub/Drive archiving preference persisted in AGENTS.md.
- Drive checkpoint: https://drive.google.com/file/d/1lD4yJP_Fbb5rGAeWH6nYH-PjEYdTuFSw/view . Details `docs/HEALTH_SNIPPET_RECONCILIATION_20261005.md`.

## PR131 / Health / Issue105 final checkpoint — 2026-10-05

- PR131 merged:7c95b0cab1fa8b682aa1d8eb0c7a5f288e3c3a43. Five ticket plugin sources match live/main; snippet30 normalized body matches.
- Health PR146 merged; current main a20491968db0ed1b8dce948092a1843fc9613c95. Live inventory service SHA25611f074916642cb16de9bd62ae053d41d56103427a578acbefcc86e3819c5bd21 matches main.
- Warning root cause: trash active=-1 counted as active (121/122), plus class methods falsely detected as duplicate globals; not cached inventory. Code Snippets125/124/61 are production KEEP_ACTIVE sources; none toggled/deleted. Final125total/45active/code_error0, health16/16, warning/critical0.
- Issue105: branch codex/fix-payment-reminder-dryrun-105, PR147, tested production commit f8fb1777e117a8340b882d339b39539238409220, five CI workflows PASS and42 synthetic assertions. Only active snippet103 updated to1.1 dry-run source docs/code-snippets/ms-incomplete-payment-dryrun.php; exact original rollback retained.
- Gates: cancelled/closed program, shared canonical start cutoff, V4 sales lock, product availability, same country-aware phone+event later native paid replacement, persisted sent marker and separate expiring dry-run marker. Missing/ambiguous mapping fails closed. No transaction ID requirement.
- Guarded post-deploy30order reads:6EVENT_CANCELLED/16SESSION_STARTED/1REPLACEMENT_PAID/2ELIGIBLE/5WAITING. Known six Kırıkkale orders excluded. Domain table hashes unchanged, including Kommo profiles; zero SQL writes/captured warnings/errors/messages.
- Sales gross42,000/nominal47,000/net42,000/capacity107; family_2_2 capacity4 preserved. Temporary117 exact original/passive restored.
- Dry-run scope complete. Issue105 remains OPEN; actual SEND disabled and requires separate administrator decision plus durable atomic idempotency. No real payment/order test or automation enable. PR147 remains separate review PR.
- Evidence docs/ISSUE_105_DRYRUN_SAFETY.md and docs/evidence/reminder105-post-deploy.json; Drive checkpoint1lD4yJP_Fbb5rGAeWH6nYH-PjEYdTuFSw updated.

## PR147 merged / Issue148 raffle reporting — 2026-10-05

- PR147 merged after final5/5 CI,42tests and live103 exact-source review. Main0b57e305ad8578ead24cbab60c9d9e546dc783f2; merged source/live103 MATCH. Issue105 remains OPEN: DRY-RUN SAFETY COMPLETE / SEND DECISION PENDING. No customer messages/order/payment write/automation enable.
- New Issue148, branch codex/raffle-reporting-dashboard, PR149, production tested commit3fbac3f58df2a676c1abad96bae57c48a336e8b7, CI6/6 green. New reporting fixture44assertions and existing replacement35assertions pass.
- Controlled deployment only raffle plugin bootstrap and includes/class-mck-reporting.php. Plugin3.3.0 version preserved to avoid admin rewrite migration; reporting1.0.0. Snippet106 replacement1.1.0 exact source unchanged. No schema/data migration.
- Campaign cards/filters, manager summary, detailed winner provenance, full local audit history and capability+nonce protected five-field result CSV with spreadsheet-formula escaping. Missing verification timestamp/initial actor explicitly shown as unavailable. Existing draw/replacement/verification POST guards preserved.
- Local counts7campaigns/2590comments/228global unique usernames/14primary winners/12verified/2pending/0current disqualified/1historical disqualified/1replacement audit. Original valid entries are distinct from remaining replacement pool.
- Before/after full fingerprints identical for campaigns/comments/draws/redraw_audit. Five list filters,7campaign details and7CSV datasets successful; remoteMeta calls0/domain writes0/captured PHP warnings-errors0. No real campaign data changed.
- Aggregate3queries/5.69ms and list audit1query; no per-campaign/per-winner N+1. Existing detail5boundedqueries after summary,3.34–10.41ms in authenticated server-render harness (not full browser latency).
- Final independent health16/16, warning/critical0; global125snippets/45active/code_error0. Temporary117 restored byte-exact/passive.
- Deployment exact read-back SHA256 bootstrap2f9d2baa6f242c784f0e24c58fdfbfe6b7a7640fc9f029eb1b34f12d022b702e, reportingf47cb324e45c2512f4b58b338f6d30e51f7dd13b30243db6a7e48f07197f907e. Rollback restores exact historical bootstrapcc6496308870c2217b2ccc42bf7191e04987c856afb787504798e60dfa41cf5d before removing only added include; private backup retained, no rollback needed.
- Historical source archive preserved byte-exact; manifest archived path now uses capture rather than mutable production source. CI archive hash contract retained.
- GitHub docs/RAFFLE_REPORTING_DASHBOARD.md and docs/RAFFLE_REPORTING_DEPLOYMENT_EVIDENCE.json; Drive report1CNEDc-_3LC5WnPWdz5fL5YToXwref_rP and source/evidence ZIP archived. No sensitive participant/comment/token values in evidence.
- PR149 pending final review/merge. Browser visual test unavailable; actual WordPress renderer tested. Issue82 OPEN/PARKED; no Kommo browser/source activity. Next Operations/task automation.


## 2026-10-05 — PR #149 closure / Operations Phase 1 (#150)
- PR #149 merged `19825eb1f1fc0029c37065532c4104a6c11f1613`; live raffle MATCH; Issue #148 CLOSED/completed. Drive closure record verified.
- Issue #150, branch `codex/operations-task-automation-v1`: existing schemas/service/admin preserved; 13 plans, 559 checks, 33 schedules, 141 open tasks; nine future programs.
- 13 high-level templates gated operations/show_day; earlier phases keep planning task. Stable keys + locks, no dates/personnel/backfill.
- Read-only readiness/upcoming view, manual schedule preservation, lodging rules, cancelled guard; Issue #121 contracts retained.
- 88 assertions pass; deployment/PR/CI/final archives pending. See OPERATIONS_TASK_AUTOMATION_V1.md.

### Operations #150 — production verification
- PR #151, production source `4345bf351c2f0a7a014fb09c344c52ef08f6e494`; CI 8/8 GREEN; 88 synthetic assertions. Only service/admin includes deployed/read back MATCH. Main remains `19825eb1f1fc0029c37065532c4104a6c11f1613` until Operations PR review/merge; do not claim Operations LIVE↔MAIN match yet.
- 18 domain table snapshots unchanged across deployment/guarded GET. Counts 13 programs/plans, 141 open tasks, 559 checks, 33 schedules; all 13 current-stage backfill preview create counts 0. No backfill, real program write, owner/deadline assignment or messages.
- Three Operations abilities: future/historical/cancelled and safe SELECT-only missing-plan fixture pass, domain writes 0; Issue #121 semantics preserved.
- Upcoming 9 programs; aggregate 1 query / 5.46ms; admin 17–18 queries / 10.82–13.45ms. No PHP warning/fatal; health16/16 warning0 critical0; snippets125/45 code_error0; temporary117 original/passive.
- Rollback not required; exact before files retained. Remaining phase2 decisions: dates, owners, notifications and legacy cancelled tasks. GitHub evidence OPERATIONS_V1_BEFORE/AFTER/DEPLOYMENT JSON and schema reports; Drive lifecycle report `1-DLRn11SFIaOqhGMlohKZuWzK4mQcDMc` plus source/evidence ZIP, canonical project state updated/read back.
- Final Drive Operations archive: `1gWzrQovHHM9JyQF6eT0x-3T2gIUlY9HI`; lifecycle report `1-DLRn11SFIaOqhGMlohKZuWzK4mQcDMc`; canonical state `1cCipf_lqxDSfMQs-0MjVPogkrD5bIKNH`.


## 2026-10-05 — Phase1 closure / Operations Phase2 (#152)
- PR151 merged at `a7883d8e4643a884f26dd33c15451646041cee7e`; live Operations service/admin byte-exact MATCH main; Issue150 CLOSED/completed. Closure GitHub/Drive records read back.
- Branch `codex/operations-task-automation-v2`, Issue152. Existing task schema preserved; no migration/backfill, external messages or finance task policy.
- Baseline141 open/total, all unmarked/protected as manual, due0/owner0; program owners13/13 valid; current operations/show_day0.
- Canonical-only deadline resolver, managed-value provenance/manual overrides, owner fallback, program-scoped POST apply with cap/nonce/lock/CAS; new task INSERT enrichment; one-query in-app cards/filters/my tasks; additive read-only AI fields.
- 171 isolated assertions pass. Production deploy/CI/final archives pending; see OPERATIONS_TASK_AUTOMATION_V2.md.

### Phase2 #152 — production verification / PR153
- Source `80fe831782bbda5c96beeb185983dc055f959bb7`; CI8/8 GREEN;171 isolated +13 actual MySQL fixture assertions. Only Operations service/admin deployed/read back MATCH branch. Main remains Phase1 merge `a7883d8e4643a884f26dd33c15451646041cee7e` until PR153 review/merge.
-22 domain fingerprints BEFORE=AFTER; GET write0, provider0, notification log0, real Apply/backfill0.141 tasks remain unmarked/protected, due/owner0. Preview13 programs/all141 tasks would update due0/owner0.
- Active/future Operations alerts9 open/tarihsiz/high; overdue/today/48h/mine0.1 query/0.97ms; filter render24 queries/10.42–13.67ms. Native3 read ability legacy contract MATCH and missing-plan fixture safe. PHP warning/fatal0; health16/16; snippets125/45 code_error0; temporary117 original/passive.
- Rollback originals retained, not needed; no DB rollback. GitHub evidence OPERATIONS_V2_BEFORE/AFTER/DEPLOYMENT and policy/tests. Drive report `1TqVQeZRsLx4cppISadJ_rtKPg7dHjw6U`, ZIP `1jeCWvyAiDK5zNhXknKKia4lt7B8HHy82`, canonical project state updated/read back.
- PR153 ready transition after evidence CI; Issue152 OPEN pending review/merge. Remaining manager decision: actual backfill and external channel/policy. Phase3 next; no SEND enabled.

## Operations Phase2 closure and Phase3A — 2026-10-05
PR153 MERGED as41bf2fa59d56c400e20435f5df0d56ad45ff9e27; live Operations service/admin main MATCH; Issue152 CLOSED/completed, CI8/8, backfill0. Drive closure read-back verified. Phase3A Issue155/PR156/branch codex/operations-task-adoption-v3: exact legacy template adoption preview and selected program POST, metadata-only/transaction/CAS/duplicate guards. Phase2 due/owner Apply separate; Phase3A production adoption/backfill0, external notification0. Tests209 isolated; CI/production guarded preview evidence pending. Details OPERATIONS_TASK_ADOPTION_V3.md.

### Operations Phase3A production verified
Issue155/PR156/branch codex/operations-task-adoption-v3/source f2b16e1ba9e7d4228a5bda952c36b72d2589f928. Exact title map and safe metadata-only adoption POST, program lock/InnoDB transaction/snapshot/byte CAS/materialized duplicate guard. Separate Phase2 deadline/owner Apply retained.141 legacy tasks:12 ADOPTABLE,128 WRONG_MODULE,1 AMBIGUOUS; duplicate/custom0.13 Operations tasks; all program contexts currently Apply-ineligible; proposed adoption/due/owner0. Cancelled program open tasks18 untouched. Actual adoption/backfill0;22 domain fingerprints unchanged, GET writes0, external notifications0;209 isolated +36 real MySQL fixture assertions and CI8/8. Live source hashes match candidate. Health16/16; snippets126/46/code_error0; temporary117 exact/passive. Full evidence OPERATIONS_V3_BEFORE/AFTER/DEPLOYMENT.json and OPERATIONS_TASK_ADOPTION_V3.md; rollback originals preserved in Drive archive. Phase3B requires manager-selected eligible programs; no lifecycle changes or external providers.

## Operations Phase3A closure and Phase3B
PR156 MERGED as c56260ca6dfc5da98abeeb612bc1d64e99da537c; live service/admin exactMATCHmain, Issue155 CLOSED/completed, CI8/8, adoption/backfill0 at closure. Drive closure byte read-back confirmed. Phase3B Issue157/branch codex/operations-operational-eligibility-v3b: lifecycle-independent eligibility, canonical event/program date and real plan/event/session; plan task needs operation timestamps, no first-session fallback. Pilot only10/11 metadata adoption, no due/owner/status writes; source/tests/pilot/verification pending.


## Operations Phase3B controlled pilot — 2026-10-06

Candidate 062cfa8115f103ce7e1d3dbb2859952013691ce5. All eight original CI jobs had runner_id=0, no steps and cancellation before execution; retry attempt2 is8/8GREEN. No code/test correction was required. Operations CI passed248 isolated and55 real-MySQL assertions. Phase3A merged main c56260ca6dfc5da98abeeb612bc1d64e99da537c remains main; PR158 must not be merged in this stage.

Fresh live preflight matched Phase3A service/admin hashes. Exact rollback copies retained locally and on host. Two-file deployment completed without schema migration; live service fc64f301cb4af1c9d6e0f6126b7796af1ecd38a2976e75d17a2ea47289d66fbd and admin 7f9a3aa7e689349c89090be2a84933cb0252bf4e398e04780f9f597f5f7d3db9 match candidate bytes. PHP syntax/CI passed. All22 monitored table hashes matched across source deployment before adoption.

Fresh pilot preview verified task109/program10 and task118/program11: exact template, unique matching task, ADOPTABLE, operationally eligible, no custom metadata blocker. Separate explicit authenticated POSTs adopted only these two selected tasks. Both raw metadata BEFORE=NULL. AFTER contains source_key=operations_v1.plan, system_generated=true, phase=pre_departure, template_version=1, adopted_from_legacy=true, adoption_policy=operations_v3_exact_title, adopted_at=2026-10-06 05:03:04 and05:03:05 respectively (WordPress local time). Full-row comparisons show metadata is the only changed column, including unchanged updated_at. Second adoption for both returns adopted=0 and no changed columns.

Separate post-adoption GET: current due=NULL, proposed due=NULL, due_source=NULL, DUE_ANCHOR_INSUFFICIENT, would_update_due=false for both. Current owner=NULL, proposed owner=281776200, would_update_owner=true for both. No due/owner Apply executed. Both program status values remain sales_open. All other139 task full hashes match. No other program adoption/update, cancelled/past task update or external message. Other seven future programs only previewed;18 cancelled-program open tasks untouched. Three native Operations read abilities succeed with guarded no-write assertions; missing-plan fixture and admin renders preserve read-only behavior.

Final health16/16, critical0/warning0. Code Snippets126total/46active/code_error0. Guarded GET PHP warnings/fatals0, blocked domain writes0, full before/after GET snapshots match. Diagnostic117 original code restored and inactive with read-back. No rollback was needed.

### Additional broad fingerprint exception — not concealed

Across the wider adoption observation window, named business tables match except the two authorized task metadata fields. The additional usermeta table (118 rows) has a hash difference:171fac17c40944be2aeb72284be4373e5e419ec773b852e45630b5e3409c0ba2→025f9a82e86711c7cd86d07dfb3f3efac061d7b642086bd91f52d0156bb0da5f. This includes separate authenticated health/API reads between snapshots. Exact changed meta key/cause cannot be proven from the aggregate-only baseline. Source audit finds no user-meta write path in the changed Operations files, guarded callbacks write0, and later snapshots remain stable. Authentication/technical metadata is a hypothesis, not a confirmed classification. No private values exported and no authentication/profile metadata reset performed. Do not claim that all22 tables match or that this exception is resolved. PR158 stays draft pending this verification exception; no merge, Issue157 remains open. This is a remaining verification limitation, separate from verified pilot metadata-only changes.

Detailed sanitized evidence: OPERATIONS_V3B_DEPLOYMENT_ADOPTION.json. Rollback: restore exact Phase3A service/admin backups atomically and invalidate opcache if required; pilot metadata restoration would require exact after-value CAS for tasks109/118 only and preserve all other columns. Do not restore unrelated user metadata blindly. Next manager decisions: owner Apply and actual operation timestamps, after resolving the additional fingerprint exception.


### Usermeta fingerprint investigation closure — 2026-10-06

The Phase3B usermeta exception was investigated without exporting raw private values. Historical evidence proves the whole-table usermeta hash is not stable across unrelated Operations observation windows: Phase2 was internally stable at `0eed5905...`, while Phase3A was internally stable at `171fac17...` before the Phase3B pilot. The Phase3B wider authenticated-read window later observed `025f9a82...`, always with 118 rows. The old baselines stored only one aggregate table hash, so the exact historical changed meta_key is unrecoverable.

A current read-only key-level inventory confirms volatile WordPress/admin/auth keys including `session_tokens`, `_application_passwords`, `_last_login`, `wc_last_active`, `wp_user-settings`, and `wp_user-settings-time`. Raw meta values were not exported. PR158 runtime diffs were separately audited and contain no `update_user_meta`, `add_user_meta`, `delete_user_meta`, `$wpdb->usermeta`, or direct usermeta SQL write path.

Conclusion: `EXACT_KEY_CAUSE=UNRECOVERABLE_FROM_AGGREGATE_BASELINE`; `ATTRIBUTABLE_TO_PR158=NO_EVIDENCE`; `BUSINESS_DOMAIN_SIDE_EFFECT=NOT_OBSERVED`. Do not reset or roll back usermeta. Future verification treats strict business-domain tables separately from volatile technical usermeta; if usermeta is observed, use sanitized meta_key-level count/hash baselines rather than whole-table equality. Detailed sanitized evidence: `docs/OPERATIONS_V3B_USERMETA_INVESTIGATION.json`.

This closes the verification exception as a fingerprint-scope limitation rather than an Operations defect. PR158 can proceed through normal final CI/review/merge while retaining the explicit limitation that the historical exact changed usermeta key cannot be reconstructed.


## Protocol live consolidation — 2026-10-06
Live protocol/free-ticket functionality is already active in Code Snippets #126/#127/#128 with code_error=0. The source matches the final stacked protocol branch after Code Snippets PHP-wrapper normalization. Because historical PRs #154/#159/#160/#161/#162/#163 are stacked on one another and not based on current main, the final live feature is being consolidated onto current main in branch `codex/consolidate-protocol-live-20261006`. The consolidation contains only canonical protocol sources, four protocol tests, protocol evidence docs and a dedicated Protocol ticket contract workflow. It does not change live WordPress or domain data. On successful merge, the stacked protocol PRs are to be closed as superseded rather than merged independently. See `docs/PROTOCOL_LIVE_CONSOLIDATION_20261006.md`.


## Corporate campaign live consolidation — 2026-10-06
Live campaign source #124 is ahead of historical PR #145 and is being backported exactly onto current main in `codex/consolidate-campaign-live-20261006`; live #125 campaign-sales report matches PR #139 after wrapper normalization. Current read-only registry: demo-kurum-a and demo-kurum-b active for Denizli; demo-okul-c active for Eskişehir with adult_campaign 490 TL and child_campaign 250 TL. Final #124 policy: normal prices are live program/session prices, only discounted adult/child campaign prices are manager-entered, and effective campaign price cannot exceed live normal price. No production option/order/payment/ticket/message write is part of the consolidation. See `docs/CAMPAIGN_LIVE_CONSOLIDATION_20261006.md`.


## Campaign multi-province live reconciliation — 2026-10-06
After PR #168 merged, live Code Snippets #124 advanced again and became live-ahead of main. Current live source uses `schema_version=2` with a `cities` map so one campaign code can target multiple provinces independently. Read-only live registry: `demo-kurum-a` = Denizli + Ankara + Eskişehir; `demo-kurum-b` = Denizli; `demo-okul-c` = Eskişehir. demo-kurum-a Ankara/Eskişehir campaign prices are 475/250; demo-okul-c Eskişehir 490/250. Public rendered `/kampanya/?kod=demo-kurum-a` shows “Denizli / Ankara / Eskişehir gösterileri”. Branch `codex/campaign-multiprovince-live-reconcile` backports the exact active #124 source, extends runtime regression for multi-province isolation and adds an admin/source contract. No production registry/order/payment/ticket/message write is part of the reconciliation. See `docs/CAMPAIGN_MULTIPROVINCE_LIVE_RECONCILIATION_20261006.md`.


## Operations Phase3C latest-main clean rebase checkpoint — 2026-10-07

BEFORE:
- Current main: `5e669cae3c4d0828d8760725b55af73c631f6508`; PR #176 already merged. Completed T1–T9, legacy PRs and reminder safety were not repeated.
- PR #172 head `fafcd7ccd7e2b9a6feb87f7f0aecfa7e54d73740` is not merged because its base predates new main; no Phase3C logic defect was found. Replacement preserves new main rather than merging the older branch.

ACTION:
- Continue existing replacement branch `chatgpt/operations-guided-timeline-v3c-rebase2-20261007` at `2ccfc51ccb1c74606522823b55ce9d25d4f17cbb`; its six existing files were verified, not recreated. Compare: 6 commits ahead, 0 behind current main.
- Main vs old merge-base `7cbc27537aa4d50e36ea3025712fdcd17261c670` blob proof: workflow `88607ad88a9ddc8e3915d1a1b07994b900ef0602`, admin `eed6d5dfc7e8ce9658cc6c63bd9f3d32a2c3183a`, service `03e30574670202eedff0b5355542e9938ec8d467` are identical at both refs. No runtime/workflow conflict.
- Replacement vs PR172 candidate blob proof: workflow `2ca9ceeeb24e3d15dfb02e4c6575c0a15908a06d`, admin `d8c855b51ac040eb81809a37842dbb69639a6fa2`, service `5723a009bc64cfecfc2e2689f771923a4a2688bc` are identical. Phase3C contract unchanged.
- Final scope: .github/workflows/operations-automation.yml; wp-content/plugins/madagaskar-management-center/includes/class-mmc-operations-admin.php; wp-content/plugins/madagaskar-management-center/includes/class-mmc-operations-service.php; docs/OPERATIONS_GUIDED_TIMELINE_V3C.md; docs/OPERATIONS_V3C_PILOT_PREVIEW.json; tests/operations/timeline-v3c.php; docs/CODEX_PROJECT_STATE.md.

AFTER / EVIDENCE:
- CI: replacement PR #177 head `6743f2440e6872b03f5ee8ca59a735060248890d` passed 12/12 fresh workflows. Operations run37588694883/job112684745140 confirms both PHP lints, 265 Phase3C assertions (including prior contracts), and 55 real MySQL fixture assertions. This documentation-only result commit must also pass its own fresh checks before PR172 closure.
- Local Git fetch/checkout/pull could not complete because configured proxy endpoint is unavailable; local PHP is absent. GitHub connector is authoritative for refs/blob comparisons; no local execution success claimed.
- Production deploy NOT performed. Domain write=0; timeline write=0; task due write=0; owner write=0; program status write=0; order/payment/ticket write=0; customer SEND=0 for this task.
- Production gate still requires real controlled file-capable hosting/SFTP/SSH access, exact live two-file backup/hash/byte size, baseline check, temp upload/lint/atomic replacement, exact read-back, health/admin/pilot/abilities checks and equal domain snapshots. Code Snippets/theme draft/eval/WPCode shims are prohibited. No merge or Issue164 closure before all gates pass.
- Fresh live `operations-plan-get` GET reads for Denizli10/Mamak11: both sales_open, plan draft/undecided, all seven timeline fields NULL. Canonical read-only unified output confirms first sessions17:30/12:00 and last19:30/18:00. Existing candidate fixture records stored door interval30, suggestions17:00/11:30, source `event.first_session_minus_door_open_minutes`; interval was NOT freshly verified live because event-get ability is absent. Candidate CURRENT/SUGGESTED/SOURCE and GET write-free are tested by CI265 assertions, not by production guided render. No production SQL-write snapshot/equality claim. Read callback calls get_program/get_plan/summary, not ensure/create/update; no domain write endpoint called. Full production proof remains deployment gate.

ROLLBACK:
- No production mutation; production rollback not needed. This checkpoint is record-only on the replacement branch. Existing six-file Phase3C candidate remains unchanged.


## Operations Phase3C controlled deployment preparation — 2026-10-07

BEFORE: Main5e669cae3c4d0828d8760725b55af73c631f6508; PR177 head802ce17191f275b0883d0552cec76377923e35e4 open/ready/mergeable/unmerged,0behind,7files,12/12GREEN. No rebase, runtime port, historical task or reminder patch repeated.

ACTION: Expanded existing docs/OPERATIONS_GUIDED_TIMELINE_V3C.md only with exact candidate/main hashes and byte sizes, private live backup requirements, pair deployment consistency/partial-failure rollback, host lint/read-back/opcache gates, canonical door interval SELECT, full-column server-side row-hash SQL generator, independent BEFORE/AFTER transactions, complete-table scope including mmc_logs and commerce storage, and explicit stop conditions. No new runtime file/table/ability/snippet. Candidate artifacts locally verified against Git blob identities; these are not live backups.

AFTER/EVIDENCE: Fresh authenticated health16OK/0warning/0critical. Current environment has no hosting credentials/identities/capabilities/TCPgrants/VPN; WPVibe file tools support draft theme only. Controlled plugin-file access remains BLOCKED. No live source/backup/tempupload/atomicreplace/guidedpreview/interval/SQLsnapshot claim. Existing candidate265Phase3C/55MySQL and lint results independently re-read from CI; new documentation head checks will be verified before handoff. docs SQL reviewed against MMC_Activator schema; not executed live. Production pair rollback must preserve legitimate sales, never restore whole DB. Legacy Redirect176 remains separate unit.

Production plugin/domain/timeline/task due/owner/program status/order/payment/ticket/customer SEND/credential writes initiated=0. Issue164 and PR177 remain open/unmerged. Issues82remoteinventory/166legacyrevoke blocked;105SENDdisabled and owner124/Aile/AI/Tickera/Phone decisions unchanged.

ROLLBACK: No production change; no production rollback needed. Documentation-only changes can be corrected/reverted without touching the preserved runtime candidate. GitHub/Drive checkpoints preserve earlier history and require read-back.


## Operations Phase3C controlled access revalidation — 2026-10-07

BEFORE: main 5e669cae3c4d0828d8760725b55af73c631f6508; PR #177 head 0afce5582a4860db4737f511d265121161c916e5, OPEN/READY/MERGEABLE/UNMERGED; 10 ahead, 0 behind, scope 7 files; all 12 workflows SUCCESS. Issue #164 remains OPEN.

ACTION: Read current GitHub refs/compare/CI, latest #164 evidence, existing checkpoint and complete deployment runbook. Revalidated available tools and current cloud configuration: no hosting secret bindings, outbound identities or file deployment capabilities; no TCP grants/VPN; WPVibe file operations are restricted to draft themes. No controlled exact-byte production plugin read/backup/temp upload/atomic pair replace/read-back/rollback channel. No prohibited workaround attempted. Existing runbook is complete and retained unchanged; no rebase, runtime port or test rewrite required.

AFTER/EVIDENCE: Documentation-only checkpoint on the same branch. Candidate runtime remains byte-identical to tested 802ce17191f275b0883d0552cec76377923e35e4. CI at incoming head confirms 12/12 SUCCESS; Operations run 37594481757 previously proved both PHP lints, 265 Phase3C and 55 MySQL assertions. New documentation head CI must be checked separately. Last authenticated health 16 OK/0 warning/0 critical is prior evidence, not a new health execution. Exact live backup/hashes, recorded door interval, Denizli #10/Mamak #11 production guided preview and SQL BEFORE/AFTER remain NOT EXECUTED. GitHub artifact hashes in the unchanged runbook are not production hashes or backups.

BLOCKER: CONTROLLED FILE ACCESS YOK. Required channel must prove real resolved plugin paths, exact bytes and hash, private backup plus backup read-back, candidate temp upload/lint/hash, consistent two-file replacement with partial-failure rollback, exact production read-back and host health/audit access. Keep PR #177 unmerged and #164 open until every runbook gate passes. Other tasks/owner decisions unchanged; #105 SEND remains closed.

Production writes initiated: plugin file=0; domain data=0; order=0; payment=0; ticket=0; timeline=0; task/due=0; owner=0; program status=0; customer message=0; credential=0.

ROLLBACK: Not needed; no production write. Runbook rollback plan is prepared, but exact production rollback copies are NOT captured or verified. This documentation append can be reverted independently. GitHub and existing Drive records must preserve prior content and be read back.


## Operations Phase3C provider discovery and access handoff — 2026-10-07

New evidence: authenticated WordPress.com site listing verifies production madagaskarsirki.com as Atomic site255726534; separate Simple site255726778 is not production. Managed backup credential reference does not grant agent file access. Provider-specific owner handoff is now recorded in docs/OPERATIONS_GUIDED_TIMELINE_V3C.md: Hosting Dashboard→correct site→Settings→SFTP/SSH; protected authentication binding plus verified host/port TCP grant; exact root/path remains unverified and must be resolved read-only. No SSH enabling/credential creation/reset/key attachment/deploy/API speculation performed. Existing runtime and deployment gates preserved; no repeated ability scan/rebase/new branch/PR. Incoming candidate0fa08320994c730008d402cb11024fa7b2d831de was12/12GREEN; documentation head CI checked separately. New live backup/hash/door interval/guided preview/SQL equality not performed. Production plugin/domain/order/payment/ticket/timeline/task/owner/customer message/credential writes=0;SENDclosed;177unmerged/164open. BEFORE→ACTION→AFTER→EVIDENCE→ROLLBACK details and official sources are in the runbook handoff; production rollback unnecessary, exact live rollback copies still absent.

> Güvenlik notu: Bu belgedeki demo-kurum/demo-okul değerleri gerçek kampanya kodlarının yerine kullanılan sentetik etiketlerdir. Canlı kodlar yalnız yetkili yönetim ekranında tutulur. Eski Git geçmişi hâlâ açığa çıkmış değerleri içerebilir; belge temizliği kodu iptal etmez.

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

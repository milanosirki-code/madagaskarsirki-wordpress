# Issue #115 — Read-only program integrity

Status: Issue115 remains OPEN. Production patch is deployed and independently verified;40/59 guarded smoke calls succeeded. WPVibe blocks further calls until about2026-10-04 15:20UTC. Temporary diagnostic snippet119 is still ACTIVE; its exact original passive backup is retained locally. Remaining19 calls, restoration/readback and final global code_error verification are pending.

## PR #118 / main reconciliation
PR118 reviewed (scope, secrets, diagnostics isolation, gross/nominal/net/capacity/family, green CI, no conflict), marked ready and merged first. New main: `4d5c7052acaff5cf4c4b5507298c4571f0f6be8b`.
Fresh live sales file and that main file share Git blob `8d635658d0feebc5d63a2ba1d937eeec6cd2dda7`, SHA256 `3fb93c6e28489b30fbd837181aef87217d65b0cd5a6997524f1fb278e45456df`.
This independently confirms LIVE↔MAIN for the previously deployed #114 source.

## Exact trigger and root cause
Ability: `madagaskar/program-integrity-checks`, readonly/idempotent, admin permission unchanged.
Endpoint: GET `/wp-json/wp-abilities/v1/abilities/madagaskar/program-integrity-checks/run`.

Call chain:
1. `mdg_ai_integrity_checks`, AI modules/system-health-integrity.php:82.
2. `MMC_Integrity_Service::checks`, MMC includes/class-mmc-integrity-service.php:221, only when an existing profile is present.
3. `MMC_Kommo_Service::status_bridge_preview`, includes/class-mmc-kommo-service.php, old line908.
4. `ensure_profile` at line103.
5. `wpdb::update` at line142.

Storage is a custom persistent CRM/program profile table `wp_mmc_kommo_profiles`; NOT user_meta, post_meta, option or transient. Controlled fixture: program9/profile9;13 profile rows in total.
The helper refreshed persisted source metadata on every preview even though the decision only needs the existing `kommo_lead_id`. It also creates profile/template rows if called for a missing profile. Integrity itself already skips that bridge check if its profile is absent.

| Updated column | Stored type | Read value representation | Purpose |
|---|---|---|---|
| event_id | bigint unsigned, nullable | string/null from wpdb; integer/null in update payload | derived event link |
| source_url | text | string → string | persisted token-based source URL |
| source_hash | char(64) | string → string | persisted source fingerprint |
| search_keywords | longtext | string → string | persisted search metadata |
| ai_source_status | varchar(30) | string → string | persisted source-sync state |
| updated_at | datetime | datetime string → datetime string | technical modification timestamp |

No private profile values or tokens are archived. Columns/types are from the canonical activation schema plus live typed snapshots. The write is not required for health/integrity results.
`build_source_text` includes the current minute (`wp_date('d.m.Y H:i')`), so its derived source hash can change across minutes even without business changes. `updated_at` is included in every UPDATE; same-second calls can store identical timestamps. The operation is therefore an unconditional write attempt, not guaranteed byte-idempotent state.

## Safe reproduction
At13:14 UTC on2026-10-04, two executions of the real registered ability each attempted the profile UPDATE, with the same six columns. The query guard threw BEFORE SQL execution; WP_Ability converted that exception to `ability_callback_exception`.
All13 profiles remained identical: aggregate SHA256 `13817bf96338fdf95c3a93fa745b77e1780d89dea391839be657ddd69594bdb9`.
Profile9 updated_at stayed `2026-10-04 08:32:20`.
These are blocked live reproductions, not intentional production state changes. They do not prove which values the initial historical audit actually changed.

## Minimum patch
Branch: `codex/fix-readonly-integrity-115`.
Production patch commit: `acbf2d71f19b796fb3d192b890969f6051035c3e`.
PR: [120](https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/120), separate from111/118.

Only `MMC_Kommo_Service::status_bridge_preview` changes: replace `ensure_profile` with existing SELECT-only `get_profile`; a missing profile gets an in-memory no-lead object.
Explicit write/synchronization paths retain `ensure_profile`.
No new maintenance endpoint or architecture is needed. Technical Kommo transient caches remain. Source generation, sales, capacity, family, school/field, AI plugin version and other MMC modules are unchanged.

## Tests and deployment
CI heads `55dc3b56eb40fe1415a449eafa3d41b986cbcff2` (production contract) and `d2db7238723958dc22ac13d335d119095c7b16a8` (batched diagnostic helper): all five applicable workflows PASS.
PHP syntax validates production source, test and temporary helper on PHP8.4.
Actual-service contract:19 assertions; repeated reads, existing metadata unchanged, missing profile without creation, invalid program, manual cancellation, missing-lead create decision, stay/advance/preserve-ahead, foreign-pipeline protection, transient caching. Original-code negative control detects the forbidden persistent UPDATE.
No local PHP runtime was available; execution and syntax evidence come from GitHub Actions.

Atomic deployment: `2026-10-04T13:15:04Z`.
File: `wp-content/plugins/madagaskar-management-center/includes/class-mmc-kommo-service.php`.
Before Git blob `10418343eb9cc162e6c463a37f70bd040e9d5f07`, SHA256 `54e1bd23c808b6fe3222bd7cacf16d1f29a54a6c03f44da1cf6c27d91980c1b0`.
After Git blob `10cd9085aef52462aa68312c08c995297dedff87`, SHA256 `41c61b1e6fb1cdb7c6a8a6ca4f66c0ef673cd99f9916a9817421f1ac8db288a3`.
Exact preimage and resulting hashes were required before atomic replacement; immediate and next-request readbacks match the branch. Opcode cache invalidated.

Post-deploy guarded call: CALL_OK, no SQL write attempts, no PHP warnings.
Two independent unguarded real GET ability calls then both succeeded with unchanged17-check semantics (13OK,4warnings,0critical) and identical all13-profile hashes. `updated_at` unchanged.
System health smoke:16OK/0warning/0critical. The program's separate region/field/school/Kommo warnings remain; they are not system-health failures and were not repaired.

## 59-read-only review
See [ISSUE_115_READONLY_SCAN.json](ISSUE_115_READONLY_SCAN.json).
SAFE48; SIDE_EFFECT_TECHNICAL8; SIDE_EFFECT_DOMAIN3; NEEDS_REVIEW0 within the assessed callback/service scope.
Technical means reachable transient/cache warming, including warm-cache calls that perform no SQL writes.
Three operations abilities (`operations-plan-get`, `operations-summary`, `operations-checklist-list`) call `summary/ensure_plan`: conditional plan/checklist/task inserts or lodging checklist updates. They remain separate follow-up [Issue121](https://github.com/milanosirki-code/madagaskarsirki-wordpress/issues/121); no operations code is changed here.
The first40 guarded live smoke calls are all successful, without write attempts/warnings; remaining19 are pending quota recovery.
SQL guard prevents any domain mutation; HTTP guard permits only GET/HEAD. Native runtime caches and framework bootstrap/cron/third-party hooks are not a universal purity proof. All13 Kommo profiles are independently hash-checked.

## Rollback and remaining scope
Rollback was not needed. Temporary admin-only POST diagnostic can reverse the exact one-file patch, with expected hashes; no business-data rollback is needed.
Existing passive snippet119 was temporarily used for diagnostics and must be restored byte-exact/passive before final closure; diagnostic source lives under docs and is not plugin-autoloaded.
Separate prior-audit admin GET side effects (Kommo render profile refresh, finance snapshots, active-program user preference) and public source option caching remain outside these59 ability callbacks. This patch does not assert every admin GET is pure.
Kommo dynamic-source refresh warning stays separate. PR120 awaits review/merge; only its one production file is deployed.
Next requested stage: Program9 school/field linkage and target validation. No school/field repair, payment sandbox, #112, family1.1.3 or AI0.7.0 deployment in this task.

## Quota recovery checkpoint

The batched helper update was blocked, so live119 still has the initial single-call helper. Do not infer the batch was activated.
Pending: safely run the remaining19 callbacks, final profile/source snapshots, exact original119 restoration (including passive state and metadata), global code_error readback, independent post-cleanup integrity/health/sales reads. If quota availability is limited, restore119 first. No issue closure claim.
The usage-reset tool can be called only after an explicit user request; the user was offered an available banked reset or continuation after15:20UTC. No reset/upgrade was purchased or used.
Detailed sanitized evidence: [ISSUE_115_DEPLOYMENT_EVIDENCE.json](ISSUE_115_DEPLOYMENT_EVIDENCE.json).
Drive archive: https://drive.google.com/file/d/1hqNCNLVrVQuXyBWC7Z3bEiXDkVDtN5Aw/view?usp=drivesdk

# Latest checkpoint — 2026-10-11

# School continuation verified — 11 October 2026

Fresh WPVibe authenticated read-only source and database checks. No production mutation.

PR211 merged and deployed10 October; current live records source contains student_scope_where using MMC_Region_Service target districts for history and export. Do not redeploy or reimport the previous batches.

|Program|Current/history known schools|Students|Eligible plan units|Campuses|Suggested/planned|Printed|Distributed|
|---|---:|---:|---:|---:|---:|---:|---:|
|Aydın/Efeler MMC5|48|16,407|167|149|16,590|0|0|
|Manisa MMC7|89|37,367|189|164|37,730|0|0|

Current school rows and2026 history reconcile exactly: Şehzadeler24 known/9,381; Yunusemre65/27,986; Efeler48/16,407. History row counts equal distinct school IDs (no duplicate rows in this scope/year). All geographic school rows are196 Manisa and170 Efeler; printing applies eligibility and therefore uses189/167 units. These are different scopes, not missing school rows. Plan known units and student snapshots agree with current/history totals. No refresh is necessary.

Unknown eligible school units remain100 Manisa and119 Aydın. No estimates entered. Printed/distributed zeros mean no recorded actual quantities, not proof no physical printing/distribution happened.

Latest Drive guide also records finance PR221/222 deployed, replacing the older pending208 checkpoint. Finance220 remains open for evidence-based prior-payment/account/PayTR reconciliation. Kommo PR223 is open; inspect current deployment and approved bot notes before applying to avoid duplicate concurrent work. No bot/send change here. Other pending issues185/166/57 remain access-dependent;112 deferred preserved.

Sources: PR211, latest Drive working guide1Q6-mOKbbkYZgWVcxFYK3yy9zLFjqEZEwKTJ14Z1PiZI, native live records PHP lines272–305, read-only aggregate SELECTs on mad_okul_tanitim, mad_okul_student_history and mmc_school_print_plans.

Scope limits: not a new complete system-health test, finance reconciliation, remote Kommo attachment test or physical stock confirmation.


---
# Historical checkpoint — superseded where newer evidence exists

# Site continuation checkpoint — 2026-10-09

Read current GitHub open issues/PRs and latest Drive working guide (modified 2026-10-09). Main CODEX_PROJECT_STATE contains older checkpoints and must not override newer deployment evidence.

## Completed verification in this continuation
Native live Student Counts UI:
- Aydın/Efeler MMC5: 167 school units, 48 with counts, 119 unknown, total 16,407 students, 48 websites.
- Manisa MMC7 (Şehzadeler + Yunusemre): 189 units, 53 with counts, 136 unknown, total 22,055 students, 53 websites.
These are current UI totals, greater than the historical first batches. No batch replay, school count write, history mutation or print quantity write was performed. Option-level batch audit and database/history idempotency proof are still not obtained; UI persistence is verified only.

## Priorities
1. School data: complete remaining Aydın119/Manisa136 using verifiable official sources/year; reconcile history and print-plan snapshots. Do not infer counts or replay older import.
2. Kommo: inventory actual active agent attachments and refresh existing unified Program/Locations only. No new per-program AI source. Browser credential protection previously prevented editing; API freshness is not certified.
3. Finance: PR208 live shared allocation backport remains draft/open/mergeable, head2368ccbeb0082cd47acefdbc14479e51f3e7447b; all13 CI workflows success. Didim documentation PR209 open. Existing fixed42,000, printing58,000 and shares must not be duplicated.
4. USKD issue57: no installed accessible dedicated repo returned; repo-create capability absent. Ready seed is not copied again.
5. Issue166 legacy credential revocation remains external follow-up; no credential changes.
6. Issue185 inactive extra V4 file cleanup remains separate explicit removal task.
7. Issue186 customer SEND disabled; issue112 deferred historical ticket/payment tests preserved.

## Sources
Drive latest guide: https://docs.google.com/document/d/1Q6-mOKbbkYZgWVcxFYK3yy9zLFjqEZEwKTJ14Z1PiZI/edit
Drive finance CI: https://drive.google.com/file/d/1yb-lS68xIiWAFCnUOE2s8Ec1lBO-tljp/view
GitHub open issues57/166/186/185/112, PR208/209.
No fresh whole-system health test, remote Kommo update, payment, customer message, or production code deployment in this continuation.

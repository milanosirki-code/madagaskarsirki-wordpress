# Operations Phase3C — guided operation timeline and real deadline generation

Issue #164 / PR #177 / branch `chatgpt/operations-guided-timeline-v3c-rebase2-20261007`.
Legacy PRs #165/#172 are superseded and closed; production deployment is still pending.

## Goal

Add a guided timeline on top of merged Phase3B without creating a new task/table system or mutating program lifecycle. Existing `mmc_operation_plans` fields remain authoritative. Timeline GET is read-only. Manager saves continue through the existing capability+nonce plan POST, now with chronology validation.

## Guidance policy

Fields:
- departure_at — manager input only
- venue_entry_at — manager input only
- setup_start_at — manager input only
- rehearsal_at — manager input only
- doors_open_at — may be suggested only from canonical first session minus stored event.door_open_minutes
- teardown_end_at — manager input only
- return_at — manager input only

No T-minus guesses, travel-duration guesses, generated last-session end, or first-session fallback for the planning-task deadline are introduced.

The guide displays CURRENT / SUGGESTED / SOURCE and whether manager input is required. Suggestions are never auto-applied.

## Chronology validation

Only present values are compared, so partial timelines are allowed. Invalid ordering is rejected before plan update:
- departure <= venue entry
- venue entry <= setup
- setup <= rehearsal
- rehearsal <= doors open
- doors open <= first session
- teardown end >= last session start
- return >= teardown end
- return >= last session start

Task due/owner fields are not written by timeline preview/save. Existing Phase2 task automation remains a separate explicit preview/apply path.

## Live read-only pilot baseline — 2026-10-06

Program #10 Denizli / PRG-2026-DEN-PAMUKK-001:
- status sales_open
- event date 2026-10-08
- first session 17:30
- last session start 19:30
- door_open_minutes 30
- all seven operation timeline timestamps NULL
- safe suggestion: doors_open_at = 2026-10-08 17:00:00
- departure/venue/setup/rehearsal/teardown/return require manager input

Program #11 Mamak / PRG-2026-ANK-MAMAK-001:
- status sales_open
- event date 2026-10-10
- first session 12:00
- last session start 18:00
- door_open_minutes 30
- all seven operation timeline timestamps NULL
- safe suggestion: doors_open_at = 2026-10-10 11:30:00
- departure/venue/setup/rehearsal/teardown/return require manager input

This baseline was SELECT-only. No production timestamp, task due, owner, program status, order, payment, message or notification write occurred.

## Tests

`tests/operations/timeline-v3c.php` covers:
- canonical door-open suggestion and provenance
- no invented unsupported suggestions
- valid partial/full chronology
- departure/venue ordering guard
- doors after first session rejection
- teardown before last session start rejection
- return before teardown rejection
- existing plan value preservation
- admin guide render
- GET domain write = 0

The Operations workflow includes the Phase3C test.

## Deployment policy

Do not deploy until PR CI is green. Before deployment capture current live service/admin source hashes and strict business-domain fingerprints. Deploy only the two existing Operations PHP files with exact source/hash gate and rollback copies. Run guarded GET/render and pilot preview. Do not save pilot timeline timestamps during deployment smoke. Actual manager timeline writes are a later explicit action.

## Controlled deployment runbook — PR #177 (prepared, NOT executed)

### Current gate / exact source pin

Main `5e669cae3c4d0828d8760725b55af73c631f6508`; tested runtime source pin `802ce17191f275b0883d0552cec76377923e35e4`.
The preparation commits below change documentation only. Recheck main/head/CI before deployment; do not reuse stale baselines.
Current authenticated health: 16 OK, 0 warning, 0 critical. Hosting bindings/identities/capabilities absent; TCP grants empty; VPN not configured. WPVibe file tools are draft-theme-only. **STOP: no controlled plugin-file channel.**
No live file read/backup, SQL snapshot, interval verification, production guided preview or deployment is claimed by this runbook.

| File relative to includes/ | Expected GitHub main SHA256 / bytes (NOT a live backup) | Candidate SHA256 / bytes |
|---|---|---|
| class-mmc-operations-service.php | fc64f301cb4af1c9d6e0f6126b7796af1ecd38a2976e75d17a2ea47289d66fbd / 80832 | 3a38a7c283aa3291c05b931c8257853112e82a0fd2e074d8ce6c4b3025e336fa / 84730 |
| class-mmc-operations-admin.php | 7f9a3aa7e689349c89090be2a84933cb0252bf4e398e04780f9f597f5f7d3db9 / 38030 | 9d5254d5fa02ea91eb22b74d97288c246fa8e4755f2e024b22fc5677abdbdd39 / 40090 |

Candidate bytes were reconstructed from authenticated UTF-8 GitHub file reads and independently checked against Git blob identities: service `5723a009bc64cfecfc2e2689f771923a4a2688bc`, admin `d8c855b51ac040eb81809a37842dbb69639a6fa2`. They are candidate artifacts, never production backups.

### Operator checklist (all required; fail closed)

1. Prove the connected host/site identity, absolute WordPress root and real resolved target paths; no theme-relative paths, symlink surprises or invented credentials. Demonstrate exact byte read, private backup, same-filesystem temp upload, rename/replace, permission/owner preservation and post-read checksum capability.
2. Read both live files before any write. Record UTC timestamp, full resolved path, byte size, SHA256, mode/owner and source marker. Save exact independent rollback copies outside webroot, restrictive permissions, hash/read-back verify. Stop if baseline mismatches expected main: investigate without overwriting unrelated production work.
3. Fetch the two candidate files at pinned tested commit. Compare Git blobs, SHA256 and bytes above. Upload temp files beside the real targets (non-public temp name chosen by the host). PHP lint both temps using host production PHP8.4; verify class names and dependency/load order. Do not upload the workflow/tests as production runtime.
4. Prove the host supports a consistent two-file deployment generation or a controlled request-drain/queue mechanism. Two separate renames are not a multi-file atomic transaction. Do not disable sales, discard PayTR callbacks, or leave requests seeing mixed opcache generations. If the channel cannot protect the two-file unit and restore both on partial failure, STOP even if single-file write is possible.
5. Prepare a host-native rollback unit before replace: both exact backups, both hashes, permissions and supported opcache generation handling. If sequential rename is used inside a proven drained unit, service first then admin; on any failure restore admin first then service, verify both hashes before releasing requests. Do not leave a half-deployed unit.
6. Capture the fresh BEFORE domain snapshot below immediately before deploy; record start/end UTC and DB schema fingerprint. Capture a second fresh snapshot immediately before each pilot GET so deploy effects and GET effects are separable.
7. Temp lint/hash gates green → controlled pair replace → read both actual live paths again → exact candidate SHA256/byte equality → syntax. No cache flush via arbitrary PHP eval. Use only the host's supported opcache/generation operation if needed.
8. Health first: admin/frontend/MMC, WooCommerce→PayTR→Tickera presence, PHP fatal/warning delta and all Code Snippets code_error flags. Do not treat aggregate16OK as proof of global PHP/code_error absence. No real checkout payment/order/ticket generation.
9. Verify recorded door interval and canonical sessions using SELECT below. Never substitute the historical30-minute fixture for a missing setting.
10. Obtain guided admin GET under authorized MMC capability, no POST/save/ensure/update/action/nonce operation. Existing `operations-plan-get` returns plan/summary, **not** the new guided fields; it is not a substitute for the rendered guide. No new ability/snippet/eval route may be invented. If the host supports a read-only SQL statement audit, capture all statements for this request; any domain DML/DDL attempt fails the gate even if the resulting hash is unchanged.
11. Test Denizli10 then Mamak11, each separately bracketed by fresh BEFORE/AFTER snapshots. CURRENT all7NULL at last live read; only doors_open_at may be SUGGESTED; SOURCE must be `event.first_session_minus_door_open_minutes`. Unsupported times remainNULL/manager-required. If a live current value was entered meanwhile, preserve it and suppress that suggestion.
12. AFTER snapshots must use new transactions/connections; reusing the same consistent snapshot would mask writes. Compare schema, per-table count and every ordered row hash. Record equality explicitly. Any mismatch or write-attempt: stop, preserve sanitized evidence, investigate cause; rollback code if unexpected deploy/GET effects. Never restore a whole database or legitimate concurrent sales.
13. After all gates, GitHub+Drive evidence/read-back, refresh exact PR head/main/CI again, merge177 and close164. Missing any gate → leave both open/unmerged. Actual manager timeline/due/owner writes remain separate work.

### Exact SQL plan: pilot anchors and schema discovery

Run through a verified **direct read-only database connection**, not a WordPress PHP eval/snippet. Examples below use `wp_` only after confirming actual table prefix; replace identifiers with the verified prefix. Use a SELECT-only principal and a new read-only transaction for each BEFORE or AFTER run. Do not print DSNs/passwords/wp-config/options/source URLs.

```sql
SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ;
START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY;
SELECT id, program_code, status, updated_at
FROM wp_mmc_programs WHERE id IN (10,11) ORDER BY id;
SELECT id, program_id, status, door_open_minutes, event_date
FROM wp_mmc_events WHERE program_id IN (10,11) ORDER BY program_id,id;
SELECT e.program_id,e.id AS event_id,e.door_open_minutes,
       MIN(s.session_time) AS first_session,
       MAX(s.session_time) AS last_session_start,
       CASE WHEN e.door_open_minutes IS NOT NULL
                  AND e.door_open_minutes >= 0
                  AND MIN(s.session_time) IS NOT NULL
            THEN DATE_SUB(MIN(s.session_time),
                          INTERVAL e.door_open_minutes MINUTE)
            ELSE NULL END AS doors_open_candidate
FROM wp_mmc_events e
LEFT JOIN wp_mmc_sessions s ON s.event_id=e.id AND s.status<>'cancelled'
WHERE e.program_id IN (10,11)
GROUP BY e.program_id,e.id,e.door_open_minutes
ORDER BY e.program_id,e.id;
SELECT program_id,departure_at,venue_entry_at,setup_start_at,rehearsal_at,
       doors_open_at,teardown_end_at,return_at,status,updated_at
FROM wp_mmc_operation_plans WHERE program_id IN (10,11) ORDER BY program_id;
COMMIT;
```

Confirm the real event schema before running anchors (`event_date` is schema-defined, not program planned_date). Compare the exact event chosen by `MMC_Event_Service::event_for_program` and the canonical session list used by `task_automation_context`; if multiple events/mapping ambiguity exists, STOP rather than infer a first event. Record WordPress timezone; do not mix UTC with stored local event times.

Mandatory complete-table fingerprint scope (not only existing IDs, so new rows are detected):
- mmc_operation_plans, mmc_operation_checklist, mmc_operation_schedule (timeline/schedule), mmc_program_resources;
- mmc_tasks (including due_at/assigned_user_id/metadata), mmc_logs, mmc_programs, mmc_events, mmc_sessions;
- mmc_sales_ledger, mmc_sales_mappings, mmc_kommo_profiles;
- posts/postmeta (Woo legacy orders and Tickera instances/payment/check-in metadata);
- wc_orders, wc_orders_meta, wc_order_addresses, wc_order_operational_data when HPOS tables exist;
- woocommerce_order_items, woocommerce_order_itemmeta;
- discovered current MDG events/sessions/order-ticket mapping tables and any additional active provider payment/ticket store.

Discover optional tables/schema read-only; absence may be marked N/A only after authoritative installed/storage evidence, never silently skipped. Require all relevant tables to be InnoDB for consistent transactional snapshots; otherwise arrange a host-supported safe snapshot mechanism or STOP. No wp_options, users/usermeta or raw customer values in exported evidence.

```sql
SELECT TABLE_NAME,ENGINE FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA=DATABASE()
  AND (TABLE_NAME LIKE 'wp_mmc_%' OR TABLE_NAME LIKE 'wp_mdg_%'
       OR TABLE_NAME IN ('wp_posts','wp_postmeta','wp_wc_orders',
          'wp_wc_orders_meta','wp_wc_order_addresses',
          'wp_wc_order_operational_data','wp_woocommerce_order_items',
          'wp_woocommerce_order_itemmeta'))
ORDER BY TABLE_NAME;
SELECT TABLE_NAME,ORDINAL_POSITION,COLUMN_NAME,COLUMN_TYPE,
       IS_NULLABLE,COLLATION_NAME
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA=DATABASE()
  AND TABLE_NAME IN ('wp_mmc_operation_plans','wp_mmc_operation_checklist',
      'wp_mmc_operation_schedule','wp_mmc_program_resources','wp_mmc_tasks',
      'wp_mmc_logs','wp_mmc_programs','wp_mmc_events','wp_mmc_sessions',
      'wp_mmc_sales_ledger','wp_mmc_sales_mappings','wp_mmc_kommo_profiles',
      'wp_posts','wp_postmeta','wp_wc_orders','wp_wc_orders_meta',
      'wp_wc_order_addresses','wp_wc_order_operational_data',
      'wp_woocommerce_order_items','wp_woocommerce_order_itemmeta')
ORDER BY TABLE_NAME,ORDINAL_POSITION;
```

For each verified included table, generate a SELECT of **server-side full-column row hashes**, never raw row data. Session setting below affects query formatting only, not domain data. Check SHOW WARNINGS for GROUP_CONCAT truncation; a warning is failure. Add discovered required MDG/provider table names to the exact allowlist before generating.

```sql
SET SESSION group_concat_max_len=1048576;
SELECT TABLE_NAME,
 CONCAT('SELECT SHA2(CONCAT(',
   GROUP_CONCAT(
     CONCAT('IF(`',REPLACE(COLUMN_NAME,'`','``'),
       '` IS NULL,''N;'',CONCAT(''V'',OCTET_LENGTH(CAST(`',
       REPLACE(COLUMN_NAME,'`','``'),
       '` AS BINARY)), '':'', HEX(CAST(`',
       REPLACE(COLUMN_NAME,'`','``'),
       '` AS BINARY)),'';''))')
     ORDER BY ORDINAL_POSITION SEPARATOR ','),
   '),256) AS row_sha FROM `',REPLACE(TABLE_NAME,'`','``'),
   '` ORDER BY row_sha;') AS snapshot_select
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA=DATABASE()
  AND TABLE_NAME IN ('wp_mmc_operation_plans','wp_mmc_operation_checklist',
      'wp_mmc_operation_schedule','wp_mmc_program_resources','wp_mmc_tasks',
      'wp_mmc_logs','wp_mmc_programs','wp_mmc_events','wp_mmc_sessions',
      'wp_mmc_sales_ledger','wp_mmc_sales_mappings','wp_mmc_kommo_profiles',
      'wp_posts','wp_postmeta','wp_wc_orders','wp_wc_orders_meta',
      'wp_wc_order_addresses','wp_wc_order_operational_data',
      'wp_woocommerce_order_items','wp_woocommerce_order_itemmeta')
GROUP BY TABLE_NAME ORDER BY TABLE_NAME;
SHOW WARNINGS;
```

Run the reviewed generated SELECTs inside each read-only snapshot transaction. Column-ordinal order, explicit NULL markers and length-prefixed binary HEX avoid NULL/empty/separator ambiguity. ORDER BY row_sha preserves duplicate multiplicity and avoids exposing IDs. Stream each table's newline-separated hashes into SHA256 plus row count; store only schema hash/count/table hash and timestamps in GitHub/Drive. Keep any detailed diff private and redact before archive. Empty table hash is SHA256 of empty bytes. BEFORE and AFTER must use identical schema serialization and query list.

Example local reduction of one **hash-only** table result (mysql --batch --raw --skip-column-names; no raw rows):
```python
import hashlib, json, sys
digest = hashlib.sha256()
count = 0
for line in sys.stdin:
    token = line.strip()
    if len(token) != 64 or any(c not in "0123456789abcdef" for c in token):
        raise SystemExit("Invalid or truncated row hash; snapshot rejected")
    digest.update((token + "\n").encode("ascii"))
    count += 1
print(json.dumps({"rows": count, "sha256": digest.hexdigest()}))
```

Live order/payment activity can legitimately change commerce hashes between runs. Do not freeze sales or mislabel drift as GET write-free. If equality cannot be established, the gate is incomplete; use a proven host request-correlated SELECT-only audit and retry a bounded bracket after investigation. SQL audit may itself create diagnostic logs; keep these separate from domain mmc_logs, and do not claim no infrastructure log writes.

### Evidence / rollback / independent T10 boundary

Record per gate: BEFORE → ACTION → AFTER → EVIDENCE → ROLLBACK, UTC, operator/channel, pinned candidate head, actual live paths/hashes/bytes and snapshot manifest.
Global code_error and scoped PHP warning/fatal checks require their own evidence; health summary alone is insufficient.
Rollback restores the two exact live files as one controlled unit, read-back hashes/modes and health. Never roll back database sales/order/ticket data as part of code rollback.
Legacy Redirects1.0.1 (#176) is a separate optional deployment/backup/rollback unit; it is not bundled with Phase3C. No owner-gated Aile/AI/Tickera/Phone updates, #124 bridge write, #105 SEND, #82 source mutation or #166 credential operation are part of this runbook.

Preparation status: SQL/host operations above are reviewed **plans**, not executed live. Recorded door interval/current source-byte baseline/full snapshots remain prerequisites pending direct access.

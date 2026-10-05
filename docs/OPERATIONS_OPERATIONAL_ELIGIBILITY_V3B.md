# Operations Phase3B — derived eligibility and metadata-only pilot

Issue157 / branch codex/operations-operational-eligibility-v3b. Phase3A PR156 merged and Issue155 completed; merged main recorded in project state and Drive closure. Existing lifecycle is mutually exclusive; sales_open must not be changed to operations for automation.

## Eligibility
Existing lifecycle allowlist: venue_confirmed, event_setup, sales_prep, sales_open, promotion, operations, show_day. No newstatus/schema; preparation/region/venue research/allocation stages and financial/completed/cancelled excluded. Real existing plan and canonical event with at least one valid non-cancelled session required. Today/future canonical event_date takes precedence over planned_date; no session-date fallback; conflicting event/program dates reported as drift. Derived helper and atomic SQL guards use same allowlist/date/plan/session conditions. No program status updates. Readiness uses bounded aggregate query and displays existing lifecycle plus OPERASYON KAPSAMINDA; show_day display still computed separately.

## Deadlines / pilot scope
Plan template uses only real departure_at→venue_entry_at; no first-session fallback. Without operation timestamps due proposalNULL and DUE_ANCHOR_INSUFFICIENT. Other13-template policies unchanged; no T-minus policy. Due policy version3, existing manual/auto provenance retained. Valid owner proposal available separately; no actual owner/due Apply authorized here.

Pilot only program10 PRG-2026-DEN-PAMUKK-001 and program11 PRG-2026-ANK-MAMAK-001, exact legacy planning task. Read preview first; explicit selected task metadata-only adoption via existing service lock/InnoDB transaction/snapshot/byte CAS/duplicate guard and capability+nonce. Separate read request after adoption shows due/owner proposals. No initialize/ensure or deadline Apply called. Pilot service cannot write title/status/priority/due/owner/notes/created/updated/completed timestamps. All other program/task hashes must remain unchanged. No new audit table; metadata adopted_at/adoption_policy plus file evidence records exact allowed changes.

## Safety / verification
248 isolated assertions incl previous209; real MySQL fixtures validate sales lifecycle/adoption/idempotency/metadata-only/no-deadline/owner proposal/missing-session guards. CI before exact-hash-gated deploy of existing service/admin only, no migration. Guarded GET all13 previews, nine future program contexts, current status/timestamp completeness, AI read callbacks, admin render, SQL write/provider guards.22 domain tables fingerprinted across source deploy. Pilot before/after compares each task/column; only2 pilot metadata fields may differ; all other domain hashes must remain equal. Actual due/owner/status/other-program writes0.18 cancelled-program tasks remain read-only inventory; no cleanup. WhatsApp/email/SMS/Kommo message0.

## Rollback / records
Keep exact Phase3A service/admin sources and source-hash snapshots. Atomic code restore and opcache invalidation on failed verification. If pilot metadata differs beyond allowance, transaction rollback/CAS restore exact pilot metadata only; do not alter other domains. GitHub/Drive source/tests/policy/pilots/preview/before-after/deploy/rollback/project state, raw-byte/hash read-back required. Manager next decision: pilot owner Apply and actual operation timestamps. Final deployment/pilot evidence appended after verification; no blind adoption of nine future programs.

## Verified pre-write preview
Live SELECT-only candidate policy preview:9 eligible future programs (1,4,5,6,7,10,11,12,13), no date drift; all7 plan timestampsNULL, valid owner13/13. Pilot10 task109 and pilot11 task118 exact planning title, empty metadata, dueNULL/ownerNULL. Simulated adoption proposes owner281776200 but dueNULL/DUE_ANCHOR_INSUFFICIENT for both. No production adoption/backfill yet. Full safe preview and task fingerprints in OPERATIONS_V3B_PILOT_PREVIEW.json; nonces not archived.

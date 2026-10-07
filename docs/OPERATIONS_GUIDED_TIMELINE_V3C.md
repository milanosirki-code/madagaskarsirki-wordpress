# Operations Phase3C — guided operation timeline and real deadline generation

Issue #164 / branch `codex/operations-guided-timeline-v3c`.

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

# Active production Code Snippets source registry

These 43 files are exact Code Snippets **bodies**, stored as .php.txt so nothing auto-loads. File ID is the WordPress snippet ID; metadata and SHA-256/Git blob hashes are in ../../STAGE4_SOURCE_INVENTORY_20261004.json. Snapshot date 2026-10-04; WordPress had 120 snippets, 43 active, 0 code_error.

This folder records deployed behaviour. It is not a bulk activation/install bundle. Never execute every file with an include loop; never reactivate migrated passive snippets. Temporary #117/#119 are deliberately absent. Sources must be reviewed against the live registry before an individual change.

Programme dates/prices embedded in legacy deployed code (for example static Event schema #25/#26/#27) are historical implementation details, not authoritative current schedule data. Current programme authority is live MMC/event state. Do not copy old programme strings into Kommo sources.

Snippet #110 is the active MMC canonical dynamic Kommo V2 owner. The older mdg-kommo-active-events-unified-source.php and passive #70 remain historical references; do not deploy them over V2. #103 is logging-only and sends no messages. #115 is the live Excel download override. #120 is the new read-only invoice description helper.

Validation: source payload hashes are exact; PHP syntax uses an isolated temporary <?php wrapper when required. No WordPress/gateway execution, real payment or customer record operation occurs.

Rollback: revert repository source-record commits. Production is unchanged. Do not deactivate live snippets as a repository rollback.

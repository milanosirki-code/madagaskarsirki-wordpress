# Finance V5 canonical identity repair — 2026-10-26

Finance records imported with MMC program IDs were interpreted as MDG event IDs. This mixes unrelated shows and misstates program profit. A reviewed manifest moves only identified historical records to UUID-verified MDG events; it does not infer ownership from transaction dates.

The V5 form binds event IDs to public UUIDs and displays the session date. Finance write abilities require event_uuid for program-scoped input, preventing silent numeric-namespace confusion. The unused Pursaklar draft remains intact and is rejected for new finance writes.

Current WooCommerce orders and items identify net revenue. Order-level fees, including family discounts, are apportioned in integer cents across all order items before matching them to programs. Refunded orders are excluded and exact item refunds deducted. Legacy WooCommerce snapshots remain visible but are not added again. A confirmed duplicate POS row retains its amounts and original source reference with an explicit excluded status; the corresponding detailed settlement row remains counted.

## Private repair manifest

The repair manifest is loaded from a private, non-autoload WordPress option. It contains source fingerprints, target UUIDs, reviewed exclusions and any duplicate peer relationship. Production identifiers, record counts, monetary values and row fingerprints are not published with this engine.

Uncertain records, existing web snapshots, orders, tickets, sessions and master event records are preserved. Original amounts and source references remain available for audit. No bank settlement is inferred.

## Validation and deploy

- PHP syntax on changed source files.
- Finance snapshot, current status/refund, family discount, cent conservation, allocation, record/audit contracts.
- Identity and manifest contracts; private current-record fixture additionally simulates apply, retry, exact rollback, audit-write failure, concurrent edit refusal and capability/nonce failures.
- Production core source matched main SHA-256 before editing. The abilities source is an older deployed version; the updated summary logic from main is included and must be reviewed against the captured original.
- Back up both source files and current finance data privately before deployment.
- Deploy only these two PHP files. No checkout or ticket hooks change.
- Open V5 finance as administrator and submit the nonce-protected repair button. The transaction stores full original rows in a non-autoload option and writes a history entry per change.
- Verify all manifest rows, protected rows, unchanged amounts/counts, per-program totals and source hashes afterward.
- Reverse the repair through its rollback control before restoring old source. Rollback refuses records edited since migration.

## Limits

Accounting totals remain provisional until independent PayTR/bank/Biletinial statements and conflicting cash declarations are reconciled. A read-only system audit is not evidence of a real payment, scanner entry, or end-to-end PDF delivery test. Report these test boundaries explicitly.

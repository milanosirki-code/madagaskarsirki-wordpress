# Didim sales mapping repair — 2026-10-09

## Problem and action
Live MMC program 6 (PRG-2026-AYD-DIDIM-001), 2026-10-16, showed 0/4 sales mappings. Canonical toolbar bridge pointed to MDG event 22. The native mapping preview verified the event, date, sessions and four published child/adult variations. Used native “Doğrulanan 4 Satış Bağını Kur”, then native 365-day WooCommerce-to-MMC ledger synchronization.

| Session | Ticket | Woo product | Variation | Tickera event |
|---|---|---:|---:|---:|
|17:30|Child|3443|3444|3441|
|17:30|Adult|3443|3445|3441|
|19:30|Child|3446|3447|3441|
|19:30|Adult|3446|3448|3441|

## Verified result
- Mapping: 4/4, green complete.
- Sync: 387 orders scanned; 14 matched sales lines.
- Dashboard: 16 tickets/person capacity; net revenue 6,500 TRY; refunds 0 TRY.
- Dashboard order counter: 9, including failed order records; failed/cancelled counter 4.
- 17:30: 5 people, 2,000 TRY, 1.0% occupancy.
- 19:30: 11 people, 4,500 TRY, 2.2% occupancy.
- Program health: 16 OK, 1 warning, 0 critical. Remaining warning not investigated in this scoped mapping repair.
- Sync timestamps displayed 2026-10-09 11:18:01–02 (site local time).
- Screenshot: didim-sales-mapping-20261009.jpg, archived in Drive project folder.

## Scope and recovery
Configuration repair only; no plugin code deployment, product edits, payment/refund execution, or WooCommerce order edits. MMC ledger synchronization reads Woo orders and updates its own ledger. Source preview contained no family variation, so no family IDs were invented. Prior four child/adult mapping slots were blank; if these links prove incorrect, restore those MMC mapping fields through the native per-row editor after checking the canonical bridge. Do not delete source orders or products.

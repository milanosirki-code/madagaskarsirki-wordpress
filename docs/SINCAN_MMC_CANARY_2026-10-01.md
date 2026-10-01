# Sincan Legacy MDG → MMC Canary — 1 Ekim 2026

## Result

The approved production canary migration for MDG event #11 (Sincan / Ankara, 2026-10-03) completed successfully.

### Created MMC control records
- Program ID: 8
- Program code: `PRG-2026-ANK-SINCAN-001`
- Program status: `sales_open`
- MMC Event ID: 8
- Program venue ID: 8
- Sessions: 3
- Active ticket codes mirrored from legacy sales: `adult`, `child`

No replacement WooCommerce or Tickera sales objects were created.

### Mapping
- Required: 6
- Mapped: 6
- Complete: true
- identity_expected: 6
- identity_matched: 6

### Sales synchronization
- Legacy WooCommerce orders found: 36
- Orders synced: 36
- MMC ledger orders_count: 36
- Ticket count: 89
- Sold capacity: 89
- Gross revenue: 43,500 TL
- Refunded: 0 TL
- Net revenue: 36,000 TL
- failed_orders metric: 8

### Paid-sales reconciliation
- MDG paid orders: 28
- MMC paid orders: 28
- MDG items: 45
- MMC items: 45
- MDG tickets: 89
- MMC tickets: 89
- MDG capacity units: 89
- MMC capacity units: 89
- missing_in_mmc: []
- extra_in_mmc: []
- MDG revenue ex-tax: 36,000 TL
- MMC revenue: 36,000 TL
- revenue_diff: 0 TL

### Bridge
- linked: true
- stale: false
- session_time_match: true

The transaction committed; rollback was not triggered.

## Post-canary limitation

Immediately after the successful migration, WPVibe Free daily fair-use capacity was exhausted during the independent closing check. Therefore:
- no separate post-canary system-health call completed,
- no dashboard read-back completed,
- the final state of temporary Code Snippet #101 was not re-read after the cap blocked the closing call.

First action when WPVibe becomes available:
1. verify/deactivate #101,
2. run health,
3. verify Program #8 read-only,
4. then continue remaining migrations one at a time.

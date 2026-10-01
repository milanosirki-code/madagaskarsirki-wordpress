# Madagaskar Sirki — Production Audit — 2026-10-01

## Scope

Read-only verification of the live `madagaskarsirki.com` sales, homepage, Kommo and MMC operational surfaces.

No test order, payment, refund, cart write, product write, Kommo write or operational assignment write was performed.

## System health

- WordPress: 7.1.2
- WooCommerce: 11.1.2
- Madagaskar AI Abilities: 0.5.0
- Active Code Snippets: 36
- System health: 0 critical / 0 warning / 16 OK
- Venue source: 190 active venues
- School source: 22,871 active schools
- Population source: 81/81 provinces, 973/973 districts
- Kommo: connected, HTTP OK
- Kommo token source: MMC_KOMMO_TOKEN
- Legacy Kommo token: false

## Kırıkkale sales verification

Program:
- Program ID: 2
- Program code: PRG-2026-KIR-MERKEZ-001
- MDG Event ID: 15
- Date: 2026-10-02
- Venue: 17 Ağustos Spor Salonu
- Sessions: 17:30, 19:00
- Bridge: linked=true, stale=false, confidence=100
- Mapping coverage: 4/4
- Session match: 2/2

WooCommerce products:
- #2848 — 17:30 — publish, purchasable=true, sales_closed=false
- #2852 — 19:00 — publish, purchasable=true, sales_closed=false

Variations:
- #2849 child — purchasable=true
- #2850 adult — purchasable=true
- #2851 family 2+2 — purchasable=true
- #2853 child — purchasable=true
- #2854 adult — purchasable=true
- #2855 family 2+2 — purchasable=true

Current MMC sales summary:
- orders_count: 13
- ticket_count: 34
- sold_capacity: 34
- gross_revenue: 20,000 TL
- net_revenue: 13,250 TL
- refunded_amount: 0 TL
- failed_orders metric: 2
- reconciliation revenue difference: 0

The `failed_orders` metric includes failed + cancelled order states; it does not mean two checkout-system failures.

### Failed/cancelled order review

Order #3467:
- Status: failed
- Total: 750 TL
- Payment: PayTR
- Paid: no
- PayTR note: customer did not complete the 3D Secure step.
- Conclusion: customer/payment-flow abandonment, not evidence of a site checkout fatal.

Order #3304:
- Status: cancelled
- Total: 1,100 TL
- Paid: no
- WooCommerce note: unpaid order automatically cancelled after the hold window expired.
- Conclusion: unpaid timeout, not evidence of a checkout fatal.

Successful comparison order #3735:
- Status: processing
- Total: 2,200 TL
- Payment: PayTR
- Paid: 2026-10-01 14:28:09
- PayTR note: Payment Accepted.
- Conclusion: live PayTR checkout is successfully completing current Kırıkkale orders.

## Homepage / cities source

Homepage Page #59 contains only:
`[ms_anasayfa_v2]`

The shortcode is provided by active Code Snippet #35, `MS Anasayfa v3`.

Snippet #35 calls:
`ms_city_v2_live_cities()`

Therefore homepage and the Cities page use the same dynamic city source.

Live homepage first three cards:
1. Ankara – Pursaklar / Ankara
2. Kırıkkale
3. Ankara – Sincan

Old Bartın / Çubuk cards are not present on the homepage.

## Kommo source consistency

Program #2 Kırıkkale:
- safe=true
- issues=[]

Program #3 Pursaklar:
- safe=true
- issues=[]

Kommo diagnostics:
- configured=true
- connected=true
- http_ok=true
- token_source=MMC_KOMMO_TOKEN
- uses_legacy_token=false

## MMC operational data gap

Kırıkkale Program #2:
- operation checklist summary: 0/42 complete
- pre-departure: 0/21 complete
- vehicles: 0
- artists: 0
- people: 0
- equipment: 0
- field target schools: 101
- assigned schools: 0
- visited schools: 0
- Meta plan: draft
- Meta spend/purchases/revenue: 0

This is an operational data-completeness gap, not a sales-system failure. No assignments were invented or written during this audit.

## Active custom-plugin review candidates

Live active inventory includes both:
- Madagaskar Bilet Yönetimi 3.6.3-ticket-invalidation-dry-run
- Madagaskar Bilet Yönetimi V4.0 4.0.14-transition

And both:
- Madagaskar – İlçe Bazlı SKU Hotfix 1.0.0
- Madagaskar – İlçe Bazlı SKU Hotfix V2 2.0.0

No production plugin was deactivated because the exact live source for these four plugins is not currently present in GitHub main, and the live checkout path is healthy. Source capture and hook-level comparison should precede any cleanup.

## Current conclusion

The live Madagaskar sales path is healthy:
- product/session mapping complete,
- active products and all Kırıkkale ticket variations purchasable,
- PayTR currently completing successful payments,
- MMC reconciliation difference 0,
- homepage/current-program source aligned,
- Kommo source consistency safe,
- system health 0 critical / 0 warning / 16 OK.

Next technical cleanup should focus on source-capturing the four active transition/hotfix plugins before deciding whether any are redundant.

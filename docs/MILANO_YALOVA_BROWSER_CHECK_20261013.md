# Yalova browser verification — 13 October 2026

Read the Milano Drive project handover and GitHub Milano PR records. Authenticated browser inspection verified the live Milano /sehirler/ page and central MMC Programs screen.

## Applied
Removed only the core shortcode block [milano_ortak_etkinlikler] from Milano page ID 6 through Gutenberg, saved and verified the live page. The obsolete administrator preview listed Madagaskar shows; it no longer renders. Existing Yalova announcement, prices, map, WhatsApp and remaining blocks were preserved. WordPress revision provides rollback.

## Confirmed event brief
22 October 2026; 90. Yıl Kapalı Spor Salonu, Yalova; 18:00 and 19:30; 500 persons per session; free seating; adult TRY490, child TRY245, family 2 adults + 2 children TRY1100.

## Live blockers
The central MMC Programs screen lists 13 existing programs and no Yalova program. Its create form exposes city, district, year, date and note, but no sales-brand selector. The brand hotfix PR240 is staging-only and has not been deployed. Creating through the current form would not establish trusted Milano ownership. PR235/239/241 contain unfinished central sales contracts, not a deployed checkout. The latest Drive record states PayTR second-domain authorization has not been obtained.

No central program, product, payment, capacity or ticket mutation was performed. Yalova online ticket sales remain closed. Required implementation sequence: isolated staging brand hotfix regression; branded preparation program; canonical event/session/product bridge; deployed central order/hold/payment/QR integration; actual end-to-end verification. User requested activation, so no renewed blanket approval was requested; the current blockers are technical.

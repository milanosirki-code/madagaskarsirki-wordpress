# Ticket policy and price advice — 2 November 2026

## Scope and confirmed rule

The existing `family_2_2` / `MDG_FAMILY_2_2` package remains unchanged: four capacity units, two adult plus two child ticket components, current prices, original AJAX/nonce validation and Tickera QR generation. The owner explicitly instructed that 1 adult + 3 children may use this existing package by entering the actual attendee names. No birth-date collection or new ticket type is introduced.

This patch changes public explanations and an optional comparison in existing frontend snippets only. It adds no WooCommerce, payment, order, QR, inventory or financial hooks. No orders, products, variations, sessions or paid transactions are modified.

## Owned surfaces

- Madagaskar Code Snippets #44: rendered FAQ data, same existing design, search and FAQ schema.
- Madagaskar Code Snippets #204/#205: existing mutually exclusive event-page presentation filters. Canonical policy FAQ answers and the shared browser price advisor are included here. The original event plugin, family plugin and `public-event.js` are unchanged.
- Milano page #22: surgical changes recorded as before/after HTML; remove expired Yalova/Kocaeli announcements, retain the shared payment infrastructure explanation and existing support address.
- `assets/ms-family-advisor.js`: tested source embedded byte-for-byte in both event snippets. Re-embed this source when editing it.

## Public policy

Physical sales only on event day at the venue box office, subject to remaining quota, cash/card. Disabled children are free only at that box office; disabled adults pay. Ticketed children and adults watch seated; free seating means unnumbered seats. Sales close when seating quota fills. No universal 60–70% claim or promise of a particular seat.

Family package permits 2+2, 1+3 or 2+1 with an optional additional child aged 3–12. A third adult cannot replace a child right. Individual tickets, packages plus extras and multiple packages are compared using the page's current prices. Recommendations never silently change selections. The optional apply button uses only the original quantity controls. Once a package is selected, the advisor does not infer the group's composition from technical adult/child component counts.

School invitations, protocol invitations, disabled-child admission and paid family packages stay distinct. QR delivery advice points to existing email/spam, WhatsApp and secure Biletlerim links. No token is invented, no legal terms or refund promises change.

## Checks and smoke test

Local: JavaScript syntax; 18 standard/Izmir expected-price examples; 441 group-limit/price comparisons; exact embedding of the tested advisor in #204/#205. PHP 8.4 lint is run by the task CI because PHP is absent from this local runtime.

Before live writes: review draft PR and successful PHP/JS checks. Re-read snippet IDs #44/#204/#205 and compare with captured originals to avoid overwriting concurrent changes. Preserve their scope, priority and active status. Apply through the owning Code Snippets API; no plugin installation or activation is required. Page #22 uses WordPress content/edit. If a tool refuses a code write or requires owner review, stop that write and report the precise blocker; do not switch to SQL or another execution route.

Live checks: Madagaskar FAQ actually renders the revised answers; Milano FAQ has no stale announcements; Çerkezköy 2 adults/1 child offers 1100 versus 1225; 1 adult/3 children offers 1100; 2 adults/3 children offers 1345; 1 adult/2 children keeps 980. Izmir equivalents 1300/1300/1590/1165. Apply a recommendation using existing steppers, check summary totals, reset all quantities to zero. Verify Milano's shared event route too. No payment or order creation is needed for these presentation changes.

## Rollback

Restore the exact captured original code for #44/#204/#205 through Code Snippets, retaining activation state/scope/priority. Restore only the six page #22 content replacements using the before HTML and verify rendered pages. Rollback changes no sales data. Do not deactivate ticket/payment plugins.

## Deployment record

Prepared after the owner's instruction to keep the ticket structure intact. Live status and evidence will be appended after verification. This repository is the code record. No Drive operational archive was written in this task because a prior automatic approval review rejected that destination.

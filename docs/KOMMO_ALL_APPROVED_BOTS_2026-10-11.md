# Kommo template and bot audit — 2026-10-11

## Submitted with explicit owner approval

madagaskar_destek_sonuclandi_v2 and madagaskar_iade_tamamlandi_v2 submitted to Meta review. UI read-back confirms İncelemede. Earlier auto-review rejection was resolved by the owner's explicit permission, not a workaround. madagaskar_odeme_hatirlatma_v2 also remains İncelemede.

## Approved templates audited

All ten V1/current templates are approved: support result, refund complete, refund start, event cancellation, event change, payment reminder, satisfaction V3, event reminder, ticket follow-up and ticket link V2.

## New saved bots

- 118594 Madagaskar – İade Başlatıldı V1
- 118596 Madagaskar – Etkinlik İptali V1
- 118598 Madagaskar – Etkinlik Değişikliği V1

Each uses its selected approved template, source 82576, main contact only; requires separate verified-event and verified-field tags; excludes repeat-start tag; records channel acceptance separately from delivery; creates responsible-user task on failure; no auto-retry. UI confirms all three saved, zero runs, no automatic trigger.

## Existing bots

118592 refund complete, 118590 support result, 118588 payment reminder are saved and gated. 118555 event reminder uses 15h before date_time. 118553 satisfaction remains 36h after date-only in the separate sales pipeline; prior audit found a coverage/time issue and it has not been changed. Ticket-link and sales-followup bots already exist.

## Limits

Refund-start, cancellation and change templates still contain unbound literal placeholders. Their bot drafts must not be enabled before field mapping and event data validation. Support/refund/payment V2 require Meta approval before replacement and live delivery validation. No customer messages sent; no actual monetary refunds executed. Old approved templates retained.

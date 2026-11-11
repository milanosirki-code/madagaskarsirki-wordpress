# Kommo reminder deployment — 2026-10-11

## Event reminder

Saved the WooCommerce pipeline trigger for bot 118555 from 15 hours before date-only Gösteri Tarihi to 15 hours before Gösteri Tarih ve Saat (lead field 2460832). Applied to existing leads in the stage. Bot list read-back confirms the new trigger. Runs remain zero at verification; message delivery is not yet demonstrated.

Created date_time field 2460832, URL field Ödeme Linki 2460834, and text field Ödeme Hatırlatma Uygunluğu 2460836.

Deployed native Code Snippets snippet 177, active globally, with the source in prototypes/payment-reminder/kommo-reminder-field-sync.php. It uses canonical MDG session UTC start time and existing payment safety exclusions. A live future paid order was verified with the correct Istanbul session time. Backfill runs asynchronously.

## Payment bot

Saved Madagaskar – Ödeme Hatırlatma V1 using approved template 86853 and channel 82576. JSON is archived beside this record. No automatic trigger is enabled. Requires ALLOW_SEND; the field sync writes only PREVIEW_ELIGIBLE or EXCLUDED and never ALLOW_SEND. Paid, closed and repeated sends are excluded. Errors create a responsible-user task without automatic retry.

BLOCKER: approved payment template displays raw {{1}}, unlike other templates that display bound Kommo fields. Its approved-template editor is disabled. Dynamic binding to Ödeme Linki has not been validated. Do not enable sending until this binding is resolved and a selected-order canary meets docs/PAYMENT_REMINDER_CANARY_CONTRACT_186.md.

## Validation and rollback

PR #223 contains field synchronization code and CI tests. All 13 workflows passed on commit d0aaeed5dec2a7569bc91bba72cabd62b8c673ad. No payment reminder was sent. Bot list confirms the payment bot exists with zero runs and no trigger.

Rollback: deactivate snippet 177 only; restore event trigger to its previous date field if reverting the UI. Existing ticket delivery and snippet 103 remain unchanged. New fields can remain for audit. Payment bot is already gated off.

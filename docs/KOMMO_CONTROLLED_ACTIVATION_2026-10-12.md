# Kommo — controlled live activation, 12 October 2026

## Live configuration completed
Kommo workspace timezone was verified as (GMT +03:00) Istanbul. Three standard scheduled triggers were created and reopened to verify daily 15:00 execution and their persisted conditions. The Pro-only immediate tag-added trigger was not used, and no plan purchase was made.

| Bot | ID | Approved mapped template | Scheduled trigger | Scope and required fields |
| --- | --- | --- | --- | --- |
| Support result | 118590 | 86942, madagaskar_destek_sonuclandi_v2 | 38145898 | All five WooCommerce stages; mdg_destek_sonuc_gonderim_onayli; ticket number 2460838, topic 2460840 and result 2460842 nonempty |
| Refund completed notification | 118592 | 86944, madagaskar_iade_tamamlandi_v2 | 38145912 | All five WooCommerce stages; BOTH mdg_iade_tamamlandi_dogrulandi and mdg_iade_bildirim_gonderim_onayli; order 2460844, amount 2460846 and completion date 2460848 nonempty |
| Payment reminder | 118588 | 86871, madagaskar_odeme_hatirlatma_v2 | 38145930 | WooCommerce pending 110416823 ONLY; eligibility 2460836 exactly ALLOW_SEND; payment URL 2460834 nonempty |

This is a daily check of records explicitly prepared for sending. It is not an immediate event-triggered notification, automatic refund approval, or a global reminder to all unpaid orders. Existing sync still sets PREVIEW_ELIGIBLE/EXCLUDED and does not set ALLOW_SEND. No existing-record apply checkbox was enabled.

Before sending, each bot sets its one-send lock. Support removes its send-approved tag; refund removes only its notification-send approval and retains the financial verified tag; payment changes eligibility to SEND_STARTED. A message error leaves the lock closed and creates the existing owner follow-up task, without an automatic retry. These saved source steps were read back. No live WhatsApp delivery canary was sent and no order or payment transaction was changed.

## Satisfaction survey cleanup
All five leads in the live all-results view were individually verified to contain a nonempty Anket result, and each still had an active 118553 survey session after replying. Only those five already-answered sessions were stopped through the native active-bot control. The survey active-session count fell from 11 to 6. Results and tags were preserved. The six unanswered sessions were retained. No new customer message was sent during cleanup. The previous saved survey send guard and terminal correction remain in place. A new post-fix customer reply round trip remains to be observed; stopping completed sessions is not itself a delivery test of new sessions.

## Remaining approved-template mapping work completed
Two short-text lead fields were created and verified:
- 2460858 — Bildirim Önceki Gösteri Bilgileri
- 2460860 — Bildirim Güncel Gösteri Bilgileri

Three mapped Utility/Turkish V2 templates were saved and submitted to Meta; all three were still In review at final readback:
- madagaskar_iade_baslatildi_v2: order 2460844 and amount 2460846.
- madagaskar_etkinlik_iptali_v2: order 2460844 and cancelled-show details 2460858.
- madagaskar_etkinlik_degisikligi_v2: order 2460844, prior-show details 2460858, current-show details 2460860.

Their original V1 bots 118594/118596/118598 received only matching nonempty-field conditions. They remain without automatic triggers and still reference the old V1 templates; they are NOT live senders. After Meta approval, derive each V2 template ID from the native picker, replace the template reference, validate the terminal paths/one-send lock and configure a guarded trigger. Do not apply financial/cancellation/change verification flags based only on template approval.

## Validation and review outcomes
- Reopened all three scheduled triggers: daily 15:00 and scopes/conditions confirmed; payment has exactly one pending stage, without closed-lost.
- Read back active bot authorization-consumption steps and the three pending bot field guards.
- Static check passed: active locks and consumed authorizations precede sends; pending bot sources differ only by additional nonempty conditions, with no graph/handler rewrites.
- Native active-bot counters and final list confirmed five completed survey sessions stopped, six unanswered retained.
- One initial recurring-trigger operation was rejected by automatic review before conditions were verified. The safer trigger with explicit approval/complete-field conditions was subsequently accepted.
- A proposed bulk rewrite of three pending bot graphs was rejected by automatic review. It was abandoned; only individually verified field guards were saved instead. No rejected rewrite was executed.

## Rollback and next checks
Remove sending eligibility/approval from a record to exclude it from the daily check; use an unmatched approval condition to suspend a scheduled trigger without deleting it. The companion source archive and prior repeat-fix archive allow restoration of affected bot sources. Restoring old terminal code can reintroduce repeats and should not be done casually. Stop changes to already-answered survey sessions do not delete messages/results; do not restart those sessions to send the survey again.

Observe the first daily eligible support/refund/payment execution and confirm WhatsApp message acceptance/delivery separately. Payment needs a fresh explicitly selected eligible owner canary; order 5869 remains failed/closed-lost and EXCLUDED. Wait for Meta approval of the three new mapped templates before activating those notification senders.

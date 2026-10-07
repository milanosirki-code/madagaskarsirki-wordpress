# Issue #186 — Incomplete-Payment Reminder SEND Design

Status: **DESIGN ONLY — SEND DISABLED**

This document defines the production-safety contract for any future incomplete-payment reminder delivery. It does not authorize or enable messages.

## 1. Existing dry-run contract to preserve

Code Snippets #103 is the current eligibility source. Any SEND implementation must preserve these fail-closed exclusions unchanged:

- cancelled, closed, completed, postponed or sold-out program/event;
- V4 sales-close meta on parent or variation;
- session has started / time-based sales closure;
- inactive, unpublished, unpurchasable or out-of-stock ticket;
- paid/cancelled/non-eligible WooCommerce order;
- already-reminded durable marker;
- later paid replacement for the same canonical phone + event;
- non-checkout/non-store-api order;
- missing/ambiguous mapping or source-read failure.

SEND must never weaken a dry-run exclusion.

## 2. Hard feature gate

Real delivery must be impossible unless a dedicated production flag is explicitly enabled after owner approval.

Required default:

```
SEND_ENABLED = false
```

No deployment, activation, cron run or provider configuration may implicitly flip this flag.

## 3. Owner decisions required before implementation

Do not fill these from inference:

- exact approved customer message text;
- delivery route/provider (Kommo/WhatsApp or another approved path);
- canonical phone source;
- exact payment-link source/field;
- allowed order age/window;
- retry policy and maximum attempts;
- business hours, if any;
- whether a later successful order suppresses an earlier failed order permanently.

No customer PII or secret payment URL may be committed to GitHub, Drive, logs or issue comments.

## 4. Preview-first API

Before SEND exists, implement/read a non-sending preview that returns only:

- order id;
- event/program/session ids;
- eligibility and exclusion code;
- masked phone presence;
- payment-link presence boolean (not the URL);
- replacement-paid boolean;
- durable reminder state;
- proposed provider name;
- message-template version;
- mode = PREVIEW;
- real_send_count = 0.

Preview must not create notes, metadata, transients, provider records or messages.

## 5. Durable idempotency

A real successful send, if later authorized, must write a durable record only after provider-confirmed acceptance.

Minimum durable fields:

- order id;
- event/program identity;
- provider;
- provider request/message id or stable hash;
- template version;
- sent timestamp UTC;
- eligibility snapshot hash.

A temporary transient is never proof that a message was sent.

Before delivery, check for a prior successful durable record for the same intended reminder identity.

## 6. Provider adapter contract

Provider code must be isolated behind one adapter interface:

- `preview(payload)` — pure/read-only, no external side effect;
- `send(payload)` — unavailable unless SEND_ENABLED is true and owner-approved configuration is complete.

The eligibility engine must not know provider credentials.

Credentials remain in the existing approved secret store; never in snippets, GitHub, Drive or logs.

## 7. Payment-link safety

Payment links must come only from the explicitly approved existing source/field. Never reconstruct a private checkout/payment URL from guessed parameters.

Preview may expose only `payment_link_present=true|false`.

## 8. Rollout sequence

1. Freeze/capture current #103 dry-run source.
2. Add automated contract tests for all #105 exclusion cases.
3. Add preview-only adapter and read-only output.
4. Validate known cancelled Kırıkkale cohort stays excluded.
5. Validate at least one current failed candidate is not blanket-excluded.
6. Owner reviews exact template/provider/field choices.
7. Separate explicit approval for a single canary send.
8. Canary: one owner-selected order only, no bulk send.
9. Verify provider receipt + durable idempotency record.
10. Only then consider broader enablement under a second explicit approval.

## 9. Non-goals

This issue does not authorize:

- enabling SEND;
- bulk or historical sends;
- messages to cancelled/closed/started sessions;
- modifying payment/order/ticket state;
- exporting customer phone/email;
- rotating Kommo credentials;
- changing #103 dry-run decisions without a regression test.

# Issue #186 — Canary and durable idempotency contract

Status: **SOURCE-ONLY / SEND DISABLED**

This checkpoint prepares the final pre-SEND safety boundary without enabling delivery.

## Idempotency identity

Stable key material contains only:

- WooCommerce order ID;
- sorted unique event IDs;
- template key;
- template version.

No phone, email, customer name, payment URL, order key or provider credential is included.

## Eligibility snapshot hash

The snapshot hash covers non-sensitive decision fields such as order/event/session/program IDs, eligibility reason, payment-link presence, replacement-paid state, already-reminded state and template version.

The hash is only for comparing the exact eligibility decision used for a future canary.

## Durable receipt proposal

If a provider is later approved and a canary SEND is separately authorized, durable receipt metadata should be written to the WooCommerce order **only after provider-confirmed acceptance**:

- _ms_oh_reminder_sent_at
- _ms_oh_reminder_provider
- _ms_oh_reminder_provider_message_id
- _ms_oh_reminder_template_version
- _ms_oh_reminder_idempotency_key
- _ms_oh_reminder_snapshot_hash

The existing dry-run gate already checks _ms_oh_reminder_sent_at.

No durable field is written by this source-only prototype.

## Canary gate

A canary may become ready for owner review only if:

- eligibility=ELIGIBLE;
- payment_link_present=true;
- already_reminded=false;
- provider_ready=true.

Even when all four are true:

- owner_approval_required=true
- send_enabled=false
- mode=CANARY_PREVIEW
- real_send_count=0

A future real send requires a separate, explicit owner approval for one selected order.

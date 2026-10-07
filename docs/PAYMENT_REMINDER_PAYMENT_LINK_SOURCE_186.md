# Issue #186 — Canonical payment-link source for preview

Status: **SOURCE-ONLY / NO DEPLOYMENT / SEND DISABLED**

## Live verification

On 2026-10-07, read-only WooCommerce REST inspection of a current failed PayTR order confirmed:

- order status: failed;
- `needs_payment=true`;
- payment method: `paytr_payment_gateway`;
- WooCommerce exposes a non-empty `payment_url`;
- the URL is same-origin on `madagaskarsirki.com`;
- its path shape is `/odeme/order-pay/<order_id>/`;
- query keys are `key` and `pay_for_order`.

The private URL and order key were deliberately not copied into GitHub, Drive, issues or chat.

## Canonical source decision

For application code, the canonical source is the WooCommerce order object's native:

`WC_Order::get_checkout_payment_url()`

The REST `payment_url` field is treated as verification that WooCommerce currently exposes the native pay-for-order URL for eligible unpaid orders.

Do not build, concatenate or guess this URL manually.

## Preview contract

The source-only prototype at:

`prototypes/payment-reminder/payment-reminder-preview.php`

returns only:

- `payment_link_present` boolean;
- source identifier;
- same-origin boolean;

plus the already-approved non-sensitive preview fields.

It never returns, logs or hashes the private payment URL itself.

Cross-origin, empty or non-payment-required URLs are treated as absent.

Provider remains `UNDECIDED`. Real SEND remains unavailable.

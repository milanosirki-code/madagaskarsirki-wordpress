# PayTR / Tickera / Milano brand isolation — implementation gate (2026-10-12)

## Verified live WordPress plugin inventory
Read-only WordPress.com `plugin.list` on `madagaskarsirki.com`:
- PayTR Virtual POS WooCommerce – iFrame API: **3.1.2, active** (`paytr-sanal-pos-woocommerce-iframe-api/paytr-sanal-pos-woocommerce-iframe-api`).
- WooCommerce **11.2.1, active**.
- Tickera **3.6.0.7, active**.
- Tickera Bridge for WooCommerce **1.7.8, active**.
- `dunya-milano-session-feed` **0.1.0, active**. IMPORTANT: old live plugin is NOT the isolated-brand v0.2.0 GitHub draft. Do not assume this production feed enforces ownership.
- Central MDG ticket manager v3.6.3 and MDG v4 transition are active as separate plugins.

## What repository code proves
`MMC_Sales_Service::hooks()` attaches `sync_order` to `woocommerce_payment_complete` priority 20; it also observes status changes/refunds. This is ledger synchronization after gateway payment, **not evidence of callback HMAC verification or a safe pre-payment block**. Tickera/PayTR vendor callback source was not read, so signature, amount comparison, idempotency and QR issuance sequence are **unverified**.

## Required verification before implementation/deployment
1. Retrieve the installed PayTR 3.1.2 callback handler from an authorized source, without exposing secrets. Record its webhook route, signature verification, merchant order ID, received amount, status update timing and idempotency.
2. Trace the WooCommerce payment completion -> Tickera Bridge -> ticket creation and email/QR hooks. Confirm duplicates on retried webhook are prevented, and failure/refund states never generate a new ticket.
3. Confirm host routing: each domain sells only its own `event_owner_brand`. The payment provider's callback domain and order site need to match the originating WooCommerce order; centralized reporting is allowed, cross-brand checkout is not.
4. Establish a brand-owner invariant at order creation and a server-side identity snapshot associated with the payment attempt. Re-check signed callback **without trusting browser/POST brand values** before settlement and ticket emission; do not alter vendor signature routines blindly.
5. Test invalid HMAC, mismatched amount, wrong domain, wrong owner, mixed cart, twice-delivered callback, after-refund callback, checkout retry, concurrent last seat and duplicate Tickera codes in staging.
6. Disable or restrict the old Milano 0.1.0 feed ONLY after a separate production safety review and explicit authorization; do not modify live plug-in status by this document.
7. Only move the gated implementation to production after a full rollback, health check and user authorization.

## Current draft state
- GitHub draft PR #235 opt-in `milano-brand-checkout-guard` contains pre-payment classic/Store API guards and gateway-neutral settlement preflight.
- The preflight helper **is not connected to PayTR**; it does not claim callback verification.
- No PayTR API key, merchant secret, real customer/order data or production callback operation may be included in GitHub test fixtures.
- Production e-commerce configuration and existing customer tickets were not changed in this audit.

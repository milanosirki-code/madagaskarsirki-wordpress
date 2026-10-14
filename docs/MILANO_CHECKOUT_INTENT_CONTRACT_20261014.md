# Milano central checkout intent — staging-only protocol harness (14 October 2026)

This prototype intentionally DOES NOT create live or test PayTR payments, central Woo orders, MDG holds, Tickera instances, notifications, or customer-visible checkouts. It is an authenticated non-public WordPress REST self-test on the central **staging** site.

## Why this exists
The Milano WordPress site lacks a payment engine; Madagaskar's central WooCommerce/MDG/Tickera/PayTR ledger must remain unique. The intended architecture is Milano storefront -> its own WordPress server -> authenticated central checkout intent -> single WooCommerce order / atomic MDG hold -> centrally hosted PayTR iframe token -> PayTR verifies transaction via centrally configured callback -> Milano branded result page reads signed order status. Customers must never browse Madagaskar-branded product pages or receive duplicated QR.

## Protocol minimum
Canonical source *must* recompute event brand ownership (exactly one active MMC–MDG bridge), session and dynamic amount/units from currently active Woo/MDG configurations. Signed claims bind merchant-side intent audience, exact Milano origin, event/session, ticket quantities, minor-units price, capacity units, expiry (<=300 seconds) and cryptographic nonce. No public client chooses product IDs, merchant_oid, amount, redirect host, callback route or brand. The signature must be verified constant-time on the central server. A real implementation must use a separate >=32-byte server-only secret exchanged out of band and rotated, not WordPress salts. Use an atomic persistent idempotency row/unique constraint; a transient is NOT enough for payment retry/replay guarantees.

## Staging smoke
Isolated plugin `integrations/milano/checkout-contract/milano-checkout-contract-stage.php` registers admin-gated `GET /wp-json/milano-staging/v1/checkout-contract-smoke` **only** on `staging-f321-milanosirki-lgajf.wpcomstaging.com`. Self-test checks signature tamper, expired token, wrong origin, wrong brand, wrong session, changed price, and unknown extra claims. Uses staging-internal `wp_salt('auth')` ONLY for testing; emits no reusable token and does not implement public checkout. No new tables, settings, orders, stock locks or API secrets.

## Go-live hard blockers
- PayTR written second-domain acceptance, site integration approval and callback verification, ability to show iframe on Milano without Madagaskar UX.
- Central order creation **exactly once** under verified signed intent and durable idempotency key, current atomic MDG hold integration, failure rollback.
- Real 2+2 family order produces 4 unique Tickera ticket instance/entry QR, each usable once; 19:30 ticket PDF displays 19:30.
- Duplicate PayTR callback / payment retry, race for final seats, refund and rollback, return URL forgery and 15m hold expiry; simulate on staging with no customer notifications.
- Explicit venue allocation verification separate from technical sale status.
- Keep PR246/247/248/253/255 and this prototype draft until independent QA. No live deployment or payment/sale activation from this PR alone.

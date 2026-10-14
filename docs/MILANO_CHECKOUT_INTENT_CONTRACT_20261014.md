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

## Verified PayTR step-2 trust boundary — 2026-10-14

Per PayTR's current iFrame API Step 2 specification (https://dev.paytr.com/iframe-api/iframe-api-2-adim), the callback HMAC input is strictly `merchant_oid . merchant_salt . status . total_amount` with `merchant_key`. **Do NOT represent `currency`, `payment_amount`, `payment_type`, `test_mode` or reason text as cryptographically authenticated by that HMAC** even if the extra fields appear in the POST. The isolated contract verifier now ignores all those fields for payment authorization. `total_amount` is HMAC-protected and may exceed the originally requested amount when installments are chosen; compare it against the server-persisted original Woo order amount and manually reconcile any signed underpayment. The original currency, merchant/test mode and brand must be checked against the centrally stored order and signed iframe initiation record, NOT callback extras. The verifier does not install any REST callback or settle Woo orders. Never use `merchant_ok_url` or `merchant_fail_url` as proof of payment.

PR #258 (https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/258) separately corrects the shared MDG hold-settlement duplicate race, with fake-DB regression and isolated real MySQL 8.4/InnoDB simultaneous settlement-versus-settlement and settlement-versus-release CI; the implementation still requires staging Woo/PayTR real callback and four unique Tickera QR tests.


## Canonical intent and Woo order uniqueness — isolated implementation (14 October 2026)

New **offline only** `integrations/milano/checkout-contract/milano-canonical-order-claim.php` implements a reusable SQL **first-writer-wins** claim + one WC order binding, but does NOT register a REST endpoint, install its schema, create an order, reserve capacity, handle a payment, send a message, or issue a Tickera ticket. Real test `tests/milano-checkout-contract/mysql-order-claim.php` and GitHub CI use two concurrent PHP processes against isolated MySQL 8.4/InnoDB (one race on same purchase intent, one race to bind competing order IDs). Both dedicated jobs passed.

Required schema, as an explicit and separately reviewed migration only: `wp_milano_order_claims_v1` with `UNIQUE intent_key`, `UNIQUE wc_order_id`, immutable `quote_sha256`, `brand=milano`, event/session, exact minor-unit total and capacity units, `claimed/bound` lifecycle, timestamps. `claim()` uses atomic `INSERT ... ON DUPLICATE KEY UPDATE intent_key=intent_key`, then compares every immutable field. First call returns `created=true`; replay returns `created=false`; mismatched amount/session/quote returns `WP_Error`. **Only the first claim holder may try creating the Woo order**; every retry must stop or read the previously bound order. `attach_order()` atomically sets WC order ID once via `UPDATE ... WHERE state='claimed' AND wc_order_id IS NULL`; same bound ID replays, a different ID is rejected. There is no background cleanup that might unwittingly unlock a charged/in-flight request.

**Critical crash-safety limitation:** If the worker creates a Woo order but dies *before* `attach_order()`, the claim stays `claimed`; a subsequent caller MUST NOT mint another order. A future production adapter must persist the intent key on the Woo order at order creation and provide secure admin reconciliation against the central order ledger. Until then, an ambiguous claim remains deliberately blocked. DB unique keys are necessary but not enough on their own for exactly-once side effects.

**Go-live preconditions (not yet met):**

1. Have PayTR confirm use of **milanosirki.com** as the external customer-facing iframe domain on the same merchant and central callback (or provide an approved alternative).
2. Install and test this ledger schema only on an isolated central staging installation using InnoDB; no production database writes from this PR.
3. Server-side verify short-lived signed Milano-only intent, original quote, session brand ownership, price/age/units, and 24/150/151 or future canonical identifiers before claiming. Quote hash must be derived from immutable **server-recomputed** fields, not browser-supplied values. Verify origin and never expose shared HMAC keys.
4. Implement a canonical Woo order creator that runs **only for created=true**, binds the order safely, records the claim key as immutable order meta, issues the existing MDG hold only once, and reconciles abandoned `claimed` status without blind retries. Integrate with existing MDG capacity PR258 and verify all Madagaskar regressions.
5. Implement one PayTR initiation and verified callback, securely linking merchant_oid to the bound Woo order and preserving a durable payment receipt. Never use browser success redirect as proof of payment. PayTR may retry notifications; only one verified transition may release valid Tickera entry tickets.
6. Run staging trial: **one Milano family 2+2 = 4 distinct valid Tickera entry QR + 4 admission capacity units + 1100 TRY**, verify independent scan and 19:30 PDF start, no duplicate Woo order or QR on concurrent retry, suppress all real customer messages and make sure existing Madagaskar sales are unchanged.
7. Approve the three existing checkout-specific Milano legal **draft** pages (#36 Mesafeli Satış Sözleşmesi, #37 Ön Bilgilendirme Formu, #38 Bilet Teslimatı) before publication, and verify checkout customer acceptance; other live privacy/KVKK/refund pages already published.

**Still CLOSED:** all Yalova central staging products/event/sessions are draft and 0 held/sold. Live Milano WordPress has no Woo/Tickera/PayTR; do not install unrelated copies or create a second ledger.

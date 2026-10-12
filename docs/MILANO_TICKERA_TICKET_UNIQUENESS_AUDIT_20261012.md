# Milano / Madagaskar — Tickera ticket uniqueness audit (2026-10-12)

## Evidence verified in the repository
- The previous MDG atomic capacity fix CI commit `bd7a42c89ebf3741f2a15768a70c19104ee27b18` has **success** in PHP syntax, Plugins CI, and Milano contracts. This validates isolated tests, not two-worker database concurrency or live PayTR retries.
- `MDG_Live_Sales::payment_complete` handles `woocommerce_payment_complete`, `woocommerce_order_status_processing`, and `woocommerce_order_status_completed`, calling capacity commit and order-map status synchronization. These hooks are *not themselves a proof* that Tickera issues one ticket.
- `MDG_Ticket_Template_QR_Binder` concerns **venue/location QR embedded in a Ticket Designer template**. Its documentation explicitly says admission QR is not changed. It cannot serve as ticket instance uniqueness protection.
- MDG V4 references `tc_tickets_instances` posts and their `ticket_code` metadata for ticket audits, migration/rollback previews. This is evidence of the ticket instance data model, not its vendor creation hooks.
- Active live plugins include Tickera 3.6.0.7, WooCommerce Tickera Bridge 1.7.8, PayTR iframe 3.1.2; the vendor implementation of ticket creation is not present in the inspected repository paths.

## Safety rule for implementation
Do not intercept or delete `tc_tickets_instances` blindly, and do not use venue QR code as a customer admission code. An actual idempotent ticket issuance gate needs a verified **order ID + order item ID + ticket unit index** identity, and vendor-compatible creation and email mechanisms.

## Read-only diagnostics required
1. On staging, for a controlled single test order with quantity 2, list ticket-instance IDs and hashed (not plaintext) ticket codes grouped by WooCommerce order and line item.
2. Re-send the *same valid, signed* PayTR callback sequentially and concurrently, then compare expected number of ticket instances, capacity `sold_units`, and outgoing ticket emails.
3. Trigger both `processing` and `completed` status hooks. Check for duplicate or missing instances, including manually resent notifications and webhook retries.
4. Check `tc_tickets_instances` parent/meta relationship to WC order/line item in the installed Tickera/Bridge version before declaring a unique key.
5. Validate wrong-brand and missing owner orders produce **zero** admission tickets; refunds and canceled orders do not produce new instances.
6. Only after verified API hooks are identified, implement idempotency with server-side transaction/uniqueness, repeatable tests, rollback, and explicit release approval.

## Production status
No vendor callback or Ticket Designer code changes. No ticket was created, disabled, or deleted. Milano remains unlaunched; Madagaskar production sales unchanged.

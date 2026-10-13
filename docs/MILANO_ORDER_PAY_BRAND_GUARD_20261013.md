# Milano existing-order payment brand guard — 2026-10-13

## Result

Dependent draft PR243: https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/243 , branch fix/milano-order-pay-brand-guard-20261013 targeting PR235 integration branch. Code commit2079b81a1bf17654ff1a6d9abfad930eea17da07: 12/12 triggered workflows success. Not merged, installed or activated on WordPress. No real order, payment, stock, ticket, QR or production mutation.

## Finding and fix

Existing guard lacked the WooCommerce existing-order order-pay submission hook. Official WooCommerce trunk class-wc-form-handler.php was read through GitHub connector: after nonce/order-key/needs_payment checks, woocommerce_before_pay_action executes before customer save, payment-method changes and standard gateway processing. That hook is outside the payment try/catch, so an uncaught checkout exception is inappropriate.

Dunya_Event_Brand_Guard draft v0.1.1 registers validate_pay_order at that hook when the existing disabled-by-default DUNYA_BRAND_GUARD_ENABLED flag is explicitly enabled. It reads real order line product/variation mappings and canonical brand owner; wrong, missing, ambiguous brands or malformed order/item objects stop via WordPress HTTP403 wp_die. Madagaskar and unrelated non-MDG orders remain allowed. It does not change order status, validate payment settlement or bypass Woo's authorization checks.

## Tests and limits

Seven cases call the actually registered callback: Milano denied, Madagaskar allowed, mixed denied, unknown denied, unrelated allowed, invalid order denied, ambiguous bridge denied. They verify simulated gateway processing is not reached on denial and serialized order remains unchanged. Existing guard contracts and PHP syntax also passed.

These are isolated dependency tests. Real Woo order-pay customer authorization, PayTR iframe/token lifecycle, pending historical orders, guest flow, campaign/legacy routes and final settlement still need staging E2E validation. No merchant-domain approval or real-payment proof is supplied by this patch. Disabled server flag remains unchanged.

## Current Yalova blocker

Staging program14 has venue preparation record14 for MDG venue181 (90. Yıl Spor Salonu), 22 October18:00/19:30, 500 each, 245/490/1100 prices. The phone/direct salon/date attestation checkbox remains unchecked. User's latest 'Devam' is continuation authorization, not factual evidence of allocation confirmation. Await explicit venue/date confirmation before marking it confirmed. Current live sale remains closed.

Rollback: revert three code/test/workflow changes in PR243; there is no production rollback needed. Keep this dependent PR separate from PR240 MMC staging changes and PR239 checkout preflight work.

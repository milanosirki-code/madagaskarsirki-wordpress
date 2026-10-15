# PayTR callback isolation evidence — 15 October 2026 15:02+03 continuation

Public sale remains CLOSED. No order, payment, admission ticket, QR or production setting was changed in this continuation.

WPVibe access available again (102 calls remaining at initial inventory); inspected current staging PayTR3.1.2 source read-only. Active official plugin confirmed. Saved staging test option is yes; gateway previously disabled remains disabled (no enable operation performed).

## Concrete root cause

includes/class-paytr-payment-gateway-iframe.php line24 registers woocommerce_api_wc_gateway_paytrcheckout. Lines67–70 instruct setting callback URL in the PayTR merchant panel, using home URL plus /index.php?wc-api=wc_gateway_paytrcheckout.

includes/PaytrCoreClass.php line30 sets request test_mode from local test option. Token request supplies merchant_ok_url and merchant_fail_url, but no callback/notification URL override was found in that request implementation. Customer return URLs do not establish server-to-server callback routing.

includes/class-paytrspi-callback-iframe.php validates HMAC then derives Woo order ID by splitting merchant_oid at PAYTRWOO, loads that order, and updates pending/failed orders. It does not establish staging-vs-production ownership isolation. No forged callback was sent and no callback signature code was changed.

Thus a local test checkbox is insufficient evidence that PayTR notifications will reach staging. The actual merchant-panel callback configuration has NOT been inspected, and credential equality across environments has NOT been checked. No claim that misrouting has occurred.

## External dependency and safe continuation

Use a distinct authorized PayTR test merchant with its callback directed to isolated staging, or obtain an officially supported isolated test route. Do not repoint the shared production merchant callback; existing Madagaskar/Yalova transactions must remain protected. No secret should be pasted into chat. Configure via secure service UI when authorized access is available.

After callback isolation is verified, enable staging test gateway and run the previously required payment/adult/child/family, four unique QR/PDF, duplicate-callback, failure/refund, concurrency and brand-route gates. Then inventory live IDs separately and deploy/open the three Milano events only after successful gates.

This corrects any implication in earlier notes that enabling local test mode alone completes the payment test setup.

# Milano corporate campaign redesign

The campaign entry is a general institution-code page. It must not display a fixed institution, city list, date range or bundle selection before the code is validated. The existing shared corporate renderer supplies the event posters, venue details, session selector, birthdate fields and quote summary.

`[milano_corporate_campaigns]` calls the shared renderer with the Milano brand. The original shortcode keeps the Madagaskar presentation. Milano events and sessions come from the handoff's verified canonical catalogue; event titles are not used as brand evidence. Campaign city membership and an optional event scope remain enforced.

The shared age calculation is reused without a new pricing algorithm: ages are calculated on the performance date, ages 0–2 are free without consuming the allowance, ages 3–12 use the free-child allowance or configured child price, and ages 13+ use adult pricing. The native adult selector supports more than two adults. This differs from the earlier fixed-bundle interface, which remains isolated pending retirement and migration review.

## Validation

- Existing 104 corporate campaign assertions plus 12 new brand, catalogue, preview and presentation assertions passed.
- Existing 69 fixed-bundle assertions still passed, preserving the separate earlier flow.
- Existing birthdate DOM suite passed: dynamic inputs, price changes, normal/campaign comparisons and age boundaries.
- PHP lint and whitespace checks passed.
- Browser checks confirmed code-first entry, four dynamically loaded event cards and server quotes for one adult/two children and two adults/four children.

These checks do not prove successful payment, capacity mutation, callback processing or ticket issuance. No payment was submitted as part of this design review.

## Preview and deployment boundary

The staging-only preview integration adds a temporary read-only preview code in memory. It never writes a campaign registry entry. Its campaign has `preview_only=true`, so the native renderer and manifest validator prohibit payment. The preview shell is host-guarded and only runs on a page containing the new shortcode.

Production deployment remains pending. The brand-aware create form and signed cart adapter are now implemented and verified in staging through checkout. Existing campaign brands are preserved; fixed-bundle records are explicitly excluded from the native corporate resolver and their existing adapter remains separate. Do not reinterpret old records or paid carts. The front-site fallback targets the intended central portal and must not be deployed before that portal is installed and verified.

## Signed cart and staging validation

The Milano adapter wraps only the recognized handoff callback at priority 90. It accepts an item only when its shared-engine HMAC is valid and the fresh canonical quote matches the event, session, parent product, variation, ticket type, role, quantity and capacity units. Other selections delegate to the original guard. Other validation failures remain failures. A campaign revoked after selection is rejected on revalidation. The shared engine fails closed if the Milano adapter is missing. Existing mixed carts are rejected before any replacement.

A synthetic code `milano-prova-20261019` was created through the native admin form in staging only: Milano brand, Kocaeli scope, adult 475 TL, child 245 TL. The existing fixed-bundle staging cart was first preserved by the mixed-cart guard, then its two previous test items were removed through the cart UI. Removing its paid adult first restored the remaining children's normal price and displayed the expected invalid-group error.

The new native campaign was then selected from an empty cart using synthetic birthdates. One adult and two eligible children produced 475 TL in the browser calculator, server result and checkout. Checkout displayed one paid adult, two free children, three participant entries and PayTR. The preflight confirmed PayTR test=yes and all four staging event readiness checks true. No order was submitted, no payment was made and PDF/QR issuance after payment remains unverified for this new flow.

The presentation-only code `MILANO-ONIZLEME` remains nonpayable. Staging source is installed in the existing isolated lab; production handoff 0.2.6 was read but not changed. Production shared snippet 124 was re-read and compared against the proposed engine before packaging.

## Controlled publication order

1. Publish reviewed source to the existing repository branch and open the draft PR; run CI. The user explicitly authorized publishing the campaign source to the public repository on 20 October 2026 after the earlier automatic approval rejection. No incident telemetry or customer information is included in this source transfer.
2. Complete the final test-payment/order step by user handoff, then verify callback, capacity, ticket and QR behavior. Never bypass the browser order-submit handoff through an API.
3. Install the corporate adapter before updating the shared engine; the order matters for callback compatibility. Back up the then-current engine first and re-check concurrent changes.
4. Create and verify the central Milano corporate page with the shared shortcode alias. Then update front page 298 to the generic entry. Keep existing campaign records unchanged and configure any new production codes explicitly through admin.
5. Verify code entry, scope, session, age calculation, cart, checkout and ordinary ticket regression after deployment. Restore the exact previous snippet/plugin/page versions for rollback; do not alter orders.

## Rollback

Restore the previous shared-renderer source, remove the staging-only preview integration and restore the previous page shortcode and slug from its WordPress revision. The campaign registry, event/session identity, product mapping, order and ticket records are untouched by this redesign. Restore only the changed module; do not disable unrelated plugins.

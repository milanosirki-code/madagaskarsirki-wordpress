# Madagaskar Web Assistant Core 0.1.0

Internal, read-only PHP services for the proposed web assistant. This package does not register a public API, UI, agent tool, cron, activation migration or checkout hook. Loading it only defines classes. It has not been deployed.

## Use from a trusted administrator context

Load the plugin normally on an isolated staging site with MMC, MDG, WooCommerce and the family plugin already loaded. `manage_woocommerce` is required. No credentials are accepted or stored by this package.

```php
use Madagaskar\WebAssistant\Party;
use Madagaskar\WebAssistant\Service;
use Madagaskar\WebAssistant\Failure;

try {
    $catalogue = Service::catalogue();
    // Render only returned offers; keep blocked diagnostics in the admin view.
    // $chosen is the exact session offer explicitly selected by the visitor.
    $party = Party::from_ages( array( 35, 32, 5, 9 ) );
    $quote = Service::quote( $chosen['session_id'], $party, $chosen['source_revision'] );
} catch ( Failure $error ) {
    // Map the stable reason code to an approved user message; do not expose
    // raw internal diagnostics or substitute an invented price/link.
}
```

The example requires a real offer selected by the caller. Never accept a client-supplied offer, price, URL, capacity or product/variation ID. A future public controller must call these services through a separately reviewed, restricted interface; do not give the anonymous caller administrator permissions or a generic abilities execution endpoint. No web controller is included in this phase.

## Source contract

- Exact `Madagaskar Sirki` owner from MMC; one active forward AND reverse MMC–MDG bridge, matching IDs and selected MDG venue.
- MMC event must be `sales_open`; program may be `sales_open`, `promotion`, `operations` or `show_day`; MDG event and session must be `onsale`. Disagreement blocks automatic recommendations. This includes A0's Keşan case; the source is not repaired automatically.
- UTC MDG time must match the active MMC local seans and event/program date in `Europe/Istanbul`. Closed, malformed, future-sale-window and sold-out sessions cannot become offers.
- Read each session's own active child/adult catalogue; do not reuse the first session's prices. Require matching published variation parent, native purchasability/stock and exact decimal price. Respect MDG shared capacity, Woo maximum quantity and the parent product quantity limit.
- Allow only the canonical HTTPS Madagaskar event URL, with no query, fragment, credentials, custom port or alternate host.
- Virtual family package availability needs the existing family plugin, per-event enablement and no active MDG multi-unit ticket collision. Price precedence follows native checkout: active MMC family price, else the event setting/default. The inactive İzmir 1,100 TL row therefore cannot override the event's 1,300 TL setting. Malformed or ambiguous family data disables family quotes, while valid singles remain usable.
- No source cache is added. Each `Service::quote` reads native data again. Optional `source_revision` detects price, stock, mapping or family-policy changes since selection. Offers expire after at most 60 seconds, earlier at session start/sale end. Offers are advisory; native checkout must revalidate and reserve capacity.

## Quote contract

All amounts are integer kuruş. Adults are 13+, children 3–12, infants 0–2. `Party::from_ages` validates boundaries without retaining dates of birth. A responsible adult is required. Counts and ages must be integers; do not round, coerce unknown ages or replace missing input with zero.

The 3 November policy allows a family package to cover up to two adults plus remaining child allowances (at most four people), including one adult + three children and two adults + one child. Every package still expands to the existing two adult + two child ticket components and consumes four capacity units, even if some allowances are unused. A third adult does not use a child allowance. The solver compares singles, family packages, extra tickets and multiple packages under component limits (20 each, at most 10 packages) and available capacity. Ties prefer fewer capacity units, then fewer packages. Alternatives include feasible combinations; more expensive combinations can have negative savings relative to the regular ticket total.

`attendance_count` includes infants; `capacity_units` follows paid ticket/virtual package rules, so they are deliberately different. `regular_total_minor` is a price comparison; `regular_option_available` separately reports whether that combination is feasible.

School invitations, campaign stacking, group sales and accessible-seat promises are not implemented. Unexpected party fields are rejected; do not silently ignore a discount request or treat it as standard admission. Use the existing human-support flow. No orders, payment data or customer messages are read or written.

## Tests and rollout

`php tests/web-assistant-core/run.php` runs fixture-backed behaviour checks, an independent recursive price-optimality oracle, age/price/stock/time/brand/state/URL failures and native-source integration with DB writes/cart initialization forbidden. GitHub Actions tests PHP 7.4 and 8.4. These are synthetic tests, not live checkout or QR acceptance.

Before production, read back the installed dependencies, load only this package in staging, inspect `Service::catalogue()` including blocked records, explicitly test the chosen session/revision/quote and the native JS family row. Recheck native guest purchase and family allocation/4QR if a later UI or checkout integration is added. Do not change Keşan status, prices, Woo mappings or active snippets to make a test pass.

Rollback: deactivate/remove only this new package. There are no migrations, settings, jobs, holds or data records to undo. Existing sales plugins and snippets remain active. Research and A0 evidence: PR #304. Implementation tracked independently in PR #305.

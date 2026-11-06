# Madagaskar Web Assistant Core 0.3.0

0.3.0 adds the optional native Aile Paketi 2 (2 adults + 4 children) policy from `MDG_Family_Package_24::offer()`. It never infers availability from a city name or from the old 2+2 price. The deterministic search compares singles, existing 2+2 compositions, exact 2+4 packages and mixed combinations, honoring the actual quantities issued, six-person capacity and component limits. The old 2+2 policy is preserved. The native 2+4 module is tracked separately in PR310; an absent/disabled module leaves previous recommendations unchanged. This branch depends on the already installed 0.2.1 preview from PR308; it is not a reason to deploy the entire branch to production. No new Gemini call or external integration is added.

Internal, read-only PHP services for the proposed web assistant. Version 0.2.0 adds a restricted preview on the explicitly verified staging origin only. It has no anonymous API, agent tool, cron, activation migration or checkout hook. On production it only defines classes and registers no preview hooks. Deployment state is recorded separately in PR #306 and the Drive worklog.

## Staging preview

On `https://staging-f321-milanosirki-lgajf.wpcomstaging.com` only, Tools → Bilet Önerisi Önizleme shows the proposed customer form to users with `manage_woocommerce`. Two REST routes (`GET mdg-web-assistant/v1/catalogue`, `POST mdg-web-assistant/v1/quote`) require the same site/permission gate, including an independent check in each callback. Cookie requests use the WordPress REST nonce. Responses are private/no-store; POST accepts only session ID, party counts and a mandatory source revision, with a 2 KiB body bound.

The UI requires explicit city and session selection, sends only validated integer counts, invalidates results on edits and rejects late responses. Source strings render with textContent, and event links must remain on the verified staging origin. Clicking the link opens the existing event page; it does not select cart items or complete checkout. Production/public rollout is a separate review step. The preview includes no LLM or chat integration.

Version 0.2.1 appends only the fixed `#bilet-secimi` anchor after validating the canonical event URL. The result names the chosen date/time and explains that the user must reselect that session and ticket quantities on the native page. The native renderer defaults to its first open session; this package does not alter it. The card expires within the server-issued quote lifetime (at most 60 seconds), conservatively counting from the start of the request with a monotonic browser timer. Timeout, link activation and returning to a visible tab invalidate expired cards. The separate Gemini intake addon remains independent.

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

The example requires a real offer selected by the caller. Never accept a client-supplied offer, price, URL, capacity or product/variation ID. A future public controller must call these services through a separately reviewed, restricted interface; do not give the anonymous caller administrator permissions or a generic abilities execution endpoint. The included staging controller does not grant public access.

## Source contract

- Exact `Madagaskar Sirki` owner from MMC; one active forward AND reverse MMC–MDG bridge, matching IDs and selected MDG venue.
- MMC event must be `sales_open`; program may be `sales_open`, `promotion`, `operations` or `show_day`; MDG event and session must be `onsale`. Disagreement blocks automatic recommendations. This includes A0's Keşan case; the source is not repaired automatically.
- UTC MDG time must match the active MMC local seans and event/program date in `Europe/Istanbul`. Closed, malformed, future-sale-window and sold-out sessions cannot become offers.
- Read each session's own active child/adult catalogue; do not reuse the first session's prices. Require matching published variation parent, native purchasability/stock and exact decimal price. Respect MDG shared capacity, Woo maximum quantity and the parent product quantity limit.
- Allow only the canonical HTTPS event URL on the trusted current production or explicitly allowlisted staging origin, with no query, fragment, credentials, custom port or alternate host. The origin is selected by the server, never supplied by a request.
- Virtual family package availability needs the existing family plugin, per-event enablement and no active MDG multi-unit ticket collision. Price precedence follows native checkout: active MMC family price, else the event setting/default. The inactive İzmir 1,100 TL row therefore cannot override the event's 1,300 TL setting. Malformed or ambiguous family data disables family quotes, while valid singles remain usable.
- No source cache is added. Each `Service::quote` reads native data again. Optional `source_revision` detects price, stock, mapping or family-policy changes since selection. Offers expire after at most 60 seconds, earlier at session start/sale end. Offers are advisory; native checkout must revalidate and reserve capacity.

## Quote contract

All amounts are integer kuruş. Adults are 13+, children 3–12, infants 0–2. `Party::from_ages` validates boundaries without retaining dates of birth. A responsible adult is required. Counts and ages must be integers; do not round, coerce unknown ages or replace missing input with zero.

The 3 November policy allows a family package to cover up to two adults plus remaining child allowances (at most four people), including one adult + three children and two adults + one child. Every package still expands to the existing two adult + two child ticket components and consumes four capacity units, even if some allowances are unused. A third adult does not use a child allowance. The solver compares singles, family packages, extra tickets and multiple packages under component limits (20 each, at most 10 packages) and available capacity. Ties prefer fewer capacity units, then fewer packages. Alternatives include feasible combinations; more expensive combinations can have negative savings relative to the regular ticket total.

`attendance_count` includes infants; `capacity_units` follows paid ticket/virtual package rules, so they are deliberately different. `regular_total_minor` is a price comparison; `regular_option_available` separately reports whether that combination is feasible.

School invitations, campaign stacking, group sales and accessible-seat promises are not implemented. Unexpected party fields are rejected; do not silently ignore a discount request or treat it as standard admission. Use the existing human-support flow. No orders, payment data or customer messages are read or written.

## Tests and rollout

`php tests/web-assistant-core/run.php` runs fixture-backed behaviour checks, an independent recursive price-optimality oracle, age/price/stock/time/brand/state/URL failures, native-source integration with DB writes/cart initialization forbidden and preview permission/request contracts. GitHub Actions tests PHP 7.4 and 8.4, then the actual JS against the actual PHP markup in JSDOM (selection, nonce, invalidation, stale/late responses, XSS and safe links). JSDOM is test-only and is not shipped in the plugin. These are synthetic tests, not visual browser, live checkout or QR acceptance.

Before production, read back the installed dependencies, load only this package in staging, inspect `Service::catalogue()` including blocked records, explicitly test the chosen session/revision/quote and the native JS family row. Recheck native guest purchase and family allocation/4QR if a later UI or checkout integration is added. Do not change Keşan status, prices, Woo mappings or active snippets to make a test pass.

Rollback: deactivate/remove only this new package; if replacing 0.1.0 in staging, preserve and restore that exact ZIP. There are no migrations, settings, jobs, holds or data records to undo. Existing sales plugins and snippets remain active. Research and A0 evidence: PR #304. Core implementation: PR #305; staging UI continuation is a separate dependent PR.


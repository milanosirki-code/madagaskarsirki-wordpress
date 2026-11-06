# Madagaskar assistant — release review, 6 November 2026

The working result is a private administrator prototype: core0.2.1 plus Gemini intake0.1.2 on staging257930487. It is **not yet a public customer assistant**. Production255726534 has neither new plugin installed. The review archive contains the exact tested staging packages for reproducibility and rollback; it must not be uploaded as a production installer.

## Fresh production evidence

Production has eight future Madagaskar programs and seventeen MDG sessions marked onsale. Seven programs/fifteen sessions have aligned program/MMC/MDG sales statuses. This is a status-only candidate list, not a new proof of every variation price, capacity, purchase path or bidirectional mapping. Tire and Ödemiş are7November; İzmir8November; Çorlu11November; Çerkezköy12November; Kırklareli13November; Edirne and Keşan15November.

Keşan program29 remains venue_confirmed/MMC configured while MDG39 is onsale with two sessions. The assistant must exclude this conflict. No existing sales state or customer-facing native page was changed to force eligibility.

| Dependency | Production | Staging |
| --- | --- | --- |
| MMC | 1.3.49 | 1.3.50 |
| AI Abilities | 0.7.0 | 0.7.1 |
| Google provider | 1.2.1 | 1.2.0 |
| Family package | 1.1.3 | 1.1.3 |
| Assistant core | not installed | 0.2.1 active |
| Gemini intake | not installed | 0.1.2 active |

The production Google credential option is nonempty. Its value was never read or copied; no production generation was attempted. The earlier successful Gemini generation proves only the existing staging credential. Staging is an old copy with only three eligible İzmir sessions, so it cannot stand in for the full production catalogue. Do not upgrade unrelated production dependencies or copy staging settings/secrets as part of this release.

## Why these packages cannot simply be made public

Core preview routes, assets and browser origin validation are explicitly staging-only. `Service::source()` independently requires manage_woocommerce. Intake additionally requires manage_options, uses the staging origin and a WordPress administrator REST nonce, and scopes its cooldown to the authenticated user. Its fifty-request daily bound is a prototype limit. Removing a permission callback or changing only a hostname would neither produce a complete visitor experience nor give it suitable anonymous abuse controls. Never grant visitors administrator capabilities or expose generic AI Abilities.

## Concrete public-pilot implementation contract

1. Keep current administrator endpoints protected. Add a separate read-only application service over the canonical WordPressSource/Catalogue/Quote logic; publish only the customer fields needed for eligible offers and quotes. Do not publish internal blocked-program diagnostics or administrative actions. The controller cannot accept client prices, URLs, offer objects or product mappings.
2. Add a customer page initially limited to the current approved İzmir event10. Read eligibility and prices from production at request time, enforce the full program/bridge/status/time/native-product/family checks, and disable the pilot after its last valid session. If that event is no longer future when launch is ready, stop and select another verified event; do not extend dates or broaden scope silently.
3. Use separate off-by-default switches for the page and Gemini. Public catalogue/quote must remain functional with Gemini disabled or unavailable. Do not reuse administrator nonce/capability/session authentication for anonymous visitors. Use a server-issued opaque session, Secure/HttpOnly/SameSite cookie and a request token bound to that session; same-origin checks supplement it and do not substitute for authentication or rate limits.
4. Bound AI input to the existing short text contract and structured count/city schema; no customer-order lookup, user URL fetching, tools, free-form prices or invented stock promises. Keep the key only in the existing production server option. Use session and trusted-network-aware IP rate controls, an atomic global request ceiling, bounded concurrency/output size/time and a fail-closed response. Do not trust arbitrary X-Forwarded-For. Hash short-lived limiter identifiers and avoid storing text or customer data. The existing user-id cooldown is not the anonymous limiter.
5. Keep a visible transfer-to-Google disclosure and confirmation of parsed fields. Require explicit session choice and fresh native quote. Link only to the verified event's fixed ticket fragment; clearly require reselecting session/quantities because the native page defaults to its first session. No cart, order, hold, payment or reservation write belongs in the pilot assistant.
6. Cover guest permission boundaries, forged session/CSRF tokens, cross-origin calls, output validation, concurrency/caps, Gemini/network/HTML429 failures, no-model fallback, event/time/status conflicts and expired quote links. Test the public controller with an anonymous user; passing the current administrator fixture is insufficient.

## Review and controlled rollout sequence

First implement and test that visitor layer in an isolated branch/staging environment. Keep public access off while reviewing the exact patch and packages. Re-read production native dependencies and verify the current pilot event, bridge, variations, family policy and sales time immediately before rollout. A deploy must not overwrite WordPressSource dependencies or unrelated production plugins. Do not copy a staging API key.

The user has confirmed the mobile recommendation card works. The red-button destination is still unconfirmed: the user replied “Görmedim”, and the tool's unauthenticated HTML fetch sees the private login screen. Complete authenticated mobile/desktop button-to-native-ticket review, including selecting a non-first session and checking the family row. These checks do not require making a purchase. Existing payment/QR checks remain separate whenever a later change touches checkout.

After code review and applicable release gates, deploy the specific approved versions with both public switches off; verify health/admin preview without customer impact, then enable the single-event pilot under the agreed request ceiling. Record actual runtime versions, event scope, observed error/usage counters and rollback result. Disable Gemini independently if provider access or cost limits fail. Disable the pilot on wrong event/price, eligibility leakage or broken navigation. Restore only the new approved packages/flags; keep native ticket sales running.

## Archive contents and state

The review ZIP includes core0.2.1 and intake0.1.2 staging ZIPs, this review and a manifest with exact SHA256/source commits. It contains no credentials, environment files, customer records or production settings. Current code tests were already recorded as successful in the handoff/QA notes; this documentation step runs no new application tests and makes no deployment or model request.

Core code commit61daa738f2877d6e9198de5dbde1c3dff434b0ec; intake code commitdd53372766df34c3d19973dff91eb5d07e3dea64. Draft PR308 depends on305/306. Existing standalone diagnostic PR307 is not a production dependency. Status: **public visitor implementation and release gates remain open**; no production publication has happened.

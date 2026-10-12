# Private satisfaction dashboard — 12 October 2026

Live WordPress admin screen: Madagaskar > Müşteri Memnuniyeti.

Operational snippet 185 is active, administrator capability `manage_options`, admin-only execution. Source code in `docs/code-snippets/kommo-satisfaction-panel.php`; draft PR #229. Changed PHP Syntax CI run 38256868028 passed before activation.

Read-only Kommo lead listing for WooCommerce pipeline 14297695, up to four 250-record pages. Explicit partial coverage warning if further pages exist. Source failure hides totals. Ten-minute cache; administrator nonce-protected refresh. No customer contact retrieval, no message, order or financial writes.

Current result uses field 2460856 rather than historical category tags, so one CRM record is counted once. Started tag is an initiation count, not a delivered-message denominator. No artificial five-point score. Canonical MDG order_map joins determine city, venue and session; unmatched or multiple sessions are labeled rather than guessed.

Live smoke verification: 13 started records, 5 current responses: very satisfied 2, satisfied 1, follow-up required 1, free response 1. All displayed context mapped to Ankara and the recorded venue/session. Negative-result filter showed exactly one record; reset restored all 13. Source pagination completed without the partial warning. No customer names, phone numbers, order identifiers or tokens are archived here.

Dependencies: active snippet177 `mdg_kr_request`, current Kommo token helper, MDG_DB/order_map. Rollback: deactivate snippet185; the rest of CRM and ticket/checkout flows remain independent.

Live automation inventory: reminder configured at 15h before actual show time; no reminder delivery proof yet. Survey has historical delivered messages and five stored replies; duplicate guard and completed-session cleanup from prior fix retained, post-fix reply roundtrip still awaits real incoming reply. Payment/support/refund-completed bots have explicit guarded daily triggers. Refund-started/cancellation/change V2 templates remain in Meta review and are not activated.

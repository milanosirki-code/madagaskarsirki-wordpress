# Issue #98: canonical purchased-session datetime — deployed

The shared module is live in Madagaskar Bilet Yönetimi. The standalone Ticket Session Datetime Fix v1.0.0 is deactivated, retained for reversible rollback. Temporary admin verification snippet #118 is inactive.

## Resolution and ownership

Tickera Bridge stores the Woo variation ID in ticket_type_id and does not supply order_item_id for the tested tickets. Normalize variation/parent IDs, then resolve a unique exact Woo order item. Resolve order_id + item_id through MDG order_map with matching event/session IDs; format UTC in Europe/Istanbul and preserve actual end_at. Purchased item/variation metadata remains the legacy fallback. Ambiguous items or invalid canonical mappings preserve existing ticket data.

All five original date filters now have exactly one canonical callback and zero standalone callbacks. Priorities and argument counts remain pre-generation 9/6 and data filters 20/4. Verified live namespaced Tickera Designer classes are preferred, with global compatibility retained. QR payload, payment, ticket metadata and check-in state are not changed by this module.

## Live evidence, 3 October 2026

Paid ticket4202 → order4194 → item540 → session124: 15 Ekim2026 17:30–18:30.
Paid ticket4069 → order4065 → item506 → session125: 15 Ekim2026 19:30–20:30.

Both actual PDFs were generated through the production tickera_ticket_designer_pre_generate filter chain after standalone deactivation. Rendered dates/times and printed-code/entry-QR agreement passed; embedded venue QR assets are present. Ticket metadata hash and order status stayed unchanged in both runs. No real payment, WhatsApp, refund or check-in action. Customer PDFs/QR codes are excluded from GitHub and Drive.

Before bootstrap SHA256: 991e3b78d8f9a9f2e36c3b689bd7c57c4175dafd9d4cade25f5095522abdc116
After bootstrap SHA256: 9863ef7feaab4096051471047cf4061ee89dc71f44deaa43e043dff1667bc23d
Canonical module SHA256: f2ed98b99b5d65e210f386e1b244a85190ff3e75c999e53d2de0496cd9379e82

Exact live 47-file baseline was captured before migration. Only bootstrap integration plus this new module were deployed; other captured files were preserved. Archives: docs/live-backups/2026-10-03/ticket-datetime/. Admin-only nonce/capability and hash-guarded deployment sources were archived before use.

Native PHP CI passed the complete MDG lint and 30 isolated regression checks, including Tickera variation normalization, unique/ambiguous item lookup, namespace compatibility, actual duration and ownership handoff. Anonymous final smoke: home, /sehirler/, /bilet-al/, /ankara/ and the actual Denizli event URL all HTTP200 without admin bar or PHP errors.

## Editor incident and recovery

The first WordPress editor save rejected duplicated PHP source. A subsequent editor save unexpectedly left the bootstrap empty. Its empty SHA256 was detected; the exact prior live bootstrap was immediately restored and verified. Deployment then used reviewed hash-guarded atomic file replacement, verified after hash and actual production PDF generation. The native editor was not used again.

## Rollback

Reactivate the retained standalone plugin; canonical registration yields on the next request. Restore the archived pre-migration bootstrap if reverting module loading is needed. Do not install older repository/plugin versions over live.

Refs #98; PR #110.
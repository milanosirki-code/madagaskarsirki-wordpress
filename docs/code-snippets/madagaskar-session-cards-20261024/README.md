# Madagaskar seans kartları

Native Code Snippets frontend UI bridge. Hook: wp_footer priority 99; scope front-end. Reads only MDG_Public_Tickets active events, MDG_Sessions by_event/local_parts/sales_closed_by_time and MDG_Public_Event live_url. Runs only homepage, exact ticket directory and public event pages. No customer/cart/order data; JSON exposes allowlisted public event/session metadata only.

Homepage and /bilet-al/ cards become exact canonical session links. Existing event picker uses its existing button click handler, preserving its prices/quantities/cart payload. Selected label and strong clock cards are added. Native business rule is preserved: sales close at session start (amber non-link); after end gray disabled. Unavailable sessions cannot be selected through new links. On stale open pages, capture guard prevents an expired selected session submitting; server validation is unchanged.

Tests: node test-mdg-session-cards.cjs. Local PHP CLI unavailable; native plugin activation check and live scoped route verification required before claiming release. Smoke: homepage link -> exact matching event picker, /bilet-al/ equivalent link, time summary/price calc, no cart submission. PHP source guard checks DateTime inputs and emits JSON_HEX_* escaped data. No HTML/content filters, no payment hooks.

Rollout: native Code Snippets API create inactive, activate via owning plugin API, verify all three public views. Rollback: deactivate this one new snippet through the same native API. Existing snippets/plugins unchanged.

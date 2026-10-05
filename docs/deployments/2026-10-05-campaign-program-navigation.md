# Campaign program navigation — 2026-10-05

Snippet125 updated, active=true/code_error=null. Campaign Sales menu registered under existing Madagaskar mmc-dashboard, not WooCommerce. MMC program screens display a link retaining program_id where selected; overview has program/code selectors. Existing Woo order administration unchanged.

Scope uses only MMC_MDG_Bridge_Service::bridge_for_program (read-only) then MDG sessions. No ensure/auto_link/status or initialization service invoked. Candidate SQL requires a campaign item variation mapped into selected event's sessions; PHP grouping also excludes unrelated sessions in mixed orders. Missing bridge fails closed. Program_id retained in pagination.

PHP lint and 13 regression checks passed. Live SELECT verified program10→event12. Live authorized GET program10 returned order4533; program13 returned zero and excluded4533; missing999999 returned409. Global bms overview still returned one order. No ticket/order/payment/bridge/registry data writes. City-scoped public campaign codes unchanged.

PR139 stacked on138. Rollback snippet125 to PR138 source. Browser admin screenshot was not captured; REST exercised same filtered query/render path. Program buttons use scoped admin_notices rather than editing MMC production plugin.

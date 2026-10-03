# Issue #98: staged canonical datetime module

This proposal is **not deployed**. Files under docs/code-snippets are reviewable staging source, not a new active WordPress snippet.

## Problem reproduced
A controlled fixture for an order item purchased at 19:30, with generic event_time=17:30, returns 17:30 through the exact standalone v1.0.0. Generic ticket data is scanned before the order item. This is a code regression reproduction, not evidence that a particular live customer's PDF is wrong.

## Canonical module
Port all five current hooks from exact live standalone SHA256 9c8449c59915ef7e171c0c1d043944e10548044b41c5f5316a934531fea6acce into MDG_Ticket_Session_Datetime. Existing callbacks, priorities 9/20, accepted arguments, PDF-generator flow and corrected fields are retained. Before/after source assessment is archived in PR #107.

Resolution order: exact MDG order_id + order_item_id -> order_map -> session, requiring matching event IDs; then legacy exact WooCommerce order item / purchased variation before generic event fields. Multiple canonical session matches preserve existing data; invalid UTC dates/durations preserve existing data. MDG dates convert from UTC to Europe/Istanbul, preserving actual end_at; absent end_at retains the old +1 hour fallback. Exact variation matching avoids taking the first item that merely shares a parent product. Legacy parser itself is retained.

Public resolve_order_item() and format_utc_session() are shared entry points for later PDF and session-display consumers. QR/location payloads and checkout/payment flows are untouched.

## Ownership / deployment sequence
1. Capture and reconcile the **complete** live Madagaskar Bilet Yönetimi plugin into its canonical repository directory. It is currently absent from this repository; a bootstrap alone is not a complete deployable plugin.
2. Add this reviewed module as includes/class-mdg-ticket-session-datetime.php, and require it from the exact live bootstrap. Register MDG_Ticket_Session_Datetime::hooks on plugins_loaded priority 20.
3. With standalone active, canonical hooks() returns without registering anything; the unique working hotfix remains owner. No duplicate hooks or function redeclaration.
4. On staging/canary, deactivate standalone and verify canonical ownership: five filters only, pre-generate priority 9/6 arguments and four data filters priority 20/4 arguments.
5. Generate actual existing two-session ticket PDFs and inspect rendered date/time, QR/link and template output; compare against exact purchased order-item/session mapping. Unit PDF-generator fixture is **not** a real PDF smoke test.
6. Only after that canary passes, deactivate standalone on live and repeat read-only PDF/session checks. Do not create orders, charge cards, send WhatsApp or change sales mappings for this validation.
7. Rollback: reactivate standalone. Canonical hook registration automatically yields to its existing callback on the next request.

## Validation
22 isolated checks passed with PHP 8.5 WASM locally: hook counts/priorities, mapped 19:30 fields, actual duration, legacy order-item precedence, second variation selection, ambiguous/invalid mapping handling, Turkey date rollover, PDF-generator input, existing pre-generated response and standalone ownership. Native PHP CI runs the same suite plus lint. No source has been installed on production; Issue #98 stays open for complete canonical capture and actual PDF canary.

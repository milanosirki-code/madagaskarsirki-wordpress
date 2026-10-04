# Issue #114 — collected gross revenue
Date: 2026-10-04. Branch: codex/fix-gross-revenue-114. PR: [118](https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/118). Base: main 8a6659d. Audit PR111 is unchanged.

## Definitions and call chain
| Term | Existing source / definition | Reporting use |
| --- | --- | --- |
| gross revenue | MMC_Sales_Service::summary(): previously unfiltered SUM(gross_amount) on mmc_sales_ledger WHERE event_id=%d | madagaskar/mmc-sales-summary -> mdg_ai_mmc_sales_summary; MMC_Sales_Admin::render (mmc-sales); MMC_Dashboard_Service::program_row -> sales |
| net revenue | SUM(net_amount); upsert_ledger_item uses native WC_Order::get_date_paid() and max(0, line gross - line refund) | Net Ciro card, session_summary revenue, dashboard program sales; dashboard overview uses date/status-scoped SUM(net_amount), finance/reporting net totals |
| paid revenue | No separate field in this summary. Historical collection evidenced by ledger paid_at copied from WC_Order::get_date_paid() | Corrected gross is original collected mapped line amount before refunds |
| refunded revenue | summary.refunded_amount = SUM(refunded_amount). Item refund via get_total_refunded_for_item + get_tax_refunded_for_item, absolute amounts | İade card; gross remains original amount, net subtracts refund |
| order total | Native WC_Order::get_total() is full order total (includes order-level fees/shipping/etc.); not the amount summed by this service | Not a new summary metric; MMC uses mapped Woo line items only |
| sales amount | Context-specific UI label: this MMC sales UI and session summary display net_amount/net_revenue | Not interchangeable with all order totals or nominal value |
| nominal order value | Existing ledger.gross_amount = WC_Order_Item_Product::get_total() + get_total_tax(): mapped line total after discounts, tax included, independent of payment | Now separate summary.nominal_order_value = old unfiltered total; stored raw rows unchanged |
| failed order value | SUM(gross_amount) for failed ledger rows; no collection implied | Four failed orders in event9 = 5,000 TRY; paid_at absent, net=0, capacity=0 |

Source paths:
- wp-content/plugins/madagaskar-management-center/includes/class-mmc-sales-service.php: hooks(), sync_order(), upsert_ledger_item(), summary(), session_summary().
- wp-content/plugins/madagaskar-management-center/includes/class-mmc-sales-admin.php: page()/render(); menu mmc-sales.
- wp-content/plugins/madagaskar-management-center/includes/class-mmc-dashboard-service.php: overview(), program_row(), finance_readonly().
- wp-content/plugins/madagaskar-ai-abilities/modules/mmc-sales-ledger.php: mdg_ai_mmc_sales_summary delegates directly to MMC_Sales_Service::summary; registration madagaskar/mmc-sales-summary.
- AI fallback mdg_ai_mmc_sales_upsert_ledger_item uses the same native paid-date/line-total/refund mapping; unchanged.
- No ability/UI-specific gross arithmetic is added. Existing common service supplies both. Dashboard overview remains a distinct daily NET metric.

## Root cause
The ledger intentionally stores attempted line amounts even for unpaid orders. Summary confused that nominal field with collected gross by summing every row. This is a calculation/semantic defect, not merely a caption defect. It is not a sum of full WooCommerce order totals and is not a missing status filter in the order importer: keeping unpaid nominal rows is intentional.

Hooks woocommerce_payment_complete (20), woocommerce_order_status_changed (20), woocommerce_order_refunded (20) remain unchanged. Existing upsert key is channel=woocommerce + external_order_item_id; duplicate callbacks update a row instead of adding gross twice.

## Corrected rule
Collected gross = SUM of original mapped gross_amount only when paid_at IS NOT NULL and order_status NOT IN (failed,cancelled,pending,checkout-draft). Null paid date excludes unpaid/on-hold orders. Recorded paid-date history is preserved across refunded status; no completed-only condition and no transaction ID requirement. Nominal remains separately available.

WooCommerce native WC_Order::is_paid() is a current paid-status/filter check (normally processing/completed). It becomes false for refunded, so using it alone would erase collected original gross after a full refund. Native get_date_paid() is already captured in this ledger and is the existing historical payment evidence for net/capacity. Payment gateway name and transaction ID alone do not establish collection.

Fresh HPOS read joined wc_orders -> wc_order_operational_data:
- 39 event9 processing PayTR orders: all 39 native paid dates present; all 39 transaction IDs empty.
- Four failed PayTR orders: all dates absent; all transaction IDs empty.
Thus valid PayTR collections cannot require a transaction ID. This is source/record verification, not new PayTR sandbox or payment execution.

## Fresh before evidence
Read-only live ability event9 at approximately 12:28–12:29 UTC:
- 43 total orders, 107 tickets/capacity units.
- Gross 47,000 TRY; net 42,000 TRY; refunds 0; failed count4.
Read-only ledger grouping: processing39 / nominal42,000 / paid42,000 / net42,000 / capacity107 / 67 paid rows; failed4 / nominal5,000 / paid0 / net0 / capacity0.
Same captured ledger with corrected calculation: 42,000 TRY, a 5,000 TRY reduction. This is the projected result before deployment, not a claimed live after value. Orders can continue changing; deployment snapshot and post-deploy read must be reported separately.

## Tests
Actual service and actual summary SQL execute against isolated in-memory SQLite on PHP8.4. No gateway/WordPress/customer writes.
79 assertions PASS:
- paid processing/completed with empty transaction IDs included;
- failed/pending/cancelled/on-hold/checkout-draft without paid dates excluded;
- processing without recorded payment date excluded (matches existing ledger semantics);
- failed/cancelled/pending even with historical paid dates excluded from collected gross while existing net/capacity behavior is left untouched;
- partial refund preserves original gross, decreases net; full refund preserves gross, net/capacity0;
- mixed refunded/failed and empty event;
- repeated callback/status cycles preserve one item row and do not double-count;
- family_2_2 is 2 adults+2 children, capacity_units4; unchanged actual upsert math checked.

On commit477e9e:
- MMC Collected Gross Contract [37202738349](https://github.com/milanosirki-code/madagaskarsirki-wordpress/actions/runs/37202738349): PASS, 79 assertions.
- Changed PHP Syntax [37202738345](https://github.com/milanosirki-code/madagaskarsirki-wordpress/actions/runs/37202738345): PASS.
- Plugins CI [37202738425](https://github.com/milanosirki-code/madagaskarsirki-wordpress/actions/runs/37202738425): PASS.
- Production Source Archive37202738265 and MMC live reconciliation37202738275: PASS.
Local PHP is unavailable and the execution environment proxy is unreachable; tests ran in GitHub Actions, not claimed as local PHP execution.

## Deployment and rollback
Only class-mmc-sales-service.php may change. Original Git blob1af29700f0281f64c1d490fc7398b0408b812684; original SHA2568af41d4457cc8fe911d63124bd6adfc0905f5096134b0d3e31580ef349834a77. Corrected Git blob8d635658d0feebc5d63a2ba1d937eeec6cd2dda7.
Temporary deployment helper docs/diagnostics/issue114-controlled-deploy.php is not production autoloaded. If used via existing passive diagnostic119, first snapshot its full original body/metadata, syntax-check helper in CI, then require exact live source hash. Atomic single-file replacement checks resulting bytes against tested Git blob. Restore119 byte-for-byte/passive afterwards.
Rollback uses the reverse exact replacement only when source still matches corrected hash; expected restored original blob is checked. No ledger/order/ticket rollback or resync required. No version-wide MMC/AI/family upload.

Deployment status: pending controlled execution and post-deploy evidence.
Scope exclusions: #112/#115, PayTR sandbox, AI0.7.0, family1.1.3, ledger redesign, legacy order cleanup and other menus.

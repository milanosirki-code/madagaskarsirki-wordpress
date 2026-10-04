# Codex Project State — Madagaskar Sirki
Updated: 2026-10-04, Stage6. Previous full historical state remains in [audit branch / PR111](https://github.com/milanosirki-code/madagaskarsirki-wordpress/blob/codex/current-system-audit-20261003/docs/CODEX_PROJECT_STATE.md). That audit PR was not modified by this production fix. This state file did not exist on main8a6659d; the current task creates the Stage6 canonical update without importing unrelated audit sources.

## Issue #114 — gross revenue semantics
- Root cause: wp-content/plugins/madagaskar-management-center/includes/class-mmc-sales-service.php, MMC_Sales_Service::summary(), unfiltered SUM(gross_amount) from mmc_sales_ledger WHERE event_id=%d. Ledger gross_amount stores nominal mapped line total including unpaid attempts.
- Affected metric: summary.gross_revenue consumed by mdg_ai_mmc_sales_summary / madagaskar/mmc-sales-summary, MMC_Sales_Admin::render and MMC_Dashboard_Service::program_row.
- Old semantics: all nominal mapped line totals, independent of payment.
- Corrected semantics: original tax-inclusive mapped line amount with native WooCommerce paid_at evidence, excluding failed/cancelled/pending/checkout-draft. Refunded paid original amounts stay gross; existing refund-adjusted net stays unchanged.
- Separate nominal_order_value retains the old all-row total. No ledger/order backfill, deletion, mapping redesign or synchronization write.
- Payment evidence: live39 processing PayTR orders have native is_paid=true and date paid, but empty transaction IDs. Failed4 have neither payment signal. No transaction-ID requirement; no completed-only gate.
- Branch: codex/fix-gross-revenue-114.
- Production patch commit:374470e84673882f5e74f1c89067c6efb42e750d. Tests/workflow commit477e9e46eec6f386aecce8147afaac69fdde9413. CI-validated deployment helper commit efacaa460051233060fdff15adb01651f8925e14. Documentation head is discoverable in this file's GitHub history (no self-referential SHA).
- PR:[118](https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/118), separate from audit111.
- Tests:79 assertions actual service/SQL with synthetic SQLite on PHP8.4; processing/completed, failed/pending/cancelled/on-hold/draft, full/partial refunds, mixed orders, callback/status retry idempotency, family_2_2 unit4, empty event.
- efacaa4 CI: Plugins37202923577, ChangedPHP37202923590, contract37202923567, archive37202923565, reconciliation37202923653 all PASS.
- Production deployment:2026-10-04T12:42:52Z, atomic replacement of sales service file only.
- Original Git blob1af29700f0281f64c1d490fc7398b0408b812684 / SHA2568af41d4457cc8fe911d63124bd6adfc0905f5096134b0d3e31580ef349834a77; fresh pre-write verified.
- Corrected live Git blob8d635658d0feebc5d63a2ba1d937eeec6cd2dda7 / SHA2563fb93c6e28489b30fbd837181aef87217d65b0cd5a6997524f1fb278e45456df; atomic readback and next-request hash verified.
- Event9 before47,000 TRY gross -> after42,000 TRY gross; failed nominal5,000 excluded, preserved in nominal47,000. Net42,000, capacity107, ticket107, order43, refund0 unchanged.
- Dashboard and actual read-only ability both42,000; sales UI server-render successful, no warning/fatal. Final independent ability after removing helper returned same.
- Final health16/16, code_error0, snippets120/43active; temporary119 body restored byte-exact/passive/clean. No permanent snippet change.
- family_2_2=2adult+2child, capacity_units4 preserved and regression checked.
- MMC stays1.3.47. AI0.7.0/family1.1.3 not deployed. #112/#115/payment sandbox/other menus untouched.
- Issue114 eligible for completed closure only after these final live checks; GitHub issue record provides authoritative current state.
- Rollback:not required; guarded reverse replacement restores original sales file; no customer/ledger data rollback.
- Remaining risk: historical missing/stale native paid-date evidence not repaired; no browser visual test/new gateway test. PR118 review/merge remains; only deployed sales file is ahead of main until merge.
- Evidence:[ISSUE_114_GROSS_REVENUE.md](ISSUE_114_GROSS_REVENUE.md), [ISSUE_114_DEPLOYMENT_EVIDENCE.json](ISSUE_114_DEPLOYMENT_EVIDENCE.json).

Next:Issue #115 read-only integrity profile metadata side effect.

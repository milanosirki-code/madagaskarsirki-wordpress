# Finance refund reconciliation — 2026-10-08

User confirmed actual WooCommerce refund 13,250 TL. Biletinial recorded refund 6,250 TL. Refund cash total 19,500 TL; Kırıkkale venue operating cost 5,500 TL. Current Woo records contain 11 fully refunded orders historically paid 17,750 TL and refund objects 13,750 TL; 500 TL native/manual discrepancy and 4,500 TL gross/actual-return discrepancy remain unresolved and explicitly shown. They are not recognized as earned revenue or an additional expense.

## Calculation
Shared monthly/all-date program web calculator reads current Woo status, paid date, item refunds. Fully refunded orders yield no retained web income. Partial item refunds subtract only tax-exclusive item amount, matching MDG line_total basis. Missing order/item warns totals incomplete. No mutations.

Exact program-scoped Woo refund expense rows are return cash movements, excluded from operating costs because native net web income already accounts for refunds. Explicit reviewed Kırıkkale Biletinial return is separated as a cash movement too; its program income is currently zero. Unreviewed Biletinial returns elsewhere are not automatically reclassified. Rows stay in expense detail. Program/month report shows separate refund cash card; original record amounts unchanged. Future Biletinial refund cases require confirming whether manual income is gross or net before extending treatment.

Existing program-month marker preserved for compatibility with finance update patcher.

## Tests / deploy
PHP8.3 lint and finance tests pass; PHP7.4 GitHub contracts cover stale refunded status, partial refund across programs, missing source warnings, failed/pending/unpaid exclusion, clamp/cache/no mutation, original snapshot exclusion and confirmed 19,500 TL return vs 5,500 TL operating cost. Before deploy V5 three files match last deployed baseline. Rollback ZIP retained. No checkout/order/refund writes or real payment test. Verify post-deploy Kırıkkale web0, expense cost5500, result-5500, return19500; Yenimahalle unchanged; financial row fingerprints unchanged. Live deployment completed via WordPress ZIP replacement at approximately12:13 Europe/Istanbul. Source readback exactly matches tested candidate. Kırıkkale all-date web0/cost5500/result-5500/refund19500 verified. Monthly Kırıkkale operating expense0 because venue expense is recorded outside October (all-date summary retains5500). October totals web216375, other1116306, cost704801.60, result627879.40; reflects current orders including additional Denizli sales, not a frozen baseline. Yenimahalle remains349050 income /113844 cost /235206 result. Selected-field fingerprints unchanged: income45,total2016246.01,88231126490; expense134,total2190906.81,297099133801. 14 GitHub workflows pass for40184319e3d197073b09c229b2af35656e00855a. Screenshot finance-refunds-confirmed-20261008.jpg.

## Remaining
Bank/PayTR historical cash reconciliation is open. October fixed costs absent; general expenses need allocation rule. Meta campaign pilot starts9October; PR202 remains draft pending pilot.

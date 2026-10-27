# Google Ads → Finans V5

Status: implementation candidate, **not deployed or connected**. Live launch needs a production-approved Google Cloud OAuth project and a successful read-only account test. Existing Meta, checkout, PayTR and ticket code is unchanged.

## Runtime and data contract

Install `wp-content/plugins/mdg-google-ads-finance/` as an independent plugin. Requires PHP 7.4+, OpenSSL, MDG_DB, current Finans V5 records/payment/history tables, and InnoDB. The live site's canonical UUID validation/finance repair release must be preserved; do not replace live V5 files with older main-branch copies to install this plugin.

The plugin only calls Google's token endpoint and `googleAds:search` on the configured customer. It has no campaign mutation endpoint. OAuth scope is `adwords`; Google's scope is broader than reporting, so a dedicated Google identity with read-only Ads account access is preferred. The code does not use or modify existing Meta credentials.

On 2026-10-26 Google's migration guide states that developer tokens were sunset 2026-09-09 and production API access belongs to the OAuth Cloud project. Configure production API access in Cloud Console; do not ask for a developer token as a prerequisite. Reference: https://developers.google.com/google-ads/api/docs/api-policy/developer-token

Daily GAQL selects customer ID/currency, campaign ID/name, date, cost_micros, impressions and clicks. Currency must be TRY; other currencies fail closed. Account timezone determines today. Fetch all pages successfully before applying any writes. Full configured period (max 366 days) is reread each hour for historical revisions. After 366 days the job stops with an error; archive/reconciliation and a new explicitly reviewed period are required, not silent truncation.

Unique daily source: `(customer_id,campaign_id,metric_date)`. Immutable campaign→MDG public UUID maps resolve canonical event IDs at runtime. Program reassignment is rejected. Expense document reference hashes customer, campaign, date and UUID. No budgets are converted into expenses. Unmapped campaign days remain in a separate source ledger, with no cost in V5 until explicitly mapped. Partial API failure never zeros costs. A prior day missing from a successful complete query can become zero only when the campaign remains in the fetched inventory. If the campaign disappears, stop and investigate rather than silently deleting its expense.

Amounts use integer micro units, rounded to kuruş per daily row. Report sum-of-rounded-days and API aggregate may differ by daily rounding. Raw cost_micros is retained. New expense uses vendor `Google Ads API`, category `Reklam / Google Ads`, method `Ödenmedi`. Refresh only changes source-owned amount/identity fields, preserving payment method, notes, attachments and payment rows. A stored expense hash refuses unexplained source-row changes. Each changed expense and its source ledger commit together with before/after finance history, or roll back. Repeated identical imports produce no second expense/history entry. A batch may partially commit complete days; retry safely resumes those not committed.

## Connection and launch

1. Back up source files, plugin list, V5 expenses/payments/settlements/history, new Google tables and nonautoload options into the private project archive. Never put secrets or live financial snapshots in this public repo.
2. Test the plugin on staging **with sync disabled** and no production OAuth token copied there. Confirm migrations, capabilities, nonce rejection and rendering. Staging writes and live API calls have not been performed during candidate preparation.
3. Use a Web OAuth client in a Google Cloud project with Google Ads API production access. Register the exact callback displayed by the plugin, including its action query. Enter client ID/secret only in the WordPress administrator's HTTPS form. They are encrypted with AES-256-GCM using a WordPress-salt-derived key, stored autoload=false, never displayed. Salt rotation requires reconnecting. No token values belong in chat, logs, PRs or reports.
4. Click Google secure connect and have the user explicitly grant the new access. State is random, ten-minute, single-use and bound to the signed-in WordPress administrator. A refresh token is required for unattended execution.
5. Save exact customer, optional manager account, start date and explicit campaign→public UUID maps. Check manual-expense overlap first. Do not guess a campaign or UUID from the city alone. A mapping cannot be silently replaced; adding mappings requires a fresh test.
6. Run the read-only API test. Compare inventory, currency, timezone, date range, daily micro totals, known campaign names/IDs and V5 program identity against Google Ads UI. Today is provisional and reporting can lag. The test creates no expense. Its configuration fingerprint expires after 30 minutes.
7. Only after successful test, enable. First import is synchronous, then hourly WordPress cron. Configure a reliable host cron/WP cron runner if site traffic is insufficient. Verify the next automatic run through status and source/expense counts; do not equate a scheduled event with proven execution.
8. Repeat import and verify identical expense IDs/counts. Test late revisions, zero revisions, wrong UUID/account/currency, failed API page, audit failure and concurrent source edit. Check Meta source counts and signatures, sale health, checkout reachability, existing order/ticket/QR connections. Do not initiate real purchases or refunds solely for a smoke test.

## Bank and invoice reconciliation

Google API cost is not proof of payment. Use existing V5 native payment tracking on the Google expense: start at zero, then post only the verified bank/card allocation, with date/account/reference and reason. A monthly payment can cover several daily expenses; allocate it once and ensure allocation totals match the bank debit. Excess prepayments and refundable balances are separate balances, not extra ad expenses.

Invoice links require a media attachment, reference, exact source expense and the operator's document-check acknowledgement. A bank link additionally requires an existing posted V5 payment for the same expense and exact reference. Links themselves create neither payments nor expenses. VAT/fees/credits and invoice-date versus service-date differences must be reconciled separately from campaign cost; never make the invoice total a second advertising cost. The integration does not download billing documents or independently verify bank authenticity. Actual bank statements/Google invoices were not supplied or matched during candidate preparation.

## Rollback

Pause Google import and clear only `mdg_gads_finance_hourly`; preserve Meta's jobs. Deactivate only this plugin (or the temporary WPCode delivery, if used). This stops future execution while retaining source ledger, expenses, payment links and history. Do not delete data or restore the whole expenses table from an old snapshot.

For a data correction, compare each affected GADS expense to its last recorded after-image and its current payments/evidence. Restore only matching, unchanged rows from the private pre-change snapshot in a reviewed transaction with a new reason/history entry. A newly inserted cost should be reversed with a reasoned zero-cost correction after resolving any posted allocations; retain its source identity and audit trail. Never wipe a confirmed bank movement or another integration's recent work. Plugin deactivation is the available non-destructive code rollback; automated data reversal is deliberately not shipped.

## Verification limits

`php tests/google-ads-finance/contract.php` uses synthetic data and a transaction-aware DB double; it is not a real MySQL/WordPress integration test. CI checks PHP 7.4 and 8.3 and packages an installable ZIP. Live auth, staging UI, real MySQL locks, Google query compatibility and source cost verification must be checked before production enablement. Any failed or missing gate means **not live**.

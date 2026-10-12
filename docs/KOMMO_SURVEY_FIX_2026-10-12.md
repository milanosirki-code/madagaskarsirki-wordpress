# Satisfaction survey fix — 2026-10-12

## Live changes

Created date_time lead field Anket Gösteri Tarih ve Saat, ID 2460854. Updated active native Code Snippets 177 to populate it from the canonical MDG session for paid processing/completed orders. Retains past session time for seven days; excludes refunded/unpaid orders, missing or ambiguous mapping, cancellation and postponement. Closed/completed sales and unavailable ticket stock do not independently invalidate a completed-show survey. Event reminder field 2460832 and payment gating remain unchanged.

Updated bot 118553: requires WooCommerce pipeline 14297695, payment-success tag, nonempty survey date, processing stage 110416827 or won stage 142, and existing repeat-send guards. Existing approved satisfaction template 80796, response handling and error task remain unchanged.

New trigger 38144604: 36 hours after lead field 2460854, WooCommerce processing AND Closed-won stages. Applied to existing stage leads. Reopened saved trigger confirms both stages and time field. Original sales-pipeline trigger remains but cannot pass the bot's new WooCommerce pipeline condition; do not treat it as enabled survey coverage.

## Validation

All 13 CI workflows succeeded at commit 04badac9bd2e7e453d172523db6051b190f0891f, including PHP syntax, survey date retention/exclusion cases and payment reminder regressions. Snippet 177 active state confirmed. Saved bot source re-read confirms all new guards and template 80796. A live paid order's field read-back is 11/10/2026 16:00. No PII stored. Delivery to WhatsApp has not been verified; trigger readiness is verified.

## Rollback

Restore previous bot JSON and snippet source from preceding branch commit, disable new trigger 38144604. Leave the new field for audit. Do not deactivate the entire integration or alter payment/order state.

# Campaign sales report deployment — 2026-10-05

Independent Code Snippets ID125 activated. Read-back active=true, code_error=null. WooCommerce → Kampanya Satışları / admin.php?page=mdg-campaign-sales. Existing campaign snippet124 and payment flows were not changed.

PHP lint and 8 financial/quantity regressions passed. Authorized GET report rendered live order4533: bms, 1 adult, 1 free child, Denizli session99 at 2026-10-08 17:30, gross500TL, failed payment, net receipts0TL. sagliksendenizli filter returned zero orders and correct empty state. Anonymous request returned401 rest_forbidden. Report reads only; no order, payment, capacity, ticket or institution registry writes.

Order items are grouped by campaign code and session; ordinary lines excluded. Invoice-like gross/refund/net amounts include line tax and line-attributed refunds. Quantities are purchased quantities with separate refunded ticket count. Top total is explicitly current page only, max50 orders/page. It does not report attendance/check-ins. Woo order CRUD handles HPOS; common item tables queried with SELECT only. No customer birthdates or contact details shown.

Draft PR138 stacked on137. Rollback deactivate only snippet125. Browser screenshot of admin navigation was not captured; authorized report endpoint exercises the same rendering path.

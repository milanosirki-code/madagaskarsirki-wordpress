# Campaign ticketing deployment — 2026-10-05

Page 4520 now uses /kampanya/ and has no password. Old links redirect to the new URL while retaining the campaign code. Existing snippet 124 updated; read-back active=true, code_error=null. PR #136 is stacked on #135.

Real institution codes enable POST admin-post.php action mdg_campaign_checkout. TEST codes remain quote-only. The selected session uses existing adult/child Woo variations with separate paid child, free child and infant lines. MDG_Live_Sales handles shared capacity holds. PayTR settings and Tickera Bridge ticket generation hooks were not changed.

Cart manifests are server-signed. Prices, campaign eligibility, dates, session and quantities are revalidated before checkout. Removing paid adults, increasing free quantities, switching variation, invalid signature or disabling a code blocks checkout. Ordinary carts remain untouched; campaign rows reject extra coupon discounts. Birthdates are retained in Woo session only and are not copied into order/ticket metadata.

Validation:
- PHP lint, 79 regression checks and jsdom tests passed.
- An isolated anonymous cookie session, temporary institution code and synthetic birthdates reached /odeme/ with PayTR present.
- Store API GET confirmed persisted cart: 1 adult 500 TL, 1 child 250 TL, 2 free children 0 TL, total 750 TL.
- Checkout server bootstrap had campaign display metadata and did not contain raw birthdates.
- No order/payment was submitted. No ticket was created and no shared capacity hold was made by this cart test.
- Temporary QA code removed after tests; institution registry restored to empty.
- Legacy URL redirects to /kampanya/?kod=TEST-DENIZLI; normal /bilet-al/ returns 200 with no fatal output.
- Adult removal and quantity manipulation were validated in regression tests; block cart did not render an HTML removal link.
- Actual bank payment, QR PDF generation and physical Checkinera scanning were not newly tested. Accompaniment is recorded on order lines; no new automatic scanner accompaniment guard is installed.

Rollback: restore snippet 124 to PR #135. Campaign carts must then be reselected. Restore page slug if needed; no products, ticket mappings or payment credentials were modified.

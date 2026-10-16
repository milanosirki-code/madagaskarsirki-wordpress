# Milano live site audit — 16 October 2026

Reviewed 17 published pages and eight articles present at the final inventory. Blog content was being published concurrently; articles were read and included without recreating them.

## Published repairs
Eight pages (#5,14,22,33,34,35,115,231), 21 scoped source replacements: obsolete sales-not-open wording replaced with current four-city sales status and Dünya Organizasyon shared checkout disclosure; privacy/KVKK factual ticket processing descriptions updated without changing legal rights; cookie panel instructions reflect verified existing panel; SSS city selection and Yalova detail destination corrected; Yalova’de corrected to Yalova’da.
Five new articles (#262,265,267,271,272), seven scoped replacements: remove unsupported 45-minute door opening guarantee, use established 30-minute arrival recommendation; clarify email delivery; correct spelling; replace misleading “this weekend”/“last week of October” with fixed October dates; avoid an unverified automatic-sales-closing promise.
Default assembler//single rendered article primary title as H2, leaving no H1. Only the core/post-title level was set to 1; layout, typography and all other template blocks retained. All eight article URLs subsequently returned 200 with one H1.

## Verification
Initial 16 pages: all HTTP200; 47 discovered page/image destinations all200; internal anchor targets present. Sixteen page titles/descriptions reviewed, exactly one H1 per page. Later blog index and eight articles read; 35 internal article link targets all200. Existing posters/gallery images load.
Actual live pages framed at 390px (effective375px after scrollbar): all17 pages plus two initial articles have scrollWidth375=clientWidth375, with no broken loaded images. Temporary unpublished QA draft253 moved back to recoverable trash.
Four city tabs show correct 2/2/2/3 session forms and canonical event IDs. Central catalog returns all nine Milano sessions with correct prices245/490/1100 and capacity snapshot500.
Native family selection: Derince24Oct14:00 → central shared-payment landing, event26/session177,4people,1100TRY. Stopped before continuation; no cart/order/charge created.
SSS disclosure expands. Main-menu Blog submenu opens. Complianz manage-consent opens, category controls displayed, Reject dismisses. No tracking settings changed and no comprehensive tracking-consent compliance claim.
All 28 content replacements read back successfully. No product/order/payment/capacity settings changed. Paid callback/refund/concurrency/live QR gaps from earlier work remain unverified.

## Rollback and source
pages-before.json / pages-after.json; posts-before.json / posts-after.json; single-template-before.json / single-template-after.html; content-patches.json and blog-patches.json. Native WordPress revisions retained. Branch fix/milano-site-audit-20261016 derives from feat/milano-professional-pages-20261015.
Public link checks were read-only; no email, WhatsApp message or purchase was sent. Browser proof screenshot captured; no screenshot was saved to Library because it was not requested as a deliverable.

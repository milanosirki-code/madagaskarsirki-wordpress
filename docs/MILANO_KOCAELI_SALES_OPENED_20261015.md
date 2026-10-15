# Milano Kocaeli sales opened — 15 October 2026

## Authorization
At 20:32:15 Europe/Istanbul the user replied “evet yap” to the explicit question whether to open the three events on Yalova's existing production infrastructure without waiting for the remaining real payment/capacity tests. This supersedes the former pre-sale test prerequisite. Unverified tests remain false; they are not represented as passed.

## Actual production operations
Used native MDG settings readiness screen and its existing nonced “Etkinliği Canlı Yayına Al” operation once each: Karamürsel28, Darıca27, Derince26. All readiness checks green. Accepted each native confirmation. No direct SQL writes, re-created sessions/products, PayTR changes, or global launch-gate toggle.
Seven sessions176–182 now onsale, each capacity500, held0/sold0 at verification. Three Tickera events and existing seven Woo parents published through the native producer.
The central handoff guard additionally requires parent _allow_ticket_checkout=yes. Read-only comparison showed Yalova6318=yes versus seven Kocaeli parents=no. Updated ONLY6531,6534,6539,6542,6547,6550,6553 through native Woo REST metadata to match Yalova. _available_checkins_per_ticket remains1. This vendor flag is also Tickera's check-out setting; live scan/re-entry semantics were not retested in this continuation.

Public central /milano-handoff/v1/catalog now returns nine Milano rows: existing Yalova2 unchanged plus Kocaeli7. All new rows show child245/adult490/family1100, correct local dates/times, capacity500, preview=false. This validates server-side product/type/brand ownership checks, not paid callback behavior.

## Customer flow verified
Milano /bilet-al/ renders nine forms with correct three price options, seven new seans and Yalova unchanged. Tested Karamürsel21Oct17:30 family1 through native form:
- Common payment landing names Milano/Karamürsel, correct date/time,4persons,1100TRY.
- Native Continue successfully reaches central /odeme/.
- Checkout shows two child245 quantities2, two adult490 quantities2; subtotal1470, family discount370, total1100TRY, four attendee forms, PayTR available.
- No order submission/card charge. Removed only these test cart rows; UI explicitly reports empty cart.
Public Milano pages125/126/127 old precautionary sales text replaced with “Biletler satışta” and existing /bilet-al/ link. All three reopened and verified.

## Public brand isolation repair
Observed custom legacy shortcode [ms_sehirler_sayfasi_v2] bypassing existing handoff public-city filters and displaying Kocaeli cards under Madagaskar. Existing Code Snippets snippet30 (“MS Dinamik Şehirler ve Biletler V1”) queried MDG rows directly.
Backed up original full code in GitHub. Added exactly one SQL read predicate: AND e.id NOT IN (26,27,28), preserving all other rows, including existing Yalova listing. Saved native editor; snippet remains active and reports updated. Reloaded public Madagaskar /sehirler/: all three Kocaeli cards absent; existing Didim/Efeler/Manisa/Yalova/Sakarya/İzmir remain.
No payment/authentication/signature logic changed. No other event/order/ticket/capacity altered.
Files: integrations/milano/live-snippet30-kocaeli-brand-exclusion.php and docs/milano-kocaeli-staging-20261015/backups/live-snippet30-before-brand-filter.php.txt.
Local php executable unavailable, so local php -l not run. Native snippet save and actual frontend execution verified without errors.

## Live identity
Karamürsel MMC16 / MDG28 / sessions181,182 / parents6531,6534 / TC6529 / Designer31.
Darıca MMC17 / MDG27 / sessions179,180 / parents6539,6542 / TC6537 / Designer32.
Derince MMC18 / MDG26 / sessions176,177,178 / parents6547,6550,6553 / TC6545 / Designer33.
Full variation/type IDs retained in integrations/milano/plans/kocaeli-live-mapping-20261015.json.
No plugin PHP file changed in this continuation; native snippet30 changed and content/metadata/status records updated.

## Remaining evidence limits
No real PayTR payment, signed duplicate-callback, paid four-unit capacity commit/refund/concurrency, or production PDF/check-in test was performed. Prior staging four-PDF/QR administrative test remains separately documented. Mobile viewport was not independently exercised; desktop screenshot reviewed, inputs/buttons render without overlap.
Production sales are OPEN by explicit user authorization to proceed on existing Yalova method despite those unverified tests. This is an accurate operational status, not an all-tests-passed assertion.

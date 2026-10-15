# Milano Kocaeli staging continuation — 15 October 2026

Status: CONFIGURATION COMPLETED; FAMILY CART PASSED; PAYMENT / FOUR QR GATE BLOCKED. Public sales remain closed. No production code or Kocaeli sales records deployed during this continuation.

## Scope and actual changes

Only staging MDG events25/26/27 and their existing seven sessions, seven Woo variable parents, fourteen variations, Tickera drafts6102/6104/6106 and Kocaeli venue records196/197/198 were configured. No duplicate programs, sessions, products or Tickera events were created. Existing Yalova/Madagaskar orders, capacities, payment settings and vendor PayTR signature/callback code were not changed.

The existing sales adopter proposed Darıca products for Karamürsel because it ranked time/price matches. The wrong proposal was not saved. It now requires recognized brand, district token, local calendar date, existing ownership agreement and a unique complete candidate. Ambiguity fails closed.

Content-only draft saves previously deleted/rebuilt session structures. They now preserve semantically unchanged session/type IDs and reject structural changes when mappings/reservations/sales exist.

A native, nonce- and permission-protected identity-finalization action validates all existing draft mappings, no holds/sales and all metadata conflicts before transactional writes. It fills managed/adopted identity and canonical production keys without recreating records. Adopted objects are excluded from generated-object cleanup.

Milano QR binding previously fell back to Madagaskar template6. Binding now uses the brand-aware plan template and verifies the template title is Milano. On staging the approved base is28; dedicated bound templates31/32/33 were created. No issued admission QR was generated or validated.

## Confirmed staging IDs

|City / local session|MDG event/session|Woo parent|Child variation/type|Adult variation/type|Tickera event|
|---|---|---|---|---|---|
|Karamürsel 21Oct17:30|25/152|6081|6082/346|6083/347|6102|
|Karamürsel 21Oct19:30|25/153|6084|6085/348|6086/349|6102|
|Darıca 23Oct17:30|26/154|6087|6088/350|6089/351|6104|
|Darıca 23Oct19:30|26/155|6090|6091/352|6092/353|6104|
|Derince 24Oct12:00|27/156|6093|6094/354|6095/355|6106|
|Derince 24Oct14:00|27/157|6096|6097/356|6098/357|6106|
|Derince 24Oct16:00|27/158|6099|6100/358|6101/359|6106|

MMC programs15/16/17 remain Milano. All seven sessions: capacity500, held0, sold0 on final readback. Parents remain draft/hidden, MDG and Tickera events draft. Child245/adult490/family1100 TRY. Local time uses Europe/Istanbul; do not treat stored UTC-like times as local.

|MDG event|Production key|Bound template|Poster|Maps QR|
|---|---|---|---|---|
|25|MDG-E25-6837c876393d|31|6109|6112|
|26|MDG-E26-b6944678a08f|32|6110|6113|
|27|MDG-E27-9efba8606c2e|33|6111|6114|

Posters imported from existing approved Milano media122/123/124 after duplicate check. Descriptions, rules, price/age text and hero set in native event editor. Venue map URLs are Google Maps address-search URLs derived from user-approved full addresses, not independently verified place pins. Kocaeli maps QR attachments are location links, not admission tickets.

## Code and validation

Branch fix/milano-sales-adoption-identity-20261015; draft PR266:
https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/266

Four modified MDG plugin files: class-mdg-sales-adopter.php, class-mdg-sessions.php, class-mdg-ticket-template-qr-binder.php, class-mdg-new-event-draft-producer.php under wp-content/plugins/madagaskar-bilet-yonetimi/includes/.

Staging edits were saved through the authenticated native plugin editor and exact saved source verified. Producer deployment preserved the staging-only last-session-end-date correction; no blind overwrite from repository. Brand-aware production-plan template28 is an existing staging prerequisite; this PR alone is not a complete live deployment bundle. Earlier PR235/265 remain unmerged.

GitHub Actions run38704942292 / job116321914137 succeeded for exact final code commit a337ab95cd7d7f519d6207fb50ec9e3befe9aea4. Four PHP syntax checks and four fixture groups passed: adoption27, structure14, template4, identity35 distinct assertions (identity fixture also reruns adoption27). These are code-level fixtures, not payment or QR end-to-end evidence.

Actual native staging family cart: one family selection added two child and two adult units. Woo showed490+980=1470 TRY, family discount370, total1100. Draft sale guard displayed “Bu etkinligin bilet satisi henuz acilmadi.” No payment/checkout bypass attempted. Both cart rows were removed and empty-cart heading verified. Final session readback remained500/0/0.

Native configuration readiness showed content, venue, mapping and ticket-template ready for all three; PayTR remained missing. Readiness is not proof of paid ticket issuance.

## Remaining mandatory launch gates

Staging has no configured authorized PayTR test gateway. Obtain/configure an authorized test flow while preserving live payment settings and callback signature security. Do not use an unannounced real bank card. Do not open public sales to bypass the draft guard.

Still required: successful adult/child/family paid orders; four distinct valid admission QR for family and correct event/date/session PDF; repeated callback idempotency; failed payment; cancellation/refund; concurrent capacity consumption; mixed-session prevention; one order counted once across ledgers; real QR check-in/replay; fee and0–2 disclosure; central server ownership validation; full mobile/desktop routing and Milano branding.

Observed brand cleanup pending: preview discovery heading still says Madagaskar, shared staging cart branding is Madagaskar; no final brand/redirect acceptance claimed. Parent product cart imagery needs review. No live ID equivalence assumed.

Next production step is blocked until those gates pass, followed by separate read-only live duplicate/ownership inventory, narrowly controlled deployment and verified Milano-only sale. No live launch date or production IDs are reported.

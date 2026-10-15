# Kocaeli live draft configuration — 15 October 2026 after16:13 Europe/Istanbul

The user explicitly confirmed the phone/direct venue-and-date attestation. Native confirmation was then completed for existing venues196/197/198; no renewed approval needed. Programs16/17/18 are venue_confirmed. The previous venue-review blocker is RESOLVED.

Applied Yalova's existing central architecture; no separate production PayTR merchant or Milano payment gateway was created. No production plugin source, callback signature, global Milano launch option or existing Yalova/Madagaskar order was changed.

## Work actually performed

Native MMC event records16/17/18 initialized by venue confirmation. Their generic Madagaskar titles were corrected to Milano before MDG transfer. Each of seven local sessions was created once in MMC at approved times, capacity500. Child245/adult490 saved; family1100/capacity4 retained. Native MDG transfer created Derince26, Darıca27, Karamürsel28 without duplicating existing live records.

The importer ignored MMC display title and generated generic Madagaskar MDG titles; corrected those three through native MDG draft editor. Filled descriptions, age/rules,60-minute duration,30-minute doors, free seating and correct imported approved posters6523/6524/6525. These are existing Milano frontend assets copied into central media after absence check, not regenerated posters.

Added standard Google Maps address-search URLs to existing dedicated Kocaeli venue masters196/197/198 using user-approved full addresses and native QR generator. Location QR media6526/6527/6528. These URLs are address searches, not independently validated exact place pins. Only those3 venue masters changed; old Karamürsel152/Darıca59/Derince60 were not edited. Refreshed only new Kocaeli event snapshots.

Before draft sales objects existed, native MDG content saves rebuilt unmapped draft session/type rows. Final live session IDs are181/182 Karamürsel,179/180 Darıca,176/177/178 Derince. Do not use earlier intermediate live session IDs or staging IDs. No content save after mappings was attempted.

A wrong-editor navigation was detected before save: Derince copy was in Darıca edit27 form. No wrong content persisted. Auto-review first rejected leaving unsaved text; preserved the complete copy in integrations/milano/plans/derince-live-copy-20261015.json, then navigated safely to correct edit26 and applied it. Final DB summaries/hero IDs all match city/date correctly.

All three native production plans were DRY-RUN HAZIR (correct Milano base designer28 and district SKU namespace). Invoked native producer once per event, only after duplicate check. Produced3 Tickera drafts,7 variable parents,14 variations and3 event-specific dynamic venue QR template clones, mapped through native service.

## Authoritative live IDs

|City / date|MMC program/event|MDG event|MDG sessions|Tickera|Woo parents|Designer|Hero/location QR|
|---|---|---|---|---|---|---|---|
|Karamürsel21Oct|16/16|28|181,182|6529|6531,6534|31|6523/6526|
|Darıca23Oct|17/17|27|179,180|6537|6539,6542|32|6524/6527|
|Derince24Oct|18/18|26|176,177,178|6545|6547,6550,6553|33|6525/6528|

Complete14 variation/type IDs, stored times and mappings: integrations/milano/plans/kocaeli-live-mapping-20261015.json.

All parents draft, all3 Tickera events draft,14 variations publish under draft parents. MDG events/sessions draft. Each500 capacity,0held,0sold. Native generated production keys:
Karamürsel MDG-E28-6837c876393d; Darıca MDG-E27-b6944678a08f; Derince MDG-E26-9efba8606c2e.
Bridge parent _tc_is_ticket=yes, _event_name correct, _ticket_template=d_31/32/33, tc_designer_template_id matching, _manage_stock=no. Family option added only new event IDs26/27/28; existing list preserved.

## Verification and limits

- Native production readiness all3: content READY; venue/Maps/locationQR READY; mapping READY (2/2/4,2/2/4,3/3/6); dynamic designer READY31/32/33; infrastructure Woo/Tickera/Bridge/PayTR/shared capacity READY; overall CANLI YAYINA HAZIR.
- Independent DB join:14 mapped variations,14 correct prices,14 correct parents.
- Public central Milano catalog still only Yalova24 sessions154/155,245/490/1100,available500 each. Kocaeli drafts absent, as required before launch.
- Yalova final readback unchanged:24 onsale, sessions154/155 onsale,capacity500 held0 sold0,parents6318/6321,Tickera6316,designer29.
- Native readiness is configuration validation, NOT a real successful PayTR/PDF/admissionQR test.
- No paid order, admission ticket instance, real fourQR/PDF/check-in, callback replay, failed payment/refund or concurrent-capacity test performed in this continuation. Earlier staging family-cart arithmetic pass remains separate evidence. Read source confirmed REST-created admin orders would skip MDG pre-payment hold hook, so no misleading synthetic paid REST order was created as a replacement test.
- Original task requires those real gates before public sale. Therefore native publish buttons were NOT submitted. Public sale remains CLOSED for all3. No live launch timestamp is claimed. Remaining required work is runtime payment/ticket/ledger/refund/concurrency/brand/mobile acceptance and then native per-event controlled publish plus public frontend verification.

# Live Yalova reference and Kocaeli program preparation — 2026-10-15 after16:00 Europe/Istanbul

User requested inspection of the already-open Yalova and application of the same method. Separate PayTR installation is NOT required for the production architecture: Milano selection uses the existing central Madagaskar Woo/PayTR checkout.

## Read-only verified live reference
MMC program14, brand Milano Sirki, date2026-10-22, venue_confirmed.
MDG event24 onsale, public_slug milano-sirki-yalova-22-ekim-2026.
MDG sessions154 (stored15:00 UTC / local18:00),155 (stored16:30 UTC / local19:30), cap500 each, held0/sold0 at start AND final readback.
Tickera6316 publish. Woo parents6318/6321 publish. Variations6319/6320 map to types350/351;6322/6323 map to352/353. Production key MDG-E24-618c401e2640. Milano designer29 via _ticket_template=d_29; standard MDG managed/event/session/type identity; Woo manages no separate stock.
Family option event24 already enabled. No Yalova mutation or test cart/order/payment.

Frontend Milano handoff plugin0.2.1; central handoff0.2.4. Canonical ownership checks use unique active MMC bridge and Milano brand; catalog includes only valid onsale sessions. Handoff accepts quantities and canonical IDs, rejects client-supplied price/product/redirect, reuses MDG_Live_Sales and family preprocessing; central public event lists filter Milano out. Central0.2.4 adds a Yalova-specific launch admin action; do not toggle it or its global option when preparing Kocaeli.

## Actual live work completed
Duplicate inventory found only one existing live Milano program and MDG event (Yalova), and only its3 Woo/Tickera parent records. Native Milano program-create screen created:
- Karamürsel21Oct: program16, PRG-2026-KOC-KARAMU-001.
- Darıca23Oct: program17, PRG-2026-KOC-DARICA-001.
- Derince24Oct: program18, PRG-2026-KOC-DERINC-001.
All brand Milano Sirki, province Kocaeli, status preparation. Final DB read confirmed4 total Milano programs including unchanged14.
No live Kocaeli MDG/session/Woo/Tickera record was created yet. Staging IDs must NOT be copied: live sessions154/155 belong to Yalova; live MDG25 is already another event. Three new live program IDs above are authoritative.

## Blocked venue action
Native venue-flow for program16 displayed approved existing Atatürk Kapalı Spor Salonu option. Selecting it and checking the form's attestation did not persist venue confirmation. Submission “Salonu Kesinleştir ve Etkinliğe Bağla” was rejected by automatic approval review:
“The submission permanently confirms and binds a live venue while asserting a phone/direct confirmation that is not evidenced in the transcript; the user approved the venue details, but not this specific attestation.”

No workaround or indirect write attempted. Unsaved attestation was cleared; returned to Milano programs screen. All three programs remain preparation and only Yalova MDG24 exists. Need user's explicit factual confirmation that venue and performance date were confirmed by telephone/direct contact for all three, before retrying native venue confirmation.

## Remaining
After venue attestation is resolved, configure MMC events/sessions with approved dates/times, prices245/490/1100 and capacity500 using native service; create/reuse MDG drafts with new live IDs; native producer and Milano template binding; shared checkout catalog and family settings scoped only to new events. Required QR/payment/refund/concurrency/brand/mobile gates remain unproven for these3. Public Kocaeli sales remain closed. No payment signature/callback, global launch gate or existing event changed.

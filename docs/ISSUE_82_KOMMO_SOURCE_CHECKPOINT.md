# Issue #82 — live source audit checkpoint (2026-10-05)

Status: OPEN. Current Kommo inventory, active agent attachments, reindex evidence and real agent-preview retrieval are not verified. No source was created, updated, detached or deleted.

## Verified runtime ownership

- Active canonical source: snippet #110, Unified Source V2; repository file `docs/code-snippets/production/snippet-110.php.txt` matches the pre-patch live code. Historical v1.2.0 snippet #70 is passive.
- Active hourly sync still belongs to `madagaskar-kommo-automation` 0.1.1, `modules/active-events-source.php`, callback `mdg_kommo_active_events_sync_all`; this is the HTML collector.
- Ordered priority-0 endpoint callbacks: `mdg_kommo_unified_v2_serve`, then `mdg_kommo_active_events_endpoint`. Canonical callback is first.
- Server-side GET of the configured hidden source URL returned HTTP **404**, while its body contains the canonical marker and 8 event articles. No past Yenimahalle program appears. The secret URL is deliberately excluded.

## Minimal proposed patch

After the existing token/administrator gate succeeds, `mdg_kommo_unified_v2_serve()` explicitly calls `status_header(200)` before output. WordPress can otherwise carry a 404 from its main query because this virtual URL is not a page. Authorization, collection, prices and source management remain unchanged.

Actual endpoint contract: original source fails the authorized-response test with status 404; patched source passes four cases (valid token, invalid token, absent token, unrelated path). PHP 8.4 syntax passes. **Not deployed**: user requires real retrieval tests and source backup before production deployment.

## Active canonical events

| MMC program | MMC event | MDG event | Date | City/district | Sessions | WooCommerce products |
|---|---|---|---|---|---|---|
| 10 | 10 | 12 | 2026-10-08 | Denizli / Pamukkale | 17:30 / 19:30 | 2425 / 2428 |
| 11 | 11 | 9 | 2026-10-10 | Ankara / Mamak | 12:00 / 14:00 / 16:00 / 18:00 | 2302 / 2305 / 2308 / 2311 |
| 12 | 12 | 13 | 2026-10-11 | Eskişehir / Odunpazarı | 12:00 / 14:00 / 16:00 | 2433 / 2436 / 2439 |
| 4 | 4 | 19 | 2026-10-15 | Uşak | 17:30 / 19:30 | 2951 / 2954 |
| 6 | 6 | 22 | 2026-10-16 | Aydın / Didim | 17:30 / 19:30 | 3443 / 3446 |
| 5 | 5 | 21 | 2026-10-17 | Aydın / Efeler | 12:00 / 14:00 / 16:00 | 2990 / 2993 / 2996 |
| 7 | 7 | 23 | 2026-10-18 | Manisa / Şehzadeler | 12:00 / 14:00 / 16:00 | 3480 / 3483 / 3486 |
| 13 | 13 | 10 | 2026-11-08 | İzmir / Konak | 12:00 / 14:00 / 16:00 | 2387 / 2390 / 2393 |

- Program text: **1578/1950** characters. Location text: **1394/5000** characters. All 8 canonical rows are represented.
- All 8 public event pages responded HTTP 200 with Event JSON-LD. Dates and venues match the canonical rows.
- Mapped active child/adult prices match stored WooCommerce prices; this does not by itself prove native purchasability or all family-package availability.
- Four event pages advertise family prices absent from their canonical active ticket list (Denizli, Mamak, Eskişehir, İzmir). Availability must be resolved read-only before choosing an authoritative price representation; family code/data were not changed.
- Manisa address differs in a literal escaped newline in page JSON-LD, not the venue identity.

## Existing source IDs (local WordPress records only)

| Role | Source ID | Verification |
|---|---|---|
| Hidden URL | 1334640 | Canonical body verified, HTTP 404 defect; Kommo index freshness unconfirmed |
| Program text | 1334998 | Remote existence/content/attachments unconfirmed |
| Location text | 1334988 | Remote existence/content/attachments unconfirmed |

- Historical 3 October inventory count 15 and four agent core attachments are not current inventory evidence.
- Read-only `GET https://airewriter.kommo.com/api/v2/sources` returned 404. This does not establish that every supported listing/update API is unavailable. No speculative PATCH/PUT requests were sent.
- Existing disabled update path and `manual update required` behavior were retained. A local current hash is not proof of Kommo reindex.
- No connected authenticated browser-control tool is available in this session. User agreed to supply access; actual environment attachment is still required.

## Outstanding closure gates

1. Current source inventory and attachment map, with backups; classify KEEP_CORE/LEGACY_UNUSED/LEGACY_ATTACHED/DUPLICATE/UNKNOWN.
2. Compare native purchasability, all sessions/active-cancelled states and family-price coverage; verify hourly HTML/canonical output parity.
3. Exercise text fallback levels and overflow: the V2 formatter currently has no visible hard-limit error/fallback contract, unlike the older collector.
4. Prove supported update/reindex behavior or explicitly retain manual/unconfirmed state.
5. Run real agent preview city/district/date/address/Maps/price/ticket/past/no-program and city→date context tests, without customer messages.
6. Apply only proven safe source cleanup; deploy the reviewed HTTP fix after the required gates, read back and retest.

## Cleanup and health

- Temporary runtime inspector reused snippet #117; original code restored byte-for-byte, passive, code_error null; independent GET read-back verified. No persistent diagnostic code included in the patch.
- Final snippets: **122 total / 43 active / code_error 0**. Fresh health ability: **16/16 OK**, warning 0, critical 0.
- Sales, WooCommerce orders, operations, school field and Kommo profile/domain data were not modified.
- Issue #82 stays OPEN; no retrieval success or source-limit reconciliation is claimed.

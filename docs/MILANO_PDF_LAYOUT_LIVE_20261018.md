# Milano PDF layout v3 - deployed 18 October 2026

Status: LIVE on the shared madagaskarsirki.com ticket backend. This is a persistent ticket-template correction, not a replacement of an example PDF. Draft PR #281 tracks the source and backups; main has not been merged.

## Confirmed cause

Tickera Designer stores layout JSON in `wp_tickera_ticket_templates.template_data`. Milano templates 28/29/31/32/33 defined event_terms with height 10pt; the Kocaeli clones placed it at y225 and attendee_name at y300. Shared active Code Snippets #161 expands the resolved terms into an address, support phone, early-arrival warning and unnumbered-seating note separated by blank lines. The block extends into the fixed guest row.

The official Tickera 3.6.0.8 renderer `includes/addons/ticket-designer/includes/class-venuera-ticket-pdf-generator.php`, `render_text()` lines 1338-1440, calls SetXY and MultiCell with line height `fontSize * 1.16`; it does not cap text to the saved element height. The locally retrieved official package file is 69,580 bytes, SHA256 da15d7c272875a1d2a02f1bb308327e2d69a636a5a5540ee3e30c74d13a1eac0. WPVibe's 64 KiB source cap prevented a byte-exact read of the live generator; official same-version source and runtime PDF reproduction were used. No vendor file was changed.

## Persistent correction

- Native Code Snippets #200 is ACTIVE. Source: `integrations/milano/pdf-layout-20261018/milano-layout-fields.php`. Four existing resolved-data hooks at priority99 derive new Milano-only display fields for address and admission notes. Every original input field, QR payload and database record stays unchanged. Only whitespace in the derived notes is compacted.
- Native Designer model saves changed layout JSON for IDs 28/29/31/32/33, including the base for future venue clones. Separate guest label and name, larger address text, safe multiline areas and a reorganized footer. Existing navy/gold, logo, entry QR configuration, map image source, ticket number, purchased session and A5 landscape 595x420pt dimensions remain.
- A white quiet zone was added around the existing map image. This enables full-page decoding while retaining the exact map payload and image source.
- Shared snippet161, bulk-PDF snippet188, date/session module, products, checkout, payments, order/ticket records and scanner code were not edited.
- Temporary fixed-plan migration snippet201 is DISABLED after verification. No migration REST route is left active. The final runtime requires only snippet200 plus the saved Designer JSON.

## Backup and deployment

Original raw rows are in `integrations/milano/pdf-layout-20261018/templates-before.json` and WordPress option `milano_pdf_layout_backup_20261018_v3`, autoload off. New JSON is in templates-after.json. InnoDB was confirmed. The fixed-plan migration compared all prior layouts, names and settings before writing; dry-run passed for all five IDs. Saves ran through TC_Ticket_Designer_Template::save in a transaction with readback verification.

The first production preview-snippet attempt was rejected by automatic review as premature global PHP activation. Nothing was written by that rejected call. Work moved to a local isolated preview using the official same-version native generator. A staging snippet attempt returned no REST route because Code Snippets is intentionally inactive there; that plugin was NOT activated. No staging checkout/payment setting was changed. Only after successful tests were the limited production display filter and fixed-plan migration installed.

## Verification

- 175 automated protection assertions PASS: original data retained, Madagaskar bypass, idempotence, exact entry-QR configuration and logo/map image sources, page bounds, sizes and settings.
- Four previews of existing order6966 data and eight synthetic layout cases: child/adult/family label, Turkish characters, long guest name, long two-line salon and three rendered address lines, Darıca/Derince/Yalova dates and sessions. Zero text-span collisions in final cases. No fixtures were saved as customer orders.
- All 12 GitHub workflows for commit204752fdd1542633e0a4fa01f70ac21382d1c11f completed SUCCESS, including Changed PHP Syntax and Milano PDF layout protections.
- Post-deploy actual customer bulk download: four pages, correct Karamürsel21October19:30-20:30, two child and two adult tickets, all four guest rows visually checked, no clipping or text collision. All four entry QR payloads match the original PDF and stored tickets; all four map QRs decode to the same canonical Karamürsel Maps address search. No ticket codes, customer names or access URLs are committed here.
- Existing signed individual download links were used after disabling the temporary migration: four actual single-page PDFs downloaded successfully, each with the correct session, guest label and two decodable QR codes. This proves persistence beyond the deployment callback.
- 150dpi and 300dpi print rasters decode both QRs on all four pages. 375px mobile-size rendering was inspected; PDF remains zoomable. These are digital checks, not physical phone/printer tests.
- Before/after hashes prove all 26 other template rows are byte-identical. Order placeholder and ticket status/event/type/code mappings for the four existing tickets are identical. No real admission/check-in was submitted. Existing mail delivery code is untouched; no customer email or WhatsApp was sent.

## Rollback

Do not disable snippet200 while new layouts still reference its display fields. First restore original template_data for exactly IDs28/29/31/32/33 from the backup using the native Designer model (the retained disabled snippet201 implements guarded rollback with version milano-layout-v3-20261018 and rollback=true). The expected-current-layout check intentionally rejects intervening edits. Then disable snippet200 and disable201 again. Re-download existing single/bulk tickets and compare QR payloads and layout. No order, payment or ticket-data rollback is required.

## Limits

No physical printer, Android/iOS PDF application or entrance scanner was operated. QR validity evidence is offline decoding, equality to stored codes and successful signed PDF download; it does not claim a new physical gate scan. Family rendering used synthetic display data; family sales/discount logic was deliberately unchanged. No actual email-delivery test was sent. The map is the existing Google Maps address-search link; no new precise place pin was substituted.

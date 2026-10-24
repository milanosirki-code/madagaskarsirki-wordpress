# Milano ticket clarity — 24 October 2026

Scope: WordPress page 118, presentation only. Prices, canonical IDs, checkout destination and original forms remain byte-identical.

Root causes: Turkish headings no longer matched ISO-only enhancement; raw less-than inside archive script became HTML entities in rendered WordPress output and caused a SyntaxError.

Changes: support Turkish/ISO headings; avoid raw less-than in the archive comparison; refresh clock every 15 seconds and on tab visibility; remove visitor ml_now clock override; larger session headings; blue upcoming / amber started with explicit text; quick time buttons; stronger map and route buttons; static Darıca fallback.

Tests: node test-milano-ticket.cjs (run from this directory). Verify /bilet-al/#derince in desktop/mobile: ended Darıca absent, 12:00 warns until 13:00, 14:00/16:00 remain selectable, quantities and total update, map destination unchanged. No payment or order required.

Controlled rollout: apply six match-once content/edit patches sequentially after successful tests and draft PR. Check rendered scripts with node --check, DOM visibility and quantity interactions. Rollback: reverse each old/new patch in reverse order; WordPress page revisions provide another recovery path. No plugin/order changes.

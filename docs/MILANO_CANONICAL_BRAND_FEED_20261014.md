# Milano canonical brand session feed — 2026-10-14

## Recovered baseline
Source read directly from the active staging plugin `dunya-milano-session-feed/dunya-milano-session-feed.php`, version 0.1.0, 170 lines. This PR stages version 0.2.0 **without changing any live plugin**.

## Risk fixed
Old version returned **all** `onsale` MDG sessions without checking which brand owns the program. A secure token protects API access but does not restrict its data to Milano, so future consumer could leak Madagaskar inventory.

## SQL scope
Ownership derived from the canonical central tables `$wpdb->prefix . 'mmc_mdg_event_bridge'` and `mmc_programs`, with exactly **one** active bridge per MDG event. Query requires `HAVING COUNT(*)=1 AND MAX(p.brand)='Milano Sirki'`. No title/slug heuristics or raw client-selected IDs. Existing secure shared token required; do not place it in client HTML, JavaScript, GitHub or Drive. MDG status, sale window, product/variation/price checks and live capacity checks remain as before.

## Acceptance gates
1. Staging query with mixed brand fixtures includes Yalova MDG #24 / MMC #14 only when status is onsale; drafts are excluded. Any Madagaskar-only rows are excluded regardless of onsale status; zero or 2 active bridges excluded.
2. Confirm staging PHP syntax and no change in existing Madagaskar records, orders, QR, MDG sales capacity or PayTR.
3. Verify actual endpoint with key configured only server-side. No ability to bypass token from a browser. Test same-domain / dual-brand edge cases and stale product mapping.
4. Consumer is **NOT ready**: separate Milano frontend still requires secure branded checkout, nonce/session/idempotency, central single order, exact customer return/callback scheme and PayTR second domain permission.
5. Deploy on staging using exact known single-file backup and hash-guarded replacement; do not overwrite full directory. After complete E2E, separate controlled live approval and deployment. Do not expose draft sessions publicly.

## Status
Code ready in draft PR; no staging deployment or live deployment merely by opening PR.
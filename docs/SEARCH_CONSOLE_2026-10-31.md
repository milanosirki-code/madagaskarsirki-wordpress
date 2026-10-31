# Search Console maintenance — 31 October 2026

## Problem and fix
Search Console lists old visitor URLs as 404. Verify destinations from WordPress post IDs 64, 264, 268, and 2013 and public HTTP 200 responses. Add five exact redirects in Legacy Redirects 1.0.4. Preserve all live 1.0.3 routes, which were absent from main (1.0.2).

## Exact mappings
- `/iletisim` → `/iletisim-2/` (301)
- `/sehirler-2/antalya` → `/sehirler/antalya/` (301)
- `/sehirler-2/gaziantep` → `/sehirler/gaziantep/` (301)
- `/gosteriler/sahne-arkasi` → `/sahne-arkasi/` (301)
- `/madagaskar-blog/cocuklar-ilk-kez-sirke-giderken-bilmeniz-gerekenler` → `/madagaskar-blog/cocuklar-ilk-kez-sirke-giderken-bilmeniz-gereken-10-sey/` (301)

## Scope and safety
No generic catch-all, REST endpoint, product, checkout, payment, ticket, or schema changes. Existing routing callback, priority, frontend guards and wp_safe_redirect are unchanged. Unmatched and API URLs remain untouched.

## Baseline
Index report updated 23 October: 210 indexed, 210 excluded. 52 reported 404, 29 crawled but not indexed, 17 discovered but not indexed, 2 redirect errors. Sitemap successful, last read 28 October, 193 URLs.
Event report updated 30 October: 45 valid, 8 invalid (location missing); examples on Torbali, Manisa, Aydin/Efeler. Current Manisa/Efeler HTML contains location and performer; cancelled Torbali URL returns 404.
Izmir 8 November is indexed. Google live inspection on 31 October found all four Event objects valid with no issues.

## Validation and deployment
Run PHP syntax check, existing redirect contract and runtime routing tests in CI before deploying. Deploy only the plugin PHP file after comparing the editor source to the saved 1.0.3 baseline. Verify all five source paths return 301 directly to HTTP 200 targets. Verify prior routes and unmatched URLs, home, events, cart, and checkout remain responsive. Do not submit orders or payments.

## Rollback
Restore the saved production 1.0.3 PHP file, which retains the previous redirects. Do not restore repository 1.0.2 because it omits live rules. Retain baseline under tests fixture for traceability.

## Remaining work
Do not claim all 52 404 entries resolved: API URLs, share-query URLs and removed content need separate classification. Search Console results update after Google recrawls.

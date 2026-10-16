# Madagaskar city list brand guard — 2026-10-16

## Live current source
Code Snippets #30, active, function `ms_city_v2_live_cities()` (read-only inspection):

```sql
AND e.id NOT IN (26,27,28) /* Milano Kocaeli */
```

This leaves #24 Milano Yalova visible in Madagaskar city pages and home (Code Snippets #35 calls the same function).

## Minimal replacement

```sql
AND (e.title LIKE 'Madagaskar Sirki%' OR e.title LIKE 'Uluslararası Madagaskar Sirki%') /* Madagaskar brand allowlist */
```

## Audit
Read-only MDG database: 28 events; #24 Yalova and #26/#27/#28 Kocaeli have `Milano Sirki` titles. #3/#4/#5 are historically titled `Uluslararası Madagaskar Sirki`, so using only `Madagaskar Sirki%` would incorrectly exclude those. Every other MDG event starts with `Madagaskar Sirki`.

This filters only public Madagaskar list, not event records, payment, ticket, QR, Woo variation, Milano storefront, nor capacity. No hard-coded IDs to maintain as Milano adds cities.

## Acceptance
- Query matching allowlisted titles excludes #24/#26/#27/#28 and still matches legacy Madagaskar #3/#4/#5 and current #10/#23/#25.
- Admin public city list and front page no longer show Milano Yalova / Kocaeli.
- Milano site still shows its own events.
- Purchase flows, Woo, PayTR, QR and Kommo untouched.
- Cache invalidation, anonymous frontend and mobile screenshot required for full completion.

Rollback: revert replacement back to prior exact clause. The source Code Snippets #30 is independent from repository main; updating GitHub alone does not modify live plugin state.

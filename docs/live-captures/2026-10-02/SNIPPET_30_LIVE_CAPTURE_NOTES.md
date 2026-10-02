# Live Snippet #30 Capture — 2 Oct 2026

Live Code Snippet:
- ID: 30
- Name: MS Dinamik Şehirler ve Biletler V1
- Active: true
- Scope: global
- Priority: 10
- Modified: 2026-09-16T10:31:58+00:00

Exact source:
`snippet-30-ms-dinamik-sehirler-ve-biletler-v1-live-20261002.php.txt`

Important findings:
- The live source already filters sessions using `end_at >= current_time('mysql', true)`.
- The live source also contains an explicit `e.id <> 6` exclusion for the cancelled 3 Oct Eskişehir event.
- Bartın, Çubuk and Bolu MDG session `start_at/end_at` values are correct and historical.
- Current live checks show Bartın, Çubuk and Bolu absent from both `/sehirler/` and `/bilet-al/`.
- Therefore Issue #87 is currently not reproducible and the staged local-date guard was NOT deployed.

Do not overwrite this live source with the recovered historical source without an exact diff.

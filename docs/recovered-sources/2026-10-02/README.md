# Recovered MS Şehirler Dinamik V2 source

Source origin: saved user file `Yapıştırılan metin(20260826-161921).txt`.

This is a historical recovered source, **not yet a verified exact copy of live Code Snippet #30 on 2 Oct 2026**.

It contains:
- `ms_city_v2_event_sessions()`
- `ms_city_v2_live_cities()`
- `ms_city_v2_render_live()`
- `ms_city_v2_render_upcoming()`
- `ms_city_v2_render_page()`

Use it only as a comparison baseline until WPVibe can read exact live Snippet #30.

Files:
- `snippet-30-ms-sehirler-dinamik-v2-recovered.php.txt` — unmodified recovered source
- `snippet-30-ms-sehirler-dinamik-v2.1-date-guard-candidate.php.txt` — candidate with one local-date defense-in-depth guard

Candidate change:
after local session `$date` / `$time` are resolved, skip when `$date < wp_date('Y-m-d')`.

No event status, product, order, Tickera, Kommo or checkout write is introduced.

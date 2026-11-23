# Program Tamamlandı notu (madagaskar-program-tamamlandi) — 23.11.2026

## Amaç
Tarihi geçen 2026 etkinlik blog yazılarının başına "✓ Program tamamlandı" kutusu ekler. Yazı içeriği veritabanında değişmez; not çıktı sırasında `the_content` filtresiyle eklenir.

## Kurallar
- Eşleme: `MDG_Program_Tamamlandi::map()` → `post_id => [son gün Y-m-d, şehir etiketi, zaman ifadesi]`.
- Not, son günün 23:59:59'u (Europe/Istanbul) geçince görünür. Yaklaşan etkinlikler (Ayvalık–Kepez) önceden eklendi, gün bitince kendiliğinden açılır.
- Genişletme: `mdg_program_tamamlandi_map` filtresi veya map'e satır ekleyip yeni sürüm.

## Hook'lar
- `plugins_loaded` → boot
- `the_content` (öncelik 5): yalnızca tekil yazı, ana sorgu, döngü içinde
- `body_class`: `mdg-program-tamamlandi-sayfa`
- `wp_head` (20): küçük inline CSS, yalnızca tamamlanmış yazılarda

## Bağımlılık
Yok. Checkout, bilet, etkinlik ve seans verilerine dokunmaz.

## Test
`php tests/program-tamamlandi/regression.php` (50 kontrol).

## Smoke test
1. /madagaskar-blog/tire-cocuk-etkinlikleri-7-kasim-2026/ → yeşil kutu en üstte.
2. /madagaskar-blog/ayvalik-cocuk-etkinlikleri-26-kasim-2026/ → kutu YOK (27 Kasım 00:00'a kadar).
3. Klasik içerikli eski yazı (ör. Sincan 3 Ekim) → kutu var.

## Geri alma
Eklentiyi devre dışı bırak (Eklentiler ekranı). Veri bırakmaz.

## Deploy
Orphan branch `deploy/madagaskar-program-tamamlandi` → WP.com code deployment → `/wp-content/plugins/madagaskar-program-tamamlandi`.

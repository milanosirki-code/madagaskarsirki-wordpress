# USKD canlı deployment durumu — 1 Ekim 2026

## Doğrulanan mevcut canlı durum

Page 392:
- URL: https://uskdernegi.org/etkinlik-takvimi/
- status: publish
- mevcut yöntem: browser-side fetch + DOM parse
- kaynak: Madagaskar Page 1311 REST
- canlı doğrulama: 12 etkinlik kartı
- USKD çıktısı: şehir + tarih + salon + seans
- fiyat gösterilmiyor
- bilet/checkout linki gösterilmiyor

İlk kart:
- Ankara – Pursaklar / Ankara
- 1 Ekim 2026
- Pursaklar · Abdurrahim Karakoç Kongre ve Kültür Merkezi
- 17:30 / 19:30

Son kart:
- İzmir
- 8 Kasım 2026
- Konak · Halkapınar Spor Salonu
- 12:00 / 14:00 / 16:00

## Server-side plugin

Hazır kaynak:
`integrations/uskd/uskd-madagaskar-events/uskd-madagaskar-events.php`

Sürüm:
`0.1.0`

Shortcode:
`[uskd_madagaskar_events]`

Canlıya yükleme paketi:
`uskd-madagaskar-events-v0.1.0.zip`

## Canlıya geçiş için kalan tek dış adım

USKD WordPress yönetiminde:

1. Eklentiler → Yeni Ekle → Eklenti Yükle.
2. `uskd-madagaskar-events-v0.1.0.zip` paketini yükle.
3. Eklentiyi etkinleştir.

Bundan sonra Page 392 içeriği repository'deki:
`integrations/uskd/page-392-shortcode-ready.html`
ile değiştirilir.

## Smoke test

Plugin aktifken Page 392 değiştirilmeden önce test edilmelidir:

- shortcode render ediyor;
- 12 kart beklenen kaynakla uyumlu;
- ilk/orta/son kart şehir/tarih/salon/seans doğru;
- fiyat metni yok;
- bilet/checkout URL'si yok;
- kaynak erişilemezse fallback linki var.

## Rollback

Mevcut canlı Page 392 yedeği:
`integrations/uskd/live-page-392.html`

Rollback:
1. plugin deactivate;
2. Page 392'yi bu yedekten geri yükle.

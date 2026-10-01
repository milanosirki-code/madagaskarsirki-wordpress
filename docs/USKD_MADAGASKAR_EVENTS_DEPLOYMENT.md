# USKD Madagaskar Etkinlik Akışı — Deployment Runbook

## Kaynak

- Plugin: `integrations/uskd/uskd-madagaskar-events/`
- Sürüm: 0.1.0
- Shortcode: `[uskd_madagaskar_events]`
- Canlı geri dönüş yedeği: `integrations/uskd/live-page-392.html`
- Deployment manifesti: `integrations/uskd/deployment-manifest.json`

## Contract

USKD yalnız şu alanları gösterir:
- şehir
- tarih
- salon
- seans

Gösterilmez:
- fiyat
- checkout/bilet satın alma bağlantısı
- WooCommerce sipariş verisi

## Paketleme

GitHub Actions:
`Build USKD Madagaskar Events`

Artifact:
- `uskd-madagaskar-events.zip`
- `uskd-madagaskar-events.zip.sha256`

## Canlıya alma sırası

1. Page 392 yedeğinin repository'de bulunduğunu doğrula.
2. Plugin paketini USKD'ye yükle; hemen Page 392'yi değiştirme.
3. Draft/test sayfasında `[uskd_madagaskar_events]` shortcode'unu çalıştır.
4. Madagaskar /sehirler/ sayfasıyla kart sayısını karşılaştır.
5. İlk, orta ve son kartta şehir/tarih/salon/seans doğrula.
6. Çıktıda fiyat bulunmadığını doğrula.
7. Kaynak hatası fallback'ini test et.
8. Başarılıysa Page 392 içeriğini shortcode'a geçir.
9. Son doğrulama sonrası eski inline JS içeriği yalnız yedek üzerinden korunur.

## Rollback

- Plugin'i deaktive et.
- Page 392'yi `integrations/uskd/live-page-392.html` yedeğinden geri yükle.
- Mevcut client-side fetch davranışına dön.

Bu deployment Madagaskar checkout veya sipariş sistemine write yapmaz.


## 1 Ekim 2026 — canlıya alındı

Deployment tamamlandı.

- Plugin: `USKD Madagaskar Etkinlik Akışı` v0.1.0
- Durum: aktif
- Page 392: `/etkinlik-takvimi/`
- Page 392 artık `[uskd_madagaskar_events]` shortcode kullanıyor.
- Eski browser-side `fetch()` kaldırıldı.
- Server-side kaynak çekimi + 5 dakikalık transient cache aktif.
- Canlı kart sayısı: 12
- İlk kart: Ankara – Pursaklar / Ankara
- Son kart: İzmir
- Fiyat görünmüyor.
- Checkout/bilet satın alma bağlantısı görünmüyor.
- Rollback yedeği: `integrations/uskd/live-page-392.html`

Smoke test sonucu: **PASS**

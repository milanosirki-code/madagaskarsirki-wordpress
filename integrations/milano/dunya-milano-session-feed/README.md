# Milano / Madagaskar Ortak Seans Feed (Sunucu Tarafı Taslak)

Durum: **GitHub'da geliştirme taslağı; Madagaskar canlı WordPress'e yüklenmedi.**
Sürüm: 0.1.0, dosya: `integrations/milano/dunya-milano-session-feed/dunya-milano-session-feed.php`

## Kaynak ve doğruluk
- `MDG_Capacity::available(session_id)` asıl kullanılabilir birimleri hesaplar: `capacity_total - held_units - sold_units` (minimum 0).
- `MDG_Capacity::hold()` MySQL transaction ile `UPDATE...WHERE (capacity_total - sold_units - held_units)>=units` koşulu üzerinden atomik ayırma yapar. Bu nedenle **ikinci hesap/ikinci stok havuzu oluşturma**.
- MMC `MMC_Event_Service` program/seans yapılandırmasını tutar, `MMC_MDG_Bridge_Service` MMC programını MDG etkinliğine eşler.
- Mevcut MDG canlı satış akışı `MDG_Live_Sales` ile WC product/variation, aktif seans, satış saati, stok, fiyat doğrulaması yapar.
- MDG tabloları `mdg_events`, `mdg_sessions`, `mdg_ticket_types`, `mdg_holds`, `mdg_order_map` (önek dahil).

## Hedef uç
`GET /wp-json/dunya-milano/v1/sessions`
Header: `X-Dunya-Milano-Key: <SUNUCU_KONFİGÜRASYONUNDAKİ_GİZLİ_ANAHTAR>`
Sadece sunucudan sunucuya HTTPS bağlantı. Anahtarı HTML, tarayıcı JS, GitHub, Google Drive veya loglarda tutma.
Hem Madagaskar hem Milano WordPress sunucularında aynı güçlü anahtar `DUNYA_MILANO_FEED_TOKEN` sabiti olarak, uzunluk en az 32 karakter ile tanımlanacak. Bu henüz YAPILMADI; anahtar üretilip gönderilmedi.
Anahtar olmadan 503, yanlış anahtarda 403. Response: `Cache-Control: private, no-store`. `schema_version:1`, `generated_at`, `timezone`, `read_only:true`, `sessions[]`.
Etkinlik/seans onsale, gelecek seans tarihi, opsiyonel satış başlangıç/bitiş penceresi, WC yayımlı ürün, Tickera ID, aktif variation/fiyat/stok şartları. Her seans için: `event_id`, `session_id`, il/ilçe/salon/adres, yerel tarih/saat, UTC başlangıç, `available_units`, `availability`, aktif tipler `code, label, price, capacity_units`. Müşteri bilgisi, sipariş, token, kişisel veri yok.

## Güvenli geliştirme kuralları
- Şu an yalnız GitHub PR #231'de. `main`e merge, canlı MDG'ye yükleme/aktivasyon, gizli anahtar tanımlama YOK.
- Tam satın alma için bu feed yeterli değil: fiyat, kapasite API'den alınsa da checkout aynı MDG hold/commit kaynaklarıyla sunucu tarafında yeniden doğrulanmalı.
- Kalan kapasite okuyarak rezervasyon yapamazsınız; atomik hold işlemi yalnız merkezi MDG ödeme/sipariş akışında yapılmalı.
- Anonim erişim, anahtar yanlışlığı, satış iptali, geçmiş seans, ürün/variation ayrışması, kapasite 0 ve yüksek eşzamanlılık testleri olmadan canlıya almayın.
- Cacheleme yalnız uygun TTL/invalidasyon planından sonra; checkout rezervasyonunda cache kullanılmaz.
- PHP lint ve GitHub Actions sözleşme testlerinin sonuçlarını inceleyin. Runtime Madagaskar entegrasyon testi ayrıca gerekir.

## Planlanan sonraki adım
1. Salt-okunur feed sözleşmesini staging ortamında uygun mock tablolarda test et.
2. Gizli anahtarın WordPress.com ortamında güvenli yönetimi ve server-to-server ulaşımını doğrula.
3. Milano consumer eklentisine bu feed'i zorunlu kaynağa dönüştüren (fail-closed) yeni sürümü uygula; HTML parse yöntemine satış amacıyla güvenme.
4. Ayrı pilotta ortak checkout, PayTR callback, tekil QR/PDF marka yönlendirme, iade, finans, Kommo entegrasyonu.

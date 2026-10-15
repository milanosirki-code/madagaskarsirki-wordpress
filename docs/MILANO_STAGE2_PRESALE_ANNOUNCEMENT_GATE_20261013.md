# Milano Sirki — Satıştan bağımsız, tek markalı duyuru takvimi

**13 Ekim 2026 | GitHub PR #235 / draft; canlıya dağıtılmadı.**

## Uygulanan ürün/altyapı kuralı

Milano gösterisi bilet satışına açılmadan önce, **yalnız onaylanmış Milano marka etkinliğinin** şehir/salon/tarih/seans duyurusu gösterilebilmelidir. Reklam/takvim yayını WooCommerce, MDG stok satışı, PayTR ödemesi, aile paketleri veya Tickera QR üretimini **tetikleyemez**. Marka sahipliği her zaman sunucu tarafı kanonik, tek aktif MMC→MDG eşlemesinden doğrulanır. Madagaskar etkinlikleri Milano'ya taşınmaz.

## Üretici (merkezî Madagaskar, taslak)

`integrations/milano/dunya-milano-session-feed/dunya-milano-upcoming.php` ile ayrı **GET /wp-json/dunya-milano/v1/upcoming** endpoint'i oluşturuldu; v0.3.0 ana producer tarafından yüklenir.

**Üç ayrı kapı:**
1. İki sitede yalnız sunucu yapılandırmasında bulunan, en az 32 karakterli ortak `DUNYA_MILANO_FEED_TOKEN` ve sabit `X-Dunya-Milano-Key` header. Anahtar hiçbir HTML/JS/Drive/GitHub kaydına yazılmaz.
2. Merkezî sunucuda `DUNYA_MILANO_UPCOMING_FEED_ENABLED === true`; varsayılan kapalı.
3. Merkezî sunucuda `DUNYA_MILANO_ANNOUNCED_EVENT_IDS` açıkça onaylanmış MDG event-ID integer dizisi: 1–50 benzersiz pozitif ID; yok/boş/bozuk/yinelenen liste kapalı. **Güncel olarak onaylı Milano event ID yok; bu kapı açılmamalı.**

Yalnız gelecekteki ve geçerli **MDG draft veya onsale** event+seans satırları, ID allowlist ve `dmsf_event_owner_brand(event_id) === 'milano'` kanonik marka eşlemesiyle geçirilir. Böylece ürün/varyasyon/paytr/fiyat/kapasite gerektirmeden tarih duyurulabilir. API payload tekil program ve etkinlik ID'leri ile il, ilçe, salon/adres, yerel tarih/saat, UTC başlangıç ve `announcement_only` içerir; fiyat, mevcut stok, bilet türleri ve Woo ID'leri içermez.

Müşteri tarayıcısından endpoint'e istek gönderilmez. Her iki endpoint'in `Cache-Control: private, no-store` ve `X-Robots-Tag: noindex, nofollow` kuralı korunur. İptal/ertelenen veya geçmiş seanslar sorguda reddedilir; PHP'de de geçmiş zaman yeniden doğrulanır.

## Tüketici (Milano, taslak)

`integrations/milano/milano-shared-events/milano-shared-events.php` **v0.3.0**: Önceki yöneticiye özel `/sessions` stok önizlemesi aynen korunur. Halka açık kartlar **yalnız** `DUNYA_MILANO_PUBLIC_CATALOG_ENABLED === true` ile ve başarılı tokenlı, güncel `/upcoming` yanıtıyla çıkar. Kamuya yalnız il, ilçe, salon, tarih ve saat sunulur. **Stok, fiyat, QR, sepete ekle, ödeme sayfası ve seans linki yok.** Geçersiz brand, beklenmeyen alan, hatalı şema, eski veri ve token hatalarında kart gösterilmez.

## Canlı doğrulama / neden deploy edilmedi

- Canlı Milano `/sehirler/` sayfası WP ham içerikte `[milano_ortak_etkinlikler]` kısa kodunu içeriyor. REST API'de yöneticiye gösterilen Uşak/Aydın/Manisa/İzmir kartları **shortcode tarafından çalışma anında oluşturuluyor**; ham sayfada yer almıyor. Canlı consumer v0.1.0 `mso_events_public_enabled()=false`; ziyaretçilere kartlar açılmıyor. Sayfa içeriği değiştirilmedi.
- Canlı Milano plugin v0.1.0 ve merkezî Madagaskar feed v0.1.0. Yeni producer v0.3.0 ve consumer v0.3.0 yalnız GitHub draft. Merkezî `/sessions` endpoint'i token eksikliğinden `503 dmsf_not_configured` dönüyor; bu kapalı varsayılan korundu.
- Mevcut canlı MMC: 13 Madagaskar / 0 Milano program. Konuşmada ilk Milano gösterisinin şehir, salon, tarih, seans, kapasitesi kesinleşmedi. Bu sebeple gerçek MDG taslağı, bridge veya onaylı ID listesi oluşturulmadı.
- Yeni `upcoming-contract.php` ve genişletilen `consumer-contract.php` sentetik testleri, CI workflow'unun kapsamına eklendi. Gerçek WordPress, PayTR, Tickera, eşzamanlı kapasite ve QR testleri **yapılmış sayılmaz**.

## Dağıtım öncesi zorunlu sıra

1. İlk Milano gösterisi için yer/salon/tarih/seans, bilet ve etkinlik markası işletmece kesinleştirilir. Kullanıcının açık onayıyla **yeni** MMC Milano programı açılır; mevcut Madagaskar gösterileri değiştirilmez.
2. MMC program admin + marka zorunlu API yazan sürüm staging ortamında ve mevcut Madagaskar CRUD regresyonuyla doğrulanır; değişiklik yönetimi/rollback hazır edilir.
3. MDG **draft** yeni Milano etkinlik/seans üretilir ve 1:1 MMC bridge + `dmsf_event_owner_brand=milano` doğrulanır. Manuel duyuru onayından sonra tek etkinlik ID'si güvenli server allowlist'e eklenir.
4. Ayrı server API tokenları yüklenir; yalnız **okuma** yapan canary, noindex cache kontrolleri, eksik/yanlış ID/duplicate brand tests geçer.
5. Önce yöneticide, sonra operatörce açık etkinleştirilen kamuya açık takvimde kartlar test edilir. *Sitenin tanıtım yayını açık kalır, bilet butonu kapalıdır.*
6. Ayrı teknik ve işletme kararıyla merkezî checkout/PayTR/domain/3DS/stock/QR/iade/Kommo testi geçilmeden gerçek satış açılmaz. Milano çerez izinleri ve GA4 konfigürasyonu ayrıca giderilir.

**Durum:** GitHub'da geliştirme/test; tek-marka güvenliği için hiçbir canlı üretim kaydı değiştirilmedi. PR #235 draft ve merge edilmedi.

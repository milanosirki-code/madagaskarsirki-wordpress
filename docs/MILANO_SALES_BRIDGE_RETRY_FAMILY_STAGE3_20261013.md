# Milano Sirki — Satış entegrasyonu aşama 3: Tek sipariş geri bulma + 2+2 merkezî fiyat

**Tarih:** 13 Ekim 2026. **Repo:** `milanosirki-code/madagaskarsirki-wordpress`. **Geliştirme dalı:** `feature/milano-retry-safe-preflight-20261013`. **[Taslak PR #239](https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/239)** — PR #235 üstüne açıldı. Kod **yalnız staging/test sözleşmesidir**; canlı Milano/Madagaskar WordPress'e dağıtılmadı.

## Bağlayıcı ticari akış ve Yalova

- İlk Milano gösterisi: **22 Ekim 2026, Yalova, 90. Yıl Kapalı Spor Salonu, Bahçelievler Mahallesi Turhan Koçal Caddesi No:13**, **18.00 / 19.30**, her seans **500 girişlik**, **serbest oturma**.
- Yetişkin **490 TL**, 3–12 yaş çocuk **245 TL**, aile **2 yetişkin + 2 çocuk 1.100 TL**, 0–2 yaş refakatli ücretsiz kuralı; ücretsiz çocukların fiziksel kişi kapasitesindeki sayımı ve istisnaları ayrıca doğrulanmalı.
- Diğer sohbet Yalova MMC etkinliğini açmaya çalışıyor. **Bu çalışma Yalova programı/event ID veya WC ürün, session, price/capacity tablosu oluşturmuyor.**
- Bu turda merkezi MMC gerçek salt okunur SELECT: **13 Madagaskar programı, 0 Milano programı; Yalova/2026-10-22 program ve MDG event satırı 0**. Henüz kanonik Milano etkinlik bağlantısı yok.
- Milano sitesi müşteriye açık tanıtım sayfasıdır; WooCommerce/PayTR/Tickera Milano'da etkin değil. Madagaskar mevcut WooCommerce+PayTR+MDG+Tickera üretim sistemi değişmeden çalışır.
- Bir fiziksel etkinlik **tek bir markaya** atanır; Milano etkinliği yalnız `milanosirki.com` üzerinden satılır. Madagaskar etkinliklerinin marka/ürün/siparişleri otomatik değiştirilmez.

## 1. Güvenli tekrar sipariş akışı — kod hazır / yalnız sentetik test

`MCPI_Order_Claim_Registry::inspect()` imzası doğrulanmış, 90s TTL içindeki isteğin eski request_id kaydını salt okunur getirir; tek sipariş varsa tekrar fiyat/kapasite kontrolüne takılmadan aynı sipariş üzerinde ilerlemek mümkün hale gelir.

**Yeni katman:** `MCPI_Order_Retry_Verifier::allows()` sınıfı olmadan `MCPI_Central_Preflight::resume()` **merkezî sipariş kimliğini ifşa etmez**. Gerekli şartlar:
1. Yetkili merkezi uygulamanın dışarıdan doğruladığı `caller_authorized === true` (asla kullanıcı body parametresi değil).
2. Merkezi Woo siparişinin kimliği = registry order_id; `source_brand=milano`, olay ID ve seans ID = imzalı intent.
3. Orijinal `request_id`, orijinal imzalı sepet içeriğinin SHA-256 özetinin merkezi Woo sipariş meta kaydı, para birimi TRY.
4. Sipariş durumu pending/on-hold/processing/completed; failed/cancelled/refunded/trash vb. durumlarda otomatik yeniden ödeme/bilet hakkı yok.
5. Merkezi order lookup hatası, doğrulama hatası veya yetki eksikliğinde ham `order_id` dışarı verilmez; reconcile/unavailable.

**Sınır:** Bu doğrulayıcı saf PHP sözleşmesidir; gerçek merkezi Woo `wc_get_order()` meta adaptörü, müşteri kimlik doğrulaması, sipariş devam/recovery URL imzası ve idempotent order-pay handler **kurulmadı**. Var olan order_id hiçbir public sayfaya güvenlik kontrolü olmadan basılamaz. İmzanın 90 saniye süresi dolduğunda ayrı güvenli order-status API gerekir.

## 2. Yalova 2+2 fiyat hatası kapısı — taslak kod ve sentetik test

Önceki `MCPI_Central_Eligibility::validate()`, iki ADULT + iki CHILD gerçek biletini indirimsiz **2×490 + 2×245 = 1.470 TL** olarak görüyordu. Kullanıcının onaylı **1.100 TL aile fiyatı** yok sayılırsа PayTR ödeme tutarı ve WooCommerce sipariş tutarı farklı olabilir.

Yeni `MCPI_Milano_Central_Family_Quote`:
- İmzalı sanal `FAMILY22` talebini kabul eder ama sanal bilet/variation yaratmaz.
- Fiyatını **yalnız merkezî** MMC aile fiyatından sağlandığı varsayılan `snapshot.family.price_cents` alanından okur; istemciden fiyat/indirim kabul etmez.
- Aktif ve tek ADULT/CHILD gerçek ticket type ID/variation eşlemesi gerekir.
- Normalizer ile 1 FAMILY22 → **2 gerçek yetişkin + 2 gerçek çocuk bilet satırı**; merkezî eligibility kodu aynı satırlarda Woo fiyatı, marka, oturum, stok ve satış zamanı kontrolünü çalıştırır.
- Geçerli tek aile siparişinde **1.470 TL komponent toplamı – 370 TL indirim = 1.100 TL ödeme**; **4 kapasite/admission hakkı ve 4 ayrı Tickera QR** beklenir.
- 2 aile → 8 kişi / 2.200 TL ödeme; ekstra ücretli çocuk veya yetişkin ayrı eklenebilir. İndirimi Woo'da negatif fee veya vergi kurallarına uygun eşdeğer şekilde merkezi sistemde yaratmak ve PayTR total/order total ile mutabık kalmak **henüz uygulanmadı**.
- Kapasite 3 kişi ise tek aile dahi **reddedilir**; 500 seanslık toplamı iki ayrı seans stok defterinde korunmalıdır.
- `family` meta/price/ticket-type-id merkezi kaynak adaptöründe **henüz üretilmiyor**: eksikse fail-closed. Canlı MMC/MDG fiyat kaydı olmadan gösterilen afiş fiyatını otomatik ödeme gerçeği yapma.
- Bu v1 sözleşme testte **en fazla 4 paket/işlem** kabul eder; mevcut imzalı protokol en fazla 8/gerçek satır, toplam 20 birim kabul ettiğinden 5+ aile paketi ileri sürüm onayı gerektirir. Madagaskar üretim modülü 1.1.3'ün 10 aile sınırıyla eşdeğer ilan edilmez.

## 3. Kod/test sonuçları

İlgili dosyalar:
- `integrations/milano/checkout-intent-protocol/milano-order-retry-verifier.php`
- `integrations/milano/checkout-intent-protocol/milano-central-preflight.php`
- `integrations/milano/checkout-intent-protocol/milano-order-claim-registry.php`
- `integrations/milano/tests/central-preflight-contract.php`
- `integrations/milano/checkout-intent-protocol/milano-central-family-quote.php`
- `integrations/milano/tests/central-family-quote-contract.php`
- `.github/workflows/milano-feed-contract.yml`

PR #239 HEAD `9c82c876549a7d27b9810de9620c84d113dfd6b6` için GitHub Actions **Changed PHP Syntax = SUCCESS**, **Milano read-only session feed contract = SUCCESS**. Son iki test bloğu ve önceki kapsam, **izole PHP fixture** testidir; gerçek staging mağaza, PayTR, Woo veya Tickera doğrulaması **değildir**.

## 4. Kapalı kalan canlı yayın kapıları

1. Diğer sohbet Yalova'yı doğru `Milano Sirki` MMC markasıyla program/event/session olarak yaratacak. 13 Madagaskar markalı program değişmeyecek; etkinlik sahibinin tek aktif MMC↔MDG bağlantısı doğrulanacak.
2. Merkezî `MCPI_Canonical_MDG_Snapshot` kanonik MMC aile 2+2 fiyatını ve gerçek ticket type ID'lerini okuyacak şekilde geliştirilip ayrı test edilecek. Fiyat sabit frontend verisinden veya referer'dan türetilemez.
3. Tek merkezî WooCommerce siparişi, MDG kapasite `hold()` transaction, `UNIQUE(request_id)`, tekrar ödeme/settlement/son koltuk yarışı ve gerçek Woo meta ile retry-verifier testleri.
4. PayTR mağazası için Milano ikinci domain/iFrame token/callback/3DS onayı ve test; PayTR resmî belgelerinde **kesin ödeme sadece POST Bildirim URL üzerinden** işlenir, `merchant_ok_url` yalnız ziyaretçi yönlendirmesidir. Tek başarılı merchant_oid ve sipariş bazlı çift callback/eşzamanlı sonuç engelleri.
5. Tikera: 1 aile=4 ayrı gerçek QR, doğru Milano PDF/e-posta/WhatsApp, doğru seans, iade ve giriş testleri. Biletinial ve gişe kapasitesi ayrı mutabakat.
6. Türkiye çerez/analitik uyumu ve Yalova satış şartları yasal inceleme. Ayrı **canlı satış onayı** gelmeden Woo/PayTR kurulumu, merkezi checkout hook veya Google Analytics açılışı yapılmaz.

Resmî PayTR: https://dev.paytr.com/iframe-api/iframe-api-1-adim ve https://dev.paytr.com/iframe-api/iframe-api-2-adim

**Sonuç:** Aşama 3'te güvenli tekrar sipariş ve 2+2 fiyat eşlemesi için kod + sentetik CI testleri tamamlandı. **Canlı ödeme/checkout entegrasyonu tamamlanmış değildir.**

# Milano — Güvenilir MMC/MDG/WooCommerce Merkezî Okuma Adaptörü

**Tarih:** 12 Ekim 2026  
**Durum:** GitHub PR #235 üzerinde **taslak PHP kütüphanesi**. Canlı Madagaskar/Milano WordPress'e yüklenmedi; REST endpoint, database migration, Woo siparişi, MDG hold, PayTR ve Tickera üretimi içermez.

## Kesin iş kuralı

Etkinlik **ya Milano Sirki ya Madagaskar Sirki** sahibindedir; iki markanın satış sitesinde aynı anda satılamaz. Milano müşterisi yalnız `milanosirki.com` üzerinden satışa girer. Teknik/mali motor ortak olabilir, ancak marka, katalog, ödeme yetkisi ve bilet iletişimi ayrılır.

## Kaynaklar — yalnız Madagaskar merkez WordPress sunucusunda çalışır

`MCPI_Canonical_MDG_Snapshot::lookup(int $event_id, int $session_id, ?int $now)`:

1. **MMC sahipliği:** `wp_mmc_mdg_event_bridge` içindeki `mdg_event_id` için **tam bir adet** aktif `program_id` aranır (`LIMIT 2` ile belirsizlik tespiti). Bu programın `MMC_Program_Service::get_program(...)->brand` değeri `Milano Sirki` değilse (Madagaskar, boş, geçersiz, birden fazla aktif bağlantı) sonuç `null`; **legacy variation eşlemesi, başlık tahmini veya kullanıcı gönderdiği brand kullanılamaz**.
2. **MDG etkinlik:** `MDG_Events::get(event_id)` kanonik kayıt. `onsale` olup olmadığı saf ön kontrol tarafından doğrulanır.
3. **MDG seans:** `MDG_DB::table('sessions')` ile `id` VE `event_id` koşullu **salt okunur SELECT**. Başlangıç ve satış aralığı UTC olarak katı biçimde ayrıştırılır; geçersiz tarih = sonuç `null`. `MDG_Sessions::sales_closed_by_time` gerçek satış kapanış kuralını kullanır.
4. **WooCommerce ürün:** MDG seansındaki gerçek `wc_product_id`; yayımlanmış variable product. `get_woocommerce_currency()` **TRY** olmalı. Değişken fiyatlar yalnız seanstaki aktif/etkisiz MDG bilet türü kayıtları ve Woo ürün varyasyonları üzerinden yeniden okunur.
5. **Bilet tipleri:** `MDG_Sessions::ticket_types_by_session`. Tekil `code`, `is_active`, `wc_variation_id`, `get_parent_id`, `is_purchasable`, `is_in_stock`, `capacity_units`, MDG/Woo fiyatları **kuruş tam sayısı** olarak döndürülür. Fiyatları float'a dönüştürmek yerine güvenilir ondalık string ayrıştırması kullanılır; kuruş altı varsa reddedilir.
6. **Kapasite:** `MDG_Capacity::available(session_id)`, yalnız ana merkezî rezervasyon/satılmış birim defteri. Bu bir snapshot'tır: fiyat/kapasite satın alma işlemine kadar değişebilir, kesin rezervasyon değildir.

**Adaptör her belirsizlikte `null` döndürür; checkout izni açmaz.** Dönen snapshot müşteri PII, telefon, kimlik, PayTR parolası, token veya özel sipariş bilgisi içermez.

## İlgili PHP modülleri

- `milano-checkout-intent.php`: Milano server'dan HMAC-SHA256 imzalı kısa süreli talep; müşteri fiyatı taşımaz.
- `milano-canonical-mdg-snapshot.php`: yalnız merkezi site üzerinde gerçek MMC/MDG/Woo verisini okuyup snapshot oluşturur.
- `milano-central-eligibility.php`: snapshot + imzalı talep birleşimini yeniden doğrular; `total_cents` ve `admission_units` hesaplar.
- `milano-order-claim-registry.php`: bir `request_id` başına tek atomik claim, `order_id` tekilleştirmesi **taslağı**; gerçek tablo/DB mutation henüz yok.
- `milano-central-preflight.php`: imza doğrula → merkez snapshot okuyucu → uygunluk → claim sırası. Ancak canlı bir REST endpoint veya ödeme sistemi değildir.

## Aile paketi / kapasite farkı

Bir `FAMILY4` test bilet türü `price=1100.00 TL`, `capacity_units=4`, `quantity=1` ise geçici uygunlukta `110000` kuruş ve **4 giriş birimi** hesaplanır. Bu testteki tip kodu bir **fixture örneğidir**, gerçek ürün kataloğunda paket kodu/UID doğru eşleştirilmelidir. Gerçek 2+2 satışta **4 ayrı native QR bileti** çıktığı Tickera/Bridge entegrasyonunda ayrıca doğrulanacaktır.

## Testler ve sınırlar

`integrations/milano/tests/canonical-mdg-snapshot-contract.php` içindeki **43 izole senaryo**, MMC yanlış/belirsiz sahiplik, olmayan/çelişkili MDG seans, satış zamanı, eksik veya hatalı Woo ürünü, varyasyon/fiyat uyuşmazlığı, döviz türü, son kapasite ve aile paketi kontrolünü çalıştırır. Test `wp_db` ve `WooCommerce` taklitleri kullanır; canlı trafik, gerçek MySQL veya PayTR ödeme yapmaz.

Sadece GitHub Actions PHP lint/contract testinin geçmesi gerçek sunucuya kurulum veya güvenli ödeme kanıtı değildir.

## Kritik üretim/staging önkoşulları

1. **WordPress.com Atomic staging** ortamı ve gizli sunucu-sunucu yetkilendirmesi. Milano tarayıcısı, JS, query parametreleri, Referer veya `Origin` kesin kaynak değildir.
2. Merkezi program–etkinlik atamaları tekil ve doğrulanmış olmalı. Mevcut 13 Madagaskar MMC programı yanlışlıkla Milano'ya geçirilemez.
3. Aynı anda iki son koltuk alımı gibi gerçek **InnoDB / MDG_Capacity::hold** ve fiyat değişikliği yarış testi; snapshot ile order/hold arasında tekrar doğrulama.
4. WooCommerce order-meta `_dunya_source_brand` ve `_dunya_checkout_request_id`, DB UNIQUE `request_id` migration ve crash recovery mutabakatı.
5. PayTR mağazasının Milano alan adı, iFrame ve merkezi callback'e izin verdiğine dair yazılı onay + 3DS, ödeme imza doğrulaması, retry ve başarısız ödemede stok iadesi.
6. Tickera 2+2 için 4 ayrı QR, tekrarlı ödeme bildiriminde ikinci QR üretmeme, iptal/iade, Biletinial/gişe kanal mutabakatı.
7. Ayrı kullanıcı onayı olmadan PR merge, canlı eklenti kurma, canlı ödeme açma, Milano launch **YOK**.

## Bağlantılar

- Taslak PR: https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/235
- Drive çalışma günlüğü: https://docs.google.com/document/d/1GSV-_niF39NTkB45--sqodofxDVH25S7XeKxefzUa4Q/edit

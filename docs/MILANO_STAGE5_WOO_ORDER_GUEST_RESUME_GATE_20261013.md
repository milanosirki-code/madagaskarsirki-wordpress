# Milano Satış Entegrasyonu — Aşama 5: Woo sipariş sahibi ve misafir geri erişim güvenliği

**13 Ekim 2026 — GitHub-only, staging hazırlığı.** Kod dalı: `feature/milano-woo-resume-identity-stage5-20261013`. İnceleme: [taslak PR #241](https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/241), taban PR #239 / geliştirme #235. **Canlı WordPress'e eklenti kurulmadı, ödeme veya sipariş oluşturulmadı.**

## Çalışma sonucu

Merkezdeki `MCPI_Central_Preflight` önce `request_id` kaydını salt okunur inceleyip aynısıysa eski Woo siparişine dönebiliyor; ancak bu **tek başına müşteri yetkisi değildir**. Yanlış kişiye başka müşterinin Woo sipariş ID'sini göstermek engellenmeli. Eski saf `MCPI_Order_Retry_Verifier` kaynak siparişin kimlik, marka, seans, imzalı sepet özeti, para birimi ve durumunu bekliyor; gerçek Woo CRUD kaynağı henüz yoktu.

### Yeni güvenlik adaptörü

Dosya: `integrations/milano/checkout-intent-protocol/milano-woo-order-resume-reader.php`

- `MCPI_Woo_Order_Resume_Reader::lookup($order_id, $trusted_customer_id)`, yalnız merkez WordPress sunucusunda `wc_get_order()` + Woo CRUD / HPOS ile **salt okunur** meta/kimlik doğrular; `wp_postmeta` SQL'e ve checkout browser formuna güvenmez.
- `$trusted_customer_id` yalnız doğrulanmış merkez Woo/WordPress oturum kimliğinden gelmeli; kullanıcıdan gelen URL/body/customer_id parametresi yetki değildir.
- Woo siparişinin gerçek `get_customer_id()` ile doğrulanmış oturum sahibi **eşit ve pozitif** olmalı. Sahte ID, fazla büyük, negatif, kesirli, bozuk sayılar kapanır.
- **Bu ileride yazılacak Milano sipariş worker'ına ayrılmış sözleşme metaverileri** beklenir: `_mcpi_source_brand=milano`, `_mcpi_event_id`, `_mcpi_session_id`, `_mcpi_request_id`, `_mcpi_intent_payload_hash`. Şimdiye kadar canlı Woo siparişlerine yazılmadılar. Eksik veya bozuksa kapanır; mevcut Madagaskar kayıtları yanlışlıkla Milano kabul edilmez.
- Currency = TRY, Woo status = `pending/on-hold/processing/completed`. `cancelled/refunded/failed` vb. siparişler geri açılmaz. Dönen snapshot PII, e-posta, müşteri ID, PayTR token veya kart verisi taşımaz.
- Saf `MCPI_Order_Retry_Verifier::allows()` ayrıca `signed_intent` ile meta `request_id`, SHA256 imzalı sepet özeti, event/session ve order ID eşleştirir. Sadece customer ID ve sipariş ID ile yetki kazanılamaz.
- Bu kod henüz gerçek `order_lookup` handler'ına otomatik bağlanmıyor; geliştirici güvenlik katmanını staging servisinde entegre etmeli. Merkezi Woo order worker bu güvenilir metaları doğrulanan tek işlemde yazmadıkça olumlu sonuç dönmez.

### Canlı ayar kanıtı — salt okunur WPVibe WP-CLI

`madagaskarsirki.com`:
- `woocommerce_enable_guest_checkout = yes`.
- `woocommerce_enable_signup_and_login_from_checkout = no`.
- `woocommerce_currency = TRY`.

**Önemli ticari karar:** Müşterinin hesap açmadan bilet alması mevcut modelin parçasıdır. Aşama 5 reader, **yalnız daha önce oluşturulmuş siparişe GERİ erişimi** başlatmak için kayıtlı WordPress müşterisini destekler; guest `customer_id=0` için fail-closed. Bu, misafir müşterinin ilk checkout'unun kapatılmasını gerektirmez. Misafir `Biletlerim`/sipariş takip için ayrı güvenli, süreli, tek kullanımlık e-posta/telefon OTP veya doğrulanmış magic-link hakkı gerekli. Sipariş numarasını bilen herkes QR/PDF alamaz; e-posta/telefonun sadece siparişte yazıyor olması yetki kanıtı değildir.

### Test / CI

Yeni izole PHP test dosyası: `integrations/milano/tests/woo-order-resume-reader-contract.php`.

31 senaryo: dört izinli Woo order state; yanlış müşteri, guest sipariş/kimlik, overflow/negatif/sahte ID; yanlış order; başka marka/eksik marka; yanlış event/session/request/hash, format hatası; yanlış para birimi; cancelled/refunded/failed/trash; bozuk eski sipariş; exception; aynı müşterinin farklı intent veya seansı. `milano-feed-contract.yml` lint ve senaryoları CI'ya dahil edildi.

Commit `7fd1ae2d2c92f09132f78a170155f2550806ec6e` üzerinde **Changed PHP Syntax** ve **Milano read-only session feed contract** GitHub Actions **SUCCESS**. Bu yalnız **sentetik Woo nesne testleridir**; gerçek staging Woo/HPOS müşteri erişim kontrolü, PayTR callback, stok rezervasyonu veya Tickera QR testleri geçilmiş sayılmaz.

## Canlı MMC/MDG ve PayTR gerçek durumu

- Son salt okunur MMC SQL: 13 Madagaskar program / 0 Milano; Yalova 22.10.2026 program/MDG etkinliği hâlâ oluşturulmamış. Etkinliği diğer sohbet hazırlamaktadır. Yalova 18:00/19:30 500 kişi/seans, serbest oturma, yetişkin 490, çocuk 245, aile 2+2 1100 TL değişmedi. Mevcut Madagaskar programları değiştirilmedi.
- Yeni taslak merkezi `MCPI_Canonical_MMC_Family_Policy` ve MDG snapshot aile fiyatı okuma kodu PR #239 ile hazır; canlı MMC'ye dağıtılmış değildir.
- Milano tanıtım sitesi public, Milano ödeme sayfası/PayTR/Tickera satış açık değil.
- PayTR iFrame resmî belgeleri: birinci adım server-side token, ikinci adım Callback/Bildirim URL POST, imza doğrulama ve plain text `OK`, tekrar `merchant_oid` bildirimlerini çift kesinleştirme olmadan ele alma. Callback sunucu işlemidir, kullanıcı oturumu yoktur; `merchant_ok_url` ödeme onayı yerine kullanılamaz.
  - https://dev.paytr.com/en/iframe-api/iframe-api-1-adim
  - https://dev.paytr.com/en/iframe-api/iframe-api-2-adim

## Sıradaki engeller ve güvenli teslim sırası

1. İlk Yalova MMC programı `Milano Sirki` markası ve tek aktif MDG bağlantısıyla **diğer sohbetin** kontrollerinden geçsin, mevcut Madagaskar programına dokunulmasın.
2. Yalova source snapshot gerçek 490/245/1100 TRY fiyatlarıyla, iki ayrı 500 kişilik seansla read-only canary doğrulansın.
3. Merkezi Woo sipariş oluşturma worker'ı `_mcpi_*` meta / `request_id` benzersizliği / atomik hold ve başka marka reddini staging'de uygulasın. Customer ID ancak merkezde yetkili oturumdan çıkartılsın.
4. Misafir müşterinin tekrar bilet görüntüleme/teslimi için sunucu kontrollü süresi kısa e-posta/WhatsApp doğrulaması + kullanıcı hakkı/tek kullanım/geri alma/audit; gerekli KVKK sözleşmeleri ve testleri. Ham QR URL'si tahmin edilebilir parametre olmamalı.
5. PayTR yetkili mağaza/destek biriminden **milanosirki.com ikinci alan adının** mevcut mağazada iframe + merkezî callback/3DS ile kullanılabileceğine dair yazılı teyit. PayTR güvenlik anahtarları GitHub/Drive/JS'e yazılmaz.
6. Eşzamanlı son koltuk, mükerrer callback/OID, tek Woo sipariş, 2+2=4 ayrı Tickera QR, iptal-iade, muhasebe ve çapraz marka REST/order-pay/old URL end-to-end staging testleri.
7. **Açık canlı satış onayı** öncesinde GitHub taslak PR'lar MERGE/DEPLOY EDİLMEZ, canlı ödeme kodu veya Madagaskar aktif Woo/PayTR/Tickera işleyişi değiştirilmez.

**Bu aşamada yalnız kod, sentetik test ve belge üretildi; üretimde müşteriler için çalışır checkout veya gerçek QR hakkı yoktur.**

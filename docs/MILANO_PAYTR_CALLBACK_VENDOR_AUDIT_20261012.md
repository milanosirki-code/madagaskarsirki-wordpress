# Milano Sirki — PayTR 3.1.2 canlı kaynak denetimi ve salt-okunur ön kontrol
12 Ekim 2026 • **Aşama: GitHub taslak / staging sözleşmesi** • canlı müdahale YOK

## Erişilen gerçek, kurulu kaynak (WPVibe wp-content salt okunur)
- `plugins/paytr-sanal-pos-woocommerce-iframe-api/includes/class-paytr-payment-gateway-iframe.php` satır 24, 67–70, 348–364: PayTR Woo gateway `paytr_payment_gateway`; webhook `woocommerce_api_wc_gateway_paytrcheckout`; adres formatı `/index.php?wc-api=wc_gateway_paytrcheckout`. Sipariş ödeme sayfası kendi WooCommerce sipariş alan adına yönlenir.
- `plugins/paytr-sanal-pos-woocommerce-iframe-api/includes/PaytrCoreClass.php` satır 25–33, 112–127, 183–190, 242–256, 320–343: `merchant_oid = time().'PAYTRWOO'.order_id`, ödeme tutarı `order->get_total()*100`, PayTR tokenı server-side alınır. Siparişe `paytr_payment_attempts` (oid, amount, currency, token cevabı durumu) eklenir, token başarısında `paytr_pending_order_id` saklanır.
- `plugins/paytr-sanal-pos-woocommerce-iframe-api/includes/class-paytrspi-callback-iframe.php` satır 10–40, 83–98, 101–123: callback HMAC-SHA256 ile oid+merchant_salt+status+total_amount doğrular. Ardından `merchant_oid` içinden WC ID çıkarır, ödeme denemesi meta'sına callback bilgisi yazar. Sipariş `pending` veya `failed` olduğunda `success` bildirimi üzerinden `wc_reduce_stock_levels()` ve `update_status()` çalışır. İade/ödeme sonrası farklı yollar başka dosyalarda da olabileceği için tam bilet çoğaltma davranışı henüz kanıtlanmadı.
- **Güvenlik sınırı:** Bu kaynak salt-okunur incelendi; gizli anahtarlar okunmadı veya dışarı aktarılmadı. Kurulu PayTR, Tickera, Woo, MDG, MMC ve WordPress seçeneklerine hiçbir yazma yapılmadı.

## Kaynak incelemesine dayanan riskler
1. **Doğru merchant_oid / aktif ödeme denemesi bağı:** Üretici `paytr_pending_order_id` kaydını tutuyor. Okunan callback, imza doğrulamasından sonra gelen oid'nin siparişteki güncel `paytr_pending_order_id` ile eşleşmesini doğrudan zorunlu kılmıyor. Eski ödeme denemelerinin ve farklı sipariş kimliklerinin doğrulanması tasarlanmalı.
2. **Tutar mutabakatı:** Callback HMAC; oid, status, total_amount alanlarını imzalar. `payment_amount` ve `currency` bildirim alanları bu hash'in girdisi değildir. Siparişte saklanan ilk ödeme denemesi kuruş tutarı ve marka/para birimi sunucu üzerinden doğrulanmalı. PayTR resmî dokümanına göre `total_amount` vade farkıyla ilk tutardan yüksek olabilir; bütün farkları dolandırıcılık diye sınıflandırmak yerine tutar eşleşmesi veya manuel mutabakat gerekir.
3. **Eşzamanlılık:** Okunan callback, mevcut Woo `pending|failed` durumunu sınar, stok azaltır ve sipariş durumunu değiştirir. Bu tek başına eşzamanlı iki callback'in ikisinin de aynı önceki durumu okuyup işlemeyeceğine dair atomik garanti vermez. WC stock reducer, MDG hold/commit ve Tickera Bridge bilet-instance üretimi staging'de birlikte ölçülmeli.
4. **Bilinmeyen sipariş / durum:** Callback `wc_get_order` sonucunun yokluğu üzerine önceden fail-closed açık koruma göstermiyor; üreticiye patch veya uyumlu checkout eklentisi için ayrı, izole düzeltme gerekiyor. Bu canlı sistemde şu an hata yaşandığı anlamına gelmez.
5. **Çok alan adı:** Callback mevcut WordPress Woo alan adına bağlı. Milano'yu ikinci domen olarak otomatik yetkili varsaymak yanlış. Ticari PayTR mağaza ve 3DS yönlendirme izinleri doğrulanmadan Milano checkout'a açılmamalı.

## Bu çalışmada eklenen GitHub kodu (CANLI DEĞİL)
- `integrations/milano/checkout-intent-protocol/milano-paytr-callback-preflight.php`: saf / yan etkisiz `MCPI_PayTR_Callback_Preflight::check()`. PayTR imzası `hash_equals` ile doğrulanır, sipariş ID/OID/aktif ödeme denemesi/marka/PayTR ödeme yöntemi/TRY/ilk tutar ve nihai durum kontrol edilir.
- `integrations/milano/tests/paytr-callback-preflight-contract.php`: izolasyonlu olumlu, eksik, tekrar, sahte ve çapraz marka senaryoları. Hata/fail-closed, daha fazla tahsilat olduğunda `manual_review`, kesinleşmiş siparişte `already_final`. Ücret farkı otomatik ücret kalemi yaratmaz.
- `.github/workflows/milano-feed-contract.yml`: PHP lint + otomatik senaryolar.
- **ÖNEMLİ:** Bu sınıfta WordPress `add_action`, HTTP endpoint, sipariş değişikliği, stok azaltma, PayTR anahtarı, SQL tablo veya Tickera çağrısı YOK. Bir preflight sonucu **ödeme gerçekleştirme izni değildir**. Özellikle aynı anda gelen callback için gerekli **atomik benzersiz settlement claim** bu dosyada bulunmaz.

## Canlıya alınmadan tamamlanması zorunlu işler
- PayTR destek/panelinden `milanosirki.com` üzerindeki iFrame/3DS, aynı işyeri hesabı ve merkezî callback adresinin yazılı uygunluk teyidi.
- PayTR vendor 3.1.2 ile doğru safhada, HMAC'den **sonra** ve stok/durum güncellemesinden **önce** çalışan, güncellemelerde korunan uyumlu hook/adapter tespiti; vendor dosyasına doğrudan canlı patch yapılmaması.
- Atomik `merchant_oid` tekilleştirmesi, sipariş durumu + MDG hold + WC/Tickera issuance için transaction/lock ve başarısız/tekrar/geri ödeme durum makinesi.
- Ortam değişmeden staging üzerinde son koltuk yarışı, tekrar callback (aynı anda iki işçi), 2+2 aile 4 fiziksel QR, iptal/iade ve geçmiş Madagaskar bileti regresyonları.
- Milano'ya ait ilk yeni etkinliğin şehir, tarih, salon, seans ve `event_owner_brand` doğrulaması; eski Madagaskar satılmış programlar değiştirilmeyecek.
- Ayrı canlı yayın ve ticari ödeme açma onayı.

Resmî PayTR: https://dev.paytr.com/iframe-api/iframe-api-2-adim
Kod/PR: https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/235
Durum: Milano Çok Yakında / satış kapalı; Madagaskar canlı satışları değişmedi.

## Aynı merchant_oid için atomik callback claim (ek taslak)
- `integrations/milano/checkout-intent-protocol/milano-paytr-settlement-claim.php`: ön kontrolün `ready_success / ready_failure` kararı sonrasında, `merchant_oid` PRIMARY KEY (InnoDB) için insert-first claim yapar. Başka worker'da aynı OID => `processing`; bitmiş => `already_final`; hash/order/status çatışması => `conflict`; bozuk/crash => `reconcile_required`. Tarihi geçmiş `claimed` satırı **otomatik devralınmaz**. DDL sadece metin üretir, tablo oluşturmaz.
- `integrations/milano/tests/paytr-settlement-claim-contract.php`: tekrarlı worker, yanlış token, tek seferlik finalize, signed error, stale claim ve eksik bağlantı için izole testler. Gerçek InnoDB yarış testi değildir.
- **Önemli ikinci koruma:** `merchant_oid` tekilliği **aynı Woo siparişinin iki ayrı PayTR ödeme denemesi** arasında yeterli değildir. Sipariş düzeyi atomik durum kilidi, nihai MDG stok hold/commit, son koltuk, tek Tickera QR kimliği ve çökme sonrası mutabakat hâlâ zorunludur.
- **Daha önemli işlem sırası:** `finish()` yalnız merkezi Woo/MDG/Tickera etkileri tekillik garanti edilerek tamamlandıktan sonra ve doğrulanmış worker token ile çağrılmalıdır. Bu koşulu sağlayan canlı servis **henüz mevcut değil**. Bu kodu tek başına WordPress'e kurmak veya doğrudan callback'e bağlamak yasaktır.


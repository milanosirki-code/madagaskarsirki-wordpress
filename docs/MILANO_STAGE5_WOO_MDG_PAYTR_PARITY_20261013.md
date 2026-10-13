# Milano Sirki — Satış entegrasyonu Aşama 5
## Tek Woo siparişi, doğrulanmış MDG rezervasyonu ve PayTR tutar eşitliği

**Tarih:** 13 Ekim 2026. **Durum:** Yalnız geliştirme dalında PHP kontratı ve izole otomatik testler. **Canlı WordPress/Woo/PayTR/MDG/Tickera işlemi yapılmadı**.

PR: https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/239
GitHub dalı: `feature/milano-retry-safe-preflight-20261013`

## 1. Canlı salt okunur durum ve kesin kullanıcı kararları

- Canlı Madagaskar MMC: **13 Madagaskar marka, 0 Milano marka program**. Yalova/22.10.2026 MMC programı henüz yok. İlgili ilk Milano etkinliği **diğer sohbette** hazırlanıyor; mevcut kayıtları bu dalda elle değiştirmedik.
- Yalova 22 Ekim 2026, 90. Yıl Kapalı Spor Salonu, **18.00/19.30**, **seans başına 500**, serbest/numarasız oturma. Yetişkin 490 TL, çocuk 245 TL, **2+2 aile 1.100 TL / dört giriş**.
- Madagaskar'da WooCommerce canlı salt-okunur option kontrolleri: `woocommerce_currency=TRY`, `woocommerce_calc_taxes=no`, `woocommerce_prices_include_tax=no`. Bunlar Woo checkout hesaplama ayarlarıdır, **fatura KDV yasal yükümlülüklerini ortadan kaldırmaz**. Finans/fatura KDV mutabakatı ayrıca şarttır.
- PayTR resmî iFrame API: tokenın ilk adımda yalnız sunucudan alınması, `payment_amount` için 100x kuruş tamsayısı; ikinci adım PayTR POST callback'te `merchant_oid`, `status`, `total_amount` ve HMAC `hash` denetlenmesi gerekir. `total_amount` taksit/fark halinde başlangıç `payment_amount` tutarından yüksek olabilir; aradaki fark normal siparişten ayrı muhasebe/uzlaşma ister. `merchant_ok_url` ödeme kanıtı değildir.
- Kaynak: https://dev.paytr.com/iframe-api/iframe-api-1-adim , https://dev.paytr.com/iframe-api/iframe-api-2-adim

## 2. Yeni saf kod: Merkezi Woo + MDG hold → PayTR token ön kontrolü

`integrations/milano/checkout-intent-protocol/milano-before-paytr-order-gate.php`

Yeni `MCPI_Before_PayTR_Order_Gate::check($quote,$order,$hold,$tax_calculation_enabled,$signed_request_id)` yalnız **sunucu tarafında doğrulanmış kanonik** verilerle kullanılmak içindir.

- Tek Woo order `pending`, `source_brand=milano`, `currency=TRY`, `paytr_payment_gateway`, event/session/MDG product bire bir doğrulanır.
- Woo'nun `checkout_request_id` özel meta kimliği, önceden kriptografik doğrulanmış tek `signed_request_id` ile bire bir uyuşmak zorundadır. Yalnız istemcinin bildirdiği ID yeterli değildir.
- MDG merkezî rezervasyonu `held` durumda, aynı order/event/session/brand için ve fiyatlandırılmış gerçek QR giriş hakları kadar `units` değerindedir. Başka seansın/markanın hold'u kabul edilmez. Bu bir **okuma/karar kontratı** olup MDG hold yaratmaz.
- Tekil gerçek Woo bilet varyasyon ID'leri, seans, adet, satır ara-toplam, satır toplamı, kapasite katsayısı 1, yanlış kod, yinelenen Woo line ID ve eksik/fazla satırlar reddedilir. Aile paketi `FAMILY22` sanal 4-seat varyasyonu Woo'ya ürün olarak giremez.
- Merkezi family quote üzerinden **2 ADULT×490 TL + 2 CHILD×245 TL = 1.470 TL**; tam bir `MILANO_FAMILY22_DISCOUNT` Woo negatif fee **−370 TL**; Woo `total_cents` **110000**; MDG hold **4 kişi**. İki aile: 8 kişi / 2.200 TL. Ek çocuk: 5 kişi / 1.345 TL. Gerçek `PayTR payment_amount` yalnız doğrulanmış Woo toplamından alınabilir.
- Bilinmeyen kupon/ek ücret, bozuk fee, farklı Woo sipariş toplamı veya sipariş satırı ve farklı rezervasyon durumunda `blocked`; hiçbir PayTR token/ödeme üretilmez.
- Vergi hesaplama aktif veya Woo'nun herhangi bir sipariş/satır KDV tutarı sıfır değilse **bu pilot gate kapalı** kalır. KDV etkinleştirilmesi ayrıca vergi dahil/hariç, fee vergi etkisi ve fatura mutabakatına göre tasarlanmalıdır.
- `eligible` sonucunda yalnız merkezi işçiye `payment_amount_cents`, `admission_units`, `order_id` döner; `token_requested=false`. Public REST API bu dahili sonuçları müşteri erişimi ispatı olmadan paylaşamaz.

## 3. İzole testler ve neyi kanıtlamadıkları

Yeni test:
`integrations/milano/tests/before-paytr-order-gate-contract.php`
ve GitHub Actions `.github/workflows/milano-feed-contract.yml` senaryo döngüsü.

Kontroller:
- İzinli: 1 aile (4×giriş/1.100 TL), iki aile (8×giriş/2.200 TL), +1 ücretli çocuk, normal 1 yetişkin+1 çocuk.
- Kapalı: yanlış brand/owner/MDG hold, farklı order/event/session/product, eksik/başka siparişe ait rezervasyon, held dışı durum, tutar farkı, yanlış fee veya ikinci kupon, Woo ile quote miktar/kod/varyasyon uyuşmazlığı, tekrar Woo order item ID, 4-unit sahte aile varyasyonu, yanlış/sahte signed request ID, para birimi, gateway, paid/on-hold order, bilinmeyen vergi modu, hatalı seans veya fiziki bilet sayısı.
- Commit `65ec4ee3d5c61c1eb018c2cf0576729e3fbf724a` için GitHub Actions **Changed PHP Syntax SUCCESS** ve **Milano read-only session feed contract SUCCESS**. Bu tamamlama yalnız izole PHP fixture'ları kapsar; canlı/paytr test ödeme kanıtı değildir.

## 4. Kalan uçtan uca bağlama işleri / canlı engeller

1. Yalova kanonik **Milano** MMC programı, bağlı tek MDG event ve 18.00–19.30 ayrı seansları diğer sohbette tamamlanmalı. Var olan Madagaskar 13 programı korunmalı.
2. İlk canlıya uygun sürüm için **staging WordPress** gereklidir: producer/consumer kurulum ve güvenli feed/checkout ayrı HMAC secret; merkezi WordPress/Woo API adapter'ı, tek Woo order ve atomik MDG hold, gerçek ürün varyasyonu ve aile indirim fee meta'ları henüz yok.
3. PayTR mağazasından `milanosirki.com` ikinci domain, iframe/3DS, marka ve merkezi callback izni yazılı teyit edilmelidir; mağaza secretları sadece güvenli sunucu ayarlarında kalmalı.
4. İmzalı callback, `merchant_oid` tekilleştirmesi, order-level settlement lock, MDG `commit_order()` idempotency ve WC sipariş state mutasyonu tek koordineli iş akışına eklenmeli. PayTR çift callback veya ikinci merchant OID aynı siparişi iki kez biletlememeli. Gerekirse kurtarma/manuel uzlaşma.
5. Tickera aile 2+2 → 4 gerçek QR, doğru Milano PDF/WhatsApp/Kommo, doğru seans, iade ve check-in; Biletinial/gişe dış kanal kapasitesinin de ayrı kontrol edilmesi.
6. KVKK Türkiye opt-in çerez/analitik tercihi, ödeme/bilet yasal metinleri, doğru KDV/fatura tutarları ve açık **canlı satış aktivasyon onayı**. Bunlar olmadan Milano frontend satın alma bağlantısı, WordPress Woo/PayTR kurulum ve Madagaskar üretim Woo hook'ları açılmaz.

**Gerçek durum:** Milano satış entegrasyonundaki bu kontrol, markaya özgü ödeme açılışından **önceki güvenlik katmanıdır**. Mevcut canlı Milano site tanıtım amaçlı yayındadır; **satış hâlâ kapalı**. Gerçek test amaçlı para tahsil edilmedi, QR veya Woo order yaratılmadı.

# Milano Sirki — Sipariş başına tek ödeme & aile paketi fiziksel bilet doğrulaması
13.10.2026 — **DRAFT / yalnız GitHub staging sözleşmesi; canlıya alınmadı**

## Karar / sabit şart

- Milano ve Madagaskar iki **ayrı marka ve tek etkinliğe tek satış sitesi**. Siparişten veya tarayıcıdan gelen brand etiketi yetki vermez; sahibi merkezi MMC/MDG köprüden tekil olarak doğrulanır.
- Satışsız yayımlama hazırlığı yapılabilir. **Milano hâlâ Çok Yakında**, PayTR/Madagaskar canlı satışlarına yazma yapılmaz.
- **Bir WooCommerce siparişi, bir başarılı settlement kazananı** olmalıdır. Aynı siparişin aynı `merchant_oid` tekrar bildirimi ve iki farklı `merchant_oid` ödeme denemesi ayrı risklerdir.
- **Bir fiziksel giriş hakkı bir benzersiz Tickera bilet kodu**. Ticari ürün sayısı fiziksel giriş sayısına eşit olmayabilir: aile 2+2 bir ürün/satır + dört kişi ise **4 farklı giriş hakkı/QR** beklenir.
- Bu başlıkların hepsi staging sözleşmesidir; **gerçek canlı garanti sayılmaz**.

## Doğrulanmış kaynaklar

1. Madagaskar WordPress'teki kurulu PayTR 3.1.2 dosyalarında `merchant_oid = time() . 'PAYTRWOO' . Woo order_id`, `paytr_payment_attempts`, `paytr_pending_order_id` alanları gözlemlendi (WPVibe salt okunur). Gerçek callback PayTR imzasını doğruluyor fakat en son ödeme denemesi ve sipariş marka düzeyi kontrolü ikinci bir katman gerektiriyor. Ayrıca tekrar/yarış için atomik mutabakat henüz garanti değil.
2. PayTR resmî bildirim dokümanı: https://dev.paytr.com/iframe-api/iframe-api-2-adim. Aynı işlem için tekrar callback gelebilir; ikinci kez mal/hizmet verilmemeli. Taksit halinde `total_amount` ilk `payment_amount` değerinden yüksek olabilir.
3. Tickera resmî dokümantasyonu: https://tickera.com/tickera-documentation/install-tickera-plugin-bridge-for-woocommerce/ — Woo siparişi processing/completed olunca Tickera bilet üretim akışı tetiklenir (genel ürün davranışı). Tickera blogu https://blog.tickera.com/tickera-in-2026-refined-reliable-ready/ `tc_created_order_ticket_instance` sonradan-oluşturma hook'undan söz eder. Bu **sonradan oluşan bilet için bildirim** olabilir, üretim öncesi yarış engeli olduğu **çıkarılamaz**.
4. Gerçek kurulu Tickera verilerinde `tc_tickets_instances` 1.322 kayıt, bunlardan 1.251 publish/71 trash; tüm ticket code, Woo `item_id` ve `post_parent` eşleşmeleri veri düzeyinde tutarlı. Publish 779 Woo satır / 1.251 bilet / 0 miktar farkı (12 Ekim salt okunur toplu SELECT). Bunlar geçmiş kayıtların veri tutarlılığı, yeni Milano satın alımının eşzamanlı garantisi değil.
5. **Sınırlama:** Bridge ana PHP dosyası 297 KB; bağlantının `read_file` sınırı 64 KiB. Bu nedenle **kurulu sürümün gerçek bilet yazan hook'u hâlâ incelenmiş değil**. Resmî genel dokümantasyondan kurulu sürümün bire bir davranışı teyit edilemez.
6. Canlı veride `Aile`/`Paket` adlı WC ürün/sipariş satırı araması sonuç vermedi; dolayısıyla 2+2 paketlerin tarihsel olarak **tek ticari satır mı, indirimli dört satır mı** olduğu bu sorguyla doğrulanamadı. Model bu iki ihtimali ayrı denetler; eski sistemin nasıl çalıştığı varsayılmaz.

## GitHub PR #235 üzerinde bu aşamada eklenenler

### 1. Sipariş düzeyi başarılı ödeme kilidi

`integrations/milano/checkout-intent-protocol/milano-paytr-order-settlement-lock.php`

- MySQL InnoDB öneri DDL: `PRIMARY KEY(order_id)` + `UNIQUE KEY(merchant_oid)`. DDL yalnız metin olarak hazırlanır; tablo **oluşturulmadı**.
- Trust boundary: başarılı, hash'i doğrulanmış ve merkezî Woo order ID, `source_brand=milano`, son `paytr_pending_order_id`, ilk tutar, Woo para birimi ve başarılı PayTR token kaydı doğrulanmış callback preflight'inden geçmelidir.
- Birinci başarılı OID `claimed`; aynı OID yinelenirse `processing`, farklı başarılı OID gelirse `different_payment_attempt`; önce kesinleşmiş işleme `already_final`; bozuk/crash `reconcile_required`.
- Başarısız ödeme sonucu sipariş kazananı kilidini işgal etmez. Başarısız callback için ayrı merchant_oid tekrar kayıt sözleşmesi zaten var.
- Eski/eski durumlu claimant **kendiliğinden devralınmaz**. `mark_final()` sadece yetkili sunucu worker'ın *gerçekten* ödeme alındığı, MDG kapasitesinin sabitlendiği ve tüm Tickera biletlerinin tekil üretildiği doğrulamasından **sonra** çağrılabilir.
- **Önemli:** Ayrı merchant-oid ledger + order lock + admission ledger tablolarının işlemleri bu taslakta tek atomik transaction içinde koordineli **değildir**. Bu yüzden yayın için yeterli değildir.

Tests: `integrations/milano/tests/paytr-order-settlement-lock-contract.php` — 23 izole senaryo. CI workflow: `.github/workflows/milano-feed-contract.yml`.

### 2. Aile paketi ve tüm bilet türleri için fiilî giriş mutabakatı

`integrations/milano/checkout-intent-protocol/milano-ticket-fulfillment-audit.php`

- Tekil kanonik quote satırı: `code`, `quantity`, `capacity_units_per_ticket_type`, `line_admission_units`, `variation_id`, sunucudan çözülmüş `ticket_type_id`.
- Woo satırı: `order_item_id`, gerçek ticari `quantity`, `variation_id`, güvenilir sunucu ilişkisinden gelen `mdg_session_id`. Kullanıcı/browser meta anahtarına kör güven **yok**.
- Her Tickera bileti: doğrulanmış `id`, ham QR içermeyen `code_hash`, `order_id`, Woo `order_item_id`, `event_id`, `ticket_type_id`, `status`.
- Gerekli kimlik, adet, etkinlik, tür, marka ve seans eşleşmeleri ardından şu eşitlik aranır:

`Expected admissions = Σ(quoted quantity × capacity_units_per_ticket_type) = Count(unique published ticket instances)`

- 1 × FAMILY4 = 4 fiziksel QR / 2 × FAMILY4 = 8 fiziksel QR; iki yetişkin + iki çocuk şeklinde dört ayrı bilet satırında da 4 fiziksel QR.
- Sipariş henüz ödenmediyse veya MDG hold kesinleşmemişse `ok=false`. Kullanıcı adı, ham ticket_code veya ödeme anahtarı tutulmaz.
- Çalışma salt algoritmadır. Gerçek uygulama, canlı satır ile quote bağının nasıl kurulduğunu, Tickera Bridge otomatik üretiminin modelle çakışıp çakışmadığını ve QR geçerliliğini ayrıca doğrulamalı.

Tests: `integrations/milano/tests/tickera-fulfillment-audit-contract.php` — 32 izole senaryo.

## Sıradaki yayın önkoşulları

1. PayTR'ye Milano alan adının ve merkezi callback satış modelinin resmi uygunluk teyidi (yazılı); test işlemi için kontrollü staging.
2. Gerçek Bridge bilet yaratma metodunun ve ödeme durumu hook'larının **kurulu sürüm kodundan** doğrulanması. `tc_created_order_ticket_instance` after-create olduğundan tekilleştirme kaynağı ilan edilemez.
3. Tek MySQL transaction / atomik iş orchestration, Woo durum geçişi, MDG hold commit, Tickera instance yazımı ve çökme sonrası mutabakat tasarımı. Ayrı ledger'ları rastgele sırayla bağlamak yasak.
4. Aynı siparişe iki ayrı PayTR `merchant_oid`, aynı `merchant_oid` çift callback, iki eşzamanlı worker, aile paketi 1 satır/4 giriş, iptal/iade, son koltuk ve eski Madagaskar biletleri regresyon testleri.
5. Tek tek yaş doğrulaması, bilette marka/etkinlik ve doğru QR check-in; Milano etkinliği Madagaskar'da satılmamalı.
6. Milano açılışı için ayrı kullanıcı onayı. Önceki üretim verileri ve Madagaskar satışları **asla otomatik değiştirilmez**.

PR: https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/235

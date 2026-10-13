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


## 13.10.2026 — GERÇEK AİLE 2+2 PLUGIN KEŞFİ / ÖNEMLİ DÜZELTME

Canlı Madagaskar WPVibe salt okunur kaynak incelemesiyle **aktif** `madagaskar-aile-paketi-22/madagaskar-aile-paketi-22.php` **sürüm 1.1.3** doğrulandı. Daha önce yalnız isme göre Woo aile ürünü/satırı aradığımız için bunun bulunmadığını raporlamıştık: nedeni fiili modelin **sanal ürün** olması. `MDG_FAMILY_2_2` AJAX satırı `preprocess_family_line` ile gerçek `child` + `adult` bilet türlerine **2+2** dönüştürülüyor. Fiyat indirimi `woocommerce_cart_calculate_fees` ile **negatif fee**; gerçek WC satırları 2 çocuk ve 2 yetişkin `_qty` üzerinden Tickera QR üretiyor. `_mdg_family22_*` meta işaretleri siparişe kaydediliyor. Yanıltıcı eski `FAMILY4` varyasyonlu tek ticari satır **bugünkü canlı yöntem değildir** ve Milano'ya aynen uygulanmamalıdır.

Güvenlik sınırları kurulu kaynakta: her seans için tek aktif çocuk ve yetişkin kodu, gerçek `capacity_units>1` aile tipiyle aynı anda kullanımı reddetme, tek işlemde en fazla 10 aile ve her gerçek çocuk/yetişkin türünde en fazla 20 satır miktarı, fiyatı merkezî MMC'de `family_2_2` kodundan (varsa) alma ve sepet miktarı azaldığında haksız indirim almamayı hedefleyen uygulama.

Buna karşılık mevcut aile eklentisinin `MMC_MDG_Bridge_Service::program_for_mdg_event()` ile ilk eşleşmeye düşen eski geriye dönük fiyat seçimi, Milano'nun **münhasır marka** güvenlik kuralına uygun değil. Milano'ya eklenti kopyalanmayacak; merkezi **tekil aktif MMC bridge** ve marka doğrulamasıyla ayrı adaptör oluşturulacak.

**Yeni GitHub yalnız staging kod:** `integrations/milano/checkout-intent-protocol/milano-family22-normalizer.php` sunucu doğrulanmış fiyat/katalog/marka ile 1 sanal paket için 2 gerçek çocuk + 2 gerçek yetişkin satırı, 4 admission/QR beklentisi ve negatif ücret indiriminin kuruş hesabını yapar. `integrations/milano/tests/family22-normalizer-contract.php` içinde 40 senaryo vardır. Woo sepeti/PayTR/bilet üretimi **yoktur**. Eski `FAMILY4=4` kapasite soyut fixture'ları yayıma esas alınamaz; fiziksel 2+2 satırları ile plan uyuşması test edilir.

### Canlı aile paketi anonim toplu denetim (13.10.2026; anlık sayılar)

- `woocommerce_order_itemmeta` üzerinden aile işaretli **271** WC satırı, **136** aile grup anahtarı; **125 grup** tam beyan edilen `2 çocuk + 2 yetişkin` oranını taşıyor. Diğer **11** grup, ek normal bilet/miktar değişimi/yarım sepet gibi nedenlerle ayrışabilir; tek başına hata diye sayılmaz.
- Bu satırların toplamı **569** gerçek bilet miktarı; **511** yayımlanmış Tickera kaydı. Durum kırılımı:
  - `wc-processing`: 110 sipariş, 220 satır, **457 beklenen = 457 yayımlanmış**; satır farkı 0.
  - `wc-refunded`: 8 sipariş, 16 satır, **42 sipariş miktarı**, 0 yayımlanmış.
  - `wc-cancelled`: 4 sipariş, 8 satır, **16 sipariş miktarı**, 0 yayımlanmış.
  - **`wc-failed`: 14 sipariş, 27 satır, 54 sipariş miktarı ve 54 yayımlanmış Tickera bilet.** Bu 14 başarısız siparişin `paytr_pending_order_id` metası var, fakat `paytr_order_id` (başarılı işlem kimliği) metası **yok**. Bu 54 yayımlanmış bilette `_mdg_invalidated`, `_cancelled_order` ve `tc_checkins` meta kayıtları görülmedi. **YÜKSEK ÖNCELİKLİ SALT-OKUNUR İNCELEME**: bunun fiilen girişe izin verip vermediği doğrulanmalı; canlı biletler burada iptal edilmedi.
- `checkinera-premium/index.php` uygulaması doğrulama için Tickera `tc-api/{api_key}/check_in` çağrısını iletir. `tickera-event-ticketing-system/includes/classes/class.checkin_api.php` satır 694–706 içinde `tickera_order_is_paid` filtresinin sonucu yanlışsa giriş bloklanıyor. **WooCommerce Bridge'in HPOS `wc-failed` için filtreyi nasıl uyguladığı bilinmiyor**. Bu 54 kaydın geçerli olduğunu veya girişten reddedildiğini iddia etmeyin.
- Bu sorgularda müşteri adı, telefon, ham QR/ticket_code veya sipariş kimliği **çıktıya alınmadı**; yalnız toplu sayımlar.
- Kurulu Checkinera/Tickera, WC veya ödeme ayarlarında **hiçbir değişiklik yapılmadı**.

### Canlıya geçiş açısından yeni zorunlu kapı

Münhasır Milano marka yetkisi + gerçek çocuk/yetişkin varyasyonları + **ödemesi kanıtlanmış sipariş** + Tickera `tickera_order_is_paid` durum eşlemesi + MDG kesinleşen 4 kişilik kontenjan + 4 ayrı geçerli QR; bunların tamamı staging'de doğrulanmadan aktif Milano checkout yapılandırılmayacak.

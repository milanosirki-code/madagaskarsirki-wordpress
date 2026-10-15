# Milano Merkezî Sipariş Tekilleştirmesi — İnceleme Taslağı

Tarih: 12 Ekim 2026
Durum: **Yalnız GitHub taslağı. Madagaskar veya Milano WordPress'e yüklenmedi; hiçbir tablo oluşturulmadı, gerçek ödeme, sipariş ve bilet işlemi yapılmadı.**

## Amaç

Milano müşterisi yalnız \`milanosirki.com\` üzerinden satın alır. Ticari/teknik merkez Dünya Organizasyon'un kanonik WooCommerce/MDG/Tickera sistemi olabilir. Her fiziksel MDG etkinliği yalnız **bir marka** tarafından satılır. Aynı merkezî checkout isteği tekrarlandığında **yeni WooCommerce siparişi, kapasite kilidi, ödeme veya QR bileti yaratılmaması** gerekir.

Bu klasörde:
- \`milano-checkout-intent.php\`: 90 saniyelik, Milano markası sabit, HMAC-SHA256 ile imzalanan kısıtlı \`request_id\` + event/session + bilet tipi/adedi talebi. **Sipariş/fiyat yetkisi vermez.**
- \`milano-order-claim-registry.php\`: imzası ve süresi doğru talebe dair benzersiz talep satırını atomik olarak edinir. Salt bir **protokol/DB sınıfı**; hiçbir WordPress hook'u, REST endpoint'i, \`dbDelta\` çağrısı veya WooCommerce sipariş oluşturma işlemi içermez.
- \`../tests/order-claim-contract.php\`: 21 bağımsız izole senaryo. Testler sahte DB ile yapılır, gerçek MySQL/InnoDB eşzamanlılık testi değildir.

## Önerilen tablo (uygulamaya alınmadı)

\`MCPI_Order_Claim_Registry::ddl('wp_dunya_milano_checkout_intents')\` yalnız inceleme için \`CREATE TABLE\` DDL metnini üretir; **çalıştırmaz**.

- \`request_id CHAR(32) PRIMARY KEY\`: tek claim, eşzamanlı istekte MySQL InnoDB tekilliği
- \`payload_hash CHAR(64)\`: aynı kimlikte farklı bilet/seans gönderilmesini reddeder; PII veya fiyat tutulmaz
- \`state\`: \`claimed\` / \`ready\`, bozuk durum \`reconcile_required\`
- \`claim_token CHAR(32)\`: yalnız ilk sunucu çalışanına verilen rastgele token; müşteriye ve günlük dosyasına yazılmaz
- \`order_id BIGINT UNSIGNED NULL UNIQUE\`: tek merkezî Woo order'a iki ayrı istek bağlanamaz
- UTC \`created_at\`, \`updated_at\`

## Sunucu akışının zorunlu sırası (henüz uygulanmadı)

1. HTTPS üzerinden sadece bilinen **Milano sunucusundan** gelen talebi al; ayrı sunucu sırrı/kimlik doğrulaması uygula; URL/Referer/Origin/query parametresine güvenme. HMAC imzasını ve geçerlilik süresini doğrula.
2. \`claim(...)\` ile kayıt al:
   - **\`claimed\`**: yalnız ilk worker ilerleyebilir; \`claim_token\` asla tarayıcıya verilmez.
   - **\`processing\`**: aynı request için ikinci worker yeni Woo siparişi yaratmaz; "işlem devam ediyor" döner.
   - **\`existing_order\`**: daha önce bağlanmış kanonik siparişi doğrula, yeni sipariş veya QR üretme.
   - **\`conflict\`**: aynı request_id ile farklı payload; güvenlik hatası.
   - **\`reconcile_required\` / \`unavailable\`**: fail-closed; yeni sipariş yok.
3. Merkezde **yeniden** MMC/MDG etkinlik markası \`milano\`, on-sale seans, geçerli Woo varyasyon fiyatı, kullanılabilir MDG kapasitesi ve bilet tipi \`capacity_units\` doğrulanmalı. Tarayıcı veya Milano sunucusundan gelen fiyat asla kabul edilmez.
4. Yalnız \`claimed\` için **tek merkezî WooCommerce order** oluştur; güvenilir \`_dunya_source_brand=milano\` ve \`_dunya_checkout_request_id\` sipariş metası sakla. Aynı kimliğe ait mevcut Woo order meta kaydı aranmalı; varsa yeni sipariş yaratmadan uzlaştırılmalı.
5. \`attach_order(...)\` ancak sahip \`claim_token\` ile, kayıt \`claimed\` ve \`order_id IS NULL\` iken başarılı; UNIQUE order_id başka request'e bağlanmasını engeller.
6. MDG_Capacity::hold, ödemeler, PayTR sunucu bildirimi, Tickera QR yalnız merkezi ödeme işletim servisi içindedir. Aynı PayTR \`merchant_oid\` için tekrar bildirimde \`already paid\` idempotence kontrolü ve tekil bilet/QR zorunludur.
7. **Crash window:** WooCommerce siparişi yaratıldıktan sonra registry \`attach_order\` gerçekleşmeden sunucu kapanabilir. Bu durumda talep \`claimed\` kalır. Otomatik zaman aşımıyla bu satırı silip yeniden sipariş oluşturma **YASAKTIR**. Woo order meta / PayTR / MDG holds ile operatör veya ayrı güvenli reconciliation servisiyle eşleştir. Bu pilot sınıfta otomatik kurtarma YOK.
8. **Aynı müşterinin yeni request_id alması ayrı bir satın alma denemesi olabilir.** UI tıklama/retry aynı request_id'yi tekrar kullanmalı; kullanıcının bilinçli ikinci satın almasını güvenli şekilde ayırmak, rate limit ve abuse önlemleri ayrıca tasarlanmalıdır.

## Testler

\`integrations/milano/tests/order-claim-contract.php\`, 21 ayrı senaryo:

- Geçerli ilk claim, mevcut \`processing\`, tekrarlı worker, \`ready\` siparişi tekrar okuma
- Aynı request ID'yi farklı payload ile yeniden imzalamaya rağmen çakışmayı engelleme
- Yanlış imza, zayıf anahtar, süre aşımı, geçersiz tablo ismi, DB arızası
- Claim token kötü niyetli değiştirme, sipariş ID tekrar kullanma, sipariş güncellemesini tek sefer yapma
- İleride yeniden sipariş oluşturmayı önlemek üzere eski \`claimed\` kaydını otomatik devralmama
- Bozuk/inconsistent \`ready\` durumunda manuel mutabakat gerektirme

Testler gerçek PayTR callback, gerçek WooCommerce, eşzamanlı MySQL/InnoDB veya Tickera QR testi sayılmaz. **Bu bir ödeme sistemi değildir.**

## PayTR alan adı / mağaza yetki durumu

PayTR iFrame API iki basamaklıdır: token arka uçtan üretilir, ödeme sonrasında ayrı callback doğrulanır. PayTR, aynı \`merchant_oid\` için birden fazla callback gönderebilir ve HTML ödeme başarı yönlendirmesi ödeme onayı değildir.

Resmî belgeler:
- https://dev.paytr.com/iframe-api
- https://dev.paytr.com/iframe-api/iframe-api-1-adim
- https://dev.paytr.com/iframe-api/iframe-api-2-adim

**Mevcut mağaza hesabının \`milanosirki.com\` için onaylandığı doğrulanamadı.** Mevcut e-posta araması Milano alan adı için PayTR onay yazışması bulmadı; bu tek başına PayTR panelindeki hesap durumunu kanıtlamaz. Destek & Kurulum > Destek üzerinden şu hususlar yazılı teyit edilmeli:

1. Dünya Organizasyon'un **aynı ticari hesabıyla iki ayrı satış markası/alan adı** (\`madagaskarsirki.com\`, \`milanosirki.com\`) destekleniyor mu?
2. Milano üzerinde iFrame/3DS görüntülenirken **merkezî HTTPS callback** ve tek WooCommerce sipariş/PayTR \`merchant_oid\` kullanımı uygun mu?
3. İki alan adında ödeme dönüş bağlantıları, onay/kayıt ve fatura/müşteri bilgilendirmesinde gerekli izin veya ayrı PayTR mağaza tanımlaması var mı?

**Onay ve gerçek testler tamamlanana kadar canlı ödeme açılmayacak.**

## Kontrol / geri alma

PR: https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/235 (draft; merge yok)
Drive: https://docs.google.com/document/d/1GSV-_niF39NTkB45--sqodofxDVH25S7XeKxefzUa4Q/edit

Geri alma: Kod yalnız GitHub taslağında olduğu sürece canlı sitede geri alma işlemi yok. İleride staging kurulumu için ayrı kullanıcı onayı, pilot test, veritabanı migration ve rollback planı gerekiyor.

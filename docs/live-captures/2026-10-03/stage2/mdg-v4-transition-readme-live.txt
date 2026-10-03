=== Madagaskar Bilet Yönetimi V4.0 ===
Contributors: Madagaskar Sirki / Dünya Organizasyon
Stable tag: 4.0.14-transition
Requires at least: 6.5
Requires PHP: 7.4

Bu paket geçiş sürümüdür.

AMAÇ
- WooCommerce + PayTR + Tickera çekirdeğini değiştirmeden Madagaskar'a özel yönetimi tek eklentide toplamak.
- V3.x test/denetim eklentilerinin menü karmaşasını kaldırmak.
- Mevcut V3.7.1 sales close meta mantığı ile uyumlu tek Satış Yönetimi ekranı sağlamak.
- Mevcut V3.8.7 mdg_postpone_map kayıtlarını okuyup toplu bilet aktarımını snapshot + rollback ile tek yerde yapmak.
- Biletlerim güvenli bağlantısını V4 içinde desteklemek.
- Eski eklentileri otomatik silmemek.

KURULUM SIRASI
1) ZIP'i Eklentiler > Yeni Eklenti > Eklenti Yükle ile kurun.
2) Etkinleştirin.
3) Madagaskar V4 > Genel Bakış ekranının görüntüsünü kontrol edin.
4) Önce Geçiş Merkezi envanterini inceleyin.
5) Henüz V3.6 / V3.7 üretim eklentilerini veya eski Madagaskar Bilet Yönetimi çekirdeğini kapatmayın.
6) Tanı/test eklentileri ancak V4 ekranları doğru göründükten sonra Geçiş Merkezi'ndeki kontrollü düğmeyle devre dışı bırakılabilir.

NOT
Bu sürüm eski V3.x eklentilerini otomatik silmez. Bu bilinçli bir güvenlik tercihidir.

== 4.0.2-transition ==
* V3.7.1 reversible sales-close davranışı V4 çekirdeğine alındı.
* `_mdg_v371_sales_closed=yes` ana ürün veya varyasyonda ise WooCommerce satın alınabilirlik filtresi false döner.
* `woocommerce_is_purchasable` ve `woocommerce_variation_is_purchasable` filtreleri priority 99 ile V4 tarafından uygulanır.
* V3.7.1 aktifken aynı karar iki kez uygulanabilir; sonuç idempotenttir. Doğrulama sonrası V3.7.1 güvenle devre dışı bırakılabilir.


V4.0.2-transition
- Existing MS Biletlerim legacy HMAC links are accepted by the V4 frontend.
- V4 can generate legacy-compatible links for migration testing.
- When the old MS Biletlerim snippet is disabled, V4 supplies ms_biletlerim_token() / ms_biletlerim_url() compatibility aliases at init so Kommo retry/sync code can keep using the historical function name.
- No order, ticket, QR, payment, product or stock records are changed by this compatibility layer.

== 4.0.3-transition ==
* Erteleme / Bilet Aktarımı ekranına yöneticiye özel V4 sentetik test ticketı aracı eklendi.
* Test ticketı WooCommerce siparişi, PayTR ödemesi, müşteri, ürün veya stok oluşturmaz/değiştirmez.
* Test ticketı yalnız tc_tickets_instances içinde `_mdg_v4_test_ticket=yes` ile işaretlenir.
* Sentetik test ticketı için sipariş/ödeme kapısı yalnız test amacıyla atlanır; gerçek ticket kuralları değişmez.
* Test ticketı snapshot, SHA-256, kontrollü aktarım, post-write invariant ve rollback zincirinden geçer.
* Aktarım yapılmış test ticketı rollback tamamlanmadan temizlenemez.
* Ticket planında V4 TEST etiketi görünür.


== 4.0.4-transition ==
* Erteleme / Bilet Aktarımı ekranına “Yeni Erteleme Oluştur” akışı eklendi.
* Kaynak Tickera etkinliği ve yeni tarih seçilerek güvenlik önizlemesi yapılır.
* Kaynakta tam 3 seans (12:00 / 14:00 / 16:00), kapalı satış, tutarlı kaynak tarihi ve variable ürün/varyasyon bütünlüğü zorunludur.
* V4 hedef Tickera etkinliğini TASLAK olarak klonlar.
* Üç WooCommerce seans ürünü TASLAK + katalogdan gizli + satış kilitli oluşturulur.
* Kaynak çocuk/yetişkin varyasyonları hedef ürünlere güvenli biçimde klonlanır; fiyat ve kaynak-hedef ilişki metaları korunur.
* Oluşturulan paket yeniden doğrulanır; doğrulama geçerse mdg-postponement-mapping-v1 payload SHA-256 ile kilitlenir.
* Herhangi bir adım başarısız olursa V4.0.4 tarafından oluşturulan hedef varyasyonlar, ürünler ve etkinlik otomatik temizlenir.
* Kaynak sipariş, mevcut ticket, QR/ticket_code, PayTR, müşteri ve stok geçmişi değiştirilmez.
* Aynı kaynak için ikinci mapping ve aynı kaynak+tarih için yinelenen hedef taslak engellenir.


== 4.0.5-transition ==
* V4 tarafından oluşturulan erteleme hedef paketleri için güvenli “Taslak Paketi Temizleme” aracı eklendi.
* Temizlik yalnız mapping V4 tarafından oluşturulmuşsa, SHA-256 kilitliyse, hedef etkinlik draft ise ve hedef ürünler draft+gizli+satış kilitli ise açılır.
* Hedef etkinliğe bağlı herhangi bir Tickera ticket varsa temizlik engellenir.
* Hedef ürün/varyasyonlara bağlı WooCommerce sipariş satırı varsa temizlik engellenir.
* Aktif başarılı transfer varsa temizlik engellenir; rollback tamamlandıktan sonra yeniden değerlendirilebilir.
* Güvenli temizlikte yalnız V4 hedef varyasyonları, hedef ürünleri, hedef Tickera etkinliği ve ilgili V4 mapping kaydı silinir.
* Kaynak etkinlik, kaynak ürün, kaynak varyasyon, sipariş, PayTR, müşteri, QR/ticket_code ve stok geçmişi değiştirilmez.


== 4.0.6-transition ==
* “İptal / İade” yönetim ekranı V4 içine eklendi.
* Tam iade öncesi salt-okunur dry-run; sipariş, gateway, ticket ve refund geçmişi güvenlik kapılarıyla denetlenir.
* Yalnız hiç iade görmemiş ve tamamı iade edilebilir ödenmiş siparişler kabul edilir; kısmi iade karmaşıklığı bilinçli olarak kapsam dışıdır.
* Ödeme geçidi WooCommerce `refunds` desteği vermiyorsa gerçek iade kilitlidir.
* İade talebi oluşturulduğunda sipariş + Tickera ticket snapshotı JSON + SHA-256 ile kilitlenir; para hareketi oluşmaz.
* Talebi oluşturan kullanıcı kendi talebini onaylayamaz. İkinci farklı WooCommerce yöneticisi zorunludur.
* İkinci onaydan sonra gerçek iade için ayrıca açık metinli son onay gerekir.
* Gerçek iade öncesi snapshot yeniden doğrulanır; aradaki sipariş/ticket değişiklikleri işlemi durdurur.
* Tam iade sırasında Tickera ticketları `_mdg_invalidated=yes` + neden metası ile geçersizleştirilir ve trash durumuna alınır.
* Gateway/API iadesi başarısız olursa ticket geçersizleştirmesi otomatik geri alınır.
* WooCommerce `wc_create_refund()` kullanılarak API iadesi + ürün stok geri kazanımı çalıştırılır.
* Provider iadesi başarıyla gerçekleştikten sonra otomatik “refund rollback” yapılmaz; post-check sapması `attention_required` olarak kayda alınır.
* V3.6.4 Tek Sipariş Tam İade ve V3.7.3 İki Aşamalı İptal Onayı, V4.0.6 doğrulanana kadar aktif bırakılmalıdır.


== 4.0.7-transition ==
* İkinci kişi onayı verilmiş (`approved`) tam iade talebi için “ONAYLI TALEPTEN VAZGEÇ” güvenlik yolu eklendi.
* Bu vazgeçme yalnız gerçek para iadesi başlamadan önce çalışır.
* WooCommerce refund ID oluşmuşsa, execute_started kaydı varsa veya siparişte herhangi bir iade hareketi görülüyorsa iptal kapısı kilitlenir.
* Onay bekleyen talebi yalnız talep eden kullanıcı kapatabilir.
* Onaylanmış talebi yalnız talep eden veya ikinci onayı veren kullanıcı kapatabilir.
* Güvenli vazgeçme sipariş, PayTR, ticket, QR/ticket_code veya stok kayıtlarını değiştirmez; yalnız V4 iade vaka kaydını `cancelled` durumuna getirir.
* Böylece ikinci kullanıcı onayı ve gerçek iade düğmesi üretim siparişinde para hareketi oluşturmadan test edilebilir.


== 4.0.8-transition ==
* Geçiş Merkezi artık planlanan durumu değil doğrulanmış operasyonel durumu gösterir.
* V3.7.1, V3.8.1, V3.8.5, V3.8.5.1 ve V3.8.7 için “V4’E TAŞINDI — KAPALI KALACAK” kararı tanımlandı.
* V3.6.4, V3.7.2 ve V3.7.3 için “DOĞRULAMA BEKLİYOR — AÇIK KALACAK” kararı tanımlandı.
* Eski ana Madagaskar Bilet Yönetimi çekirdeği “ŞİMDİLİK KALACAK” olarak ayrı tutulur.
* Madagaskar Yönetim Menü Düzenleyici “YARDIMCI — AÇIK KALABİLİR” olarak sınıflandırılır.
* Tanı/test/canary araçları aktif değilse “TANI/TEST — KAPALI KALACAK” olarak gösterilir.
* V4’e taşınmış bir eski modül yeniden aktif edilirse Geçiş Merkezi uyarı verir.
* Doğrulama bekleyen bir üretim modülü yanlışlıkla kapatılırsa Geçiş Merkezi uyarı verir.
* Hiçbir üretim eklentisi otomatik kapatılmaz veya silinmez; durum ekranı yalnız karar desteği sağlar.
* Üretim iş mantığında değişiklik yapılmamıştır.


== 4.0.9-transition ==
* Geçiş Merkezi V4.0.8 sınıflandırma hatası düzeltildi.
* Kritik üretim modülleri artık plugin açıklama metninden veya başka sürüm referanslarından sınıflandırılmaz.
* V3.7.1, V3.8.1, V3.8.5, V3.8.5.1 ve V3.8.7 yalnız tam plugin basename eşleşmesiyle “V4’E TAŞINDI” sayılır.
* V3.6.4, V3.7.2 ve V3.7.3 yalnız tam plugin basename eşleşmesiyle “DOĞRULAMA BEKLİYOR” sayılır.
* V3.7.4 Toplu İade Kuyruğu Dry-Run yeniden tanı/test sınıfında kalır; yanlışlıkla üretim doğrulaması bekleyen modül sayılmaz.
* Beklenen çekirdek sayaçları 5 taşındı / 3 doğrulama bekliyor olarak doğrulanır; sapma varsa kırmızı güvenlik uyarısı gösterilir.
* Üretim iş mantığı, iade, erteleme, satış kapatma veya Biletlerim fonksiyonlarına dokunulmamıştır.


== 4.0.10-transition ==
* İkinci yöneticiyle yapılan V4 onay testi ve ikinci kullanıcı erişim testi başarıyla tamamlandığı için Geçiş Merkezi güncellendi.
* V3.7.2 — Güvenli Kullanıcı Rolü artık “V4’E TAŞINDI — KAPALI KALACAK”.
* V3.7.3 — İki Aşamalı İptal Onayı artık “V4’E TAŞINDI — KAPALI KALACAK”.
* V3.6.4 — Tek Sipariş Tam İade tek doğrulama bekleyen eski üretim modülü olarak bırakıldı.
* Beklenen Geçiş Merkezi sayaçları 7 / 1 / 0 / 1 olarak sabitlendi; farklı sonuçta kırmızı uyarı gösterilir.
* Üretim iş mantığında değişiklik yapılmadı.
* Gerçek PayTR tam iadesi güvenle doğrulanmadan V3.6.4 kapatılmamalıdır.


== 4.0.11-transition ==
* Gerçek PayTR tam iade testi sonrası görülen Tickera post-status yarış durumu için “İade Sonrası Ticket Uzlaştırma” eklendi.
* WooCommerce refund başarıyla oluştuğunda V4 ticket geçersizlik metasını ve trash durumunu idempotent biçimde yeniden sabitler.
* Sipariş/refund hookları ticketı yeniden publish ederse uzlaştırma ikinci kez post-check sonrası uygulanır.
* `attention_required` durumundaki mevcut bir V4 iade kaydı için güvenli manuel uzlaştırma düğmesi eklendi.
* Manuel uzlaştırma kesinlikle `wc_create_refund()`, gateway `process_refund()` veya PayTR ödeme API çağrısı yapmaz.
* Manuel uzlaştırma yalnız şu kapılarda açılır: sipariş `refunded`, kalan iade 0, toplam tutarın tamamı iade edilmiş, WooCommerce Refund ID geçerli ve aynı siparişe ait, ilk SHA-256 snapshot okunabilir.
* Ticket kimliği korunur: `event_id`, `ticket_type_id` ve `ticket_code` SHA-256 ilk snapshotla eşleşmeden otomatik trash yapılmaz.
* Başarılı uzlaştırmada vaka `success` olur; Refund ID ve ödeme kaydı aynen korunur.
* V3.6.4 gerçek iade doğrulaması, #1986 uzlaştırması tamamlanana kadar açık kalmalıdır.


== 4.0.12-transition ==
* Gerçek PayTR tam iadesi ve iade sonrası Tickera ticket uzlaştırması üretimde başarıyla doğrulandığı için V3.6.4 geçişi tamamlandı.
* V3.6.4 — Tek Sipariş Tam İade artık “V4’E TAŞINDI — KAPALI KALACAK”.
* Geçiş Merkezi beklenen sayaçları 8 / 0 / 0 / 1 olarak güncellendi.
* “Doğrulama bekleyen” eski üretim modülü kalmadı.
* Satış kapatma, erteleme, Biletlerim, iki kullanıcı onayı, PayTR tam iade ve ticket uzlaştırma zinciri V4 içinde doğrulanmış kabul edilir.
* Eski ana Madagaskar Bilet Yönetimi 3.6.3 otomatik kapatılmaz; ayrı emeklilik denetimine kadar “ŞİMDİLİK KALACAK” durumundadır.
* Üretim iş mantığında yeni para hareketi veya iade davranışı eklenmemiştir; bu sürüm Geçiş Merkezi final durumunu kesinleştirir.


== 4.0.13-transition ==
* Geçiş Merkezi'nde kalan eski V4.0.8/V4.0.9 sınıflandırma uyarıları temizlendi.
* Eski hard-coded “5 taşındı / 3 doğrulama bekliyor” kontrolü kaldırıldı.
* Geçiş Merkezi için tek doğruluk kaynağı artık 8 / 0 / 0 / 1 expected_counts kontrolüdür.
* Bilgi bandındaki eski “7 / 1 / 0 / 1” metni final “8 / 0 / 0 / 1” durumuyla düzeltildi.
* Üretim, PayTR, iade, ticket, erteleme, Biletlerim veya satış kapatma iş mantığında hiçbir değişiklik yapılmadı.


== 4.0.14-transition ==
* Eski ana Madagaskar Bilet Yönetimi 3.6.3 için yalnız-okuma “3.6.3 Emeklilik Denetimi” sayfası eklendi.
* Denetim gerçek eski plugin dosyasını ve plugin klasöründeki PHP kaynaklarını okur; paket SHA-256, global semboller, statik hook/shortcode etiketleri çıkarılır.
* WordPress runtime `$wp_filter` ve shortcode callbackleri Reflection ile dosya köküne göre sahiplik bazında denetlenir.
* Kritik runtime hookların V4 tarafında aynı hook etiketi bulunup bulunmadığı gösterilir.
* Aktif eklentiler, MU eklentileri ve aktif/parent tema PHP dosyalarında eski çekirdeğin global fonksiyon/class/constant adlarına doğrudan referans adayları aranır.
* Eski shortcode varsa içerik kullanım sayısı kontrol edilir.
* V4 içindeki `ms_biletlerim_url()` uyumluluk aliası ve Tickera resmi download helper varlığı güvenlik kapısına dahil edildi.
* Sonuç yalnız “KONTROLLÜ KAPATMA DENEMESİNE HAZIR” veya “HENÜZ KAPATMAYIN” verir; hiçbir eklentiyi otomatik kapatmaz/silmez.
* Sipariş, PayTR, ticket, QR, stok, satış veya müşteri verisine yazma yapılmaz.

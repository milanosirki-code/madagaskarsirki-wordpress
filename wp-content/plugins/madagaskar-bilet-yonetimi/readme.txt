Madagaskar Bilet Yönetimi

Sürüm 3.5.0
- Müşteri / Bilet Listeleri için Excel (.xlsx), CSV ve PDF/Yazdır dışa aktarma
- Şehir -> Etkinlik -> Seans bağımlı filtreleme
- Seans listesi yalnız seçili etkinliğin seanslarını gösterir
- Excel formül enjeksiyonu koruması ve HPOS uyumlu sipariş okuma korunur

V3.1.1: Şehirler sayfası için tema/page-builder bağımsız güvenli template route düzeltmesi. Mevcut statik sayfa içeriği değiştirilmez.

=== Madagaskar Bilet Yönetimi ===
Contributors: dunyaorganizasyon
Requires at least: 6.5
Requires PHP: 7.4
Stable tag: 3.6.1-native-paytr-refund-readiness
License: GPL-2.0-or-later

Madagaskar Sirki için özel salon, etkinlik, seans, ortak kapasite, raporlama ve entegrasyon çekirdeği.

== 2.2.0-venue-qr ==
* 81 il + ilçe seçimi ve yerel önbellek/yedek mekanizması.
* Salon ana veri kaydı: adres, kapasite, süre, koordinat, yetkili ve operasyon notları.
* Maps bağlantısından yönetim tarayıcısında yerel QR üretimi.
* QR görselinin WordPress Media Library içinde saklanması.
* Maps URL hash değişmedikçe QR tekrar üretilmez.
* Maps URL değişirse yeni QR oluşturulur; eski attachment silinmez (geçmiş etkinlik snapshot güvenliği).
* QR üretim sorunu salon kaydını veya ileride bilet satışını durdurmaz.
* Etkinlik tablosuna salon Maps/QR/kapasite/süre snapshot alanları eklendi.
* Salon QR işlemleri audit log'a yazılır.

== Third-party ==
QRCode.js (davidshimjs/qrcodejs) is bundled under the MIT License.
See assets/vendor/qrcodejs/LICENSE.


== 2.3.0-event-content ==
* Etkinlik Yayınla ekranı artık gerçek taslak etkinlik kaydı oluşturur.
* Etkinlik kapak/hero görseli, çoklu galeri ve tanıtım videosu alanları eklendi.
* Kısa tanıtım, uzun gösteri açıklaması, yaş/süre/kapı açılışı/oturma düzeni/kurallar/organizatör/SEO alanları eklendi.
* Salon seçildiğinde salon bilgileri etkinlik kaydına snapshot olarak yazılır.
* Bu sürüm WooCommerce/Tickera ürünleri oluşturmaz ve mevcut canlı satış akışını değiştirmez.

== 2.4.0-session-capacity ==
* Etkinlik taslağına çoklu tarih/seans satırları eklendi.
* Her seans için tek ortak kapasite tanımlanır; kapasite bilet türlerine bölünmez.
* Çocuk/Yetişkin varsayılan bilet türleri ve opsiyonel Aile Paketi 2+2 şablonu eklendi.
* Bilet türlerinde fiyat ve kapasite tüketimi (kişi birimi) tanımlanır.
* Aile Paketi 2+2 varsayılan olarak 4 kapasite birimi tüketir.
* Etkinlik + seans + bilet türü kayıtları transaction ile güncellenir.
* WooCommerce/Tickera üretimi hâlâ kapalıdır; mevcut canlı satış akışına dokunulmaz.

== 2.4.1-venue-import ==
* Salonlar ekranına XLSX/CSV toplu içe aktarım eklendi.
* V2 İçe Aktarım sayfası otomatik okunur.
* İçe aktarım silme yapmaz; mevcut dolu alanları boş verilerle ezmez.
* Adresi eksik arşiv salonları uyarılı olarak taslak etkinliklerde seçilebilir.

== 2.4.2-xlsx-fix ==
* XLSX Relationship attribute sırasına bağlı okuma hatası düzeltildi.
* Type→Target→Id ve Id→Type→Target gibi farklı geçerli XLSX sıraları desteklenir.
* Boş/self-closing hücrelerin sütun kaydırmasına yol açan parser hatası düzeltildi.
* Madagaskar Son 3 Sezon Salon Arşivi V2 dosyası ile gerçek içe aktarım parser testi yapıldı: 104 veri satırı okunuyor.

== 2.4.3-venue-match-fix ==
* Etkinlik Yayınla ekranındaki salon filtresi il plaka kodu, il adı ve Türkçe Unicode/boşluk farklarına dayanıklı hale getirildi.
* İçe aktarılan aktif salonların ilçe seçiminden sonra görünmemesi sorunu giderildi.
* Kayıt yoksa salon alanında açık durum mesajı gösterilir.


V2.4.5: İlçe sözlüğü Türkçe karakterleri koruyan kaynağa taşındı; eski Altindag/Altındağ benzeri kayıtlar aksansız eşleştirme ile kanonikleştirildi. Event/Salon dropdown eşleşmesi ve importer düzeltildi.


2.4.5: Etkinlik Excel önizleme/içe aktarma ve taslak kopyalama eklendi.


2.4.7: Etkinlik galerisinde çoklu/eklemeli görsel seçimi ve tek tek kaldırma; Salonlar listesinde arama, il, ilçe, durum ve eksik bilgi filtreleri.

2.4.8: Güncel salon arşivi için güvenli senkronizasyon; eski/generic/geçersiz ilçe adı tekil ve daha doğru resmi ilçe ile aynı salon ID'si korunarak güncellenir. Aynı isimli farklı ilçe salonları otomatik birleştirilmez; mevcut adres/Maps/iletişim bilgileri korunur.


V2.5: SSS editörü ve müşteri etkinlik sayfası güvenli taslak önizlemesi eklendi. Taslaklar noindex ve yalnızca manage_woocommerce yetkili kullanıcılarına açıktır. WooCommerce/Tickera satış üretimi henüz bağlı değildir.


= 2.5.1 =
* Galeri sabit oranlı responsive kartlara alındı; dikey görseller object-fit ile kırpılıyor.
* Bilet adetleri ve seans seçimi her açılışta deterministik olarak 0 / ilk seansa sıfırlanıyor.
* Önizleme ödeme CTA'sı bilet seçilince aktif görünür ve gerçek ödeme açılmadan davranış testi yapar.
* Masaüstü ve mobil sticky bilet özeti eklendi.
* İlk SSS açık, organizatör kısa marka adıyla gösteriliyor.
* Kare/dikey hero görseli için önizleme uyarısı eklendi.


== 2.6.0-sales-audit ==
- Ayarlar ekranına salt-okunur WooCommerce/Tickera/Bridge uyumluluk analizi eklendi.
- Mevcut _tc_is_ticket=yes ürünlerden örnek bağlar listelenir.
- Bu sürüm hiçbir WooCommerce/Tickera satış nesnesine yazmaz.


== 2.6.1-sales-dry-run ==
- Salt-okunur WooCommerce/Tickera referans desen analizi.
- V2 taslağı için seans bazlı Woo variable product ve varyasyon/SKU üretim dry-run planı.
- Hiçbir satış nesnesine yazma yapmaz.


== 2.6.2 ==
* Mevcut canlı WooCommerce/Tickera satış nesnelerini yeni kopyalar oluşturmadan Madagaskar V2 seans/bilet türlerine güvenli ID mapping ile bağlama.
* Fiyat, seans, varyasyon ve Tickera etkinliği eşleşmeden bağlantı yapılmaz.
* Geri alma yalnızca V2 mapping alanlarını temizler; WooCommerce/Tickera nesnelerine dokunmaz.

V2.7.5-event-template-qr-binder
- Yetkili taslak önizlemesinde gerçek WooCommerce sepet testi.
- Eşlenmiş mevcut variable ürün/varyasyonları kullanır; yeni ürün veya Tickera etkinliği oluşturmaz.
- Sipariş/ödeme oluşturmaz; seçim sonrası WooCommerce sepet sayfasına yönlendirir.
- Fiyat, parent variation, purchasable/stock ve MDG mapping kontrolleri zorunludur.
- Sepette başka ürün varsa otomatik temizlemez; test güvenliği için işlemi durdurur.


== 2.7.2-ticket-designer-diagnostic ==
Checkout variable product attribute map hardened; stale WooCommerce notices cleared after successful administrator cart test.


V2.7.0
- Salon Maps URL -> Media Library QR akışı korunur.
- Tickera Ticket Designer için Madagaskar Salon Konumu QR özel elementi eklendi.
- Salon güncellemesinde yalnızca taslak V2 etkinlik snapshot'ları güvenle senkronize edilir.
- Önizleme ekranındaki V2/test ibareleri müşteri diline çevrildi.
- Ayarlar ekranında son Tickera biletlerinin dinamik Maps/QR çözüm tanısı eklendi.


== 2.7.1 ==
* Tickera 3.6 Ticket Designer için özel Salon Konumu QR element kaydı plugins_loaded priority 1'e çekildi.
* V2.7.0'daki salon Maps/QR ve satış işlevleri korunur.


== 2.7.5-event-template-qr-binder ==
* Kullanıcıdan alınan Tickera 3.6.0.2 tanı çıktısına göre özel salon QR elementi gerçek namespaced API'ye geçirildi.
* Kayıt artık `\Tickera\tickera_register_template_element()` ve `\Tickera\TC_Ticket_Template_Elements` üzerinden, mevcut Ticket Type (Custom) eklentisiyle aynı desende yapılır.
* Amaç: Ticket Designer > ADD-ON FIELDS altında `Madagaskar Salon Konumu QR` öğesini görünür hale getirmek.
* Mevcut statik salon QR'ı, satışlar, WooCommerce, PayTR, Tickera biletleri ve Kommo verileri değiştirilmez.


== 2.7.4 ==
- Ticket Designer add-on element loader hook'u plugins_loaded beklenmeden kaydedilir.
- Tickera plugin bootstrap sırasında tc_load_ticket_template_elements çalışsa bile Madagaskar Salon Konumu QR callback'i hazırdır.


== 2.7.5 ==
- Tickera 3.6 Designer add-on alanı yaklaşımı bırakıldı.
- Mevcut çalışan şablon etkinlik bazında klonlanır.
- Sağ-alt statik salon QR resmi, salon kaydındaki otomatik QR PNG ile değiştirilir.
- Klon yalnızca ilgili WooCommerce seans ürünleri/varyasyonlarına atanır.
- Giriş QR koduna dokunulmaz.
- Geri alma ve yeniden güncelleme desteği vardır.

V2.7.6 HOTFIX
- V2.7.5 Ayarlar ekranındaki kritik hata düzeltildi.
- MDG_Sessions API çağrıları mevcut çekirdek sınıfla eşleştirildi: by_event / ticket_types_by_session / start_at.
- Bu sürüm yalnızca QR şablon bağlayıcı analiz ekranındaki çalışma zamanı hatasını giderir; satış, ödeme, sipariş ve mevcut bilet verilerini değiştirmez.


== 2.8.1 Kontrollü Canlı Yayın ==
- Hazırlık kontrolünden geçen taslak için kontrollü canlı yayın butonu.
- /etkinlik/<slug>/ halka açık SEO/Schema etkinlik sayfası.
- Mevcut WooCommerce/Tickera mapping ile canlı sepet akışı; yeni ürün üretmez.
- WooCommerce Checkout Block ve klasik checkout öncesinde atomik ortak seans kapasite kilidi.
- Ödeme tamamlanınca held -> sold; başarısız/iptal siparişte kapasite serbest bırakılır.
- Canlıya geçişte mevcut ödenmiş WooCommerce siparişleri HPOS uyumlu CRUD sorgusuyla seans sold_units değerlerine uzlaştırılır.


== 2.8.2 Canlı Satış UX Temizliği ==
- Halka açık bilet seçicisinde adet kutusundaki rakamların görünürlüğü sabitlendi.
- Yalnızca Madagaskar V2 biletlerinden oluşan sepet fiziksel gönderim gerektirmez; checkout'taki gönderim adımı kaldırılır.
- Tickera katılımcı alanları için Türkçe etiket fallback'i eklendi.
- Aynı e-posta pazarlama onayı iki kez oluşursa yalnızca biri görünür; onay hiçbir zaman otomatik işaretlenmez.
- PayTR, Tickera QR, Kommo, mevcut ürün/varyasyon mapping'i ve ortak seans kapasitesi değiştirilmez.


== 2.8.3 ==
* Checkout'ta aynı pazarlama/e-posta izin metninin farklı eklentiler veya Checkout Blocks tarafından iki kez render edilmesi durumunda görünür kopyaları DOM sırasına göre tekilleştirir.
* İlk izin alanını kullanıcıya bırakır; gizlenen kopyaları otomatik olarak işaretlemez, aksine devre dışı bırakır.
* Bilet-only sepetlerde gönderim kapatma, Türkçe katılımcı alanları, PayTR, kapasite ve satış mapping davranışı değiştirilmez.


== 2.8.4 Checkout Son Temizlik ==
* WooCommerce Checkout Block resmi additional checkout fields API'si ile tek bir isteğe bağlı pazarlama izin kutusu kaydedilir.
* Eski checkout yığını tarafından üretilen yinelenen pazarlama izinleri gizlenir; resmi MDG alanı korunur ve hiçbir zaman otomatik işaretlenmez.
* Onay tercihi WooCommerce tarafından saklanır; siparişte ayrıca MDG denetim meta alanlarına evet/hayır ve onay zamanı yansıtılır.
* “Şu anda konuk olarak ödeme yapıyorsunuz.” yardımcı metni bilet checkout'unda gizlenir.
* Gönderimsiz bilet akışı, Türkçe katılımcı etiketleri, PayTR, Tickera, Kommo, dinamik salon QR ve ortak seans kapasitesi değiştirilmez.


== V2.9.1 ==
- Dry-run hazır yeni etkinlik için tek Tickera taslağı, seans başına Woo variable product ve varyasyonları taslak katmanda üretir.
- Production key, idempotency, mapping-last, rollback ve etkinlik-bazlı salon QR Designer klonu eklenmiştir.
- Otomatik publish yoktur.


== 2.9.2 ==
* V2.9.1 dry-run ekranında producer kontrollerinin render edilmemesi düzeltildi.
* Dry-run planı hazırsa 'Taslak Satış Nesnelerini Oluştur' onay alanı artık Üretim Planı içinde görünür.
* WooCommerce/Tickera verisine bu düzeltmenin kendisi yazma yapmaz.

== 2.9.4 ==
* Taslak üretim tamamlandıktan sonra dry-run'ın kendi oluşturduğu MDG ürün/varyasyon/Tickera nesnelerini URL/SKU/mapping çakışması olarak göstermesi düzeltildi.
* Aynı production key + MDG event kimliği taşıyan nesneler güvenli tekrar-çalıştırmada tanınır; farklı/uyumsuz nesneler hâlâ bloklayıcıdır.


== 2.9.5 ==
- Yetkili MDG taslak önizlemesinde V2.9 ile üretilen draft/private WooCommerce seans ürünleri güvenli sepet testine alınabilir.
- İstisna yalnızca _mdg_managed=1 ve aynı MDG event_id nesneleri için geçerlidir.
- Halka açık canlı satış endpoint'i publish ürün zorunluluğunu korur.



== 2.9.8 ==
- Yönetici taslak önizleme sepetinde WooCommerce normal /sepet ve /odeme sayfalarının doğrudan is_purchasable doğrulaması desteklendi.
- Yalnızca manage_woocommerce yetkili kullanıcının, mdg_preview_cart_test işaretli ve aynı draft MDG etkinliğine ait mevcut sepet satırları için draft/private ürün-varyasyon satın alınabilirlik istisnası uygulanır.
- Halka açık mağaza ve canlı/publish ürün davranışı değiştirilmez.

== 2.9.7 ==
* Yönetici taslak önizleme sepetinde draft/private MDG ürünlerinin WooCommerce oturumundan /Sepet sayfasına geçerken silinmesi düzeltildi.
* İstisna yalnızca manage_woocommerce yetkili kullanıcı + MDG preview cart flag + aynı draft MDG etkinliği ile sınırlandırıldı; halka açık draft ürün satın alınabilirliği değişmedi.


= 2.9.9-invoice-checkout-fields =
* Bilet checkout'una gelecekteki otomatik fatura entegrasyonu için sipariş-bazlı fatura veri alanları eklendi.
* Fatura türü: Bireysel / Kurumsal.
* Bireysel: opsiyonel T.C. Kimlik No.
* Kurumsal: Firma/Şirket Unvanı, Vergi Numarası ve Vergi Dairesi.
* Alanlar WooCommerce Additional Checkout Fields API ile order konumunda tutulur; müşteri profil verisine dönüştürülmez.
* Veriler ayrıca ilerideki fatura API adaptörü için private order meta alanlarına sanitize edilerek aynalanır.
* T.C. Kimlik No / vergi verileri bilet PDF, Kommo ve halka açık etkinlik sayfasına gönderilmez.
* PayTR, Tickera, ortak kapasite ve mevcut canlı satış zinciri değiştirilmez.


== 3.0.1 ==
* Yayındaki/Süresi Dolan etkinlik tablolarındaki ilk seans saati artık UTC ham değer yerine WordPress site saat diliminde gösterilir.
* Satış, ödeme, Tickera, QR, ürün, fiyat ve kapasite akışına değişiklik yapılmaz.


== 3.1.0 ==
* Şehirler sayfası MDG canlı etkinliklerinden otomatik üretilir.
* Yalnızca satışta ve gelecekte seansı bulunan şehirler listelenir.
* Salon kaydı tek başına şehri halka açmaz.

V3.2.0: /bilet-al/ sayfası MDG satıştaki etkinliklerinden dinamik üretilir.


V3.3.0 – Satış Raporları V1
- Salt-okunur satış raporu açıldı.
- Bugün/Dün/7 gün/30 gün/Tümü/Özel tarih filtreleri eklendi.
- Şehir, etkinlik, seans ve bilet türü filtreleri eklendi.
- Sipariş, bilet, kişi/kapasite birimi, ciro ve ortalama sipariş kartları eklendi.
- Etkinlik/seans/bilet türü kırılımları ve güncel doluluk gösterimi eklendi.
- WooCommerce HPOS tablolarına doğrudan SQL kullanılmaz; MDG order_map + ortak seans kapasitesi kullanılır.


== 3.4.0 ==
* Salt-okunur Müşteri / Bilet Listeleri ekranı eklendi.
* Dönem, şehir, etkinlik, seans, bilet türü, sipariş durumu ve sipariş no filtreleri eklendi.
* WooCommerce müşteri/sipariş bilgileri HPOS uyumlu CRUD API ile okunur.
* Tickera ticket instance / kod / check-in bilgisi güvenli ve salt-okunur biçimde algılanabildiği ölçüde gösterilir.
* CSV dışa aktarım eklendi; yalnız manage_woocommerce yetkisi ve nonce ile çalışır.


== 3.6.3 ==
* Tek sipariş için gerçek PayTR/WooCommerce iade testi eklendi.
* İşlem başına 10 TL sabit güvenlik limiti.
* Yalnız seçili MDG etkinliğini içeren, ödenmiş PayTR siparişleri uygundur.
* Yazılı onay + iki checkbox + tarayıcı onayı + nonce + tek kullanımlık token + sipariş kilidi.
* wc_create_refund(refund_payment=true, restock_items=false) kullanılır.
* Başarı/hata audit log ve sipariş notuna kaydedilir.
* Bu sürüm etkinliği iptal etmez, biletleri geçersizleştirmez ve toplu iade yapmaz.

Madagaskar Yönetim Merkezi V2 + V3
Version 1.3.2

1.3.2
- Madagaskar özel etkinlik şablonu tarayıcı tarafında güvenli biçimde tanınır.
- Özel bilet seçenekleri ve Devam Et düğmesi için view_item ve add_to_cart olayları eklendi.
- Bilet türü, adet, birim fiyat ve toplam değer GA4 öğelerine aktarılır.

1.3.1
- GA4 web akışı ölçüm kimliği ayarı ve temel Google etiketi eklendi.
- Özel /etkinlik/ sayfalarında view_item olayı gönderilir.
- Özel Bilet Al formları ve WooCommerce düğmeleri için add_to_cart takibi genişletildi.
- Mevcut Ads etiketi, sipariş, stok, ödeme ve CRM kayıtlarına dokunulmaz.

1.3.0
- Mevcut Google etiketi üzerinden GA4 view_item, add_to_cart, begin_checkout ve purchase e-ticaret olayları gönderilir.
- Olaylara ürün, miktar, para birimi ve tutar; purchase olayına sipariş numarası, vergi, kargo ve kupon bilgileri eklenir.
- Aynı siparişin teşekkür sayfası yenilendiğinde tekrar gönderilmesi tarayıcıda ve sabit transaction_id ile engellenir.
- Ölçüm kodu WooCommerce siparişlerini, stokları, ödeme durumlarını veya Kommo kayıtlarını değiştirmez.

1.2.3
- Bağlantı bilgileri tamamlanmamış kaynaklar saatlik görevde çalıştırılmaz; gereksiz hata kayıtları oluşmaz.
- Manuel senkronizasyon düğmeleri ilgili API bilgileri tamamlanana kadar pasif görünür.
- Mevcut Meta, Instagram, GA4, Kommo ve WooCommerce verileri korunur.

1.2.2
- WooCommerce siparişi Kommo lead ile eşleştiğinde etkinlik bağlamı Madagaskar ana veritabanından otomatik çözülür.
- Şehir, Gösteri Tarihi, Seans, Salon, Etkinlik Adresi ve Konum Linki Kommo lead alanlarına isimle eşleştirilerek yazılır.
- Salonlar / Etkinlik Yayınla tarafındaki etkinlik snapshot verisi canlı salon kaydına göre önceliklidir; geçmiş sipariş bağlamı korunur.
- Salon/adres/harita verisi eksikse venue_id üzerinden Salonlar tablosu yedek kaynak olarak kullanılır.
- Siparişe _mdgy_event_snapshot_v1 ve ilgili etkinlik meta alanları kaydedilir.
- V3 CRM Reklam → Sipariş Atıf Zinciri tablosuna Etkinlik sütunu eklendi.
- Kommo alan güncellemesi yalnız güvenle eşleşmiş WooCommerce sipariş lead'lerinde yapılır; diğer lead'lere dokunulmaz.

1.2.1
- Kommo WooCommerce sipariş eşleştirmesine güvenli başlık yedeği eklendi.
- Kommo özel sipariş alanı boşsa, lead adı tam olarak Order#1234 biçimindeyse sipariş numarası otomatik çıkarılır.
- Eşleşme WooCommerce siparişi varlığıyla doğrulanır; bulunan Kommo lead ID hem atıf tablosuna hem sipariş meta verisine yazılır.
- Woo Sipariş No alan ID ayar açıklaması yeni otomatik yedek yöntemi gösterecek şekilde güncellendi.

1.2.0
- Meta erişim anahtarını gerçek API çağrısıyla test eder.
- Reklam hesaplarını ad, ID, para birimi ve saat dilimiyle listeler.
- Facebook Sayfasına bağlı Instagram Business hesabını bulur.
- Tek hesap bulunursa reklam ve Instagram kimliğini otomatik kaydeder.
- Bağlantı hatasını erişim anahtarını göstermeden bildirir.
- Entegrasyon formunun mobil görünümü iyileştirildi.

1.1.0
- V5.4 ile aynı Madagaskar ana menüsüne bağlandı.
- Günlük menüye V2 Pazarlama, V3 CRM ve Entegrasyonlar eklendi.
- Eklenti ekranına doğrudan Entegrasyonlar bağlantısı eklendi.
- Devre dışı bırakmada yinelenen tüm zamanlanmış senkronlar temizlenir.

Amaç
- V2 Pazarlama: Meta Ads + Instagram + GA4
- V3 CRM: Kommo + WhatsApp + reklam -> müşteri -> satış atıf zinciri

Güvenlik
- Reklam/lead oluşturmaz veya satış aşamasını değiştirmez. Kommo’ya yalnız güvenle eşleşen WooCommerce sipariş lead’lerinde etkinlik bağlamı özel alanlarını yazar; diğer API kullanımı raporlama/senkronizasyon içindir.
- Meta/GA4/Kommo secret değerleri AUTH_KEY/SECURE_AUTH_KEY türevi anahtarla AES-256-CBC şifreli saklanır.
- Kommo webhook endpointi rastgele secret query key ile korunur.
- Webhook mesaj metni, telefon veya e-posta içeriğini raporlama tablosuna kaydetmez.

Kurulum
1. ZIP'i WordPress > Eklentiler > Yeni Eklenti > Eklenti Yükle ile kurun ve etkinleştirin.
2. Madagaskar > Entegrasyonlar ekranını açın.
3. Meta, GA4, Kommo bağlantı bilgilerini girin.
4. Manuel senkron butonlarıyla tek tek doğrulayın.
5. Kommo webhook URL'sini Kommo Ayarlar > Entegrasyonlar > Web hooks alanına ekleyin.

Meta URL standardı
utm_source=meta&utm_medium=paid_social&utm_campaign={{campaign.name}}&utm_id={{campaign.id}}&utm_content={{ad.name}}&utm_term={{adset.name}}

GA4
- Google Cloud'da Analytics Data API etkin olmalı.
- Service account e-posta adresi GA4 mülkünde Viewer/Analyst erişimine eklenmeli.

Kommo
- Tek hesap için Private Integration + Long-lived token önerilir.
- Webhook olayları: add_lead, update_lead, status_lead, add_message, add_outgoing_message.
- Woo sipariş no custom field ID girilirse Kommo lead -> Woo order eşleşmesi yapılır.

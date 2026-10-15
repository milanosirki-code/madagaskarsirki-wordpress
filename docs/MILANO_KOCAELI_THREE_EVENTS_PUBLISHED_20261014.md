# Milano Sirki — 21/23/24 Ekim Karamürsel, Darıca, Derince yayın kaydı (14.10.2026)

## Gerçek üretim değişiklikleri (Milano WordPress.com ID 257916618)

Kullanıcının yüklediği 3 özgün Milano afişi doğrudan WordPress.com medya kütüphanesine aktarıldı:
- Karamürsel medya #122: https://milanosirki.com/wp-content/uploads/2026/10/milano-karamursel-21-ekim-2026.png
- Darıca medya #123: https://milanosirki.com/wp-content/uploads/2026/10/milano-darica-23-ekim-2026.png
- Derince medya #124: https://milanosirki.com/wp-content/uploads/2026/10/milano-derince-24-ekim-2026.png

3 müstakil WordPress **yayında** etkinlik sayfası:
- #125: https://milanosirki.com/milano-sirki-karamursel-21-ekim-2026/
  - 21.10.2026 Çarşamba, 17:30 / 19:30, Atatürk Kapalı Spor Salonu, 4 Temmuz Mah., 115 Nolu Sokak, Karamürsel/Kocaeli
- #126: https://milanosirki.com/milano-sirki-darica-23-ekim-2026/
  - 23.10.2026 Cuma, 17:30 / 19:30, Eray Şamdan Kapalı Spor Salonu, Bağlarbaşı Mah. Özbay Sokak No:67, Darıca/Kocaeli
- #127: https://milanosirki.com/milano-sirki-derince-24-ekim-2026/
  - 24.10.2026 Cumartesi, 12:00 / 14:00 / 16:00, Derince Kapalı Spor Salonu, Yenikent Mah. Serpil Sokak No:2, Merkez Bankası Derince Anadolu Lisesi yanı, Derince/Kocaeli

**Kaynak:** 3 afiş + kullanıcıca sağlanan Karamürsel, Darıca, Derince `Biletinial yayın bilgileri` Word belgeleri. Yeni 3 etkinlik için belgelerdeki standart 500 TL yetişkin, 250 TL çocuk, 1.200 TL 2+2 aile + platform hizmet bedeli; 0-2 yaş ücretsiz; numarasız/serbest oturma; hayvansız yaklaşık 60 dk; kapılar ~45 dk önce. Gerçek kontenjan bilgisi olmayan yeni üç etkinlikte kontenjan uydurulmadı.

`/sehirler/` sayfa #6 güncellendi: Yalova'nın mevcut detayları korunarak özgün afişli 3 yeni etkinlik kartı, ayrı sayfa bağlantıları, seans/salon ve fiyat bilgileri eklendi. Ana sayfa #5 turne bölümüne 3 etkinliğin ayrı bağlantıları eklendi. Karamürsel 21 Ekim olduğundan Yalova'nın önceki “İlk gösteri” etiketleri ana sayfa ve şehir sayfasında düzeltildi. WP REST/WordPress.com readback: pages 5,6,125,126,127 **status=publish**, bağlantı içerikleri ve medya ID'leri doğrulandı.

## Marka ve bilet ayrımı
- Yalova'nın mevcut Milano staging MMC/MDG/Woo/Tickera üretim taslaklarına ve Madagaskar'ın canlı ürün/sipariş/PayTR/QR bilgilerine **dokunulmadı**.
- Yeni üç etkinlik **sadece Milano'nun kamuya açık etkinlik duyuru sayfalarına eklendi**. Bunlar MMC/MDG programı yaratıldığı, WooCommerce ürünü aktif edildiği, PayTR onayı alındığı veya bilet satışının başladığı anlamına GELMEZ.
- `/bilet-al/` sayfası zaten vardı; etkinlik sayfaları o sayfaya bilgilendirme bağlantısı veriyor. Bu adımda üç etkinlik için yeni otomatik ödeme/QR oluşturulmadı.
- **Fiyat tutarsızlığı açık:** Yalova'nın mevcut canlı bilgilendirme fiyatı 490/245/1100 TL; kullanıcıca aynı anda sağlanan `Milano_Sirki_Biletinial_Yalova_2026(1).docx` ise 500/250/1200 TL + hizmet bedeli. Kullanıcı “Yalova'yı yapmıştık” dediğinden Yalova mevcut kayıt/fiyatına dokunulmadı. Yetkili fiyat onayı ve kanallar arası uzlaştırma gerekir.
- Derince için adres belgedeki “Merkez Bankası Derince Anadolu Lisesi yanı” ifadesini korur; güvenilir kapasite bilinmiyor.
- Biletinial platformuna doğrudan yayın yapıldığı doğrulanmadı; yalnız Milano WordPress tanıtım sayfaları yayımlandı.

## Teknik kısıt
WPVibe günlük kullanım sınırı bu işlem sırasında doldu. Devam eden bölüm bazlı ana sayfa/Şehirler düzenlemeleri WordPress.com WordPress blok section API'si üzerinden güvenle yapıldı, site ayarları değiştirilmedi.

## İzlenecek işler
1. 3 etkinliğin MMC marka=Milano kanonik program, salon, seans ve kapasite oluşturma aşamasını Yalova'dan sonra bağımsız staging planlarıyla ele al; Madagaskar programlarıyla karıştırma.
2. Biletinial dış platform etkinlikleri gerçekten yayımlanacaksa ayrı platform erişimi/işletme onayıyla işlem yap; linkleri doğrulamadan varsayma.
3. Yalova farklı fiyatlarını ve platform hizmet bedelini kullanıcı ile uzlaştır.
4. Canlı seans/ödeme açılışını ancak marka, kapasite, dört QR aile, PayTR callback ve staging E2E doğrulaması tamamlandığında değerlendir.

Bu commit **rapor kaydıdır**; WordPress canlı değişiklikleri doğrudan yetkili WordPress bağlantılarıyla yapıldı. GitHub PR #235 kodları canlıya merge/deploy edilmedi.

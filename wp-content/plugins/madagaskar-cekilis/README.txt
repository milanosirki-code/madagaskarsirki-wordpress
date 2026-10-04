MADAGASKAR ÇEKİLİŞ SİSTEMİ – v3.0

Amaç
Simpliers benzeri temel Instagram yorum çekiliş akışını, yıllık üyelik olmadan kendi WordPress sitenizde yönetmek.

Özellikler
- Birden fazla çekilişi kalıcı olarak saklar.
- Instagram profesyonel hesabının kendi gönderilerindeki yorumları Meta Instagram API üzerinden çeker.
- Yorum içindeki farklı @arkadaş etiketlerini sayar.
- Marka hesabını ve yorum sahibinin kendisini arkadaş etiketi saymaz.
- “Her farklı yorum yeni şans” modelini destekler.
- İstenirse her kullanıcıya yalnızca 1 hak verilebilir.
- Aynı kullanıcının birebir aynı yorum tekrarını eleme seçeneği vardır.
- Aynı hesabın birden fazla asil/yedek sıra kazanmasını engelleme seçeneği vardır.
- Asil + yedek sonuçları kalıcı kaydedilir.
- Kazananlarda takip/beğeni manuel doğrulama durumu ve notu tutulur.
- CSV/Excel uyumlu katılım ve sonuç listesi indirilebilir.
- Çekiliş sonrası yorum listesi dondurulur.
- Denetlenebilir, tekrar hesaplanabilir HMAC-SHA256 tabanlı seçim sıralaması ve audit seed saklanır.
- Instagram access token WordPress veritabanında OpenSSL varsa AES-256-GCM ile şifreli tutulur.

Meta gereksinimleri
Instagram API with Instagram Login için temel izinler:
- instagram_business_basic
- instagram_business_manage_comments

Hesap Business veya Creator olmalıdır.

Önemli sınır
Instagram API, çekiliş katılımcısının gönderiyi beğenip beğenmediğini ve hesabı takip edip etmediğini çekiliş filtresi olarak güvenilir kişi bazında sunmaz. Bu nedenle seçilen aday kazananlarda “takip + beğeni” son kontrolü Instagram uygulamasından yapılır ve eklentide Doğrulandı / Şartı sağlamadı olarak işaretlenir.

Kurulum
1. ZIP dosyasını WordPress > Eklentiler > Yeni Ekle > Eklenti Yükle bölümünden yükleyin.
2. Etkinleştirin.
3. Sol menü > Sirk Çekiliş > Instagram Bağlantısı.
4. Meta Developer üzerinden alınan access tokenı kaydedin.
5. Bağlantıyı Test Et’e basın.
6. Yeni Çekiliş sekmesinden gönderi linki ve kuralları girin.
7. Yorumları kontrol edin.
8. Çekiliş kapanınca Çekilişi Yap’a basın.
9. Aday kazananlarda takip/beğeni kontrolünü yapıp sonucu işaretleyin.
10. CSV listesini arşivleyin.

Madagaskar standart çekiliş ayarı
- Minimum etiket: 1
- Asil: kampanyaya göre 2 veya 5
- Yedek: 2 veya 5
- Aynı yorum tekrarını tek say: AÇIK
- Her kullanıcı yalnızca 1 hak: KAPALI
- Aynı hesap birden fazla sıra kazanmasın: AÇIK

Sürüm: 2.2.0


V2.1 notu: Yorum çekmede ikinci bir field-expansion yedek yolu, gönderi comments_count kontrolü ve Development/Live teşhis mesajı eklendi.


2.2.0: Instagram Login istekleri Meta'nın sürümlü graph.instagram.com/v26.0 uç noktalarına taşındı; yanlış Live-mode teşhisi kaldırıldı.

2.3.0: API Teşhis Testi eklendi. Tokenı göstermeden /comments varyasyonlarını, field expansion ve Facebook Graph karşılaştırmasını özetler; Live/App Review varsayımlarına gitmeden gerçek API davranışını ayırır.

2.5.0: Instagram permalink eşleştirmesi kısa kod üzerinden yapılıyor. /p/, /reel/ ve /tv/ URL biçimleri aynı medya olarak tanınır; Reel bağlantılarının medya listesinde bulunamaması düzeltildi.


V2.5 yenilikleri:
- 1080x1920 Instagram Story sonuç görseli ve tarayıcıdan PNG indirme.
- Her çekiliş için 6 karakterli doğrulama kodu.
- Kodla açılan kamuya açık sonuç doğrulama sayfası.
- Asil/yedek ve manuel doğrulama durumları sonuç sayfasında gösterilir.
- Instagram API yorumcu profil fotoğrafını vermediğinden Story görselinde güvenli varsayılan avatar/baş harf kullanılır.


v2.6 paylaşım güncellemesi
- Story sonuç görselinde yalnızca asil kazananlar gösterilir; yedekler kamuya açıklanmaz.
- Story görselinde “Kontrol bekliyor” gibi iç doğrulama durumları gösterilmez.
- Etkinlik adı büyük başlık olarak yer alır.
- Geçerli katılım sayısı ve çekiliş sonucu açıklaması sadeleştirildi.
- Her kazanan için 1 yetişkin + 1 çocuk çift kişilik giriş hakkı bilgisi eklendi.
- Biletlerin etkinlik günü salon girişindeki gişeden Instagram kullanıcı adı ile teslim alınacağı notu eklendi.
- Kamuya açık doğrulama sayfası yalnızca asil kazananları gösterir; yedekler yönetim panelinde saklanmaya devam eder.


v2.7 paylaşım ve web sayfası güncellemesi
- Kullanıcının verdiği Madagaskar Sirki logosu Story görseline ve kamuya açık sonuç ekranına eklendi.
- Story bilet metni güncellendi: 1 veli + 1 çocuk, salon gişesinde Instagram kullanıcı adı göstererek ücretsiz bilet temini ve istenilen seansta kullanım.
- Yedekler kamuya açık Story ve sonuç sayfalarında gösterilmez.
- WordPress içinde otomatik “Çekiliş Sonuçları” sayfası oluşturulur.
- [madagaskar_cekilis_sonuclari] kısa kodu son 50 çekilişi, asil kazananları, geçerli katılım sayısını ve doğrulama kodunu listeler.
- Yönetim paneline “Çekiliş Sonuçları Sayfası” bağlantısı eklendi.

v2.8 kurumsal sonuç sayfası ve doğrulama URL güncellemesi
- Story görselindeki üst Madagaskar Sirki logosu büyütüldü; alt marka logosu da daha görünür hale getirildi.
- Genel “Çekiliş Sonuçları” sayfası açık krem/beyaz kurumsal arka plan, beyaz kartlar, Madagaskar kırmızısı ve koyu lacivert ayrıntılarla yeniden tasarlandı.
- Çekiliş/şehir başlığı kamuya açık sonuçlarda otomatik büyük harf gösterilir.
- Asil kazanan kullanıcı adları Instagram profillerine tıklanabilir bağlantı haline getirildi.
- Doğrulama bağlantıları temiz URL yapısına geçirildi: /cekilis-dogrula/KOD/
- Eski ?mck_verify=KOD bağlantıları geriye dönük olarak çalışmaya devam eder.


2.9.0: Çekiliş Sonuçları sayfasında bazı temalarda görülen sağa kayma/yatay taşma sorunu giderildi; sonuç alanı güvenli, ortalanmış kart düzenine alındı.


v3.0 yenilikleri
- Çekiliş Sonuçları sayfası mobilde daha kompakt hale getirildi.
- Logo ve başlık alanı mobilde küçültülerek kazananların ekranda daha erken görünmesi sağlandı.
- Tarih ve saat birlikte, tek parça meta alanı olarak gösterilir.
- Ücretsiz giriş hakkı metni kısaltıldı ve “istenilen seans” vurgusu ayrı satıra alındı.
- Mobilde Sonucu doğrula butonu tam genişlik olur.
- Kamuya açık doğrulama sayfasının mobil görünümü sıkılaştırıldı.
- Story sonuç tasarımında üst başlık ve kazananlar yukarı taşındı, boşluklar azaltıldı.

== 3.1.0 ==
- Çekiliş Sonuçları sayfasını klasik WordPress ana/header menüsüne otomatik eklemeyi dener.
- Google için sonuç sayfasına SEO başlığı, meta açıklaması, canonical, Open Graph ve Schema.org işaretlemesi ekler.
- Doğrulama sayfaları artık index/follow olarak yayımlanır ve benzersiz SEO başlık/açıklama kullanır.
- /cekilis-sitemap.xml özel çekiliş site haritası oluşturur.
- robots.txt içine çekiliş site haritası eklenir.
- Sonuç arşivine arama motorları için açıklayıcı metin eklendi.


V3.2:
- Çekiliş Sonuçları üst menüden otomatik olarak gizlenir.
- Footer altında sade Çekiliş Sonuçları bağlantısı gösterilir.
- Sitemap endpointi /cekilis-sitemap.xml ve /cekilis-sitemap.xml/ biçimlerini destekler.
- Sitemap yanıt başlıkları Google bot erişimi için sadeleştirildi.


V3.3 SEO / Sitemap:
- Ayrı cekilis-sitemap.xml kaldırıldı.
- Çekiliş doğrulama URL'leri WordPress çekirdek wp-sitemap.xml sistemine özel sağlayıcı olarak eklenir.
- /cekilis-sonuclari/ normal WordPress sayfası olduğu için page sitemap içinde yer alır.
- Eski /cekilis-sitemap.xml adresi geriye dönük olarak /wp-sitemap.xml adresine 301 yönlenir.
- Eklenti artık ekstra footer şeridi basmaz; footer bağlantısı tema şablonundan yönetilir.

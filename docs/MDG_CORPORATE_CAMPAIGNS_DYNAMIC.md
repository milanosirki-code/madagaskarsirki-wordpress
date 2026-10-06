# Ortak kurumsal kampanya sayfası

Kullanıcı Denizli'ye özel sabit ekran yerine kodla ilinin güncel gösterilerini açan, haftalık sayfa düzenlemesi gerektirmeyen ortak kampanya akışı istedi. Şifre istenmez. Sayfa adresi `/kampanya/`; eski `/kurumsal-davetiye-pilot/` bağlantısı GET için 301, POST için 307 ile yönlendirilir. Kurum kodları gerçek Woo checkout akışına bağlanmıştır; TEST- kodları yalnız denemedir.

## Ortak satış kaynağı

`MDG_Public_Tickets::active_events()` doğrudan kullanılır; bu, normal `/bilet-al/` sayfasının da kaynağıdır. Ayrı etkinlik listesi, sabit Woo ürün kimlikleri veya haftalık kopyalama yoktur. Kod province_name ile bağlanır; aynı ilde satışa açılan yeni etkinlikler ve seanslar bir sonraki istekte otomatik gelir. Geçmiş/kapalı seans, dolu kapasite, eksik mapping ve satışa uygun olmayan varyasyonlar gösterilmez. ADULT/CHILD ve YETISKIN/COCUK türleri desteklenir; aile paketi ücretsiz çocuk hesabına karıştırılmaz.

Yalnız kampanya shortcode'unun bulunduğu sayfa için `template_redirect` aşamasında DONOTCACHEPAGE ve nocache_headers istenir. Ancak canlı WordPress.com GET yanıtında platform `public, max-age=300, s-maxage=600` başlığı gönderdi; host önbelleğinin tamamen devre dışı kaldığı iddia edilmez. Ana kullanıcı akışı kod girme ve hesaplama POST'larıyla her istekte kaynakları yeniden okur. GET ile paylaşılan kodlu bağlantılar host önbelleği süresince gecikebilir. Diğer sayfaların önbellek davranışı değiştirilmez.

## Kodlar

- Case-insensitive; I/İ/ı, ş/Ş, ğ/Ğ, ü/Ü, ö/Ö, ç/Ç aynı ASCII karşılığa dönüşür. Baş/son boşluklar temizlenir, boşluk/altçizgi ayraçları tire kabul edilir. Diğer noktalama reddedilir.
- Yeni yönetim menüsü: WooCommerce → Kurumsal Kampanyalar.
- Bir kurum kodu bir ile bağlanır; kurum adı ve isteğe bağlı son geçerlilik günü tanımlanır. Kod açılıp kapatılabilir. Aynı normalize kod ikinci kez tanımlanamaz.
- Bütün kayıtlar yalnız `mdg_corporate_campaign_codes_v1` option'ında tutulur; kod tanımı dışındaki etkinlik/bilet/sipariş/ödeme verileri yazılmaz.
- Kurum kodları yalnız yetkili `manage_woocommerce` kullanıcısının nonce korumalı admin formunda kaydedilir. TEST- öneki denemeye ayrılmıştır.
- Satıştaki her ilin `TEST-<il>` kodu otomatik çalışır. Örnek: TEST-DENIZLI, TEST-İZMİR, test-manisa. Bu kodlar indirim yetkisi veya üyelik doğrulaması değildir.
- Paylaşımda `?kod=kurum-kodu` kullanılabilir. Kodu olmayan ziyaretçi yalnız giriş formunu görür. Kod/nonce hatasında başka iller açılmaz.

## Akış ve sınırlar

Kod → aynı ildeki aktif gösteriler → salon/tarih/seans/güncel yetişkin ve çocuk fiyatı → kişi sayısı ve her çocuğun yaşı → kampanya hesabı. 0–2 ücretsiz ve kampanya hakkını tüketmez. 3–12 yaş dahil çocukların ücretli yetişkin bileti başına ikisi ücretsiz; hak üzerindeki çocuklar normal Woo çocuk fiyatıyla ücretlidir. 13+ yaş girilen kişiler yetişkin fiyatıyla yetişkin bileti sayılır ve ücretli yetişkin bileti sayısına dahil olur. Aynı kişi hem yetişkin sayısına hem yaş listesine eklenmemelidir.

İzmir gibi farklı fiyatlar güncel Woo varyasyonundan okunur. Her çocuk için yaş yazmak yerine doğum tarihi girilir. Tamamlanmış yaş, seçilen seansın Türkiye yerel gösteri gününe göre hesaplanır; satın alma günü esas alınmaz. Gösteri gününde 13. doğum gününü tamamlayan kişi yetişkin fiyatıdır. Geçersiz takvim günü, gelecekte doğum, eksik/dizi tarih ve 120 yaş üstü reddedilir. Leap-day doğumları geçerli takvim hesabıyla ele alınır.

Kod/seans ve doğum tarihleri her hesaplamada sunucuda yeniden doğrulanır; başka ile ait seans gönderilirse reddedilir. Tarih sayısı seçilen çocuk sayısına eşit olmalıdır. Doğum tarihi alanları, hesaplanan yaş ve ücretli/ücretsiz durumları JavaScript ile anlık güncellenir; seans değişince yaş yeniden hesaplanır. Sunucu sonucu yetkilidir. JavaScript yoksa doğum tarihi alanlarını güncelle düğmesi kullanılabilir. Hesaplamada doğum tarihleri yalnız istek içinde kullanılır. Ödemeye geçerken WooCommerce oturumuna yazılır; sipariş veya bilet metalarına kaydedilmez.

Form 1–10 yetişkin ve 0–20 çocuk girdisi destekler; ücretsiz hak ücretli yetişkin bileti sayısıyla artar. Bunlar girdi sınırlarıdır, toplu/kurumsal grup alımına kampanya izni değildir. Hesaplama rezervasyon veya bilet değildir. Gerçek kurum kodları imzalı sepet seçimiyle mevcut Woo/PayTR/Tickera akışına geçer. Güncel kod, seans, doğum günü, fiyat ve adetler sunucuda yeniden doğrulanır. Ücretsiz çocuk ve 0–2 yaş mevcut çocuk varyasyonuyla ayrı sıfır fiyatlı satırlar olur; hepsi ortak kapasiteye dahildir. Mevcut MDG_Live_Sales ödeme öncesi atomik hold mekanizması kullanılır. Yeni bilet üretim hooku eklenmez; mevcut Tickera Bridge kullanılır. Kuponların kampanya satırlarına ek indirim yapması engellenir. Ücretli yetişkinin çıkarılması veya ücretsiz adet/ürün manipülasyonu ödemeyi engeller. Yetişkinle giriş koşulu sipariş satırında görünür; scanner tarafında yeni otomatik eşlik engeli eklenmemiştir. Gerçek banka ödemesi ve sahadaki QR okutma henüz test edilmemiştir. Kurum kodu paylaşım bazlıdır; üyeliği doğrulamaz. Ortak kod üyeliği kanıtlamaz.

Denizli fiyatıyla örnekler: 1 yetişkin + 3 uygun çocuk = 500 + 250 = 750 TL; 2 yetişkin + 3 çocuk = 1.000 TL; 2 yetişkin + 5 çocuk = 1.250 TL. 1 yetişkin + yaşı 13 olan kişi + 3 uygun çocuk = 2 yetişkin bileti, 3 ücretsiz çocuk = 1.000 TL.

## Deploy ve test

Kaynak `docs/code-snippets/mdg-corporate-campaigns.php`. Yeni Code Snippets kaydı, açılış PHP etiketi çıkarılarak scope global. Önce pasif kaydet, aktivasyon öncesi lint ve regression, draft PR. Mevcut kampanya sayfa 4520'nin yalnız başlığı ve shortcode'u `[mdg_corporate_campaigns]` olarak değiştirilir; slug /kampanya/ yapılır ve parola boş bırakılır. Eski Denizli shortcode snippet 123 yeni modül doğrulandıktan sonra pasifleştirilir. Diğer snippetler değiştirilmez.

Kontroller: normalize Türkçe/büyük/küçük kodlar; kodsuz ekran; şehir kapsamı; yanlış kod; kapalı/süresi bitmiş kod; yeni etkinliğin otomatik gelmesi; kapalı/geçmiş/dolu seans; çapraz şehir seans manipülasyonu; güncel fiyat; eşleşme; nonce; normal bilet-al/sepet/ödeme URL smoke testi. Admin kod ekle/durdur ekranı ayrı yetkili tarayıcı smoke testi gerektirir.

`php -l docs/code-snippets/mdg-corporate-campaigns.php`

`php tests/corporate-invitation/dynamic-catalogue.php`

Rollback: yeni snippet'i pasifleştir, snippet 123'ü eski koduyla tekrar etkinleştir, sayfa 4520 shortcode'unu eski hale getir. Kurum kodu option'ını körlemesine silme. Normal bilet sistemine dokunma.

## Denizli kurum fiyatı — 5 Ekim 2026

Denizli program 10 / MDG etkinlik 12 için gerçek kurum kodlarıyla ücretli yetişkin 475 TL (normal Woo varyasyon 500 TL korunur). Kartta normal fiyat üstü çizili ve kampanya fiyatı görünür. Çocuk 250 TL, ücretsiz haklar aynı kalır. TEST kodları normal fiyat önizler. Başka Denizli etkinliğine veya İzmir'e indirim uygulanmaz. Sunucu hesabı, canlı JS veri alanları ve imzalı sepet aynı etkin fiyatı kullanır. Önceden oluşmuş siparişler değişmez. Normal fiyat 475 altına düşerse kampanya daha pahalı olmaz. 1 yetişkin + 2 çocuk 475; 2 yetişkin + 4 çocuk 950; 1 yetişkin + 3 çocuk 725 TL. Rollback snippet 124 PR137 kaynağına döner.

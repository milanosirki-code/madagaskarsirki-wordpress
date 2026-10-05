# Ortak kurumsal kampanya sayfası

Kullanıcı Denizli'ye özel sabit ekran yerine kodla ilinin güncel gösterilerini açan, haftalık sayfa düzenlemesi gerektirmeyen ortak kampanya akışı istedi. Şifre istenmez. Bu sürüm önceki pilot gibi ödeme/QR üretmeyen deneme aşamasıdır; gerçek ücretsiz bilet üretimi ayrı entegrasyon aşamasıdır.

## Ortak satış kaynağı

`MDG_Public_Tickets::active_events()` doğrudan kullanılır; bu, normal `/bilet-al/` sayfasının da kaynağıdır. Ayrı etkinlik listesi, sabit Woo ürün kimlikleri veya haftalık kopyalama yoktur. Kod province_name ile bağlanır; aynı ilde satışa açılan yeni etkinlikler ve seanslar bir sonraki istekte otomatik gelir. Geçmiş/kapalı seans, dolu kapasite, eksik mapping ve satışa uygun olmayan varyasyonlar gösterilmez. ADULT/CHILD ve YETISKIN/COCUK türleri desteklenir; aile paketi ücretsiz çocuk hesabına karıştırılmaz.

Yalnız kampanya shortcode'unun bulunduğu sayfa için `template_redirect` aşamasında DONOTCACHEPAGE ve nocache_headers uygulanır; kod bazlı sonuç ve yeni seanslar sayfa önbelleğine takılmaz. Diğer sayfaların önbellek davranışı değiştirilmez.

## Kodlar

- Case-insensitive; I/İ/ı, ş/Ş, ğ/Ğ, ü/Ü, ö/Ö, ç/Ç aynı ASCII karşılığa dönüşür. Baş/son boşluklar temizlenir, boşluk/altçizgi ayraçları tire kabul edilir. Diğer noktalama reddedilir.
- Yeni yönetim menüsü: WooCommerce → Kurumsal Kampanyalar.
- Bir kurum kodu bir ile bağlanır; kurum adı ve isteğe bağlı son geçerlilik günü tanımlanır. Kod açılıp kapatılabilir. Aynı normalize kod ikinci kez tanımlanamaz.
- Bütün kayıtlar yalnız `mdg_corporate_campaign_codes_v1` option'ında tutulur; kod tanımı dışındaki etkinlik/bilet/sipariş/ödeme verileri yazılmaz.
- Kurum kodları yalnız yetkili `manage_woocommerce` kullanıcısının nonce korumalı admin formunda kaydedilir. TEST- öneki denemeye ayrılmıştır.
- Satıştaki her ilin `TEST-<il>` kodu otomatik çalışır. Örnek: TEST-DENIZLI, TEST-İZMİR, test-manisa. Bu kodlar indirim yetkisi veya üyelik doğrulaması değildir.
- Paylaşımda `?kod=kurum-kodu` kullanılabilir. Kodu olmayan ziyaretçi yalnız giriş formunu görür. Kod/nonce hatasında başka iller açılmaz.

## Akış ve sınırlar

Kod → aynı ildeki aktif gösteriler → salon/tarih/seans/güncel yetişkin fiyatı → kişi sayısı → kampanya hesabı. Çocuklar 3–12 yaş dahil, ücretli yetişkin başına en fazla iki; 0–2 mevcut ücretsiz kuralına tabi. İzmir gibi farklı fiyatlar güncel Woo varyasyonundan okunur. Kod ve seans her hesaplamada yeniden doğrulanır; başka ile ait seans gönderilirse reddedilir. Hesaplama rezervasyon veya bilet değildir.

İlk pilotta yalnız 1–2 yetişkin ve 1–4 çocuk hesabı vardır. Gerçek alışveriş/PayTR/Tickera, kupon kapsamı, kota, iade ve yetişkin bağlı check-in entegrasyonu henüz yoktur. Ortak kod üyeliği kanıtlamaz.

## Deploy ve test

Kaynak `docs/code-snippets/mdg-corporate-campaigns.php`. Yeni Code Snippets kaydı, açılış PHP etiketi çıkarılarak scope global. Önce pasif kaydet, aktivasyon öncesi lint ve regression, draft PR. Mevcut kampanya sayfa 4520'nin yalnız başlığı ve shortcode'u `[mdg_corporate_campaigns]` olarak değiştirilir; URL korunur ve parola boş bırakılır. Eski Denizli shortcode snippet 123 yeni modül doğrulandıktan sonra pasifleştirilir. Diğer snippetler değiştirilmez.

Kontroller: normalize Türkçe/büyük/küçük kodlar; kodsuz ekran; şehir kapsamı; yanlış kod; kapalı/süresi bitmiş kod; yeni etkinliğin otomatik gelmesi; kapalı/geçmiş/dolu seans; çapraz şehir seans manipülasyonu; güncel fiyat; eşleşme; nonce; normal bilet-al/sepet/ödeme URL smoke testi. Admin kod ekle/durdur ekranı ayrı yetkili tarayıcı smoke testi gerektirir.

`php -l docs/code-snippets/mdg-corporate-campaigns.php`

`php tests/corporate-invitation/dynamic-catalogue.php`

Rollback: yeni snippet'i pasifleştir, snippet 123'ü eski koduyla tekrar etkinleştir, sayfa 4520 shortcode'unu eski hale getir. Kurum kodu option'ını körlemesine silme. Normal bilet sistemine dokunma.

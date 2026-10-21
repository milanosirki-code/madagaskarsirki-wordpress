# Torbalı — yayın ve satış kontrol kaydı

Güncelleme: 21 Ekim 2026. **Durum: Satışta.** Bu kayıt önceki taslak kontrol sonucunu günceller; önceki sürümler Git geçmişinde korunur.

## Yayın
Mevcut Torbalı etkinliği sistemin kendi yayın servisiyle yayımlandı. İki seans satışta; WooCommerce ürünleri yayımlanmış ve satın alınabilir. Mevcut nesneler korundu, mükerrer program/etkinlik/bilet oluşturulmadı. MMC–MDG satış eşleştirmesi 4/4 tamamlandı; program durumu Satışta.

Canlı etkinlik ve bilet seçimi: https://madagaskarsirki.com/etkinlik/uluslararasi-madagaskar-sirki-torbali-30-ekim-2026-30-ekim-2026/

18.00 ve 19.30 seçimi aynı sayfada yapılır; ayrı seansa özel kalıcı bağlantı doğrulanmadı.

## Fiyatlar ve kapasite
|Seans|Çocuk 3–12|Yetişkin 13+|Aile 2+2|Kapasite|
|---|---:|---:|---:|---:|
|18.00|245 TL|490 TL|1.100 TL / 4 kişi|500 kişi|
|19.30|245 TL|490 TL|1.100 TL / 4 kişi|500 kişi|

30 Ekim 2026 Cuma, Torbalı Spor Salonu. 0–2 yaş ücretsiz. Aile paketi 2 çocuk + 2 yetişkin satırıyla dört kişi olarak işlenir; 1.470 TL üzerinden 370 TL indirim uygulanır.

## Tamamlanan kontroller
- Native yayın hazırlık kontrolü başarılı; mevcut ürün, varyasyon, Tickera etkinliği ve PDF şablonu bağlantıları korundu.
- Canlı 18.00 aile paketi: sepette 4 kişi / 1.100 TL; checkout doğru seans, dört katılımcı alanı ve PayTR seçeneği gösterdi.
- Canlı 19.30 çocuk + yetişkin: sepette ve checkout'ta 735 TL, doğru seans ve PayTR seçeneği görüldü.
- Sipariş Ver kullanılmadı; canlı müşteri siparişi, gerçek tahsilat veya sahte ödeme bildirimi oluşturulmadı. Test sepeti temizlendi; boş sepet doğrulandı.
- Son kapasite kontrolü her seans 500; tutulan ve satılan kişi sıfır.
- Anonim etkinlik, bilet-al ve şehirler sayfaları HTTP 200; Torbalı listelerde görünür, canlı etkinlik index/follow.
- Milano bilet sayfası HTTP 200; marka ayrımı korunuyor. Ortak ödeme anahtarları, callback veya Milano siparişleri değiştirilmedi. Milano için tam checkout regresyonu yapılmadı.
- Torbalı afişi etkinlik, paylaşım görseli ve ürünlere bağlı. İzmir'den uyarlanmış 15 SSS mevcut; iade cevapları resmî satış koşullarından alındı.
- Etkinliğe özgü SEO başlığı/açıklaması, afiş OG görseli ve Event + iki subEvent yapılandırılmış verisi doğrulandı.

## Test edilmemiş veya açık kalanlar
- PayTR gateway aktif ve native sağlık durumu prepared. Başarılı ödeme/callback doğrulaması yapılmadı; verified ödeme iddiası yok.
- Gerçek Torbalı PDF çıktısı, QR tarama/doğrulama ve müşteriye teslim zinciri uçtan uca doğrulanmadı. Native örnek PDF sekmesi oluştu fakat çıktı incelenemedi. Şablonda bağımsız fiyat alanı doğrulanmadı.
- Başarısız ödeme, iptal ve iade sonrası kapasite davranışı işlemsel olarak test edilmedi.
- Mobil ekran boyutunda görsel kontrol tamamlanmadı.
- Genel site SEO katmanı aynı HTML'de ek description/OG etiketleri üretiyor; bu genel ayarlar değiştirilmedi.

Yayın, bu eksikler kullanıcıya bildirildikten sonra verilen “Devam et satışa aç” talimatıyla gerçekleştirildi. Bu kayıt tüm uçtan uca testlerin başarılı olduğu anlamına gelmez.

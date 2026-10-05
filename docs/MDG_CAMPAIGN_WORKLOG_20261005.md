# Madagaskar Sirki — Kurumsal kampanya çalışma ve devir kaydı
Kayıt tarihi: 5 Ekim 2026 (Türkiye saati).
Kapsam: Bu görüşmede yapılan kurumsal kampanya, biletleme, raporlama ve fiyat gösterimi çalışmaları.

## Canlı sonuç
- Kampanya sayfası: https://madagaskarsirki.com/kampanya/
- Sayfa ID: 4520; şifresiz, kurum kodu ile ilgili il/program açılır.
- Kısa kod: [mdg_corporate_campaigns].
- Aktif kampanya snippet: 124. Aktif satış raporu snippet: 125. İlk Denizli pilot snippet 123 pasif.
- Kurum kodları: bms ve sagliksendenizli; Denizli kapsamlı.
- Normal Bilet Al kataloğunda satışa açık programlar/seanslar kampanya kataloğuna otomatik yansır. Kapalı, geçmiş veya kapasitesi tükenen seanslar gösterilmez.
- Kod normalizasyonu büyük/küçük harf ve Türkçe harf farklılıklarını karşılar.
- Afiş, kısa tanıtım, gösteri içeriği, salon ve seans bilgileri seçilen etkinliğin kendi verisinden gelir.

## Bilet kuralları
- Doğum tarihi girilir; yaş seçilen gösteri tarihinde otomatik hesaplanır.
- 0–2 yaş ücretsiz; 3–12 yaş çocuk; 13 yaş ve üzeri yetişkin.
- Her ücretli yetişkin yanında en fazla 2 çocuk kampanyadan ücretsizdir.
- Fazla 3–12 yaş çocuk normal çocuk fiyatıyla ücretlendirilir.
- 13+ doğum tarihi girildiğinde yetişkin bileti hesabına dahil edilir; aynı kişi ikinci kez yetişkin sayısına eklenmemelidir.
- Bir çocuk seçildiğinde ikinci doğum tarihi alanının zorunlu kalması giderildi. Çocuk sayısı alanları, doğum tarihleri ve ücret hesabı birlikte güncellenir.
- TEST il kodları yalnız teklif/test içindir; gerçek ödeme başlatamaz.

## Denizli fiyatları ve pazarlama gösterimi
Program 10 / MDG etkinlik 12; 8 Ekim 2026, 17.30 ve 19.30; Denizli Büyükşehir Belediyesi Kongre ve Kültür Merkezi Özay Gönlüm Salonu.
- Normal satış yetişkin: 500 TL; çocuk: 250 TL. Normal WooCommerce ürün fiyatları değiştirilmedi.
- Yalnız bu Denizli programının gerçek kurum kampanyasında yetişkin 475 TL.
- 1 yetişkin + 2 çocuk (3–12): normal ayrı bilet toplamı 1.000 TL üzeri çizili; kampanya 475 TL.
- Tek çocuk (3–12): 750 TL → 475 TL; çocuksuz: 500 TL → 475 TL.
- 1 yetişkin + 3 çocuk (3–12): 1.250 TL → 725 TL.
- 2 yetişkin + 4 çocuk (3–12): 2.000 TL → 950 TL.
- Normal karşılaştırma mevcut bireysel yetişkin/çocuk fiyatlarından hesaplanır; Aile Paketi fiyatı olarak sunulmaz.
- Bebekler normal karşılaştırma tutarına eklenmez. Kişi/yaş değişince normal ve kampanyalı toplamlar yeniden hesaplanır.

## Ödeme, bilet ve satış raporu
Mevcut WooCommerce → PayTR → Tickera/Checkinera hattı korunmuştur. Kampanya yetişkin, ücretli çocuk ve ücretsiz çocuk satırları mevcut ürün eşlemeleriyle sepete eklenir. Sunucuda imzalı kampanya verisi ve sepet miktarları doğrulanır; yetişkinsiz ücretsiz çocuk veya değiştirilmiş miktarlar engellenir. Normal sepetler etkilenmez.
Madagaskar → Kampanya Satışları menüsünde program bağlantısıyla kampanyalı satışlar izlenir. Rapor salt okunurdur.
Ham doğum tarihleri sipariş metadatasına yazılmaz.
Çocukların yetişkinleriyle giriş yapması kampanya koşuludur; bu çalışma yeni bir QR üretim motoru veya birlikte giriş için yeni turnike kontrolü eklememiştir.

## GitHub kayıtları
Repo: milanosirki-code/madagaskarsirki-wordpress.
Son birleştirilmemiş çalışma dalı: codex/campaign-family-price-comparison.
- PR132: ilk pilot
- PR133: dinamik kurum/il kataloğu
- PR134: çocuk yaşı ve ücret hesabı
- PR135: doğum tarihi
- PR136: mevcut bilet/ödeme hattına bağlama
- PR137: çocuk sayısı ve JavaScript düzeltmesi
- PR138: kampanya satış raporu
- PR139: Madagaskar/programa bağlı rapor menüsü
- PR143: Denizli kurum yetişkin fiyatı 475 TL
- PR144: etkinlik afişi ve içerik
- PR145: normal aile toplamı / kampanyalı fiyat karşılaştırması
Son PR: https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/145
Bu çalışmalar draft PR zincirindedir; main'e birleştirilmiş olarak değerlendirilmemelidir. Canlı snippet yayını ayrıca yapılmıştır.

## Doğrulama
Son değişiklik: PHP syntax geçti; 96 katalog/teklif/koruma kontrolü ve DOM etkileşim kontrolleri geçti.
Canlı bms ve sagliksendenizli sayfalarında afiş ve 1.000 → 475 gösterimi doğrulandı. Canlı HTML ve yayınlanan script birlikte çalıştırılarak 2/1/0 çocuk normal toplamlarının 1.000/750/500 ve kampanya toplamının 475 olduğu doğrulandı.
Native WooCommerce yetişkin variation 2427 fiyatı 500 TL olarak okundu.
Önceki biletleme aşamasında izole sepet ve PayTR ödeme ekranı 475 TL ve ücretsiz çocuk satırlarıyla kontrol edildi. Gerçek sipariş/ödeme tamamlanmadı, gerçek QR okutma testi yapılmadı.
Son fiyat gösterimi aşamasının HTTP POST canlı teklif kontrolü tamamlanamadı; sunucu hesaplaması otomatik PHP testleriyle doğrulandı.

## Kaynaklar, teknik notlar ve geri alma
Yaş standardı için güncel MADAGASKAR_SIRKI_bilgi_notu_GUNCEL.docx incelendi. Kullanıcının bu görüşmedeki Denizli kampanya fiyat kararı uygulandı.
Kaynak dosyalar:
- docs/code-snippets/mdg-corporate-campaigns.php
- docs/code-snippets/mdg-campaign-sales-report.php
- tests/corporate-invitation/dynamic-catalogue.php
- tests/corporate-invitation/age-ui.cjs
- docs/CAMPAIGN_FAMILY_PRICE_COMPARISON.md
JavaScript shortcode içeriğine gömülmemeli; WordPress içerik filtrelerindeki entity dönüşümü nedeniyle wp_footer üzerinden ayrı yayınlanır.
Fiyat gösterimini geri almak için snippet 124 kaynağı codex/campaign-event-poster dalındaki önceki sürüme döndürülür; aktif tutulur. Kurum kayıtları, ürünler ve siparişler değiştirilmez.
Daha kapsamlı geri alma için ilgili PR'ın önceki kaynağı ve bağımlılıkları birlikte incelenmelidir.
Bu kayıt bir tam site/veritabanı yedeği değildir; kampanya işinin kaynak ve devir kaydıdır.

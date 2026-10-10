# Finans mutabakat inceleme listesi — 10 Ekim 2026

İş #220'nin ikinci adımı: Finans → Mutabakat. Mevcut gider, gelir ve sabit gider kaynakları okunur; yeni muhasebe defteri veya banka entegrasyonu kurulmaz.

## Davranış

- Kayıt ayı ve seçili MDG programı korunur. Program seçiliyken başka programlar, işletme ortak giderleri ve işletme sabit giderleri listelenmez; kapsam açıklaması görünür.
- Ödeme/tahsilat beyanı, geçmiş teyidi eksik, bekliyor, takip başlatılmış, kayıtlı tutar farkı ve kaynak sistem kayıtları ayrı süzülür. Ek dosya, ödeme yöntemi, belge numarası ve “Ödendi” notu banka teyidi oluşturmaz.
- Gelir satırında brüt, komisyon ve net ayrı görünür. Eski “collected” durumu bankaya geçen tutar olarak sunulmaz. WooCommerce/iade/Meta kayıtları kendi kaynağında incelenir; manuel çoğaltma bağlantısı verilmez.
- Takipli kayıtta geçmiş teyit, yeni hareket ve kalan ayrı gösterilir. Geçmiş teyit hesap hareketine tekrar eklenmez. İnceleme bağlantısı mevcut gerekçeli düzeltme/teyit formunu açar.
- Bu sayfa banka/PayTR ekstresi eşleştirme veya tamamlanmış hesap mutabakatı değildir. Hesap, başlangıç bakiyesi ve doğrulanmış dönem ekstresi olmadan gerçek banka bakiyesi üretilmez.

## Doğrulama ve canlı kontrol planı

PHP syntax; web snapshot, web refund ve ortak gider sözleşmeleri; 43 finans kayıt kontrolü geçti. Yeni kontroller: beyan/ek dosya ödeme sayılmaz, otomatik kaynak korunur, fazla tutar farkı görünür, program kapsamı ve boş filtre, brüt/komisyon ayrımı, inceleme sayfası finans kaydı değiştirmez.

Canlı kontrol: finans dosyası deploy öncesi GitHub main ile birebir; Mutabakat sekmesi açılışı, Manisa program ve kayıt ayı seçimi, durum filtresi, kaydı inceleme bağlantısı; finans takip tabloları ve korunan eski giderler önce/sonra karşılaştırılır. Gerçek ödeme, gelir, hesap veya başlangıç teyidi deneme amacıyla oluşturulmaz. Checkout/sipariş kodu değişmez.

## Geri alma ve sonraki adım

Yalnız `class-mdg-v5-finance.php` dosyasını deploy öncesi sürüme döndürün. Şema ve kayıt dönüşümü yoktur; veri silme gerekmez. Geçmiş “Ödendi” kayıtlarını belgeleriyle inceleyip mevcut formdan gerekçeli teyit etmek ve gerçek banka/PayTR ekstreleriyle eşleştirme bir sonraki kapsamdır. Hesap ve kanıt bulunmadan otomatik toplu teyit yapılmaz.

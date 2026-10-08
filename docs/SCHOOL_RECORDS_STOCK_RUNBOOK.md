# Okul veri / rota çıktı / yıllık baskı-stok akışı

- Okul Tanıtım 1.8.2; MMC 1.3.48 korunur.
- MEBBİS kurum importu aynı kalır. Öğrenci araştırması ayrı, ID yaratmayan önizleme/onay aktarımıdır.
- Öğrenci Aktarımı / Geçmiş: aynı sayfanın XLSX şablonunu indir; il/ilçe/kurum adı eşleşir. Sayı boşsa mevcut değer korunur. Aynı okul/çift eşleşme hataları kaydedilmez.
- Temiz Liste + Birleştirme Kontrolü bulunan araştırma kitabında kampüs toplamı ayrı okullara çoğaltılmaz; bileşen okul sayıları kullanılır. Bileşeni olmayan birleşik kampüsler manuel incelemeye bırakılır.
- Manuel Öğrenci Sayıları: öğrenci, kaynak türü (MEB il/ilçe müdürlüğü dahil), URL, evrak/kaynak notu ve veri yılı. Yeni kayıt yıllık snapshot ekler; geçmiş yıla girilen kayıt daha yeni güncel sayıyı ezmez.
- Rota: mevcut rota sırası/personel/program/salon/tarih/adres/öğrenci/Maps içeren gerçek .xlsx; Rota Planı / PDF sayfasında tarayıcı Yazdır → PDF kaydet. Çıktılar rota veya atama değiştirmez.
- Okul Baskı Planı okul/kampüs dağıtım hedeflerini saklar. Baskı / Stok ve Geçmiş fiziksel kâğıt basımını program/yıl/tür bazında saklar; ikisi iki kez toplanmaz.
- Kalan=devreden+basılan-dağıtılan-fire. Negatif tutarsız adet reddedilir. Devreden fiziksel sayımdan elle girilir. Yeni yıl eski yılı silmez. Fiilî kayıt yoksa bilinmiyor, tahmin yok.
- Tanıtım kâğıt bileti, Tickera/WooCommerce QR biletine dönüşmez. Satış/ödeme hooklarına dokunulmaz.

Doğrulama: CI PHP lint ve records-functional.php parser/yazar roundtrip, virgül/noktalı virgül CSV, kampüs ayrıştırma, eşleşme, bilinmeyen/zero değer, arşiv idempotence, geçmiş yılın güncel kaydı ezmemesi, transaction rollback ve stok mutabakatını çalıştırır.
Canlı: şema/sürüm/aktif durum, rota XLSX download, PDF önizleme, yüklenen araştırma kitabı onaysız önizleme, öğrenci manuel alanları, stok formu ve eski menüler; satış smoke yalnız read-only. Kullanıcının gerçek öğrenci veya stok adetleri test amacıyla kaydedilmez.
Rollback: 1.8.1 ZIP; eklenen arşiv/stok tabloları silinmez.

# Milano ana sayfa tasarımı — 18 Ekim 2026

Durum: **Hazır taslak / canlıya alınmadı.** Kullanıcının “Sırasıyla yapalım” talebi üzerine ilk üç iş hazırlanmıştır.

## Değişiklik
- Gerçek sahne görseli (Milano medya 169), kısa açıklama, doğrudan şehir/bilet düğmesi.
- Hero altında Karamürsel, Yalova, Darıca ve Derince kartları; seans hedefleri mevcut /bilet-al/#şehir URL'leridir.
- Kompakt üst alan, mevcut içerik hedeflerini koruyan menü, görünür Bilet Al düğmesi.
- Tema paleti Milano renklerini içermediğinden mevcut lacivert/kırmızı/altın marka renkleri korunmuştur. Tema Inter yazı ailesi kullanılır.
- Kart verileri 18 Ekim'de okunan canlı şehir/bilet sayfalarına dayanır; bu aşamada statiktir. Ortak veri kaynağına otomatik bağlama yol haritası madde 11'dir. Doluluk/son bilet gibi doğrulanmamış iddialar yoktur.

## Kaynak ve yedek
Milano ana sayfa: 5. Tam WordPress içerik yedeği: draft 285. Önerilen ilk üç bölüm: draft 286, template-blank. Kaynak blokları ve yalnız değişecek eski/yeni parçalar bu dizindedir. Instagram erişim alanı dahil tam orijinal sayfa GitHub'a aktarılmamıştır.
Header önceki kaydı ayrıca saklanmıştır. Dağıtım paketi canlı yazma işlemi yapmaz; uygulanacak parçaları ve beklenen eski içerikleri açıklar.

## Doğrulama
- @wordpress/blocks 16.2.0 ve @wordpress/block-library registerCoreBlocks ile parse: üç kaynakta isValid=true, validationIssues yok.
- WordPress taslağı kaynakla readback eşleşti; native core group/columns/heading/paragraph/buttons/image/navigation/navigation-link kullanılır.
- Aynı Assembler 0.0.126 sürümüyle staging sayfa 6123'te masaüstü görünümü kontrol edildi.
- Staging 6124 iframe içinde 375px telefon genişliği: document.clientWidth=scrollWidth=375, tek H1 ve fotoğraf yüklenmiş.
- Menü açma kontrolünde portal overlay için özel lacivert arka plan ve beyaz yazı native navigation attribute'larıyla düzeltildi; rendered background rgb(9,23,70), color rgb(255,255,255); 375px'de taşma yok.
- Gerçek telefonda cihaz testi, tahsilat, PayTR callback, PDF/QR teslimi yapılmadı. Sipariş/kapasite/ödeme kodları değiştirilmedi.
- JSDOM bazı modern WordPress CSS'lerini ayrıştıramadığına ilişkin uyarı verdi; blok validasyonu geçti. Görsel CSS kontrolleri gerçek tarayıcıda yapıldı.

## Canlı durum / engel
Ana sayfa değişiklik öncesi içerikle aynı kaldı. İlk canlı hero yazma denemesi otomatik onay denetiminde taslak görsel/Gutenberg/smoke kontrolleri eksik gerekçesiyle reddedildi.
Staging masaüstü ve telefon kontrollerinden sonra ikinci deneme de “kullanıcı önceki ret sonrasında tam canlı değişikliği açıkça onaylamadı” gerekçesiyle reddedildi. Başka araç veya dolaylı yöntemle canlı yazma denenmedi.
Milano tema önizleme klonu 409 taslak çakışması verdi; izin verilen tek tekrar da aynı sonucu verdi. Eski taslak/klasör silinmedi veya değiştirilmedi. Görsel kontrol bu nedenle izole staging sayfasında yapıldı.

## İnceleme
https://staging-f321-milanosirki-lgajf.wpcomstaging.com/milano-tasarim-onizlemesi-20261018/?design=20261018b
https://staging-f321-milanosirki-lgajf.wpcomstaging.com/milano-telefon-onizlemesi-20261018/?design=20261018b
https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/282

## Sonraki adım
Bu tam tasarımın Milano ana sayfası ve üst menüsünde yayınlanması için kullanıcıdan açık onay alınır. Sonra güncel içerik/hash doğrulanır, yalnız ilgili parçalar uygulanır ve canlı readback + masaüstü/telefon + şehir/seans bağlantıları kontrol edilir.
Liste 4–12 henüz uygulanmadı. Bir sonraki konu bilet seçim ekranı, seçim özeti ve seans bağlantılarıdır.

## Geri alma
Ana sayfa içerik yedeği draft 285 ve header-before kaydı. Geri almadan önce eşzamanlı değişiklikleri incele; sadece bu tasarıma ait parçaları geri yükle. Hiçbir sipariş, ürün, ödeme, QR veya kapasite kaydı silinmez.

# Milano ana sayfa tasarımı — 18 Ekim 2026

## Son durum: CANLIDA
Kullanıcı “Onaylıyorum” diyerek önizlenen ilk üç değişikliğin yayınını açıkça onayladı. Ardından Milano ana sayfa5 ve assembler//header güncellendi. Henüz uygulanmamış liste4–12 bu yayının dışında kaldı.

## Yayınlanan değişiklikler
- Gerçek sahne fotoğrafı (medya169), kısa tanıtım ve doğrudan şehir/bilet düğmesi.
- Hemen altında Karamürsel, Yalova, Darıca ve Derince kartları; mevcut /bilet-al/#şehir hedefleri.
- Kompakt üst alan, içerik hedeflerini koruyan menü ve görünür Bilet Al düğmesi.
- Ana sayfanın sonundaki eski, yalnız Yalova odaklı takvim kaldırıldı; tek gosteri-takvimi bağlantı hedefi korundu.
- Başka ana sayfa blokları korunmuştur. Instagram özel erişim alanı GitHub'a aktarılmamıştır.
- Kart tarih/seans/fiyatları 18 Ekim'de okunan canlı şehir/bilet ekranlarına dayanır; statiktir. Otomatik ortak veri kaynağına bağlama madde11'dir.

## Ön kontrol ve yedek
Yayın öncesi ana sayfa ve header hazırlanan eski kaynakla birebir eşleşti. Tam ana sayfa içerik yedeği WordPress draft285 ile eşleşti. Header önceki kaynak dosyası bu dizindedir. Tasarım draft286'da da saklanmaktadır.

## Yayın işlemi
Ana sayfa eski hero bölümü, yeni hero + turne kartlarıyla match-once değiştirildi (1 eşleşme). Eski takvim grubu match-once kaldırıldı (1 eşleşme). Header native template-part API üzerinden onaylanan kaynakla güncellendi.
Canlı sayfa post_content readback beklenen parça dönüşümüyle birebir eşleşti; header readback kaynakla birebir eşleşti. Ana sayfada takvim id'si tam bir kez bulunuyor.
Sipariş, ürün, kapasite, ödeme/PayTR ve QR koduna müdahale edilmedi.

## Doğrulama
- @wordpress/blocks16.2.0 + registerCoreBlocks: hero/tour/header kaynaklarında isValid=true, validationIssues yok. Yayın öncesi tekrar geçti.
- Taslak ve staging aynı Assembler0.0.126 sürümünde incelendi. 375px iframe: clientWidth=scrollWidth=375, tek H1, fotoğraf yüklü.
- Telefon menüsünün portal overlay'i native renk attribute'larıyla lacivert/beyaz olarak doğrulandı.
- Canlı https://milanosirki.com/ masaüstü ekran görüntüsünde yeni tasarım görünür. DOM: clientWidth=scrollWidth=1348, tek H1, dört kart, hero fotoğrafı yüklenmiş.
- Ana sayfadaki Yalova kartından /bilet-al/#yalova açıldı; 18:00 ve19:30, doğru salon ve490/245/1100TL seçenekleri görüldü.
- Aynı bilet ekranında Karamürsel17:30/19:30, Darıca17:30/19:30 ve Derince12:00/14:00/16:00 seçenekleri okundu.
- Gerçek cihaz, tahsilat, PayTR callback, PDF/QR teslimi test edilmedi. Bu tasarım yayınına bağlı ödeme başarısı iddia edilmez.
- JSDOM modern WordPress CSS ayrıştırma uyarıları blok validasyonunu etkilemedi; görsel CSS kontrolü gerçek tarayıcıda yapıldı.

## Tarihsel yayın engeli
Önceki turda otomatik onay denetimi görsel/blok kontrolleri, sonra açık yayın onayı gerekçesiyle canlı yazmayı reddetmişti. Son kullanıcı onayı bu engeli giderdi; aynı kapsam yayına alındı.
Milano tema klonu409 taslak çakışması vermişti. Eski klasör/taslak silinmedi; görsel önizleme izole staging sayfasında yapıldı. Tema dosyaları değiştirilmedi.

## Takip
PR: https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/282
Canlı: https://milanosirki.com/
Önizleme: https://staging-f321-milanosirki-lgajf.wpcomstaging.com/milano-tasarim-onizlemesi-20261018/?design=20261018b
Telefon genişliği: https://staging-f321-milanosirki-lgajf.wpcomstaging.com/milano-telefon-onizlemesi-20261018/?design=20261018b
Sonraki konu: madde4 bilet seçim ekranı; sonra seçim özeti ve seans bağlantıları. Henüz bu maddeler uygulanmış değildir.

## Geri alma
Güncel içerik/hash ve eşzamanlı değişiklikleri kontrol et. Yalnız yeni hero+tour yerine eski hero bölümünü, eski takvim grubunu önceki konumuna ve header-before kaydını geri yükle; gerekirse tam ana sayfa için draft285'i kullan. Ürün/sipariş/QR verisine dokunma.

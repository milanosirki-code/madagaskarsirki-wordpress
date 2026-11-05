# A2 — Yetkili staging önizlemesi

5 Kasım 2026. A1 tabanı: `c64e4cca4809eb460a1c1ba407d1bb2030f4d50d` / PR #305. Bu değişiklik ayrı bir dependent PR olarak A1 dalına dayanır; main'e bağımsız uygulanmamalıdır.

## Uygulama

0.2.0, bilinen staging alanında Araçlar → Bilet Önerisi Önizleme ekranını ve yalnız `manage_woocommerce` yetkisiyle çalışan iki dar REST rotasını ekler. Üretim alanında bu hook'lar kaydedilmez; callback'ler ayrıca staging ve yetki kontrolü yapar. Anonim/yanlış site çağrısı reddedilir. Cookie tabanlı önizleme WordPress REST nonce kullanır. JSON gövdesi 2 KiB ile sınırlı, sonuç private/no-store'dur. Gizli anahtar veya genel ability yürütücüsü eklenmez.

Ekran mevcut sitenin kırmızı, beyaz ve altın renklerini, kartlarını ve tipografisini izler. Şehir/seans açıkça seçilir; yetişkin/çocuk/0–2 kişi sayıları girilir; gerçek servis en ucuz geçerli birleşimi döndürür. Seçim değişince önceki sonuç ve bağlantı kaldırılır, geciken yanıtlar gösterilmez. Değişmiş kaynakta yeniden seçim istenir. Kaynak metinleri HTML olarak çalıştırılmaz. Bilet bağlantısı yalnız mevcut staging etkinlik sayfasına gider; sepet/ödeme yazmaz.

Native URL üretimi staging'de staging adresi döndürdüğü için A1'in yalnız üretim alanı doğrulaması bu önizlemeyi engelliyordu. Açık iki origin allowlist'i eklendi; origin yalnız sunucudaki home_url üzerinden seçilir. Rastgele wpcomstaging alanları, istemci origin'i ve üretim/staging çapraz bağlantıları reddedilir.

## Ortam ayrımı

- Üretim: madagaskarsirki.com / WordPress site255726534.
- Doğrulanmış özel staging: staging-f321-milanosirki-lgajf.wpcomstaging.com / site257930487.
- Staging eski bir kopyadır: incelemede gelecekte yalnız İzmir program13 ve MDG10/seans88,89,90 vardı. Bu veri üretimin 8 güncel etkinlik/17 seans kataloğu değildir.
- Staging MMC1.3.50/AI0.7.1, üretim MMC1.3.49/AI0.7.0'dan farklıdır; yalnız yeni paket yüklenir, bağımlılıklar topluca değiştirilmez.

## Doğrulama ve dağıtım kaydı

CI: PHP7.4/8.4 lint + çekirdek/preview sözleşmeleri; gerçek PHP markup ve gerçek JS üzerinde JSDOM etkileşim testleri. JSDOM görsel tarayıcı veya erişilebilirlik uygunluk sertifikası değildir. Canlı ödeme/QR veya müşteri mesajı testi yapılmaz.

Staging kurulumu yalnız CI doğrulanmış ZIP'ten yapılmalı; önce yeni plugin'in varlığı/sürümü kontrol edilir. Var olmayan plugin için overwrite=false; varsa ancak doğrulanmış önceki paket yedeğiyle overwrite. Kurulum/aktivasyon sonucu belirsizse yeniden yazmadan önce plugin durumunu oku. Kurulum sonrası sürümü, salt-okunur catalogue ve quote yanıtını, izin sınırını ve mevcut satış verisi sayımlarını geri oku. Veri ekleyerek testi geçirme veya üretimi kopyalayarak eski staging'i ezme.

Gerçek kurulum, smoke sonuçları, test edilen commit, ZIP hash'i ve kalan sınırlar PR açıklaması/Drive günlüğünde tarihlenir. Bu dosya tek başına staging kurulmuş veya görsel olarak doğrulanmış demek değildir. Bağlı browser-control aracı bu oturumda mevcut değil; gerçek tarayıcı/mobil görsel QA ayrıca gerekir.

Rollback yalnız yeni plugin'i staging'de devre dışı bırakmak/kaldırmaktır; önceki kurulu çekirdek varsa aynı ZIP'i geri yükle. Üretimde değişiklik yapılmaz. Sonraki ürün kararı: staging sonuçları ve müşteri ekranı incelemesi sonrası public pilot için ayrı kısıtlı controller/gönderim politikası.

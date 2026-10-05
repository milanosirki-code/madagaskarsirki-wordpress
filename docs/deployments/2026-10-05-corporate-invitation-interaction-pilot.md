# Denizli kurumsal davetiye etkileşim pilotu canlı kaydı

5 Ekim 2026. PR #132. Yalnız test kurumu etkileşim prototipi.

- Yeni Code Snippets kaydı: 123, scope global, aktif; read-back code_error null. PHP kaynak dosyası PR branch ile aynı.
- Yeni şifreli sayfa: 4520, /kurumsal-davetiye-pilot/. Mevcut sayfa/ürün/kupon/sipariş veya ödeme ayarı değiştirilmedi; menüye eklenmedi.
- Şifresiz GET: yalnız WordPress parola formu. Pilot form ve nonce açılmıyor.
- Head: noindex, nofollow doğrulandı.
- PHP 8.3 lint ve 24 policy/isolation/metadata/nonce/password/render kontrolü geçti.
- Canlı PHP render kontrolü: geçici private sayfa 4521 üzerinden shortcode formu, iki seans ve test açıklaması çıktı. Kontrol sonrasında sayfa trash durumuna alındı.
- Bilet-al ve sepet HTML ekranları normal açılıyor. Boş sepetle ödeme URL'si sepete dönüyor; gerçek ödeme smoke testi yapılmadı.

Aktivasyon REST isteği 500 yanıt verdi; yazma tekrar edilmedi. Hemen hedef read-back ile aktif=true ve code_error=null, normal frontend erişimi doğrulandı. 500 yanıtının kök nedeni doğrulanmadı; origin hatası olduğu varsayılmadı.

Doğrudan HTTP testi tamamlandı: şifre girildikten sonra form açıldı. Canlı POST ile 17:30 1 yetişkin + 2 çocuk = 500 TL ve 19:30 2 yetişkin + 4 çocuk = 1.000 TL doğrulandı. 1 yetişkin + 3 çocuk ve yanlış kod reddedildi. Pilot robots noindex/nofollow. Bilet-al, sepet ve ödeme URL GET testleri HTTP 200; pilot formu sızmıyor ve fatal notice görünmüyor. Boş sepetle ödeme URL'si sepete yönlenir; bu gerçek ödeme testi değildir. Görsel tarayıcı incelemesi yapılmadı.

Gerçek ücretsiz çocuk QR üretimi bu sürümde yoktur. Kurum/kota/süre tanımı ve mevcut Tickera/kapasite/ödeme entegrasyon testleri ayrı aşamadır. Rollback: yalnız snippet 123'ü pasifleştir ve sayfa 4520'yi draft yap.

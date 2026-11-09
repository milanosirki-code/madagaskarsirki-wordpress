# Kommo anket botu yeniden devreye alma — 17 Ekim 2026

Kullanıcı, Uşak 19.30 seansının 36 saatlik tetiklenmesinden önce botun yeniden yazılıp devreye alınmasını istedi. Hedef: 17 Ekim 2026 07.30, Europe/Istanbul.

## Canlı değişiklik
- Mevcut bot118553 üzerinde kaynak güncellendi; yeni ve mükerrer tetikleyici oluşturulmadı. Panel adı Gösteri Sonrası Anket V1 olarak korunur; yeni iç tanı notları V2 adını kullanır.
- Yanlış conditions bloğundaki sipariş adı regex kuralı, ayrı validations adımına taşındı. Başlangıç kontrolleri ayrı condition adımlarına ayrıldı; reddedilen başlangıçlar nedenini iç nota yazar.
- Ödeme başarı etiketi, tarih doluluğu, Order#numara biçimi, processing/won aşamaları, önceki gönderim/başlatma/yanıt korumaları korunur.
- Başlatma etiketi mesajdan önce yazılmaya devam eder. Gönderildi kutusunun mesaj öncesindeki erken yazımı kaldırıldı; yalnız gönderim adımı sonrasında işaretlenir. WhatsApp gönderim hatasında kilit korunur ve mevcut takip görevi açılır; otomatik yeniden deneme yok.
- Onaylı template80796 / source82576 / ana kişi gönderim parametreleri aynıdır. Yanıt yolları, olumsuz değerlendirme görevi ve48saat yanıt bekleme aynıdır.
- Belirsiz terminal notu, her sonlanmayı müşteri yanıtı gibi yorumlamayacak şekilde düzeltildi.

## Doğrulama
Eski kaynakta karma koşul şeması yapısal testle yeniden üretildi; yeni kaynakta bu kusur yok. Goto hedefleri, tekrar kilidi, gönderim parametrelerinin ve yanıt/hata yollarının korunması testten geçti. Bu test Kommo motoru veya WhatsApp teslimat testi değildir.

Canlı Kaydet sonrası editör kapatıldı ve yeniden açıldı. Kalıcı JSON hazırlanan sürümle anahtar sırasından bağımsız tam eşleşti; validations ve80796 tekrar okundu.

Ana38144604 tetikleyicisi canlıda doğrulandı: Anket Gösteri Tarih ve Saat2460854 üzerinden36saat sonra; WooCommerce işleniyor/kazanıldı; Her zaman; mevcut tüm kayıtlara uygula kapalı. Tetikleyici değiştirilmedi. Eski tarih-only tetikleyici yeniden etkinleştirilmedi.

## Durum ve sınır
Yeni kaynak canlıda kalıcıdır ve mevcut zamanlayıcıya bağlıdır. 07.30 otomatik çalışması ve gerçek WhatsApp teslimatı henüz gözlemlenmedi. Kesin uçtan uca başarı iddiası yok. 05.30'da sonlanan17.30 kayıtları topluca yeniden başlatılmadı. Müşterilere manuel test/erken anket gönderilmedi; çalışan bilet110115,ödeme,hatırlatma,Woo/PayTR değişmedi.

## Geri alma ve arşiv
Önceki kaynak fix/kommo-survey-start-20261014 dalındaki prototypes/satisfaction-survey/kommo-survey-bot-runtime-20261014.json dosyasında; yerel yedeği kommo-survey-bot-backup-20261017.json. Yalnız118553 kaynağını eski JSON ile geri yükleyin; tetikleyici veya müşteri alanlarını sıfırlamayın.

Yeni kaynak: fix/kommo-survey-validation-20261017 dalı, draft PR277. İlk kod commit555296f8915c871d4a89dc7594f512bbc2e309bd. CI tamamlandı veya main'e birleştirildi iddiası yok. Kaynak: https://developers.kommo.com/docs/salesbot-dp . Kişisel müşteri verileri ve özel bağlantılar arşivlenmedi.

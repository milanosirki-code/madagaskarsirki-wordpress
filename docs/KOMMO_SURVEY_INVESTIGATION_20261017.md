# Memnuniyet anketi otomatik gönderim incelemesi — 17 Ekim 2026

Salt okunur canlı inceleme; müşteri mesajı, bot/tetikleyici/alan değişikliği veya yeniden başlatma yapılmadı.

## Kesin bulgu
Uşak 15 Ekim 17:30 seansına ait ödenmiş bir Woo siparişinde 17 Ekim 05:30 tarihli SalesBot (Madagaskar – Gösteri Sonrası Anket V1) notu görüldü: “Anket akışı sonlandırıldı. Yanıt sonrasında otomatik yeniden anket gönderimi engellendi.” Anket başlangıç notu, gönderim/hata etiketi, dış anket mesajı veya sonuç yok; gönderildi kutusu boş. Ödeme başarı etiketi, processing aşaması, Order# sayısal adı ve Anket Gösteri Tarih ve Saat=15/10/2026 17:30 mevcut.
Bu kayıt tetikleyicinin zamanında botu başlattığını, fakat gönderim öncesi koşul yolundan terminal13'e gidildiğini kanıtlar. Terminal metni bütün çıkışlarda aynı olduğundan müşterinin önceden cevap verdiğini kanıtlamaz.

## Zaman ve kapsam
Ana tetikleyici38144604, Woo14297695 processing110416827/won142, Anket Gösteri Tarih ve Saat2460854 +36saat, Her zaman. Mevcut aşamalara toplu uygula seçili değil; incelenen kayıt zaten başlamış olduğundan burada temel açıklama değildir.
Eski38133528, tarih-only ve ana satış hattı, gönderildi boş değil koşuluyla daraltılmış; son kayda uygun. Bunlar yeniden değiştirilmedi.
17:30 hedefi17Ekim05:30;19:30 hedefi17Ekim07:30 (Türkiye). İnceleme06:18 sonrası başladı;19:30 seansının36saati henüz dolmamıştı. Üç19:30 örneğinde anket alanı dolu, gönderildi/sonuç boş; bunlar gecikmiş örnek sayılmaz. Uşak metin araması58 eşleşme/55processing gösterdi; bu rakam uygun anket alıcı sayısı değildir.

## Bot kaynağı
118553 canlıJSON: step0 and koşulları başladı etiketi yok, gönderildi1/true/Y değil,2460854 dolu, Order# regex, WooCommerce Ödeme Başarılı etiketi; step10 processing/won; step1 tekrar/sonuç kilitleri; step12 başlangıç etiketi+flag+not ve80796/82576 gönderimi; step2 48saat yanıt bekleme; step8 hata; step13 genel terminalnotu.

En güçlü kod adayı: step0 handler=conditions içinde term1/term2 kuralları ile client_value/type=regex/condition_value/contains karıştırılmış. Resmî belge regex biçimini validations altında, term1/term2 biçimini condition/conditions örneklerinde gösteriyor. Bu şema uyumsuzluğu olası açıklamadır; motorun hangi koşulu reddettiği mevcut nottan kesin ayrıştırılmadı. lead.name ifadesinin çalışma anındaki çözümü de ayrı doğrulanmalı. Koşulları kaldırmak veya ödeme/tekrar korumasını gevşetmek çözüm değildir.
Kaynak: https://developers.kommo.com/docs/salesbot-dp (condition, validations ve regex bölümleri;17Ekimokundu).

## Önerilen dar düzeltme ve kabul
Regex doğrulamasını belgelenmiş ayrı validations adımına ayır; diğer ödeme, tarih, aşama ve tekrar kilitlerini koru. Önkoşul retlerine kişisel veri içermeyen ayrı neden kodları ekleyerek hangi kapının durdurduğu görünür olsun. Mesaj üretmeyen kontrollü tanı ile koşul yorumlamasını doğrula; sonra açıkça yetkilendirilmiş tek uygun testte kabul/teslimat ve tekrarsızlık kontrolü yap. YerelJSON testi gerçek Kommo motor testi değildir. Gecikmiş gerçek müşterilere toplu/otomatik yeniden gönderim bu incelemede başlatılmadı.

Bilet110115 ve diğer çalışan otomasyonlar korunmuştur. Örneklerden genel tüm-müşteri teslimat oranı çıkarılmadı. Müşteri kimlikleri,telefon,e-posta,sipariş/lead kimliği ve özel biletURLleri bu kayda alınmadı.

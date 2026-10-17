# Ödeme hatırlatma otomasyonu canlıya alındı — 17 Ekim 2026

Kullanıcının “botu da çalıştır, izin veriyorum” talimatıyla uygulandı.

## Canlı değişiklikler
- Yeni Code Snippets kaydı 199 aktif. Canlı kaynak GitHub kaynağıyla birebir karşılaştırıldı.
- Yeni siparişler, etkinleştirme anından itibaren kapsama girer. 20 dakika sonunda Action Scheduler `mdg_pa_due` ile değerlendirilir. Beş dakikalık `mdg_pa_sweep` eksik hook/CRM gecikmesini telafi eder; eski siparişler otomatik topluca gönderilmez.
- Snippet103 ve177, ödeme/sonradan ödeme/gelecek seans/satış uygunluğu kaynakları olarak korundu. Biletbot110115, anket ve etkinlik hatırlatma botları değiştirilmedi.
- Kommo bot118588 mevcut şablon86940 ve kanal82576 ile başlatılır. ALLOW_SEND alanını mevcut kontrollerden sonra yazar, resmî POST /api/v4/bots/run üzerinden başlatır.
- Günlük15:00 trigger38145930 silinmedi. Eşleşme değeri ALLOW_SEND yerine DISABLED_AUTO_DISPATCH_V1 yapılarak geri alınabilir biçimde etkisizleştirildi; kaydet/reload/readback doğrulandı. Böylece iki zamanlayıcı aynı gönderimi başlatmaz.
- Sipariş başına atomik ve kalıcı başlatma kilidi var. Ağ sonucu belirsizse otomatik tekrar yok. Ödeme durumu API çağrısı öncesinde tekrar değerlendirilir. HTTP202 yalnız başlatma kabulüdür; WhatsApp teslimatı değildir.
- Yöneticiye özel status GET ve tek sipariş dispatch POST uçları manage_woocommerce yetkisi ister; telefon/token/ödeme URL'si yanıtlanmaz.

## Doğrulama
- PHP-WASM lint: geçti. 15 davranış kontrolü: geçti (20 dakika, eski sipariş dışlama, ödenmiş/seansı geçmiş sipariş, CRM kilitleri, ödeme değişimi, timeout/tekrar engeli, tek sipariş pilotu).
- Canlı199 active=true ve kaynak eşleşmesi doğrulandı.
- Otomatik sweep: bir complete ve sonraki pending görevi görüldü.
- Güncel uygunluğu yeniden doğrulanan iki sipariş tek tek başlatıldı. İkisinde Kommo HTTP202 ve bot başlangıcı görüldü.
- İlk alıcı: WhatsApp “Hata”, kod3126. Kommo açıklaması alıcının koşulları kabul etmesi/uygulamasını güncellemesi/numara kaydı ihtimalini belirtiyor. Tekrar gönderilmedi.
- İkinci alıcı: gerçek WhatsApp mesajı “Teslim edildi” durumunda görüldü.
- İki kalıcı launch kilidi veritabanında doğrulandı. Botun “gonderildi” etiketi ilk alıcıda da var; etiket teslimat kanıtı değildir.
- Site sağlık kontrolü:15OK/0kritik/1 eski eklenti uyarısı; WooCommerce, bilet zinciri, PayTR ve Kommo bağlantısı OK. Gerçek ödeme veya checkout değişikliği yapılmadı.
- Aktivasyon POST yanıtı500 döndü; işlem tekrar edilmedi. Sonraki okumada aktif olduğu, kod eşleşmesi, status endpoint, cron ve iki gönderimle çalıştığı doğrulandı.500'ün kök nedeni bu tur belirlenmedi.

## Kanıt sınırı ve takip
20 dakika kuralı test edildi, canlı zamanlayıcı çalışması ve aynı dispatcher üzerinden gerçek teslimat görüldü. Aktivasyondan sonra açılmış yeni bir siparişin doğal20dakika yolculuğu bu oturumda beklenip gözlenmedi. Bu son kabul kontrolü sonraki uygun yeni siparişte yapılmalı. Kullanıcıya tüm müşterilere kesin teslim garantisi verilmez;3126 alıcı bazlı sorun olarak kalır.

## Geri alma
199'u devre dışı bırak; launch kilitlerini silme. Bekleyen scheduler işleri callback yoksa mesaj göndermez. Eski günlük kuralın geri açılması gerekirse38145930 eşleşmesini ALLOW_SEND yap; önce mevcut izinleri ve kilitleri incele, toplu yeniden gönderme.

GitHub draft PR279: https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/279
Resmî API: https://developers.kommo.com/reference/launch-salesbots
Müşteri kimliği, telefon, ödeme bağlantısı ve token arşivlenmedi.

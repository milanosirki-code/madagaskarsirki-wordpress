# Bilet desteği — 9 Kasım 2026

## Yayındaki iyileştirmeler

- Biletimi Bul formu sayfanın üstüne alındı ve site menüsüne eklendi.
- Kommo cevapları, kişisel bilet bağlantısı veya sipariş numarası + telefon formunu önerecek şekilde güncellendi.
- Form sonuç vermediğinde veya sipariş numarası bulunamadığında canlı desteğe aktarım çalışıyor.
- Gerçek ödenmiş bir siparişte bilet bulma, kişisel sayfa ve dört sayfalık toplu PDF indirme doğrulandı.

Sayfa: https://madagaskarsirki.com/biletlerim/

## Hazır, henüz etkin değil

- E-postadaki Download/İndir düğmesi için “QR Biletimi Aç / İndir” etiketi.
- Sipariş numarası bulunamadığında kayıtlı telefona WhatsApp bilet bağlantısını yeniden gönderme.
- Yanlış numaraya gönderimi ve sık tekrarlanan talepleri engelleyen kontroller.

Otomatik güvenlik/söz dizimi testleri başarılı. Yeni kod WordPress'te kapalı kaydedildi; etkinleştirme sonrası işletmenin kendi test telefonu ile gerçek WhatsApp teslimi ayrıca kontrol edilmeli. Gerçek müşteriye deneme mesajı gönderilmedi.

İnceleme kaydı: https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/320

Bu özet müşteri adı, telefon, QR kodu, kişisel bilet bağlantısı veya erişim anahtarı içermez.

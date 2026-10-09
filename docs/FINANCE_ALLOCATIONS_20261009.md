# Ortak gider dağıtımı — 9 Ekim 2026

Kullanıcı matbaa 58.000 TL için Pursaklar %10, Kırıkkale %10, Sincan %40 ve Yenimahalle %40 paylarını belirledi. Kaynak gider98 korunur. Programlar MDG16/15/11/7. Program payları 5.800/5.800/23.200/23.200 TL.

V5 gider satırlarında Programlara dağıt formu: toplam yüzde100, yetki/nonce, kaynak tutarı değişmişse reddetme, geçersiz program ve doğrudan gider koruması. Paylar tek optionda kaynak gider kimliğine bağlıdır, gider/gelir satırı oluşmaz. Önceki dağıtım son değişiklik audit kaydında korunur. Dağıtımı kaldır işlemi kaynak gideri korur. Tutar değişimi, silinen kaynak veya geçersiz program halinde eski paylar hesaba katılmaz. Kuruşlar deterministik en büyük kalan yöntemiyle korunur.

Program tüm-tarih özeti ortak payı toplam maliyete dahil eder, ayrı kart ve kaynak gider satırını gösterir. Aylık program tablosu kaynak gider tarihindeki ay paylarını gösterir. Aylık işletme maliyeti değişmez. İade ve web hesapları korunur. Ödenmemiş/bilinmeyen ödeme bilgisi pay bazında gösterilir.

PHP8.3 syntax, allocation conservation/stale/direct/invalid/rounding tests ve mevcut web/refund contracts geçti. PHP7.4 CIye allocation eklendi. Native canlı kurulum exact source SHA ve private backup kontrolüyle yapılacak. Kurulum henüz doğrulanmadı; kullanıcı oranları native formdan tek gider için kaydedilecek. Rollback önce dağıtımı kaldır, ardından exact kaynak yedeğini geri yükle. Checkout/sipariş/gerçek ödeme değişikliği yok.

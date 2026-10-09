# Ortak gider dağıtımı — 9 Ekim 2026

Kullanıcı matbaa 58.000 TL için Pursaklar %10, Kırıkkale %10, Sincan %40 ve Yenimahalle %40 paylarını belirledi. Kaynak gider98 korunur. Programlar MDG16/15/11/7. Program payları 5.800/5.800/23.200/23.200 TL.

V5 gider satırlarında Programlara dağıt formu: toplam yüzde100, yetki/nonce, kaynak tutarı değişmişse reddetme, geçersiz program ve doğrudan gider koruması. Paylar tek optionda kaynak gider kimliğine bağlıdır, gider/gelir satırı oluşmaz. Önceki dağıtım son değişiklik audit kaydında korunur. Dağıtımı kaldır işlemi kaynak gideri korur. Tutar değişimi, silinen kaynak veya geçersiz program halinde eski paylar hesaba katılmaz. Kuruşlar deterministik en büyük kalan yöntemiyle korunur.

Program tüm-tarih özeti ortak payı toplam maliyete dahil eder, ayrı kart ve kaynak gider satırını gösterir. Aylık program tablosu kaynak gider tarihindeki ay paylarını gösterir. Aylık işletme maliyeti değişmez. İade ve web hesapları korunur. Ödenmemiş/bilinmeyen ödeme bilgisi pay bazında gösterilir.

PHP8.3 syntax, allocation conservation/stale/direct/invalid/rounding tests ve mevcut web/refund contracts geçti. PHP7.4 CIye allocation eklendi. Native canlı kurulum 04:49 Türkiye saatiyle tamamlandı. Kaynak baseline bd7a38ec0b8052eed67a6fa0cb3b9f89da2e81feaa9aa6f4d88e0ca5a49bf732, candidate 1a8b03fe8349c000f4ce2292581d9b1545fa66cd04f7ea8325dd3661ad56e47f SHA ile eşleşti. Private backup option mdg_alloc_install_backup_20261009 saklandı. Snippet172 kurulum sonrası pasifleştirildi. Kullanıcı oranları native formdan gider98 için kaydedildi. Rollback önce dağıtımı kaldır, ardından exact kaynak yedeğini geri yükle. Checkout/sipariş/gerçek ödeme değişikliği yok.

## Canlı doğrulama
Kurulum ve dağıtım öncesi/sonrası gider142 kayıt, toplam2.206.125,68 TL, selected-field CRC sum318825269392 aynı. Kaynak gider98 tutarı58.000 korunuyor. Dört pay ekranında5.800/5.800/23.200/23.200 görülüyor. Program tüm tarih sonuçları: Pursaklar gelir151.750/maliyet91.678,40/sonuç60.071,60; Kırıkkale0/14.300/-14.300; Sincan753.006/158.579,83/594.426,17; Yenimahalle349.050/140.044/209.006. Her program sabit pay3.000. Diğer dağıtılmamış giderler ve iade mutabakatı nedeniyle sonuçlar mevcut kayıtlara göre geçici.

Aylık program tablosu ile tüm-tarih özeti ayrı kapsamlardır. Salon gideri başka ayda kayıtlıysa aylık tablodaki maliyet farklı olabilir; örneğin Kırıkkale aylık0 doğrudan/5.800 ortak/3.000 sabit=-8.800, tüm-tarih5.500 doğrudanla=-14.300. Bu değişiklik gelir tarihlerini veya gider tarihlerini taşımaz.

Kanıt finance-allocation-live-20261009.jpg, dört program kartları finance-allocation-evidence-20261009.json. GitHub workflow sorgusu henüz run döndürmedi; PHP7.4 CI başarı iddia edilmedi. PHP8.3 testleri geçti.


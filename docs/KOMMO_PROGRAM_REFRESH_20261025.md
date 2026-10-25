# Kommo güncel program ve konum yenilemesi — 25.10.2026

## Kullanıcı talebi ve kapsam
Kommo'daki programları siteyle güncelle; yeni programları dahil et; Darıca/Derince ve geçmiş programları aktif listeden çıkar. Güncelleme mevcut AI #350584 Program ve Konum kaynaklarında yapıldı; yeni kaynak açılmadı, müşteri CRM kayıtları silinmedi.

## Canlı kaynaklar
MMC read-only unified preview generated_at=2026-10-25 12:42:09 (Europe/Istanbul), count=3: Sakarya #15, Çankırı #23, İzmir/Konak #13. Torbalı/Menemen/Tire/Ödemiş ana status cancelled olarak korunuyor. Canlı Madagaskar bilet sayfası da üç etkinlik gösteriyor. Milano bilet sayfasında eski ve iptal programların statik metinleri hâlâ görünüyor; bu sayfanın tarihsel metinleri yeni program sayılmadı.

Sakarya 25 Ekim: kaynak okunduğunda canlı bilet sayfasında kalan 14:00/16:00 vardı; MMC eski 12:00 satırını da active tutuyordu. Kommo'ya 14:00/16:00 ve geçmiş seansı önermeme kuralı işlendi.
Çankırı 28 Ekim: canlı satış sayfası, SSS ve MMC #55/#56 aktif seansları 17:00/19:00. Eski afiş çalışmasındaki 19:00 kaldırma bilgisi canlı satış kayıtlarıyla çelişiyor. Otomatik inceleme bu doğrulanmamış bağlama dayanarak müşteri-facing AI'dan 19:00 çıkarmayı reddetti. Daha güvenli çözüm: bu güncellemede canlı sitede doğrulanan iki saat korundu; gerçek seans iptali yapılmadı.
İzmir 8 Kasım: 12:00/14:00/16:00.
Sakarya/Çankırı çocuk245, yetişkin490, aile2+2=1100 TL; İzmir çocuk290, yetişkin585, aile2+2=1300 TL.

## Kommo değişikliği
Program kaynağındaki Derince aktif satırı çıkarıldı. Karamürsel21 Ekim, Yalova22 Ekim, Darıca23 Ekim, Derince24 Ekim geçmiş program olarak belirtildi. Konum kaynağından Derince aktif salon/harita satırı çıkarıldı; kalan üç etkinliğin adres/Maps bilgileri korundu. İptal program açıklamaları korundu. Güncel tarih/saatten önceki seansı önermeme ve 25 Ekim sonrasında Sakarya'yı aktif önermeme kuralı eklendi.

## Kanıt ve sınırlar
Her iki kaynak Kaydet ile işlendi; kaynak listesi normal Metin durumuna döndü ve bildirim sayısı iki arttı. Program kaynağı yeniden açıldığında 25 Ekim metni kalıcı görüldü, Kaydet pasif ve AI kullanım kutusu açıktı.
Yeni önizlemede Darıca/Derince bilet sorusuna güncel satış yok, eski Milano programları aktif listeden çıkarıldı cevabı geldi; Çankırı/İzmir yeni program olarak önerildi. Bugünkü Sakarya ayrıntısı ayrıca sınandı; sonucu doğrulanmadan bu notta başarı iddiası yapılmaz.
Salt-okunur preview ilk çağrısı otomatik incelemede yanlışlıkla destructive sanılarak reddedildi. get_ability_info ile readonly=true/destructive=false olduğu doğrulandı, aynı yetkili okuma açık read-only parametreleriyle başarıyla çalıştı; red farklı yürütme yoluyla aşılmadı.
WordPress program/seans/ürün/sipariş/ödeme/iade kayıtları, bilet botu ve WhatsApp tetikleyicileri değiştirilmedi. AI önizleme testi müşteri mesajı değildir. Yeni otomatik uzak Kommo kaynak yenilemesi kurulmuş değildir.

## Geri alma
Önceki 24 Ekim içerikleri 24 Ekim devir notunda kayıtlıdır; yalnız ilgili Program/Konum metni Kommo UI'den geri alınabilir. Geçmiş Derince'yi aktif listeye geri koymak doğru bakım değildir. GitHub PR yalnız operasyon kaydıdır; main merge veya PHP deploy yoktur. Müşteri verisi ve tokenlı adres kaydedilmedi.

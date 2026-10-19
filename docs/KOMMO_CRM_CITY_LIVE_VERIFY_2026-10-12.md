# CRM şehir kaydı canlı doğrulaması — 12 Ekim 2026, 22:36–23:00 Türkiye
Önceki PR #234 içindeki gerçek kart yazımı bekleniyor maddesi için ek doğrulama.

## Gerçek kayıt doğrulandı
Native Kommo lead listesinde CRM İlgi Şehri (2460892) alanı Aydın olan gerçek kayıt bulundu. İlgili görüşmede 22:36 müşterinin Aydın yanıtı ve aynı dakikada AI agent (Madagaskar Sirki satış asistanı) tarafından CRM İlgi Şehri alanı değişiklik olayı gözlendi. Kartın alan değeri Aydın olarak okundu. Operatör olarak alanı elle doldurmadık.
Aydın ilgi preset'i açıldığında 1 gerçek lead sonucu doğrulandı. Önizleme ile sınırlı doğrulama artık en az bir gerçek kayıt için tamamlandı; tüm şehirler/kanallar için teslim veya eksiksizlik iddiası yok.

## Kontrol ekranı
Native tüm-lead listesine Şehir (2448378) ve CRM İlgi Şehri (2460892) sütunları drag/drop ile eklendi ve Bitti ile kaydedildi. Tüm hesap kullanıcıları checkbox'ı kapalı; kullanıcı görünümü.
CRM — Şehir kontrol / bu hafta adlı preset 206777927 kaydedildi. Filtre: tüm hatlarda aktif aşamalar, oluşturulma tarihi current_week. 12–18 Ekim haftası için 57 kayıt gözlendi.
Bu preset yalnız eksik kayıtları filtrelemez; dolu/boş şehirler sütunlar üzerinden incelenir. Native kısa metin filtresinde boşluk operatörü bulunamadığı için yanlış bir boş-kayıt segmenti oluşturulmadı. Segment taslağı iptal edildi.

## Rapor
CRM — Aydın / gösteri ilgisi adlı sayı widget'ı 2460892=Aydın, tüm hatlarda aktif aşamalar filtresinden oluşturuldu. Dashboard yeniden açılıp başlık ve 1 kayıt/0 CRM değeri readback ile doğrulandı.
Mevcut aylık/current-state widget anlamları aynıdır; CRM tutarı tahsilat değildir.

## Sınırlar
Geçmiş eksik şehirlerin toplu backfill'i yapılmadı. Bilgi olmayan kayıtlara şehir tahmini yazılmadı. Otomatik satış aşaması kuralları bu çalışmada eklenmedi. Test kampanyası/müşteri mesajı veya ödeme/iade işlemi yapılmadı.
Arşivde müşteri adı, telefon, lead/order ID veya sohbet dökümü tutulmadı.

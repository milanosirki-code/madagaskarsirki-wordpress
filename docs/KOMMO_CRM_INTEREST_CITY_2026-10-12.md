# Kommo CRM ilgi şehri — 12 Ekim 2026

## Canlı değişiklik
Kommo 36560615 hesabında lead kısa metin alanı **CRM İlgi Şehri (2460892)** oluşturuldu. Siparişin etkinlik şehri alanı 2448378 korunuyor.

AI temsilcisi 350584 için yeni eylem kaydedildi:
- WHEN: Müşteri şunları sağlıyor → katılmak istediği gösterinin ilini açıkça ve tek bir şehir olarak.
- DO: Alan ayarla → müşteri (lead) → CRM İlgi Şehri → AI ile doldur.
- İşlemi kaydet sonrasında global Kaydet uygulandı; sayfa yeniden yüklenip kuralın kaldığı doğrulandı.
- MORE: Yalnız açık tek gösteri ili; standart Türkçe il adı. Izmir/İzmir, Aydin/Aydın, Usak/Uşak normalizasyonu. İlçe, adres, eski şikâyet, çoklu şehir veya karşılaştırmadan tahmin yok. Sipariş, ödeme, seans, bilet, anket ve satış aşaması değişikliği yok. Alan kaydı pazarlama izni değildir. Teknik kayıt mesajı yok.

## Kaydedilmiş aktif kayıt görünümleri
| Ad | Preset ID |
|---|---|
| CRM — İlgi / Manisa | 206773087 |
| CRM — İlgi / İzmir | 206773195 |
| CRM — İlgi / Aydın | 206773247 |
| CRM — İlgi / Uşak | 206773275 |
| CRM — İlgi / Ankara | 206773327 |

Manisa için aynı filtreyi kullanan ikinci preset 206773163 de oluştu; müşteri kaydı ya da gönderim çoğaltmaz. Diğer presetlerin ve alan koşullarının kalıcı DOM kayıtları doğrulandı. Görünümler tüm hatların aktif aşamalarında yeni 2460892 alanını tam metinle filtreler; mevcut şehir alanı için önceki görünümler ayrı durur. Manisa ilgi filtresi açıldığında 0 kayıt gözlendi; geçmiş kayıtlar otomatik doldurulmadı.

## Test ve sınırlar
Manisa için 18 Ekim 14.00 talebi native AI önizlemede denendi. AI güncel 245/490/1100 TL fiyatlarını verdi. Niyet belirlendi ayrıntısında yeni tek-il koşulu açıkça göründü.
Önizleme gerçek müşteri kartına alan yazıldığını kanıtlamaz; gerçek gelen mesajda alan değerinin doğrulanması bekleniyor. Kurulum müşteri sohbetlerinde çalışacak şekilde kaydedildi, müşterilere test mesajı gönderilmedi.
Karşılaştırma/henüz şehir seçmedim testinde AI tercihi kaydetmediğini söyledi. Bu yeni yanıtta Niyet belirlendi/şehir kayıt koşulu görünmedi. Bu, koşul tanımanın önizleme kontrolüdür; gerçek kart alan yazımı testi değildir.
Eski kayıtların temizliği tamamlandı iddiası yok. Açık bilgi olmadan şehir backfill yapılmadı. Otomatik satış aşaması ataması eklenmedi.
Native alan yazma AI yorumuna dayanır; kesin deterministik kural değildir. Mevcut sipariş alanlarını koruyan ayrı alan bu riski sınırlar.

## Geri alma
AI Eylemler'de yalnız bu yeni şehir eylemini kaldırıp global Kaydet uygulayın. Alan ve kayıtlı görünümler raporlama için kalabilir; veri silme gerekmez. Diğer AI insan aktarım kuralları korunmuştur.

## Kaynak
https://support.kommo.com/docs/ai-agent-manual-setup
https://support.kommo.com/docs/ai-agent-assist
Canlı belge ve güncel rehber önceliği korundu; eski rehber fiyatları kullanılmadı.

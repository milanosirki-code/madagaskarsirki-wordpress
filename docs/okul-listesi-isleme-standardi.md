# Okul Tanıtım Listesi İşleme Standardı

Bu standart, Madagaskar Sirki saha tanıtımı için MEBBİS ve diğer kurum listelerinden temiz okul/kreş listesi hazırlanırken uygulanır.

## Girdi

- Dosyalar `.xls`, `.xlsx` veya `.csv` olabilir.
- `.xls` uzantılı dosya gerçek Excel yerine HTML tablo içerebilir.
- İl ve ilçe, öncelikle `IL_ADI` ve `ILCE_ADI` sütunlarından tespit edilir.

## Dahil edilecek kurumlar

Resmî kurum türleri:

- Anaokulu
- İlkokul
- Ortaokul
- İmam Hatip Ortaokulu
- Yatılı Bölge Ortaokulu (şehir/merkez uygunsa)

Özel kurum türleri:

- Özel Türk Okul Öncesi Kurumu
- Özel Türk İlkokulu
- Özel Türk Ortaokulu

MEB dışı listelerde, kurum adında `KREŞ`, `GÜNDÜZ BAKIMEVİ` veya `GÜNDÜZ BAK` bulunan kayıtlar dahil edilir.

## Kesinlikle çıkarılacaklar

Liseler, meslek liseleri, özel eğitim okulları, özel eğitim ve rehabilitasyon merkezleri, kurslar, sürücü kursları, kişisel gelişim merkezleri, mesleki eğitim ve halk eğitim merkezleri, BİLSEM, RAM, yurtlar, öğretmenevleri, millî eğitim müdürlükleri, idari kurumlar ve diğer hedef dışı kurumlar.

## Kırsal filtre

Hedef kurum türünde olsa bile kurum veya adres bilgisinde bağımsız ve açık biçimde aşağıdakiler bulunuyorsa ana listeden çıkarılır:

- KÖYÜ / KÖY
- BELDESİ / BELDE
- KÜME EVLERİ / KÜME KÜME EVLERİ
- KÖYİÇİ

Alt dize eşleşmesi yapılmaz. Örneğin `Köyortası Mahallesi` ve `KEMERKÖPRÜ` kırsal kabul edilmez. Uzaklığı kesin olmayan mahalleler harita/koordinat kanıtı olmadan çıkarılmaz.

## Mükerrer temizliği

Mükerrer anahtarı normalize edilmiş `KURUM_ADI + ADRES` birleşimidir. Büyük/küçük harf, noktalama ve gereksiz boşluk farkları aynı kaydın tekrar sayılmasına neden olmaz. Aynı ilçenin mevcut temiz dosyası varsa yeni kayıtlar onunla birleştirilir.

## Excel çıktısı

### Tanıtım Listesi

Yalnızca şu sütunlar bulunur:

1. `IL_ADI`
2. `ILCE_ADI`
3. `KURUM_ADI`
4. `ADRES`

### Kırsal Çıkarılanlar

- `IL_ADI`
- `ILCE_ADI`
- `KURUM_ADI`
- `ADRES`
- `ÇIKARILMA NEDENİ`

Bu sayfaya yalnızca hedef kurum olduğu hâlde kırsal filtre nedeniyle çıkarılan kayıtlar yazılır.

## Biçim

- Başlık: koyu mavi zemin, beyaz ve kalın yazı
- İlk satır sabit
- C ve D sütunlarında metin kaydırma
- Yaklaşık genişlikler: A=12, B=14, C=46, D=72
- Saha kullanımına uygun sade görünüm

## Dosya adı

`IL_ILCE_Tanitim_Listesi_2026.xlsx`

Türkçe büyük harfler korunur. Örnek: `KOCAELİ_İZMİT_Tanitim_Listesi_2026.xlsx`.

## Drive yerleşimi

Çıktı, ana okul listeleri klasöründeki doğru il klasörüne kaydedilir. İl klasörü yoksa oluşturulur. Aynı ilçeye ait dosya varsa gereksiz kopya oluşturulmaz; içerik birleştirilip mükerrerler temizlenir.

## Sonuç özeti

Her işlem sonunda il/ilçe, ham kayıt sayısı, ana liste sayısı, kırsal çıkarılan sayısı, hedef dışı çıkarma bilgisi ve oluşturulan Drive dosyası bağlantısı bildirilir.

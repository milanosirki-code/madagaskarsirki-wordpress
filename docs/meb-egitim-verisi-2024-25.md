# MEB 2024/25 Eğitim Verisi — Veri Ambarı Entegrasyonu

## Kaynak

- Yayın: **Millî Eğitim İstatistikleri, Örgün Eğitim 2024/25**
- Kurum: T.C. Millî Eğitim Bakanlığı, Strateji Geliştirme Başkanlığı
- Kapsam: Resmî İstatistik Programı
- Kullanılan tablo: **1.17 — İstatistiki Bölge Birimleri Sınıflaması 1., 2. ve 3. düzey ve eğitim seviyesine göre okul, şube, öğrenci, öğretmen ve derslik sayısı**
- Veri düzeyi: **İl (İBBS 3)**
- Öğretim yılı: **2024/25**

PDF'nin tamamı depoya kopyalanmaz. Veri Ambarına yalnızca Madagaskar planlama ekranında gereken türetilmiş il toplamları ve kaynak metadatası alınır.

## Üretilen göstergeler

Her il için:

- `school_count`: okul öncesi + ilkokul + ortaokul + ortaöğretim okul toplamı
- `student_count`: aynı kademelerdeki öğrenci toplamı

Veri seti 81 ili içerir. Doğrulama toplamları:

- 74.040 okul
- 17.956.523 öğrenci

Bundled veri dosyası:

`wp-content/plugins/madagaskar-population-data/data/meb-2024-25-provinces.json`

Veritabanı tablosu:

`{prefix}mmc_education_provinces`

## Hazırlık Dashboard davranışı

`mmc-preparation` ekranındaki **4. İl Geneli Referans** bölümünde:

- Nüfus → 2025 il nüfus verisi
- Okul → MEB 2024/25 il geneli okul sayısı
- Öğrenci → MEB 2024/25 il geneli öğrenci sayısı

olarak gösterilir.

Bu MEB tablosu ilçe düzeyinde değildir. Bu nedenle MEB il toplamları **Hedef Bölge Özeti** içindeki seçili ilçelere dağıtılmaz ve tahmin üretilmez. İlçe okul sayısı mevcut Okul Tanıtım ana listesinden; ilçe öğrenci sayısı ise ancak ilçe düzeyinde doğrulanmış ayrı bir kaynak bulunduğunda kullanılmalıdır.

## Yönetim ekranı

**Madagaskar Yönetim Merkezi → Veri Ambarı → Nüfus ve Eğitim Verisi**

bölümünde hem 2025 nüfus verisi hem de MEB 2024/25 eğitim verisi durumları görülebilir ve yeniden aktarılabilir.


## Hedef Bölge Özeti — Okul Tanıtım bağlantısı

Sürüm 1.3.0 ile Hazırlık Dashboard, seçili hedef ilçeler için okul sayısını doğrudan `{prefix}mad_okul_tanitim` ana listesinden hesaplar.

- **Hedef İlçe** kartı: checkbox seçimleri ile "Ek ilçe adları" alanındaki ilçelerin birleşimini gösterir.
- **Okul Sayısı** kartı: seçili ilçelerdeki Okul Tanıtım kayıtlarının toplamını gösterir.
- **Veri Kalitesi → Okul**: kaç hedef ilçede okul listesi bulunduğunu gösterir.
- **Veri Kalitesi → Okul listesi**: toplam okul kayıt sayısını gösterir.
- **Öğrenci Sayısı**: ilçe düzeyi doğrulanmış öğrenci kaynağı olmadığı sürece tahmin edilmez ve mevcut "Veri yok" davranışı korunur.

MEB Tablo 1.17 verisi yalnız **İl Geneli Referans** içindir; ilçe okul veya öğrenci toplamına dağıtılmaz.

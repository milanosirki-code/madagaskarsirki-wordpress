# Canlı eklenti baz sürümleri — 23.09.2026

Bu çalışma, kullanıcı tarafından sağlanan iki canlı eklenti paketinin sürümleri esas alınarak devam ettirilir.

## Madagaskar 2025 Nüfus Verisi

- Sağlanan ZIP sürümü: **1.1.2**
- SHA-256: `607f7f087cf6151af1fa019cbe979143353ba3f01b5512f0f74b4cfa2ba182a2`
- MMC v1.3.3 ile sunucu taraflı doğrudan entegrasyon için `mmc_population_get_districts_by_province()` ve `mmc_population_get_all_district_names()` yardımcıları içerir.
- GitHub geliştirme sürümü bu yardımcıları koruyarak **1.3.1** sürümüne yükseltilmiştir; daha önce eklenen MEB 2024/25 eğitim veri katmanı korunur.

## Madagaskar Management Center

- Sağlanan ZIP sürümü: **1.3.3**
- SHA-256: `e447b3e66ddc8dd4fb22257d088f26e28b0c10a918b1bb4e694a495af4729fc6`
- Nüfus eklentisini `MMC_Population_Source_Service` üzerinden sunucu tarafında doğrudan okur.
- Hazırlık Dashboardu hedef ilçe nüfusunu nüfus eklentisinden; okul listesini `MMC_School_Source_Service` üzerinden Okul Tanıtım ana kaynağından alır.
- MMC aktifken eski nüfus DOM/JS köprüsü devre dışı bırakılır.

## MEB 2024/25 il geneli okul/öğrenci verisi

MMC v1.3.3 mevcut şeması `mmc_region_metrics` tablosuna il geneli `school_count` ve `student_count` metriklerini alabilir.

Hazır içe aktarma dosyası:

`data-imports/meb-2024-25-province-metrics.csv`

Dosya 81 il için iki metrik içerir:

- `school_count`
- `student_count`

Toplam 162 metrik satırı vardır. İlçe alanı boş bırakılmıştır; bu nedenle bu kayıtlar yalnız **İl Geneli Referans** için kullanılır ve hedef ilçe toplamlarına dağıtılmaz.

### MMC içinde yükleme

**Madagaskar Yönetim Merkezi → Bölge Veri Ambarı → Ek Metrikleri CSV ile Yükle**

alanından `meb-2024-25-province-metrics.csv` dosyasını yükleyin.

Bu yükleme sonrasında Hazırlık Dashboardu → **4. İl Geneli Referans** bölümünde okul ve öğrenci verileri MEB 2024/25 kaynağıyla görünür.

# 2025 Nüfus Verisi Modülü

Bu modül, Madagaskar Sirki WordPress yönetim merkezine 2025 il ve ilçe nüfus verilerini yüklemek için hazırlanmıştır.

## Kaynak

- Veri seti: `ubeydeozdmr/turkiye-api`
- Yıl: 2025
- İl: 81
- İlçe: 973
- Kaynak projenin belirttiği nüfus kaynağı: TÜİK MEDAS
- İl dosyası: `datasets/2025/provinces.json`
- İlçe dosyası: `datasets/2025/districts.json`

Not: İlk gönderilen `yigith/TurkiyeSehirlerBolgeler` deposu ilçe nüfusu içermediğinden nüfus veri kaynağı olarak kullanılmamıştır.

## Kurulum

`wp-content/plugins/madagaskar-population-data` klasörünü WordPress'e yükleyin ve eklentiyi etkinleştirin.

Etkinleştirildiğinde iki ayrı tablo oluşturulur:

- `{prefix}mmc_population_provinces`
- `{prefix}mmc_population_districts`

Bu isimler mevcut Madagaskar tablolarına zarar vermemek için ayrı tutulmuştur.

## Yönetim ekranı

Eklenti, yönetim menüsünde "Madagaskar" üst menüsünü bulursa altına **Nüfus Verisi** ekranını ekler. Bulamazsa **Araçlar** altında görünür.

Ekrandaki **2025 Nüfus Verisini Getir ve Güncelle** düğmesi:

1. İl JSON dosyasını indirir.
2. İlçe JSON dosyasını indirir.
3. 81 il ve 973 ilçe kontrolünü yapar.
4. Verileri transaction içinde yazar.
5. Aynı yıl tekrar aktarılırsa kayıtları günceller.

## Hazırlık ekranına bağlama

Mevcut `mmc-preparation` ekranında hedef ilçe toplam nüfusu için:

```php
$summary = mmc_population_get_target_summary(
    'Aydın',
    ['Efeler'],
    2025
);

$total_population = $summary['total_population'];
$coverage = $summary['covered_district_count'] . ' / ' . $summary['target_district_count'];
```

Bir ilçenin nüfusunu almak için:

```php
$district = mmc_population_get_district('Aydın', 'Efeler', 2025);
$population = $district ? (int) $district['population'] : 0;
```

İl geneli için:

```php
$province = mmc_population_get_province('Aydın', 2025);
$population = $province ? (int) $province['population'] : 0;
```

## 0–14 yaş alanı

Bu veri setinde ilçe bazlı 0–14 yaş verisi yoktur. Modül bu alanı tahmin etmez ve `null` bırakır. Ayrı bir resmi veri kaynağı sağlanınca eklenmelidir.


## MMC Hazırlık ekranı bağlantısı

Sürüm 1.1.0 ile eklenti, `admin.php?page=mmc-preparation&program_id=...` ekranına otomatik bağlanır.

- Seçili hedef ilçeleri ekrandaki checkbox/select alanlarından okur.
- Programın ilini ekrandan veya `mmc_programs` tablosundan belirler.
- Program il alanı yabancı anahtar ile tutuluyorsa `mmc_provinces` tablosu üzerinden il adını çözer.
- **Hedef Bölge Özeti → Toplam Nüfus** değerini seçili ilçelerin 2025 nüfus toplamıyla günceller.
- **Veri Kalitesi → Nüfus** satırını `x / y ilçe` olarak günceller.
- **İl Geneli Referans → Nüfus** satırına ilin 2025 nüfusunu yazar.
- 0–14 yaş verisini üretmez; bu alan kaynakta olmadığı için boş/veri yok kalır.
- 2025 veri seti henüz içe aktarılmamışsa yönetici oturumunda ilk hazırlık ekranı isteğinde bir kez otomatik içe aktarmayı dener.

Köprü kodu: `wp-content/plugins/madagaskar-population-data/includes/mmc-preparation-bridge.php`.

# Madagaskar Sirki WordPress

Madagaskar Sirki yönetim sisteminin kaynak kod deposu.

## Canonical eklenti kaynakları

Canlıya alınacak eklentilerin tek doğru kaynak klasörü:

- `wp-content/plugins/madagaskar-management-center/` — **MMC 1.3.28**
- `wp-content/plugins/madagaskar-aile-paketi-22/` — **Aile Paketi 2+2 v1.1.2**
- `wp-content/plugins/madagaskar-okul-tanitim/` — **Okul Tanıtım 1.7.2**
- `wp-content/plugins/madagaskar-population-data/` — **Nüfus ve Eğitim 1.3.1**

Yeni geliştirmeler bu klasörlerde yapılır. `plugins/` ve `updates/` klasörleri geçmiş geliştirme/upgrade kayıtları olarak korunabilir; canlı kaynak olarak kullanılmaz.

## Veri sahipliği

- **Program ve süreç:** Madagaskar Management Center
- **Salon ana kaynağı:** Madagaskar → Salonlar
- **Okul ana kaynağı:** Madagaskar → Okul Tanıtım
- **İl/ilçe nüfusu:** Nüfus ve Eğitim eklentisi, 2025 veri seti
- **MEB il geneli okul/öğrenci referansı:** MEB 2024/25
- **Satış:** WooCommerce + Tickera + PayTR
- **CRM:** Kommo

MMC, salon/okul/nüfus ana verilerini ikinci kez üretmez; ilgili ana kaynaktan okur ve program gerektiğinde snapshot tutar.

## Geliştirme akışı

`feature branch → PHP lint/CI → Pull Request → main → sürüm ZIP → WordPress`

`main` canlıya alınabilir kaynak dalıdır. Doğrudan kontrolsüz geliştirme yapılmamalıdır.

## Otomatik kontrol

`.github/workflows/madagaskar-plugins-ci.yml` üç canonical eklenti klasöründeki tüm PHP dosyalarını sözdizimi açısından kontrol eder ve beklenen sürüm başlıklarını doğrular.

## Veri ilkeleri

- İl düzeyi MEB verileri ilçe toplamı gibi gösterilmez.
- İlçe düzeyi öğrenci verisi doğrulanmış kaynak olmadan tahmin edilmez.
- 0–14 yaş verisi kaynakta yoksa türetilmez.
- Teminat, iade kesinleşene kadar gider sayılmaz.

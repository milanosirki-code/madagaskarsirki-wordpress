# Madagaskar Management Center v1.3.4 yükseltmesi

Bu klasör, canlıda kullanılan **Madagaskar Management Center v1.3.3** paketi üzerine uygulanacak v1.3.4 yükseltmesini içerir.

## Baz sürüm

- Paket: `madagaskar-management-center-v1.3.3(1).zip`
- SHA-256: `e447b3e66ddc8dd4fb22257d088f26e28b0c10a918b1bb4e694a495af4729fc6`
- Hedef sürüm: **1.3.4**
- Veritabanı şema sürümü: **1.3.3** (şema değişikliği yok)

## v1.3.4 değişikliği

MEB **Millî Eğitim İstatistikleri, Örgün Eğitim 2024/25 – Tablo 1.17** il geneli okul ve öğrenci verileri artık CSV'yi yönetim ekranından elle yüklemeden otomatik olarak `mmc_region_metrics` veri ambarına senkronlanır.

Doğrulama kuralları:

- 81 il
- 162 metrik kaydı
- 74.040 okul
- 17.956.523 öğrenci
- yalnız il geneli kayıtlar; `district_name` boş kalır
- il toplamları hedef ilçelere dağıtılmaz ve tahmin üretilmez

## Dosyalar

1. `madagaskar-management-center.php` — v1.3.4 giriş dosyası; MEB servis sınıfını yükler ve `maybe_sync()` çalıştırır.
2. `includes/class-mmc-meb-source-service.php` — idempotent MEB senkron servisi.
3. `data/meb-2024-25-province-metrics.csv` — 81 il × okul/öğrenci verisi.

Servis önce paket içindeki CSV'yi kullanır. Dosya paketlenmemişse GitHub `main/data-imports/meb-2024-25-province-metrics.csv` kaynağına güvenli geri dönüş yapar.

## Uygulama

Canlı v1.3.3 kaynak paketinde aynı göreli yollara bu dosyaları kopyalayın. Mevcut diğer v1.3.3 dosyaları korunur. WordPress'te eklenti sürümü 1.3.4 olarak görünür. Yönetim sayfası ilk yüklendiğinde MEB kaynağı veri ambarına idempotent olarak senkronlanır.

Tam yüklenebilir v1.3.4 ZIP paketi ayrıca bu çalışma sırasında üretilmiştir. Paket SHA-256:
`e85583910b9909980944b4a6cffc6a4d4dd7288029ad82b367e4e2bd5acc175a`

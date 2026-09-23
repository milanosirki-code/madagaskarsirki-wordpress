# Madagaskar Management Center v1.3.5

Bu sürüm **v1.3.4 üzerine küçük uyumluluk düzeltmesidir**.

## Düzeltmeler

- Eski program kayıtlarındaki `efeler` / `Efeler` gibi ilçe adı biçim farkları tek biçime çevrilir.
- `mmc_programs` ve `mmc_program_target_districts` il/ilçe alanları bir kez normalize edilir.
- Okul Tanıtım kaynak tespiti yenilenir.
- Okul Tanıtım v1.7.2 ile birlikte MEBBİS'ten gelen `AYDIN / DİDİM` benzeri adlar `Aydın / Didim` biçiminde tutulur.
- Hazırlık Dashboardunda aynı ilçenin iki kez görünmesi ve Okul Tanıtım eşleşmesinin yazım biçimi nedeniyle kaçırılması engellenir.

Veritabanı şema sürümü değişmez: **1.3.3**.

## Kurulum sırası

1. Okul Tanıtım eklentisini **v1.7.2** sürümüne yükseltin.
2. Madagaskar Management Center'ı **v1.3.5** sürümüne yükseltin.
3. Hazırlık Dashboardunu yeniden açın.
4. Aydın / Didim gibi bir hedefte okul sayısı ve Okul listesi kapsamını kontrol edin.

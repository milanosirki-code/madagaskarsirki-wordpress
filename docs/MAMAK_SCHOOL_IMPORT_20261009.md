# Mamak okul kaynağı production import ve saha senkronu

Durum: production doğrulandı.

## Kaynak

- Drive dosyası: `ANKARA_MAMAK_Tanitim_Listesi_2026.xlsx`
- Drive file ID: `18aHPIQWCvOaUyufcr9fOYf4EeFgFRVW7`
- Kaynak SHA-256: `41b18d347ebda27009a803eafd4eb2ca8e13e10d40a8cf8c3c5a3f665647e226`
- Canlı Okul Tanıtım sürümü: `1.8.4`
- MMC programı: `#11 PRG-2026-ANK-MAMAK-001`
- Okul Tanıtım legacy programı: `#5`

## Import dry-run

Canlıya yazmadan önce güncel Drive dosyası Okul Tanıtım 1.8.4 filtreleriyle doğrulandı:

- ham satır: 228
- İmam Hatip Ortaokulu: 4 → hariç
- hedef dışı / geçersiz: 4 → hariç
- kırsal: 7 → ana havuzdan hariç, kırsal tablosuna
- kabul edilen okul birimi: 213
- tekil açık adres / fiziksel ziyaret noktası: 204

Import başlangıç kapısı:
- Ankara / Mamak ana okul kaydı: 0
- Ankara / Mamak kırsal çıkarılan: 0

## Production import

Tek-seferlik geçici importer WordPress.com özel ZIP ile yüklendi. Importer, aktif Okul Tanıtım eklentisinin kendi `mad_okul_*` filtre ve insert fonksiyonlarını kullandı.

Güvenlik:
- 0/0 baseline zorunlu;
- dry-run sayıları tam eşleşmeden yazma yok;
- transaction kullanıldı;
- post-write sayımı tam eşleşmezse rollback;
- audit option: `mdg_mamak_import_20261009_audit_v1`.

Canlı sonuç:
- ana okul tablosu: 213
- kırsal çıkarılan: 7
- tekil adres: 204
- öğrenci sayısı bilinen: 0
- mevcut kayıt update: 0
- yeni ana kayıt: 213
- yeni kırsal kayıt: 7

Geçici importer doğrulama sonrası deaktive edildi ve uninstall edildi. Okul verileri ana Okul Tanıtım tablolarında korunur.

## MMC köprü ve saha hedefleri

- MMC #11 ↔ Okul Tanıtım legacy #5 köprüsü idempotent oluşturuldu.
- Program hedef ilçesi: Ankara / Mamak.
- Bölge özetinde okul kaynağı: 213 okul, öğrenci sayısı eksik 213.
- İlk `field-sync-target-schools`: created 213 / updated 0.
- İkinci idempotency koşusu: created 0 / updated 213 / available 213.
- MMC saha özeti: target_schools 213, districts 1, assigned 0, visited 0.
- Sistem sağlık kontrolü: 16 OK / 0 warning / 0 critical.

## Açık veri işi

Mamak kaynak dosyasında öğrenci sayısı ve okul web adresi sütunları yoktur. Bu nedenle öğrenci sayısı tahmin edilmedi ve baskı miktarı otomatik üretilmedi.

Sonraki veri aşaması:
1. doğrulanmış okul web adresleri veya MEB/ilçe müdürlüğü öğrenci sayısı kaynağı,
2. kaynak URL / kaynak türü / veri yılı ile öğrenci sayısı kaydı,
3. 204 fiziksel ziyaret noktası için kampüs toplamları,
4. doğrulanmış öğrenci sayılarına göre baskı planı,
5. gerçek basılan / dağıtılan / kalan stok adetleri.

Sipariş, ödeme, bilet, iade veya müşteri kaydı değiştirilmedi.

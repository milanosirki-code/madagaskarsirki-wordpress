# Madagaskar MMC Menü Denetimi — 29.09.2026

## Amaç
Madagaskar / MMC yönetim menülerinin veri kaynağı, saat dilimi, yaşam döngüsü, MDG köprüsü ve güvenlik davranışlarını kontrol etmek.

## Canlıda doğrulanan / düzeltilen
- Pursaklar ve Kırıkkale satış mutabakatı doğru.
- Pursaklar/Kırıkkale event durumları sales_open.
- Kırıkkale hedef ilçe ve 101 okul saha hedefi tamamlandı.
- Pursaklar MDG-kaynaklı salon bütünlük kontrolü düzeltildi.
- Kommo seans +3 saat kayması canlı snippet hotfix ile düzeltildi.
- Kommo text-source duplicate yaratma davranışı güvenli refresh_needed modeline alındı.
- ID78/ID85 permission callback isim çakışması giderildi.
- Müşteri/Bilet raporundaki UTC seans görünümü yerel saate çevrildi.
- Hazırlık/Bölge için ileri program statüsünü geriye çekmeyi önleyen canlı ID83 koruması eklendi.

## Staging'de kalıcılaştırılan menü düzeltmeleri

### Hazırlık & Bölge
- set_program_targets artık yalnız preparation aşamasını region_analysis'a taşır.
- sales_open ve daha ileri aşamalar hedef ilçe düzenlemesinde geriye çekilmez.

### Finans
- Teminat iade satırı ve dilekçe salonu yalnız legacy mmc_venues yerine MMC_Venue_Service üzerinden çok-kaynaklı çözülür.
- MDG kaynaklı salonlar (ör. Pursaklar) boş görünmez.

### Operasyon
- MMC yerel session_time değerleri tekrar wp_date(strtotime()) ile çevrilmez.
- Gösteri başlığı ve +60 dk bitiş zamanı yerel saatle hesaplanır.
- datetime-local alanlarının ekran gösterimi +3 saat kaymaz.

### Okul / Saha
- Portal ziyaret datetime-local değeri site yerel saatinde parse edilir.
- Yeni portal raw tokenı admin query string'ine yazılmaz; kısa ömürlü tek-kullanımlık transient ile gösterilir.

### Pazarlama
- İçeriklerde seans saatleri ham yerel session_time üzerinden HH:MM üretilir.
- Meta/İçerik datetime-local kayıtları site saat diliminde parse edilir.

### Bilet Yönetimi / MDG Köprüsü
- family_2_2 sanal paket MDG draft'ta üçüncü fiziksel ticket variation olarak oluşturulmaz.
- Program Bütünlüğü, draft köprü yanında aynı yapıda onsale event varsa warning verir.

### Satış & Müşteri
- Legacy MDG COCUK/YETISKIN kodları MMC child/adult kodlarına normalize edilir.
- family_2_2 fiziksel satış mappingine import edilmez.

## Uşak — canlı onarım sırası
Program #4 / MMC Event #4 / 15.10.2026.
- Mevcut bridge: MDG #18 (draft, WC/Tickera yok).
- Gerçek satış event: MDG #19 (onsale).
- #19 seansları: 17:30, 19:30 yerel.
- #19 WooCommerce: ürünler 2951 / 2954.
- #19 Tickera event: 2949.
- Mevcut MMC mapping coverage: 0/4.
- Ücretli satış henüz yok.

Güvenli sıra:
1. #19'u kaynak alarak MMC Event #4'e legacy sales mappings import et.
2. Coverage 4/4 olduğunu doğrula.
3. Bridge'i Program #4 -> MDG #19 olarak manuel bağla.
4. Bridge status: date/venue/session true ve identity 4/4 doğrula.
5. Event integration health/sales readiness kontrol et.
6. #18 draft'ı hemen silme; yeni bridge ve mappingler doğrulanmadan arşiv/temizlik yapma.

## Aydın / Efeler — canlı onarım sırası
Program #5 / MMC Event #5 / 17.10.2026.
- Mevcut bridge: MDG #20 (draft, WC/Tickera yok).
- Gerçek satış event: MDG #21 (onsale).
- #21 seansları: 12:00, 14:00, 16:00 yerel.
- #21 WooCommerce ürünleri: 2990 / 2993 / 2996.
- #21 Tickera event: 2988.
- Mevcut MMC mapping coverage: 0/6.
- Ücretli satış henüz yok.

Güvenli sıra:
1. #21'i kaynak alarak MMC Event #5'e legacy sales mappings import et.
2. Coverage 6/6 olduğunu doğrula.
3. Bridge'i Program #5 -> MDG #21 olarak manuel bağla.
4. Bridge status: date/venue/session true ve identity 6/6 doğrula.
5. Event integration health/sales readiness kontrol et.
6. #20 draft'ı yeni bridge/mapping doğrulanmadan silme.

## Pursaklar gölge draft
- #16 onsale, gerçek satış ve bridge kaynağı.
- #17 draft, 0 order row, 0 active bridge.
- #17 için temizlik ancak diğer MDG referansları ayrıca kontrol edildikten sonra yapılmalı.

## Koruma ilkeleri
- Ücretli satış bulunan event silinmez.
- Aktif bridge taşıyan draft doğrulanmadan silinmez.
- Bridge taşımadan önce WooCommerce/Tickera mapping coverage tamamlanır.
- family_2_2 sanal paket kapasite=4 standardı korunur.
- Gerçek refund/transfer/rollback otomatik çalıştırılmaz.

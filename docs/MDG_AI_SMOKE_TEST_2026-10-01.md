# Madagaskar AI Abilities — Read-only Smoke Test

Tarih: 1 Ekim 2026

Bu test PR #46 için production üzerindeki mevcut aktif abilities ile salt-okunur olarak yapıldı. Bu çalışma sırasında satış sync, mapping write, Kommo write, görev/operasyon write, pazarlama write veya gerçek para iadesi çalıştırılmadı.

## Sonuç özeti

- Sistem sağlığı: 0 critical / 2 warning / 14 OK
- Geçici introspector snippet #76: pasif
- Pursaklar MMC Program #3: bridge bağlı, stale=false, confidence=100
- Kırıkkale MMC Program #2: bridge bağlı, stale=false, confidence=100
- Her iki programda tarih, salon, il, ilçe ve normalize seans eşleşmesi doğru
- Her iki programda satış mapping coverage: 4/4 complete
- Pursaklar MDG↔MMC ücretli satış mutabakatı: 15 sipariş / 55 bilet / 55 kişi / 20.750 TL, fark 0
- Kırıkkale MDG↔MMC ücretli satış mutabakatı: 4 sipariş / 17 bilet / 17 kişi / 6.750 TL, fark 0
- Kommo bağlantısı başarılı; token kaynağı MMC_KOMMO_TOKEN
- Pursaklar Kommo source consistency: safe=true
- family_2_2: 2 yetişkin + 2 çocuk, capacity_units=4 doğrulandı
- Refund preflight: gerçek ödeme alınmış bir siparişte ready=true, failed_checks=[]
- Gerçek refund yapılmadı

## Sistem

`madagaskar/system-health-checks`

- critical: 0
- warning: 2
- ok: 14

Mevcut uyarılar:
1. Genel health kontrolünün seçtiği aktif program zincirinde satış nesnesi 0/0 görünüyor; program bazlı bridge kontrollerinde Pursaklar ve Kırıkkale satış mutabakatı tamdır.
2. Health servisi legacy MS_KOMMO_TOKEN sabitinin kaldırılabileceğini bildiriyor. Canlı Kommo diagnostik sonucu token_source=MMC_KOMMO_TOKEN ve uses_legacy_token=false.

PR #46 içindeki staged system-health snippet'i bu ikinci durumu çalışma zamanındaki gerçek token kaynağına göre normalize eden ek kontrol içerir.

## İade V4

`madagaskar/refund-preflight` yalnız dry-run olarak çalıştırıldı.

- ready: true
- failed_checks: []
- gateway bulundu
- gateway refunds destekliyor
- ödeme alınmış
- önceki refund yok
- ticket kayıtları mevcut ve aktif
- line item mevcut
- canlı refund case yok

Para iadesi, vaka oluşturma veya onay işlemi yapılmadı.

## Bölge / Nüfus

Ankara ilçe kaynağı 25 ilçe döndürdü.

Pursaklar #3:
- hedef ilçe: Pursaklar
- population_total: 168.881
- population year snapshot: 2025
- population source: TurkiyeAPI / TÜİK MEDAS
- nüfus kaynağı hazır: 81/81 il, 973/973 ilçe
- okul kaynağı: Okul Tanıtım harici ana tablo
- hedef okul: 76

population_total için program snapshot'ı kullanıldığı doğrulandı.

## Saha

Pursaklar #3:
- hedef okul: 76
- atanmış: 76
- ziyaret: 0
- tekrar ziyaret: 0
- fotoğraflı ziyaret: 0

Okul kayıtları salt okunur listelenebildi. Atama veya ziyaret write işlemi yapılmadı.

## Operasyon

Pursaklar #3:
- plan status: draft
- operation_mode: undecided
- checklist: 42 toplam / 0 tamam
- pre_departure: 21 / 0
- problem: 0
- araç: 0
- sanatçı: 0
- personel: 0
- ekipman: 0

Plan oluşturma/güncelleme yapılmadı.

## Pazarlama / Meta

Pursaklar Meta planı mevcut:
- campaign: MDG | PURSAKLAR | 01.10.2026 | SALES
- status: draft
- spend: 0
- purchases: 0
- revenue: 0

Pazarlama veya Meta write işlemi yapılmadı.

## Kommo

- configured: true
- connected: true
- http_ok: true
- account bağlantısı başarılı
- token_source: MMC_KOMMO_TOKEN
- uses_legacy_token: false
- source consistency: safe=true
- issues: []
- family_2_2 detected
- detected_capacity_units: 4
- source text family definition: doğru
- seans kaynağı Pursaklar için 17:30 / 19:30 ile uyumlu

Kommo lead/source write işlemi yapılmadı.

## MMC ↔ MDG köprüsü

### Pursaklar #3

- MMC event #3
- MDG event #16
- linked: true
- stale: false
- confidence: 100
- province_match: true
- district_match: true
- date_match: true
- venue_match: true
- session_time_match: true
- güçlü aday: MDG #16, score 2900
- publish preview: ready=true

Satış mutabakatı:
- MDG orders: 15
- MMC orders: 15
- MDG tickets: 55
- MMC tickets: 55
- revenue: 20.750 TL / 20.750 TL
- fark: 0

### Kırıkkale #2

- MMC event #2
- MDG event #15
- linked: true
- stale: false
- confidence: 100
- province_match: true
- district_match: true
- date_match: true
- venue_match: true
- session_time_match: true
- güçlü aday: MDG #15, score 2900
- publish preview: ready=true

Satış mutabakatı:
- MDG orders: 4
- MMC orders: 4
- MDG tickets: 17
- MMC tickets: 17
- revenue: 6.750 TL / 6.750 TL
- fark: 0

## Satış summary semantiği

MMC `orders_count`, ledger'daki tüm farklı WooCommerce siparişlerini sayar; pending/failed/cancelled kayıtlar dahil olabilir.

Pursaklar:
- ledger orders_count: 24
- ücretli mutabakat siparişi: 15
- ticket_count: 55
- net_revenue: 20.750 TL

Kırıkkale:
- ledger orders_count: 9
- ücretli mutabakat siparişi: 4
- ticket_count: 17
- net_revenue: 6.750 TL

Bu nedenle UI'da ledger siparişi ile ücretli sipariş ayrıştırılmalıdır.

## Live ↔ staged snippet drift

Canlı #79 ve #81–#88 snippetleri ile PR #46 dosyaları boyut farkı açısından yalnız PHP dosya açılış etiketi düzeyinde eşleşmektedir.

Gerçek drift bulunan iki modül:

### #80 Sistem Sağlığı

PR #46 staged sürümünde canlıdan sonra eklenen:
- `mdg_ai_health_normalize_kommo_secret_check()`
- gerçek çalışma zamanı token kaynağı MMC_KOMMO_TOKEN ise legacy sabit uyarısını OK olarak normalize etme
- health summary'yi normalize edilmiş check listesinden yeniden hesaplama

### #89 MMC Satış Defteri

PR #46 staged sürümünde canlıdan sonra eklenen güvenlikler:
- satış mapping write öncesi `MMC_MDG_Bridge_Service::status()` ile bridge doğrulaması
- linked olmayan veya stale bridge üzerinde mapping write engeli
- mapping-save için en az bir WooCommerce/Tickera dış kimliğini açıkça zorunlu tutma

Bu staged değişiklikler canlıdaki sürümden daha korumacıdır. Canlıya geçirilmeden önce CI ve modül smoke testi gerektirir.

## Production write testleri

Bu aşamada özellikle çalıştırılmadı:
- refund case / gerçek refund
- mmc-sales-mapping-save
- mmc-sales-import-legacy
- mmc-sales-sync-order
- mmc-sales-sync-event
- Kommo sync/source write
- MDG manual/auto link
- operasyon ensure/update
- saha assignment/update
- görev write
- finans write
- pazarlama publish/performance write

Bunlar gerçek operasyon ihtiyacı ve açık onay ile ele alınacaktır.

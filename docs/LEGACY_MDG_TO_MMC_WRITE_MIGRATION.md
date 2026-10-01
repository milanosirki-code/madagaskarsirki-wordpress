# Controlled Legacy MDG → MMC Write Migration

This module is intentionally separate from the read-only preview.

## Abilities

- `madagaskar/legacy-mdg-mmc-migrate-one`
- `madagaskar/legacy-mdg-mmc-migrate-batch`

Both require the exact confirmation string:
`MIGRATE_LEGACY_MDG_TO_MMC`

## Safety model

Each MDG event is processed in its own SQL transaction.

Before any write:
- read-only preview must return `safe_for_later_write=true`
- suggested action must be `create_program_then_bridge`
- no bridge may already exist
- no same province/district/date MMC program may exist
- event date must be today or future
- every legacy session must have WooCommerce product and Tickera event IDs
- active ticket-code sets and prices must be consistent across sessions

During write:
- create MMC program only
- attach existing MDG venue; never create a replacement venue
- create MMC event/session control records
- mirror only legacy ticket types actually present
- reuse existing WooCommerce product/variation and Tickera event IDs
- create MMC sales mappings
- create MMC↔MDG bridge
- resync only WooCommerce orders already referenced by the MDG order map

Before COMMIT:
- mapping coverage must be complete
- bridge must be linked/non-stale
- province, district, date, venue and session times must match
- identity_expected must equal identity_matched
- if sales exist, MDG↔MMC reconciliation must pass

Any failure triggers ROLLBACK.

## Initial migration target

Run Sincan MDG #11 first as canary. After health/bridge/sales verification, migrate:
- #7 Yenimahalle
- #12 Denizli
- #9 Mamak
- #13 Eskişehir
- #10 İzmir


## 1 Ekim 2026 — Sincan canary sonucu

Sincan MDG #11 kontrollü migration başarıyla tamamlandı.

- Program: `PRG-2026-ANK-SINCAN-001`
- MMC Program ID: 8
- MMC Event ID: 8
- Program status: `sales_open`
- 3 seans
- aktif legacy bilet kodları: adult, child
- mapping coverage: 6/6
- 36 legacy WooCommerce siparişi bulundu ve sync edildi
- MMC ledger: 36 sipariş / 89 bilet / 36.000 TL net gelir
- bridge linked=true, stale=false
- identity: 6/6
- paid-sales reconciliation: 28/28 sipariş, 45/45 item, 89/89 bilet
- revenue difference: 0 TL

Transaction COMMIT oldu; rollback tetiklenmedi.

### Geçici operasyon notu

Canary tamamlandıktan hemen sonra WPVibe günlük fair-use limiti doldu. Bu nedenle bağımsız post-run health/dashboard çağrısı ve geçici #101 runner'ın pasif duruma döndüğünün doğrulanması tamamlanamadı.

WPVibe tekrar açıldığında **ilk işlem**:
1. #101 durumunu oku; aktifse kapat.
2. system health çalıştır.
3. Program #8 bridge/dashboard read-only kontrol et.
4. Ancak üçü temizse kalan eventlere devam et.

Kalan sıra:
1. #7 Yenimahalle
2. #12 Denizli
3. #9 Mamak
4. #13 Eskişehir
5. #10 İzmir

# Madagaskar AI Abilities

Bu plugin, kalıcı AI ability iş mantığını Code Snippets'tan test edilebilir plugin koduna taşımak için hazırlanmıştır.

## Güvenli migration modeli

Plugin aktive edildiğinde **varsayılan olarak hiçbir modül yüklenmez**.

Etkin modüller WordPress option ile seçilir:

`mdg_ai_abilities_modules`

Comma-separated değer örneği:

`system-health-integrity,mmc-sales-ledger`

## İlk migration kapsamı

| Modül | Eski canlı snippet |
|---|---:|
| system-health-integrity | #80 |
| mmc-sales-ledger | #89 |
| mmc-dashboard | #81 |
| mmc-tasks | #82 |

## Canlı geçiş sırası

Her modül ayrı ayrı taşınır:

1. Plugin aktif, modül option'da kapalı olmalı.
2. İlgili Code Snippets kaydının rollback yedeği doğrulanmalı.
3. Snippet deaktive edilmeli.
4. Modül option'a eklenmeli.
5. İlgili read-only smoke test çalıştırılmalı.
6. Sorun varsa modül option'dan çıkarılıp snippet yeniden aktive edilmeli.
7. Başarıdan sonra sonraki modüle geçilmeli.

İki kaynak aynı anda aynı ability'yi register etmemelidir.

## Smoke test

### system-health-integrity
- `madagaskar/system-health-checks`
- Pursaklar ve Kırıkkale `program-integrity-checks`

### mmc-sales-ledger
- `madagaskar/mmc-sales-health`
- `madagaskar/mmc-sales-mappings`
- `madagaskar/mmc-sales-summary`
- `madagaskar/mdg-bridge-status`

Migration smoke testinde satış sync veya mapping write çalıştırılmaz.


## Phase 2 — #81 ve #82

- `mmc-dashboard`: yalnız read-only dashboard abilities.
- `mmc-tasks`: task read + create/update/status abilities içerir. Migration smoke testinde yalnız read abilities kullanılacaktır.


## Phase 3 — sıradaki gated modüller

| Modül | Canlı snippet | Migration smoke testi |
|---|---:|---|
| v4-refund-safety | #79 | refund-preflight / refund history; gerçek refund yok |
| mmc-region-population | #83 | sources / districts / program-summary / population lookup |
| mmc-field | #84 | field-summary / targets / routes / recent-visits |
| mmc-operations | #85 | plan-get / summary / checklist-list / schedule-list |
| mmc-marketing | #86 | marketing-pack-get / item-get / meta-plan-get |
| mmc-kommo | #87 | configuration / diagnostics / source consistency / stage preview |
| mmc-mdg-bridge | #88 | bridge-status / candidates / publish-preview |

Bu modüller v0.3.0 içinde yalnız gate'e eklenir; production option'a otomatik eklenmez.
Write abilities migration sırasında çağrılmaz.


## Phase 4 — raporlama ve V4 güvenlik

| Modül | Canlı snippet | Not |
|---|---:|---|
| reporting-customer | #78 | müşteri/bilet/satış audit + rapor okuma; report-send-now write çağrılmaz |
| v4-operations-safety | #94 | satış durumu / erteleme mapping / preflight / transfer scan read-only güvenlik akışları |

Bu modüller de gate ile kapalı gelir ve canlı option'a otomatik eklenmez.

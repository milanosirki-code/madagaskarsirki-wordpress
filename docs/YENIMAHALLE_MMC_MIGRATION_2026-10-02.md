# Yenimahalle MMC Migration — 2 Ekim 2026

## Sonuç

Legacy MDG Event #7, Ankara / Yenimahalle, 4 Ekim 2026 programı kontrollü transaction ile MMC kontrol zincirine başarıyla taşındı.

### Oluşturulan MMC kayıtları

- Program ID: 9
- Program code: `PRG-2026-ANK-YENIMA-001`
- Program status: `sales_open`
- MMC Event ID: 9
- Program venue ID: 9
- Session count: 3
- Session times: 12:00, 14:00, 16:00
- Active ticket codes: adult, child

### Mapping ve bridge

- Mapping required: 6
- Mapping mapped: 6
- Complete: true
- Bridge linked: true
- Bridge stale: false
- Identity expected/matched: 6/6
- Session time match: true
- Province match: true
- District match: true
- Date match: true
- Venue match: true

### Satış senkronu

- Legacy orders found: 11
- Orders synced: 11
- MMC orders_count: 11
- Ticket count: 26
- Sold capacity: 26
- Gross revenue: 12.750 TL
- Net revenue: 10.000 TL
- Refunded: 0 TL
- Failed-orders metric: 2

### Paid-sales reconciliation

- MDG/MMC paid orders: 9 / 9
- Items: 17 / 17
- Tickets: 26 / 26
- Units: 26 / 26
- Missing in MMC: []
- Extra in MMC: []
- MDG revenue ex-tax: 10.000 TL
- MMC revenue: 10.000 TL
- Revenue difference: 0 TL

### Kapanış güvenlik kontrolleri

- System health: 0 critical / 0 warning / 16 OK
- Temporary migration runner Snippet #101: active=false after migration
- Transaction completed successfully; rollback was not triggered.

## Sonraki sıra

1. Denizli MDG #12
2. Mamak MDG #9
3. Eskişehir MDG #13
4. İzmir MDG #10

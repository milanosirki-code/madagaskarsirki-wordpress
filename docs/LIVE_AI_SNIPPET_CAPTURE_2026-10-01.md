# Live AI Snippet Capture — 1 Ekim 2026

Bu dosya canlı Code Snippets kayıtları #72, #74, #75 ve #77'nin birebir kaynak yakalama envanteridir.

## Secret taraması

Dört kaynak için yapılan taramada gömülü token, parola, API anahtarı, Basic Auth URL, JWT veya uzun yüksek-entropili secret literal tespit edilmedi.

## Kaynaklar

| ID | Modül | Live modified | SHA1 | Bytes | Ability |
|---:|---|---|---|---:|---:|
| 72 | Program + Salon + Etkinlik + Satış | 2026-09-27 18:10:41 | a12c111c8ea5965bd4d7ab4ba706c240ba1b9404 | 80969 | 27 |
| 74 | Fatura Takip | 2026-09-27 18:16:46 | 962eab394e4817bbd8a7392612da3f052fdbc54a | 22234 | 6 |
| 75 | Okul Tanıtım | 2026-09-27 18:22:04 | 2207e26bd5119ee777832c446234da2c9e1f9105 | 26778 | 10 |
| 77 | V5 Finans | 2026-09-27 18:32:45 | b348c26d54163b87dbb852d091de94c78d7078d0 | 27242 | 10 |

Ham kodlar:
- `docs/live-captures/2026-10-01/snippet-72-program-venue-event-sales.php.txt`
- `docs/live-captures/2026-10-01/snippet-74-invoice-tracking.php.txt`
- `docs/live-captures/2026-10-01/snippet-75-school-promotion.php.txt`
- `docs/live-captures/2026-10-01/snippet-77-v5-finance.php.txt`

## Read-only smoke test adayları

### #74 Fatura Takip
- `madagaskar/invoices-list`
- `madagaskar/invoice-get`
- `madagaskar/invoice-summary`
- `madagaskar/invoice-system-audit`

Migration sırasında çalıştırılmaz:
- invoice-set-state
- invoice-bulk-set-state

### #75 Okul Tanıtım
- `madagaskar/schools-list`
- `madagaskar/school-get`
- `madagaskar/schools-summary`
- `madagaskar/school-programs-list`
- `madagaskar/school-bridge-status`
- `madagaskar/school-route-plan`

Migration sırasında çalıştırılmaz:
- school-ensure-bridge
- school-update
- school-assign
- school-task-result

### #77 V5 Finans
- `madagaskar/finance-expenses-list`
- `madagaskar/finance-incomes-list`
- `madagaskar/finance-fixed-costs-list`
- `madagaskar/finance-attendance-list`
- `madagaskar/finance-summary`

Migration sırasında çalıştırılmaz:
- finance-expense-create
- finance-income-create
- finance-fixed-cost-create
- finance-attendance-set
- finance-delete

### #72 Program + Salon + Etkinlik + Satış

Read-only:
- programs-list / program-get
- venue-candidates / venues-list / venue-get / venue-location-options
- events-list / event-get
- sessions-list
- event-sales-readiness
- ticket-types-list
- channel-prices-list
- integrations-list

Migration sırasında hiçbir create/update/link/delete/ensure/sales-ready ability çalıştırılmaz.

## Önerilen canlı migration sırası

1. #74 Fatura
2. #75 Okul
3. #77 Finans
4. #72 Program + Salon + Etkinlik + Satış

#72 en geniş write yüzeyine sahip olduğu için en sona bırakılır.

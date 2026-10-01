# Madagaskar Kommo Automation

Bu plugin Code Snippets'taki Kommo otomasyonlarını kalıcı ve test edilebilir plugin koduna taşır.

## Modüller

- `location-menu-fix` ← #67
- `location-answers` ← #66
- `active-events-source` ← #70
- `automatic-ticket-link` ← #12

Plugin aktive edildiğinde varsayılan olarak hiçbir modül yüklenmez.

Etkin modüller:
`mdg_kommo_automation_modules`

## Güvenlik

#12 canlı snippetinde bulunan hard-coded `MS_KOMMO_TOKEN` değeri bu kaynakta saklanmaz.
Compatibility gerekirse `MS_KOMMO_TOKEN`, runtime `MMC_KOMMO_TOKEN` sabitine alias edilir.

## Migration sırası

#67 → #66 → #70 → #12

#70 ve #12 canlı müşteri/Kommo akışına daha yakın olduğu için en sona bırakılır.

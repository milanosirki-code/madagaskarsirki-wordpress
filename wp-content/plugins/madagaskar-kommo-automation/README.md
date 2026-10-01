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
`MS_KOMMO_TOKEN` kaynak kodda veya yeni production runtime'da gerekli değildir. Secret, `autoload=off` olan `mmc_kommo_runtime_token` option'ından plugin bootstrap sırasında yalnız `MMC_KOMMO_TOKEN` sabitine yüklenir.

## Migration sırası

#67 → #66 → #70 → #12

#70 ve #12 canlı müşteri/Kommo akışına daha yakın olduğu için en sona bırakılır.


## Canlı durum — 1 Ekim 2026

Migration tamamlandı:

- #67 → `location-menu-fix`
- #66 → `location-answers`
- #70 → `active-events-source`
- #12 → `automatic-ticket-link`

Canlı plugin: **v0.1.1**

Etkin modüller:
`location-menu-fix,location-answers,active-events-source,automatic-ticket-link`

Eski snippetler #12/#66/#67/#70 pasif, installer #101 pasif.

### Secret politikası

- #12 içindeki eski hard-coded Kommo token kaldırıldı.
- Pasif #12 rollback kopyası da sanitize edildi.
- Secret repository'de tutulmaz.
- Runtime kaynak: `mmc_kommo_runtime_token` WordPress option.
- Autoload: `off`
- Runtime sabit: `MMC_KOMMO_TOKEN`
- Legacy `MS_KOMMO_TOKEN`: gerekli değil.

### Son doğrulama

- Kommo configured: true
- connected: true
- http_ok: true
- token_source: MMC_KOMMO_TOKEN
- uses_legacy_token: false
- source consistency: safe
- system health: 0 critical / 0 warning / 16 OK
- aktif Code Snippets: 43

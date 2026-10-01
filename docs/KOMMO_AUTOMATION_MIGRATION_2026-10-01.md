# Madagaskar Kommo Automation — Canlı Migration Kaydı

Tarih: 1 Ekim 2026

## Son durum

`madagaskar-kommo-automation` v0.1.1 canlıda aktif.

Modüller:
- location-menu-fix ← #67
- location-answers ← #66
- active-events-source ← #70
- automatic-ticket-link ← #12

Dört legacy snippet de pasif bırakıldı. Silinmedi.

## Doğrulamalar

### Konum
Program #3 için konum option kaydı korundu:
- şehir alanı mevcut
- salon alanı mevcut
- Maps URL alanı mevcut

### Aktif etkinlik kaynağı
Tokenlı bilgi merkezi endpoint'i plugin modülünden başarıyla yüklendi.
Kontrol:
- Kommo Ana Talimat mevcut
- Pursaklar kaydı mevcut
- 17:30 ve 19:30 seansları mevcut
- `kommo-source-consistency-check`: safe=true, issues=[]

### Otomatik bilet linki
İlk #12 geçiş denemesinde load-order problemi tespit edildi. Kök neden, eski #12 snippetinin hard-coded `MS_KOMMO_TOKEN` ile gerçek secret kaynağı olmasıydı.

Rollback hemen uygulandı; Kommo health yeniden yeşile döndü.

Kalıcı çözüm:
1. Secret server-side olarak eski #12 kaynağından okundu; yanıta veya GitHub'a yazılmadı.
2. `mmc_kommo_runtime_token` option'ına taşındı.
3. Option `autoload=off`.
4. Plugin bootstrap secret'ı `MMC_KOMMO_TOKEN` olarak yükler.
5. Ticket-link modülü tokenı çalışma anında çözer.
6. Legacy `MS_KOMMO_TOKEN` artık gerekli değildir.
7. Inactive #12 rollback kodundaki token literal de temizlendi.

Final:
- configured=true
- connected=true
- http_ok=true
- token_source=MMC_KOMMO_TOKEN
- uses_legacy_token=false
- system health=0 critical / 0 warning / 16 OK

## Rollback

Bir Kommo otomasyon modülü sorun çıkarırsa:
1. ilgili slug'ı `mdg_kommo_automation_modules` option'ından çıkar,
2. ilgili eski snippet'i geçici olarak yeniden aktive et,
3. cache temizle,
4. Kommo diagnostics + system health çalıştır.

#12 rollback kopyası secretsizdir; runtime secret plugin option kaynağından gelir.

## Güvenlik

Secret değeri:
- GitHub'a yazılmaz,
- log'a yazılmaz,
- rapora yazılmaz,
- autoload edilmez.

Production snapshot:
`deploy/kommo-automation/production-state-2026-10-01.json`

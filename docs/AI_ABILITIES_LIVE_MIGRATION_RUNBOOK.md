# Madagaskar AI Abilities — Canlı Migration Runbook

## Amaç

WPVibe çağrılarını minimumda tutarak Code Snippets içindeki AI ability modüllerini kalıcı `madagaskar-ai-abilities` plugin'ine tek tek devretmek.

## GitHub kaynak durumu

- Source sürümü: **0.4.0**
- Plugin yolu: `wp-content/plugins/madagaskar-ai-abilities/`
- Manifest: `migration-manifest.json`
- GitHub Actions paketi: **Build Madagaskar AI Abilities**
- Üretilen artifact:
  - `madagaskar-ai-abilities.zip`
  - `madagaskar-ai-abilities.zip.sha256`

## Bilinen canlı durum — 1 Ekim 2026

Canlı plugin bilinen sürümü: **0.2.0**

Plugin'e devredilmiş modüller:
- `system-health-integrity` ← snippet #80
- `mmc-sales-ledger` ← snippet #89
- `mmc-dashboard` ← snippet #81
- `mmc-tasks` ← snippet #82

Bu snippetler pasiftir. Geçici installer #101 de pasiftir.

Son sağlık doğrulaması:
- 0 critical
- 1 warning
- 15 OK
- 60 aktif snippet

Kalan uyarı plugin migration kaynaklı değildir; aktif program ticket-chain kontrolünde satış nesnesi 0/0 görünümüdür.

## Kota-dostu canlı geçiş

WPVibe penceresi açıldığında gereksiz discovery çağrıları yapma.

### A. Önce plugin dosyalarını 0.4.0'a güncelle

Tercih sırası:
1. GitHub Actions artifact ZIP ile kontrollü plugin upload/update imkânı varsa onu kullan.
2. Yoksa daha önce doğrulanan tek-dosya installer yöntemiyle yalnız `main` raw kaynaklarını yaz.
3. Installer kullanılırsa işlem sonunda snippet #101 pasif olmalı.

Dosya güncellemesi tek başına yeni modülleri açmaz. Gate option yalnız mevcut dört modülü içermeye devam ettiği sürece yeni dosyalar inerttir.

### B. Her modül için yalnız üç temel işlem

1. İlgili eski snippet'i pasifleştir.
2. Aynı module slug'ını `mdg_ai_abilities_modules` option'ına ekle.
3. Manifestte belirtilen **read-only smoke testlerden** gerekli minimum seti çalıştır.

Başarılıysa sonraki modüle geç.

### C. Hata halinde rollback

1. Hatalı module slug'ını option'dan çıkar.
2. Eşleşen eski snippet'i yeniden aktive et.
3. Object cache temizle.
4. Aynı read-only smoke testi tekrar çalıştır.
5. `madagaskar/system-health-checks` ile sonlandır.

## Canlı migration sırası

1. #79 → `v4-refund-safety`
2. #94 → `v4-operations-safety`
3. #78 → `reporting-customer`
4. #83 → `mmc-region-population`
5. #84 → `mmc-field`
6. #85 → `mmc-operations`
7. #86 → `mmc-marketing`
8. #88 → `mmc-mdg-bridge`
9. #87 → `mmc-kommo`

Yüksek riskli #88 ve #87 en sona bırakılmıştır.

## Migration sırasında kesinlikle çalıştırılmayacaklar

- gerçek refund
- satış sync veya sales mapping write
- MDG auto/manual link
- MDG draft oluşturma
- Kommo lead/source write
- token migration
- operasyon plan/resource/checklist/schedule write
- saha atama/ziyaret/token write
- pazarlama/meta write
- report-send-now

## Kaynağı henüz GitHub'da yakalanmamış canlı snippetler

Issue #52 ile takip edilir:
- #72 Program + Salon + Etkinlik + Satış
- #74 Fatura Takip
- #75 Okul Tanıtım
- #77 V5 Finans

Bu kayıtlar canlı source okunmadan yeniden üretilmez.

## Tamamlanma kriteri

Bir snippet ancak:
- source GitHub'da,
- CI başarılı,
- plugin gate içinde,
- canlı snippet pasif,
- plugin modülü aktif,
- read-only smoke test başarılı,
- rollback yolu kayıtlı
ise migrate edilmiş kabul edilir.

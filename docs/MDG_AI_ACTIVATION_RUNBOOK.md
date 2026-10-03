# Madagaskar AI Abilities — Canlıya Alma Runbook

Bu belge `ai-abilities-staging-2026-09-28` dalının tarihsel aktivasyon referansıdır.

> Güncel durum — 3 Ekim 2026: PR #46, 1 Ekim 2026 06:04:07 UTC tarihinde merge edildi (merge commit `bcfe8f97b97dff542fcf334ef51919e4f80d5ddc`). AI abilities kalıcı plugin katmanına taşındı; canlı v0.6.1, main v0.7.0. Eski snippet aktivasyon adımları otomatik tekrar uygulanmaz. Güncel kaynaklar: [proje durumu](CODEX_PROJECT_STATE.md), [migration runbook](AI_ABILITIES_LIVE_MIGRATION_RUNBOOK.md) ve [üretim kaydı](../deploy/ai-abilities/production-state-2026-10-01.json). Şehir/program/kimlik örnekleri tarihsel olup her işlem öncesi canlıda doğrulanmalıdır.

## Temel kurallar

- PR #46 merge edilmiştir. Yeni değişiklik güncel main tabanlı `codex/...` branch, test ve PR ile hazırlanır; merge canlı deployment kanıtı değildir.
- Ability kaydı oluşturmak veri değiştirmez. Destructive işaretli ability'ler yalnız açık kullanıcı talebiyle çalıştırılmalı.
- Gerçek para iadesi için ability yoktur ve eklenmemelidir; V4'ün iki-yönetici güvenlik akışı korunur.
- Kommo secret/token değerleri hiçbir çıktıda gösterilmemelidir.
- Madagaskar aile paketi standardı `family_2_2` = “2 yetişkin + 2 çocuk” ve `capacity_units = 4` olarak korunmalıdır.
- Kommo AI source consistency check bu doğru `family_2_2` standardını hata saymamalı; yalnız tanım/kod/kapasite tutarsızlığında unsafe dönmelidir.
- MMC satış defteri senkronu, MMC↔MDG köprüsü ve satış mapping kapsamı doğrulanmadan çalıştırılmamalıdır.

## 1. Tarihsel temizlik adımları

Aşağıdaki adımlar eski aktivasyon planıdır; güncel aktif/pasif durum okunmadan çalıştırılmaz. 3 Ekim yeniden keşfinde #76 pasif ve eski AI snippet'leri pasiftir.
1. Geçici Code Snippet #76 — `MDG Modül Introspector — geçici` — deaktive edilir.
2. Daha önceki geçici introspection snippet'lerinin aktif olmadığı doğrulanır.
3. Mevcut canlı abilities sayısı ve Code Snippet code_error alanları kontrol edilir.

## 2. Önce salt-okunur / düşük riskli katmanlar

Tarihsel plan aşağıdaki snippet'leri kapsıyordu. Güncel kalıcı plugin modülü ile aynı snippet birlikte etkinleştirilmez. Yeni aktivasyonda migration runbook kullanılır; ability discovery ve read-only smoke test yapılır:

- `mdg-ai-v4-refund-safety.php`
- `mdg-ai-system-health-integrity.php`
- `mdg-ai-mmc-dashboard.php`
- `mdg-ai-mmc-region-population.php`
- `mdg-ai-mmc-field.php`
- `mdg-ai-mmc-tasks.php`
- `mdg-ai-mmc-operations.php`
- `mdg-ai-mmc-marketing.php`
- `mdg-ai-mmc-kommo.php`
- `mdg-ai-mmc-mdg-bridge.php`
- `mdg-ai-mmc-sales-ledger.php`

Aktivasyonda domain write ability çalıştırılmaz.

## 3. Read-only smoke test matrisi

### Sistem
- `madagaskar/system-health-checks`
- `madagaskar/dashboard-overview`
- `madagaskar/tasks-summary`
- Beklenti: PHP/ability hatası yok; WooCommerce/Tickera/PayTR ve veri kaynağı durumları dönüyor.

### V4 İade
- Son ödeme alınmış gerçek siparişlerden biri için `madagaskar/refund-preflight`.
- Yalnız dry-run sonucu okunur.
- İade case oluşturulmaz, onay verilmez, para iadesi yapılmaz.

### Bölge / Nüfus
- Ankara için `madagaskar/region-districts`.
- Pursaklar programı için `madagaskar/region-program-summary`.
- Nüfus ana kaynağının hazır olduğu ve `population_total` için ikinci kaynak kullanılmadığı doğrulanır.

### Saha
- Pursaklar için `madagaskar/field-summary`.
- `madagaskar/field-targets` read-only sorgusu.
- Mevcut Okul Tanıtım 76 okul / atama durumu ile MMC saha havuzu birbirinden ayrılarak yorumlanır.

### Operasyon
- Pursaklar için `madagaskar/operations-plan-get`, `operations-summary`, `operations-checklist-list`.
- Plan yoksa smoke test amacıyla `ensure` çalıştırılmaz; üretim kaydı oluşturma açık kullanıcı talebine bırakılır.

### Pazarlama
- Pursaklar için `madagaskar/marketing-pack-get` ve `meta-plan-get`.
- Paket yoksa smoke test amacıyla zorla üretim yapılmaz.

### Kommo
- `madagaskar/kommo-configuration`
- `madagaskar/kommo-connection-diagnostics`
- Pursaklar için `madagaskar/kommo-source-consistency-check`.
- Beklenti: `family_2_2`, “2 yetişkin + 2 çocuk” ve `capacity_units = 4` birlikte görülüyorsa aile paketi açısından `safe=true` olabilir.
- Eski “1 yetişkin + 2 çocuk” metni veya `family_2_2` için 4 dışı kapasite görülürse `safe=false` beklenir ve AI source write ability çalıştırılmaz.

## 4. MMC ↔ MDG köprüsü — satış senkronundan önce zorunlu

Önce:
- Pursaklar MMC Program #3
- Kırıkkale MMC Program #2

için sırasıyla:
- `madagaskar/mdg-bridge-status`
- `madagaskar/mdg-bridge-candidates`
- `madagaskar/mdg-publish-preview`

kontrol edilir.

Bir bağlantının güvenli kabul edilmesi için:
- il eşleşmeli,
- ilçe eşleşmeli,
- etkinlik tarihi eşleşmeli,
- kesin salon eşleşmeli,
- MMC ve MDG seans saatleri yerel saate normalize edildiğinde eşleşmeli,
- mümkünse WooCommerce ürün/varyasyon veya Tickera kimliği örtüşmeli,
- başka MMC programa bağlı MDG event çakışması olmamalı.

`mdg-bridge-auto-link` yalnız tam bir güçlü aday varsa kullanılabilir.
Birden çok güçlü aday varsa veya tarih/salon/seans uyuşmazlığı varsa manuel inceleme yapılır; link yazılmaz.

## 5. MMC satış mapping doğrulaması

Köprü doğruysa:
- `madagaskar/mmc-sales-mappings`
- `madagaskar/mmc-sales-summary`

çalıştırılır.

Mapping coverage eksikse:
1. Önce mevcut legacy MDG kimlikleri karşılaştırılır.
2. Uygunsa `mmc-sales-import-legacy` açık onayla kullanılır.
3. Tekil eksikler `mmc-sales-mapping-save` ile açık onayla düzeltilir.

Yanlış WooCommerce product/variation veya Tickera event ID tahmin edilmez.

## 6. WooCommerce → MMC satış defteri senkronu

Köprü ve mapping doğrulandıktan sonra:
- Önce `mmc-sales-summary` ile BEFORE snapshot alınır.
- Açık kullanıcı onayıyla `mmc-sales-sync-event` çalıştırılır.
- Sonra tekrar `mmc-sales-summary` alınır.
- Sonuç MDG `order_map` / Müşteri-Bilet listesi ile karşılaştırılır.

Beklenen:
- gerçek ücretli sipariş sayıları MMC defterine gelir,
- bilet adedi / kapasite birimi satışlarla uyumlu olur,
- iade edilmiş miktarlar net satıştan düşülür,
- aynı order_item tekrarlandığında duplicate değil update oluşur,
- WooCommerce/Tickera/PayTR doğrulanınca event/program satış durumları ileri taşınabilir.

Bu işlem idempotent olsa da program durumunu ileri taşıyabileceği için açık onay gerekir.

## 7. Kommo yazma işlemleri

Aşağıdakiler harici CRM yazımıdır:
- `kommo-sync-program-lead`
- `kommo-create-text-source`
- `kommo-sync-ai-source`

Ön koşullar:
- connection diagnostics başarılı,
- pipeline diagnostics geçerli,
- stage preview pipeline mismatch göstermiyor,
- source consistency `safe=true`,
- aile paketi kuralı `family_2_2` / “2 yetişkin + 2 çocuk” / `capacity_units = 4` olarak güncel.

Kommo kartı MMC'den ilerideyse geriye çekilmez.
İptal programlarda otomatik stage hareketi yapılmaz.

## 8. Production write test politikası

Smoke test için aşağıdaki write işlemleri yapılmaz:
- finans gelir/gider ekleme veya silme,
- görev tamamlama/iptal,
- operasyon planı oluşturma/güncelleme,
- okul/saha atama,
- pazarlama içeriğini published işaretleme,
- Meta bütçe/performance yazma,
- MDG manuel link,
- satış sync,
- Kommo sync,
- gerçek refund.

Bunlar yalnız gerçek operasyon ihtiyacı geldiğinde kullanılır.

## 9. Geri dönüş

Bir staged snippet aktivasyonunda PHP/ability hatası oluşursa:
1. Yalnız o snippet deaktive edilir.
2. Diğer çalışan canlı Madagaskar abilities korunur.
3. Domain verisi test amacıyla değiştirilmediyse veri rollback gerekmez.
4. Hata güncel main tabanlı yeni `codex/...` dalında düzeltilip tekrar smoke test edilir.

MMC satış sync veri yazmışsa kayıtları körlemesine silmek yerine önce ledger/mapping/log farkı incelenir; sync upsert/idempotent olduğundan doğru mapping ile yeniden çalıştırma tercih edilir.

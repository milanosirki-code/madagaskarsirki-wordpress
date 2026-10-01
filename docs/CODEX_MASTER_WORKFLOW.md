# Madagaskar Sirki — Codex Master Workflow

## Amaç

Codex, ChatGPT, GitHub ve WordPress/WPVibe çalışmalarını tek, izlenebilir geliştirme sürecine bağlamak.

## Sistem rolleri

### GitHub
Üretim kodunun kanonik kaynağıdır. Kalıcı PHP/JS/CSS, MMC eklentisi, snippet kaynakları, runbook ve testler burada tutulur.

### Codex
Repository üzerinde:
- kod inceleme,
- hata ayıklama,
- refactor,
- test yazma/çalıştırma,
- patch hazırlama,
- branch ve PR üretme
işleri için kullanılır.

### ChatGPT
Operasyon, gereksinim, doğrulama, kaynaklar arası karşılaştırma ve deployment koordinasyon katmanıdır.

### WPVibe / WordPress
Canlı site doğrulaması, kontrollü aktivasyon ve smoke test katmanıdır. Production üzerinde yapılan kalıcı değişiklik GitHub ile senkron tutulmalıdır.

### Google Drive
Operasyon belgeleri, sözleşmeler, Excel şablonları, raporlar, yedekler ve kullanıcı rehberleri için belge katmanıdır. Uygulama kaynak kodunun ana kaynağı değildir.

## Branch stratejisi

- `main`: üretim için onaylanmış kanonik kod.
- `codex/*`: Codex geliştirmeleri.
- `fix/*`: hata düzeltmeleri.
- `feature/*`: yeni özellikler.
- `ai-abilities-staging-2026-09-28`: mevcut AI abilities staging çalışması; PR #46 ile takip edilir.

Her görev kendi branch'inde olmalıdır. Birbirinden bağımsız sorunlar tek dev PR içinde birleştirilmemelidir.

## Değişiklik sınıfları

### Düşük risk
Dokümantasyon, test, read-only admin görünümü.

### Orta risk
MMC admin modülü, raporlama, mapping görüntüleme, read-only REST/ability.

### Yüksek risk
Checkout, WooCommerce order hook'ları, Tickera/Checkinera, PayTR, satış ledger write, MMC↔MDG mapping write, Kommo write, finans, refund.

Yüksek riskli değişikliklerde reproduction + test + rollback planı zorunludur.

## Codex görev şablonu

Her Codex görevi şu sözleşmeyle başlamalıdır:

1. Önce repository ve ilgili dosyaları oku.
2. Çalışan özellikleri koru.
3. Sorunun kök nedenini kanıtla; tahminle patch yapma.
4. Minimum güvenli değişikliği yap.
5. İlgili testleri ekle/çalıştır.
6. Secret, token, API key veya kişisel veri commit etme.
7. Production'a doğrudan deploy etme.
8. Ayrı branch'te çalış.
9. Değişiklik özeti, riskler, test sonucu ve rollback adımı bulunan draft PR hazırla.

## MMC için özel kontrol listesi

MMC değişikliğinde en az şu alanlar düşünülür:
- class/include load order
- admin menu/page routing
- capability/nonce
- WooCommerce compatibility
- program↔salon ilişkisi
- session normalization
- WooCommerce/Tickera ID mapping
- sales ledger idempotency
- old data/backward compatibility

## Checkout değişikliği kontrolü

Checkout veya order hook'u değiştiyse:
- sepete ürün eklenebiliyor mu,
- checkout sayfası fatal vermeden açılıyor mu,
- seans/etkinlik meta bilgisi korunuyor mu,
- ödeme tamamlanınca sipariş durumu doğru ilerliyor mu,
- MMC satış senkronu checkout'u bloke etmiyor mu,
- aynı hook iki kez kayıtlı mı,
- loglarda fatal/deprecation oluşuyor mu
kontrol edilir.

## Kommo değişikliği kontrolü

- Kaynak secret/token log veya HTML'e sızmamalı.
- Aktif program bilgisi kanonik kaynaktan gelmeli.
- şehir/ilçe/tarih/salon/seans/fiyat/konum tutarlılığı doğrulanmalı.
- write işleminden önce connection + pipeline + source consistency geçmeli.
- `family_2_2` kapasitesi 4 olmalı.

## Snippet → plugin dönüşümü

Kalıcı hale gelen snippetler için:
1. bağımlılıkları çıkar,
2. hook'ları isimlendir,
3. namespace/class yapısına taşı,
4. seçenek/ayar migrasyonunu tasarla,
5. backward compatibility koru,
6. snippet ile plugin aynı anda iki kez hook bağlamasın,
7. önce test, sonra snippet deactivation yap.

## PR kabul kriterleri

PR açıklamasında:
- problem,
- root cause,
- changed files,
- risk level,
- automated tests,
- manual smoke tests,
- production deployment steps,
- rollback
bulunmalıdır.

Testi olmayan yüksek riskli patch merge edilmemelidir; test yazılamıyorsa neden ve manuel doğrulama açıkça yazılmalıdır.

## Production deployment

1. Yedek/geri dönüş noktası doğrulanır.
2. Değişiklik branch/PR ile izlenir.
3. Gerekli snippet/plugin sürümü hazırlanır.
4. Canlıya yalnız ilgili modül uygulanır.
5. PHP/code error kontrol edilir.
6. Alan smoke testleri yapılır.
7. Checkout etkileniyorsa satış akışı test edilir.
8. Başarılıysa deployment kaydı tutulur.
9. Hotfix varsa aynı patch main'e geri işlenir.

## Rollback ilkesi

Bir modül hata verirse:
- yalnız o değişikliği geri al,
- çalışan modülleri kapatma,
- veri yazıldıysa körlemesine silme,
- mapping/ledger/upsert farkını incele,
- rollback sonrası aynı smoke testleri tekrar çalıştır.

## Mevcut kritik çalışma

PR #46, AI abilities paketinin staging alanıdır. Bu PR:
- production'a otomatik merge edilmemeli,
- modül bazlı smoke test ile doğrulanmalı,
- sales ledger, bridge ve Kommo write işlemlerinde açık kontrollere tabi tutulmalıdır.

Bu workflow yeni görevlerde varsayılan çalışma standardıdır.

# AGENTS.md — Madagaskar Sirki WordPress

Bu repository, madagaskarsirki.com için üretim kodunun ana kaynağıdır.

## Öncelik sırası

1. Canlı bilet satışı, ödeme ve checkout akışını koru.
2. Mevcut çalışan özellikleri bozma.
3. En küçük güvenli değişikliği yap.
4. Her değişikliği test edilebilir ve geri alınabilir tut.
5. Production'a doğrudan yazma; branch + test + PR akışını kullan.

## Yetkili çalışma alanları

Başlıca kod alanları:
- `wp-content/plugins/madagaskar-management-center/`
- `docs/code-snippets/`
- `docs/`
- repo içindeki test, workflow ve bakım dosyaları

Bir dosyanın rolü belirsizse önce mevcut çağrı zincirini, bağımlılıklarını ve canlı karşılığını incele.

## Yasak / yüksek riskli işlemler

Açık kullanıcı talebi ve gerekli doğrulamalar olmadan:
- gerçek para iadesi yapma,
- WooCommerce siparişi silme/değiştirme,
- PayTR veya ödeme kimlik bilgilerini değiştirme,
- Kommo secret/token değerlerini çıktı verme veya commit etme,
- production veritabanında toplu/destructive işlem yapma,
- aktif etkinlikleri, bilet ürünlerini veya satış kayıtlarını tahmine dayalı değiştirme,
- çalışan MMC modüllerini topluca devre dışı bırakma.

## Geliştirme standardı

Her görevde:
1. Sorunu yeniden üret veya ilgili kod yolunu doğrula.
2. Etkilenen dosya ve bağımlılıkları belirle.
3. Minimum patch hazırla.
4. Syntax/static kontrolleri çalıştır.
5. Varsa ilgili otomatik testleri çalıştır.
6. Checkout, etkinlik, seans, bilet ve admin ekranlarını etkiliyorsa smoke-test planı yaz.
7. Ayrı branch kullan.
8. Açıklamalı draft PR oluştur.
9. Production deploy öncesi manuel/live smoke test gerektiren noktaları açıkça belirt.

## WordPress / WooCommerce kuralları

- WordPress capability, nonce, sanitization ve escaping kurallarını koru.
- WooCommerce API'lerinde güncel metodları tercih et.
- Sipariş/veri senkronlarında idempotency gözet.
- Hook kaldırma/ekleme değişikliklerinde mevcut priority ve callback sınıfını doğrula.
- Bir class yükleme hatasında bütün plugini kapatmak yerine load-order/include/guard düzeltmesini tercih et.
- REST ve admin endpoint'lerinde yetki kontrollerini gevşetme.

## Madagaskar domain kuralları

- Aile paketi standardı: `family_2_2` = 2 yetişkin + 2 çocuk; `capacity_units = 4`.
- Kommo kaynaklarında secret/token sızdırma.
- Satış ledger senkronundan önce MMC↔MDG mapping/bridge doğrulanmalı.
- Yanlış product, variation, Tickera event veya program ID tahmin edilmemeli.
- Program/salon/seans/fiyat/konum verilerinde mümkün olan en güncel kanonik kaynağı kullan.
- Aktif checkout akışını değiştiren patch'ler production öncesi sepet→checkout→ödeme adımlarında doğrulanmalı.

## Snippet politikası

Code Snippets üretimde geçici/operasyonel köprü olabilir; kalıcı iş mantığı mümkün olduğunda test edilebilir plugin koduna taşınmalıdır.

Yeni veya değişen snippet için:
- amacı,
- hook'ları,
- bağımlılıkları,
- rollback yöntemini
dokümante et.

## Canlıya alma

Kodun GitHub'da hazır olması production'a alındığı anlamına gelmez.

Normal akış:
`issue/istek → branch → kod → test → draft PR → review → staging/live smoke test → kontrollü deploy → post-deploy doğrulama`

WPVibe/WordPress üzerinde canlı değişiklik gerekiyorsa GitHub koduyla fark bırakma. Canlı hotfix yapılmışsa aynı değişiklik repository'ye geri işlenmelidir.

## GitHub ve Drive kayıtları

Kullanıcı talimatı: her çalışmanın değişikliklerini, doğrulama kanıtlarını ve proje durumunu GitHub ile mevcut Drive proje arşivine ekle/güncelle. Hassas müşteri değerleri veya tokenları arşivleme. Bir kanala yazılamazsa başarı iddia etme; eksik arşiv adımını açıkça kaydet.

## İlgili dokümanlar

- `docs/CODEX_MASTER_WORKFLOW.md`
- PR #46 içindeki staged AI aktivasyon runbook'u: `docs/MDG_AI_ACTIVATION_RUNBOOK.md` (henüz main'de olmayabilir)

Bu dosyadaki güvenlik ve deployment kuralları alt klasörlerdeki talimatlardan aksi açıkça belirtilmedikçe geçerlidir.

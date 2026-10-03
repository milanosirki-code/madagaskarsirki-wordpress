# Madagaskar Sirki — Codex Project State

## Güncel devir: 3 Ekim 2026 (UTC)

Bu kayıt canlı program/fiyat listesi değildir. Dinamik veriyi işlem anında canlıda doğrula. En son yönetici talimatı → doğrulanmış canlı program → canlı WordPress/WooCommerce/MMC/Kommo → güncel GitHub → güncel Drive → tarihsel dosyalar sırası geçerlidir.

Mimari korunur: WordPress → WooCommerce → PayTR → Tickera → QR/check-in. MMC yönetim/operasyon, Kommo CRM/iletişim katmanıdır. `family_2_2` = 2 yetişkin + 2 çocuk, kapasite 4. Ödeme yoksa bilet yok. Gerçek iade, sipariş/toplu müşteri silme ve geri dönüşü zor üretim işlemleri açık onay gerektirir.

## Bu çalışma grubu

- İş: güncel main, son commit/PR, runbook, MMC/AI/snippet kaynakları, Actions, canlı read-only sağlık/satış/Kommo önizlemesi ve Drive devir kaydı yeniden keşfedildi.
- Sorun: eski AGENTS/workflow/runbook PR #46'yı hâlâ staging/merge edilmemiş gibi anlatıyor; tamamlanan Issue #98 sonrasında geçici kaynak okuyucu #117 aktif kalmış.
- Kök neden: tarihsel aktivasyon planları güncellenmemiş; geçici tanı aracının yaşam döngüsü kapanmamış.
- Değişiklik: eski PR #46 talimatları tarihsel işaretlendi; #117 tam kaynağı GitHub'a yedeklendi, readback eşitliği kontrol edildi, yalnız bu snippet pasifleştirildi. Plugin/ödeme/sipariş/bilet kodu değiştirilmedi.
- GitHub dosyaları: `AGENTS.md`, `docs/CODEX_MASTER_WORKFLOW.md`, `docs/MDG_AI_ACTIVATION_RUNBOOK.md`, bu dosya, [denetim manifesti](live-captures/2026-10-03/codex-rediscovery-manifest.json), [#117 yedeği](live-captures/2026-10-03/snippet-117-ticket-datetime-source-reader.php.txt).
- WordPress karşılığı: Code Snippets #117, `GEÇİCİ — Ticket Datetime Source Reader`; final `active=false`, `code_error=null`. #76/#118 ve eski AI migration snippet'leri zaten pasif; tekrar değiştirilmedi.
- Branch: `codex/current-system-audit-20261003`; başlangıç main SHA `6e21a5e4217c085d6668f6932a874f846845a7de`.
- Yedek commit: `4f843d1bd371165bc6f93955a755b71e0c0e8bad`; denetim kaydı commit: `8b787e46d6e00a8e9be6abbeaa793beae2bfd56f`. Bu durum dosyasının commit'i GitHub geçmişindedir; dosya kendi commit SHA'sını içeremez.
- PR: oluşturma sonrası bu alana numara/link kaydedilir.
- Rollback: ihtiyaç halinde mevcut #117'yi owning Code Snippets yönetiminden yeniden etkinleştir; kaynak aynı kalmıştır. Belge değişikliklerini ilgili commit üzerinden geri al.

## Doğrulanan durum

- PR #46 1 Ekim 2026 06:04:07 UTC'de merge edilmiş; merge SHA `bcfe8f97b97dff542fcf334ef51919e4f80d5ddc`.
- PR #108 merge edilmiş; MMC main/canlı 1.3.47. Aynı günün 41/41 eşitlik manifesti main'dedir. Bu oturumda tam MMC dosyaları yeniden okunmadı; yeni tam eşitlik iddiası yok.
- PR #110 main'e alınmış; kanonik bilet seans motoru mevcut. Standalone Ticket Session Datetime Fix pasif. Canlı MDG bootstrap SHA256 `9863ef7feaab4096051471047cf4061ee89dc71f44deaa43e043dff1667bc23d`, main ile birebir.
- Canlı WordPress 7.1.2, PHP 8.4.26, AI Abilities 0.6.1; main AI 0.7.0. Bu bilinen deployment farkı, otomatik yükseltme gerekçesi değildir; 0.7.0 write migration canlı modülü olarak kurulmamış.
- 118 snippet okundu; başlangıç 43, #117 sonrasında 42 aktif. Bütün okunan kayıtlarda `code_error=null`. Kimlikler ve aktif kaynak SHA256'ları manifesttedir.
- Sağlık denetimi önce/sonra 16 OK, 0 warning, 0 critical. WooCommerce/Tickera/PayTR varlığı ve Kommo bağlantısı/pipeline başarılı. Sağlık çıktısı ödeme tahsilatını veya tam checkout'u kanıtlamaz.
- Canlı MMC Program #9 ↔ MDG #7: tarih/salon/il/ilçe/seans uyumlu, 6/6 kimlik, 3/3 seans. Read-only mutabakat: 21 ücretli sipariş, 38 kalem, 62 bilet/kapasite, ciro farkı 0. Sales summary 24 sipariş ve 3 başarısız sipariş gösteriyor; bu fark ücretli ve tüm sipariş kapsamlarından gelir. Başarısız siparişler net ücretli satışa eşitlenmez.
- 13 MMC `family_2_2` kaydının tamamında kapasite 4. Bu kontrol Tickera QR adedi veya gerçek ödeme sonrası kapasite düşümünün uçtan uca testi değildir.
- Kommo Program #9 yerel kaynak önizlemesi `safe=true`, seanslar uyumlu; bu programda aile paketi algılanmadığı için aile paketi metin doğrulaması yapılmış sayılmaz.
- Kommo iptal Program #2 önizlemesi açık iptal/satışa sunmama metni içeriyor. `safe=false`: aktif-program aile/seans denetimi kısaltılmış iptal metnini reddediyor. İptal kaynağına aktif satış bilgisi eklenmez; ayrı validator incelemesi gerekir.
- #15/#30/#103 canlı SHA256'ları PR #107 head kaynakları ile birebir; #15 için PHP açılış etiketi hariç karşılaştırıldı. Bu güncel kaynaklar main'e henüz alınmamış; PR #107 source-record draft'ıdır. Eski main snippet #30/#103 canlıya yüklenmez.
- Son 10 Actions başarılı. Son başarısız kayıtlar eski datetime/MMC branch commit'lerinde; daha sonraki başarılı çalışma/merge kanıtları var. Her tarihsel failure güncel hata sayılmaz. Arşiv branch syntax failure'ı ayrıca açık PR kapsamında incelenmeli.
- Açık PR'lar: #109, #107, #106, #45, #43, #38, #1. Bunlar merge sırası ve güncel canlı karşılığı okunmadan uygulanmaz.
- Drive güncel rehberi 3 Ekim 12:54:16 UTC güncellenmiş; son bölümü #108/#110 tamamlanmasını doğruluyor. Aynı belgedeki önceki “MMC 1.3.30/hotfix aktif/PR110 draft” paragrafları tarihsel.

## Smoke test ve sınırlar

Bu oturumda canlı authenticated GET sepet API, ability sağlık, bridge/sales-summary ve Kommo kaynak önizlemeleri çalıştı. #117 pasifleştirme isteği HTTP500 döndürdü; yazma tekrarlanmadı. Hemen hedef GET readback `active=false/code_error=null` doğruladı; sonraki sağlık ve sepet API başarılı. HTTP500'ün PHP/host/eklenti nedenini belirleyen origin log kanıtı yok.

Yerel SHA256 karşılaştırmaları başarılı; belge/manifest kontrolleri çalıştırılır. Yerel PHP CLI yok ve shell HTTPS proxy bağlantısı kurulamadı; GitHub connector kullanıldı. Anonim HTML/admin menü tarayıcı smoke, dolu yeni sepet → checkout → PayTR → sipariş → bilet zinciri bu oturumda doğrulanmadı. “Checkout fatal yok / ödeme-bilet akışı çözüldü” sonucu çıkarılmaz. Daha önceki #110 PDF/QR canlı kanıtı aynı gün tarihsel referanstır, yeni test değildir.

Kommo canlı ajan retrieval'ı bu oturumda test edilmedi. Yerel source consistency ile uzak AI'nın aynı kaynağı kullandığı varsayılmaz. PayTR bağımsız mutabakat, Biletinial, gişe/POS ve tüm program satış eşitliği kontrol edilmedi. Tam canlı kaynak drift'i sıfır denemez.

## Sıradaki işler — öncelik sırası

1. İzole anonim/test oturumunda güncel satılabilir ürünle checkout smoke; gerçek ödeme gerekmeyen PayTR sınırına kadar ilerle. Tam test imkânı ve log erişimi sağlanmadan checkout değişikliği yapma. Mevcut yönetici sepeti değiştirilmedi; okunurken geçmiş saatli Sincan kalemleri görüldü, checkout kabul/ret durumu test edilmedi.
2. #117 deactivate HTTP500 origin log nedenini araştır; başarılı readback nedeniyle aynı yazımı tekrar çalıştırma.
3. PR #107 canlı kaynak kayıtlarını güncel main ile dosya bazında uzlaştır; #15/#30/#103 eşitlik kanıtlarını kullan. #106/#109 eski kaynak tabanı ve sabit ID istisnalarını aynı anda değerlendirmeden merge etme.
4. WooCommerce↔MMC ücretli/net/iade/kapasite mutabakatını bütün güncel programlara genişlet; mapping coverage olmadan sync çalıştırma.
5. Issue #105 dry-run guard kapsamını V4 satış kapanışı, başlamış seans ve ödeme/tekrar hatırlatma regresyonlarıyla tamamla; gerçek mesaj gönderimi kapalı kalır.
6. Kommo iptal-kaynak validator semantiği ve uzak retrieval/source kapasitesi Issue #82 kapsamında incelenir.
7. Menü/modül tarayıcı smoke ve kalan kaynak envanteri; ardından teknik borç.

Yeni çalışmada main/head ve bu kaydı yeniden oku. Finansal veya destructive işi geçmiş talimattan tahmin ederek tekrarlama.

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
- PR: [#111](https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/111), draft; merge edilmedi. Son manifest commit: `cab00ddb84210e95e8921c8c0becb36b10972068`.
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

Yerel SHA256 karşılaştırmaları ve Python JSON/envanter/Markdown/tarihsel talimat kontrolleri başarılı. Yerel PHP CLI yok ve shell HTTPS proxy bağlantısı kurulamadı; GitHub connector kullanıldı. Anonim HTML/admin menü tarayıcı smoke, dolu yeni sepet → checkout → PayTR → sipariş → bilet zinciri bu oturumda doğrulanmadı. “Checkout fatal yok / ödeme-bilet akışı çözüldü” sonucu çıkarılmaz. Daha önceki #110 PDF/QR canlı kanıtı aynı gün tarihsel referanstır, yeni test değildir.

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


## 2026-10-03 — Aşama 2: checkout, HTTP 500 ve live/main kaynak uzlaştırması

- Branch: `codex/current-system-audit-20261003`; audit/devir PR: [#111](https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/111), **draft korunuyor**.
- Main pin: `6e21a5e4217c085d6668f6932a874f846845a7de`.
- Tanılama kodu / syntax-tested SHA: `cad39f9347f7da06c9087418d34464d19ae52581`; kaynak/kanıt arşiv commit: `921de99cd9ddd85a30d275e3389ab04b05682b67`.
- GitHub dosyaları: `docs/code-snippets/stage2-runtime-audit.php`, `docs/live-captures/2026-10-03/stage2/*`, bu devir kaydı. Üretim eklenti kaynakları değiştirilmedi. Arşivler deployment paketi değildir.
- WordPress karşılığı: yeni GEÇİCİ diagnostic Code Snippet **#119**; inceleme sonunda **pasif**, `code_error=null`. #117 de pasif. Toplam **119 snippet / 42 aktif / 0 code_error**. MMC **1.3.47**; health **16 ok / 0 warning / 0 critical**.

### Checkout smoke ve ödeme sınırı

**CHECKOUT BASELINE NOT FULLY VERIFIED.** Ürün/sepet/checkout GET sağlıklı; ödeme/sipariş/bilet zinciri tamamlandı denemez.

2026-10-03 16:42:16 UTC: canlı MDG seans60 ↔ variation2278 doğrulandı (gelecek onsale seans, satın alınabilir/stokta).
İzole misafir cookie jar / Store API nonce-cart token yalnız bellekte tutuldu; çıktı veya repoya yazılmadı.

| Endpoint türü | Sonuç |
| --- | --- |
| Public `/bilet-al/`, `/odeme/` HTML | Fatal görülmedi; sayfa aracı redirect cookie korumuyor, boş sepet tam checkout kanıtı sayılmadı |
| `GET /wc/store/v1/products/2278` | Satılabilir doğru bilet varyasyonu |
| `GET /wc/store/v1/cart` | 200, başlangıç boş misafir sepeti |
| `POST /wc/store/v1/cart/add-item` | 201, ürün sepette doğrulandı |
| `GET /wc/store/v1/cart` | 200, 0 cart error |
| `GET /wc/store/v1/checkout` | 200, `checkout-draft`, **order_id=0** |
| `POST /wc/store/v1/cart/remove-item` → cart GET | 200 → 200, yalnız test sepeti temizlendi |

- **Gerçek ödeme/tahsilat yok. Yeni sipariş oluşmadı.** GET'in draft durum etiketi persist edilmiş Woo siparişi değildir; HPOS `wc-checkout-draft` sorgusu 0 kayıt döndü.
- PayTR: `paytr_payment_gateway` misafir sepetinde kullanılabilir yöntem. Token/iframe/callback isteği, checkout POST ve payment_complete çalıştırılmadı.
- MMC Sales Service: payment_complete priority20 ve order_status_changed priority20 kayıtlı; MDG pre-payment classic/Store API hooks priority5 ve paid hooks priority5 kayıtlı. Hook metadata + canlı/main hash doğrulaması yapıldı; gerçek sipariş gönderimi bu testte yürütülmedi.
- Tickera: aktif Bridge1.7.7 / core3.6.0.6. Başarılı ödeme sonrası QR üretimi **yeni işlemle doğrulanmadı**.
- İlk probe add-item201'i yanlışlıkla başarısız saydı; HTTP201 kabulü düzeltildi ve yeniden test geçti. O ilk misafir sepeti yalnız oturum expiry'sine bırakıldı; sipariş/hold üretilmedi. Son başarılı test sepetleri temizlendi.
- Aktif tanılama kodunu aynı requestte yeniden değerlendiren PUT bir kez function redeclare guardına takıldı ve #119 pasif kaldı. Pasif kaydet → yeni requestte aktive et sırası kullanıldı; son durumda hata yok. Çalışan üretim snippet/modülleri kapatılmadı.

### HTTP 500 kök nedeni — write başarılı, response filtering fatal

**Kanıtlandı:** Code Snippets REST activate/deactivate callback'i `Code_Snippets\\Snippet` nesnesi döndürüyor. `fields` aracılığıyla gönderilen `_fields`, WordPress core'da dizi bekleyen filtreyi tetikliyor.

- Exception: **TypeError**
- Dosya/satır: **wp-includes/rest-api.php:930**
- Çağrı zinciri: başarılı Code Snippets mutation → `rest_filter_response_fields` (:989) → `_rest_array_intersect_key_recursive` (:930) → `array_intersect_key`.
- İlk #117 deactivation log: **15:32:05 UTC**. #119 activation aynı hata: **16:33:54 ve 16:37:32 UTC**.
- Log kanıtı: okunabilir hosting `php-errors` ve WooCommerce `fatal-errors-2026-10-03-*.log`. `WP_DEBUG_LOG=false`; debug.log yok. DB Woo log boşluğu fatal yok anlamına gelmiyor.
- Etkilenen endpointler: `POST /code-snippets/v1/snippets/{id}/activate|deactivate` **_fields ile**.
- **Uygulanan ve doğrulanan workaround:** action POST'ta `fields/_fields` kullanma; ardından ayrı GET'te `id,active,code_error` oku. #119 pasifleştirme/etkinleştirme/pasifleştirme bu şekilde hatasız tamamlandı (response `{}`, readback doğru).
- MMC/MDG/PayTR kaynaklı değil; timeout/shutdown varsayımı desteklenmedi. WordPress core veya vendor plugin kör patch edilmedi. 500 sonrası target readback yapmadan mutation tekrarlanmaz.

### Live ↔ main ↔ PR107 ↔ audit branch

126 canlı dosya: **119 byte-exact SAME**, **2 davranışsal SAME / whitespace-only**, kalan **5 kayıt** (aşağıda). Kapsam altı proje plugin alanı + aktif V4 transition alanıdır; tüm third-party pluginler veya 42 aktif snippet için tam drift-free iddiası yok.

| Kaynak | Kategori | Karar |
| --- | --- | --- |
| MMC41 dosya, Sales/Region/ledger/MDG bridge dahil | SAME | Main1.3.47 zaten canlı kaynak; PR108 ile uzlaştırılmış |
| MDG checkout/live-sales/kapasite ve bootstrap | SAME | Canlı kod korunuyor |
| public-event.css ve canonical ticket-session-datetime.php | SAME | Sadece baş/son newline; trim-exact, kod overwrite gereksiz |
| AI bootstrap | MAIN AHEAD | Live0.6.1 / main0.7.0; migrate write modülü live'da yok, otomatik deploy yok |
| Family plugin | MAIN AHEAD | Live1.1.2 / main1.1.3 MMC fiyat lookup ekli; fiyat/kapasiteyi testi olmadan değiştirme |
| Family readme | LIVE AHEAD | Belge snapshot alındı |
| Aktif MDG V4 transition PHP + readme | HOTFIX ONLY / NEEDS REVIEW | Same-path main yok; canlı tam kaynak arşive alındı, canonical ownership değerlendirmesi gerekli |
| Snippet15 / 30 / 103 | LIVE AHEAD; PR107 after ile SAME | Güncel kaynaklar bu branch arşivine taşındı; SEO/day guard/cancelled reminder guard korunuyor |
| Snippet110 MMC Canonical Unified V2 | LIVE AHEAD / NEEDS REVIEW | Yeni ayrı kaynak owner arşive eklendi; main'deki eski70k unified-source implementasyonu aynı kod değil, onunla overwrite etme |
| PR107 eski main1.3.30 karşılaştırması | OBSOLETE | Main artık1.3.47, snapshot yalnız tarihsel |
| PR107 standalone datetime fix | OBSOLETE runtime / tarihsel kaynak | PR110 canonical module devraldı; standalone canlıda pasif |

PR107 merge edilmedi. Yalnız eski karşılaştırmayı tekrar ederek yeni main'i downgrade etme. Audit branch üretim kodunu main'den değiştirmiyor; eksik canlı owner'lar exact .php.txt arşivleri ve mapping ile izlenebilir hale getirildi. Source reconciliation **main'e henüz merge edilmiş değildir**.

### Açık riskler / sıradaki güvenli iş

1. **P1 / [#112](https://github.com/milanosirki-code/madagaskarsirki-wordpress/issues/112):** 43 failed HPOS siparişinde `date_paid_gmt IS NULL`, bağlı **104 publish Tickera instance**; 8 cancelled unpaid siparişinde25 trash instance. Canlı Bridge create_order_ticket_instances (:1902) / Store API request update (:993) ödeme öncesi instance/code üretimini içeriyor. Download gate (:2875) normalde processing/completed ile sınırlı. Bu bulgu yetkisiz indirme/check-in kanıtı veya eksiksiz geçmiş ödeme doğrulaması değildir. **Ödeme yoksa bilet yok standardı henüz onaylanamaz.** Gerçek sipariş/bilet değiştirilmedi/silinmedi.
2. Ayrı staging/fixture testinde attendee fields korunarak paid-only, idempotent ticket creation adaptörünü hazırla; failed/cancelled/retry/duplicate callbacks/HPOS/family2+2 testleri olmadan production deployment yapma. Ardından izole checkout POST → PayTR geçişini ücretsiz/sandbox akışta doğrula. Payment_complete'u canlıda sahteleme.
3. KommoV2 preview10 program içeriyor; aynı gün son16:00 seansı bitmiş Sincan,19:47 Türkiye saatinde hâlâ listeleniyor; public bilet listesi9. Date-only kaynak filtresinde session/status sunset review gerekli. Preview güncel MMC okumasını gösterir; Kommo indeks/retrieval tazeliğini tek başına kanıtlamaz. Mevcut kaynaklara write/refresh/send yapılmadı.
4. Main-ahead family fiyat lookup ve AI migration için ayrı kontrollü deployment değerlendirmesi; audit PR111'i deploy paketine dönüştürme.
5. Kalan aktif snippet owner eşleştirmeleri, V4 canonical ownership ve Kommo retrieval tamamlanmadan global drift-free ilan etme.

### Test / rollback

- PHP8.4 Changed PHP Syntax CI: final diagnostic SHA `cad39f9`, run101 **success**. Önce syntax, sonra temporary live activation.
- Misafir Store API smoke yukarıdaki sonuçlarla geçti; özel müşteri/finans/bilet write yok.
- Tüm arşivler hash/snapshot kimliğiyle kayıtlı; PHP kaynakları .txt olduğundan auto-load/deploy olmaz.
- Son envanter119/42/0error, #117/#119 pasif; health16/16. PR111 draft, PR107 unmerged.
- Rollback: yalnız #119'u pasif tut; audit branch docs'ı revert edilebilir. Vendor/core/PayTR/MMC deployment veya gerçek order/ticket rollback gerekmiyor.

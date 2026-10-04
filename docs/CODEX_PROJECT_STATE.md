# Madagaskar Sirki — Codex Project State

## Güncel devir — Aşama 5, 4 Ekim 2026 (UTC)

- Yönetici kapsamı: PR #113 merge + MMC menü/fonksiyon/ability denetimi. PayTR sandbox/tam checkout ertelendi; #112 açık/nonblocking. #107 merge edilmez. Family 1.1.3 ve AI bootstrap 0.7.0 deploy edilmez.
- Güncel main SHA: `8a6659d6f247a439171c92c4460a32146407ae9f`. PR #113 reviewed head: `67b38ae173ce21c9962fe4067478831d71f56e1b`; merge başarılı, CI 3/3 yeşil.
- Audit branch: `codex/current-system-audit-20261003`, PR #111. Yeni main audit geçmişine alınmış; wp-content production source farkı yok. Son tanı kaynağı commit: `c9244fd185c9513ab5b51a9512e268fd820dfde4`.
- Canlı MMC 1.3.47 / Okul Tanıtım 1.7.9 / AI 0.6.1. 120 snippet, 43 aktif, code_error 0; post-audit sağlık 16/16.
- Source inventory: MATCH 191, WHITESPACE_ONLY 2, LIVE_AHEAD 0, GITHUB_AHEAD 2, NO_REPO_SOURCE 0. 28 pasif diagnostic/installer production sayımına dahil değil. Fresh MMC 43/43 ve aktif snippet 43/43 hash main ile aynı. Diğer plugin dosyaları için Stage 4 capture + yeni main blob eşleşmesi kullanıldı; Stage 5'te tamamı yeniden okunmadı.
- Menü haritası: toplam 71; mmc-* 31 (CSS gizlenen detaylar dahil), görünür mmc-* 15. Live callback render OK 67, NEEDS_REVIEW 4 (GET yerel SQL yazma isteği engellendi). UI_BUG/menu DATA_BUG/API_BUG/PHP_ERROR/INCOMPLETE/DEAD/DUPLICATE saptanan 0. Ability sonucu DATA_BUG 1 (#114). Görsel CSS/JS, form write akışı ve diğer rollerin negative permission testi yapılmadı.
- Ability: madagaskar/* 98; read-only etiketli 59 çağrı döndü; write 39 registration/input-output schema/callback/permission kaynak kontrolü, çalıştırılmadı. Execute/permission callback eksik 0. UI_ONLY 29 / UI_AI 42 (kısmi kapsama); AI_ONLY ayrı utility 2.
- Salt okunur etiket önemli sınır: program-integrity-checks, Kommo ensure_profile UPDATE yoluna ulaşabilir (#115). İlk metadata bazlı çağrı bunu saptamadan önce çalıştığı için yerel profil/source metadata güncellenmiş olabilir. Sonraki render testleri SQL mutation öncesinde bloke etti. Harici CRM, ödeme/sipariş/bilet veya program durum write işlemi çağrılmadı.
- Kök neden #114: MMC_Sales_Service::summary içindeki filtresiz SUM(gross_amount), failed nominal tutarları brüt satışa katıyor. Event #9: 25 processing / 28.250 TL + 4 failed / 5.000 TL nominal → API gross 33.250 TL; net 28.250 TL ve capacity 73 doğru. Bu raporlama/alan semantiği kusuru; ödeme/kapasite arızası kanıtı değil.
- Kök neden #115: integrity checks → status_bridge_preview → ensure_profile → profile update. mmc-kommo/mmc-integrity/mmc-finance/mdg-kommo-active-events-source ekranlarının GET yerel write yan etkileri güvenli audit tarafından engellendi; native PHP fatal sayılmadı. DESC metadata false positive diagnostic'te düzeltildi; system/snippet ekranı ardından başarılı.
- Operasyonel uyarılar: Program #9 hedef ilçe/okul yok, aday eski okul programı #7 bridge kaydedilmemiş; eşleşme/işletme hedefi doğrulanmadan repair yok. Kommo AI refresh_needed; downstream retrieval tazeliği kanıtlanmadı (#82).
- Değiştirilen GitHub dosyaları: docs/MMC_MODULE_AUDIT.md; docs/MMC_MODULE_AUDIT_20261004.json; docs/STAGE5_SOURCE_VERIFICATION_20261004.json; docs/CODEX_PROJECT_STATE.md; docs/diagnostics/stage5-mmc-audit.php. PR #111 güncel audit/devir açıklaması. #114 ve #115 açıldı.
- WordPress karşılığı: yalnız geçici #119 backup→GitHub diagnostic→PHP CI→kontrollü aktivasyon→read-only probes→özgün kod restore/pasif→GET readback. #119 code_error yok, byte equality doğrulandı; #117 pasif. Kalıcı production snippet/plugin kodu değişmedi/deploy yok.
- Test: PR #113 CI runları 37177289680 / 37177289672 / 37177289673 başarılı. Son diagnostic PHP syntax 37180627884 başarılı. 71 canlı callback denemesi, 59 ability read çağrısı, 98 callback metadata kontrolü, 43 MMC ve 43 aktif snippet hash karşılaştırması, snippet 120/code_error 0 ve health 16/16 readback. Yerel JSON kapsam/sayım/callback/secret pattern doğrulaması rapor kaydından önce uygulanır.
- Aile regression seviyesi: family_2_2 = 2 yetişkin + 2 çocuk / capacity_units 4 standardı değiştirilmedi; source/hash/definition korundu. Gerçek paid order veya capacity mutation testi yapılmadı.
- Deployment bekleyenler: yalnız bilinen family 1.1.3 / AI 0.7.0; bu görev deploy yetkisi olarak kullanılmaz.
- Açık issue: #115, #114, #112 (ertelendi/nonblocking), #105, #87, #82, #57.
- Öncelik: P0 yeni bulgu yok; P1 #114 gross semantiği; P2 doğrulanmış okul/saha setup/bridge ihtiyacı; P3 #115 read-only purity ve #82 retrieval; P4 GET side effects/AI coverage/visual proof. Sıradaki en yüksek teknik iş #114 için ayrı sales-reporting patch PR'ı.
- Rollback: main merge revert yalnız repo kaynağını geri alır; otomatik live deployment yok. Diagnostic #119 zaten restore/pasif. Legacy 43 order/104 instance değiştirilmedi; cleanup/PayTR testlerine dönülmedi.
- Rapor ayrıntıları: docs/MMC_MODULE_AUDIT.md ve JSON. Güncel Drive çalışma kayıtları milano sirki / 00 - Site Kod ve Codex Yönetimi altında; aynı project-state dosya ID'si korunur.

---

## Tarihsel çalışma kayıtları (en son bölüm yukarıdadır)

## Tarihsel devir: 3 Ekim 2026 (UTC)

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


## 2026-10-04 — Aşama 3: sabit cohort verifier audit + izole native contract

- Orders audited: **43** (önceki 18 gün/PayTR/boş paid-date ve transaction araştırması tekrar edilmedi).
- Published instances: **104**. Exclusive classification: **Record only 0; QR generated 104; Check-in capable 0; Checked-in 0; Unknown 0**.
- QR generated = gerçek ticket_code mevcut + native resolver aynı instance'a çözüyor; QR bitmap oluşturma/gönderim doğrulanmadı. Native check-in geçmişi ve Pass sayısı 0; fiziksel kapı girişinin bağımsız kanıtı değildir.
- Canlı doğrulama 2026-10-04T01:30:57Z: her 104 instance için native resolver + gerçek registered paid filter read-only çalıştırıldı; tamamı false, check-in core error 11 yoluna gider. Gerçek check-in fonksiyonu/attendance writer çağrılmadı. Tüm instance/order/item/event eşleşmeleri ve 104 güncel variation→session mapping kontrol edildi; güncel mapping geçmiş programın otoritesi olarak kullanılmadı.
- Ayrım: Woo order exists true; recorded payment absent; Tickera code exists true; verifier-valid false. MMC ledger 56 kayıt, net qty/units/amount 0.
- Root cause: native WooCommerce/Tickera Bridge1.7.7 checkout aşamasında payment guard olmadan publish + ticket_code oluşturuyor. Hook woocommerce_new_order_item → create_order_ticket_instances priority11; Store API checkout update → create_order_ticket_instances_from_store_api priority10. 71 instance order ile aynı saniye,31 +1s,2 +26s; izole native reproduction aynı pre-payment üretimi doğruladı. Tarihsel plugin/snippet aktivasyon tarihine özel neden kanıtı yok.
- Verifier: Tickera3.6.0.6 TC_Checkin_API::ticket_checkin → tickera_order_is_paid alias tc_order_is_paid → TC_WooCommerce_Bridge::tc_modify_order_is_paid priority10; processing/completed kabul, failed/pending/cancelled ret. Paid-date/transaction bağımsız kontrolü yok; PayTR finansal callback doğrulaması kapsamı dışı. Pre-check-in override aliaslarının tamamı boş. Error11 attendance write öncesinde. Checkinera aynı native API yoluna proxy.
- Reproduction: gerçek native method excerpts + sentetik WordPress/Woo storage; PHP8.4 CI **25 assertion PASS**, warning/fatal yok. pending/failed/cancelled early code/verifier reject, sentetik paid/processing accept, completed/processing cycles ve tekrar MDG callback duplicate0, mevcut sentetik paid ticket regression yok. family_2_2 capacity_units=4 ve 2+2 component davranışı korunuyor.
- Test commit **344b9452acefce24a3759e64e3a3fadbe9821675**; contract run **37172718605 PASS**, Changed PHP Syntax **37172718564 PASS**. İlk iki fixture denemesindeki eksik current-user stub/global fixture storage çakışması test harness düzeltmeleriyle giderildi; production kaynak hatası değildi.
- Gerçek WordPress staging checkout / PayTR başarısız callback / sandbox başarılı ödeme reproduction yapılmadı. PayTR canlı test=no; **successful-payment reproduction unavailable without real charge**. T0→T1 erken instance izole fixture timeline'da; gateway T2/T3 yapılmadı, paid T4 sentetik, code T1'de zaten mevcut. Gerçek ödeme/tahsilat yok.
- Risk **MEDIUM**, erken kod üretimi/veri bütünlüğü. 104 kullanılabilir/ücretsiz bilet veya yetkisiz giriş sonucu çıkarılmadı.
- Patch needed: **No verifier security patch justified by current evidence**. Erken instance üretimini ödeme sonrasına erteleme veri bütünlüğü işi açık; full WP staging + attendee/cart metadata ve checkout retry coverage olmadan patch yapılmadı. Status-only gate tüm olası ödeme durumlarında provider-confirmed payment garantisi olarak sunulmadı.
- Patch branch/commit/PR: **none**. Audit branch **codex/current-system-audit-20261003**, draft **PR111**. Issue112 kanıta göre güncellendi; erken üretim ve PayTR/staging takip işi nedeniyle açık.
- GitHub dosyaları: docs/code-snippets/stage2-runtime-audit.php; tests/tickera-payment/native-excerpts.json; tests/tickera-payment/reproduction.php; .github/workflows/tickera-payment-contract.yml; docs/TICKERA_PAYMENT_AUDIT_20261004.md; docs/CODEX_PROJECT_STATE.md.
- WordPress karşılığı: geçici read-only diagnostic snippet **119**, final GET active=false/code_error=null; **117 pasif**. Diagnostic kaynak GitHub ile arşivlendi. Son SQL envanter119/42; code_error SQL kolonu yok, final119 REST readback temiz. MMC1.3.47/16health önceki doğrulanmış baseline, bu aşamada production patch yok.
- Deployment status: **NOT DEPLOYED**. Legacy43 order/104instance hiçbir değişiklik/silme/check-in yapılmadı. Rollback: diagnostic119/117 pasif tut; audit/test/docs revert edilebilir, üretim data rollback gerekmez.
- Sonraki önerilen iş: tam izole WP/Tickera/PayTR test ortamı oluşturup gerçek sandbox callback + payment-first deferral tasarımını attendee alanları ve checkout retry idempotency ile sınamak. Tam checkout/payment/ticket zinciri hâlâ doğrulanmadı; PR111 draft kalır.
- Detay kanıtı: [TICKERA_PAYMENT_AUDIT_20261004.md](TICKERA_PAYMENT_AUDIT_20261004.md). Bu kayıt commit SHA'sı GitHub dosya history'sinde; test ve evidence SHA'ları yukarıda, self-referential commit SHA yazılmadı.


## Kalıcı çalışma kayıt kuralı — yönetici talimatı 2026-10-04

Her önemli çalışma sonunda yapılan iş, teknik notlar, kanıtlar, test sonuçları, açık riskler ve sonraki adım **hem GitHub hem Google Drive** üzerinde kaydedilir. Bu, sonraki Codex çalışmalarında da uygulanacak proje standardıdır.

- GitHub: ilgili codex/... branch ve PR/issue; docs/CODEX_PROJECT_STATE.md güncellenir. Gerekli ayrıntılı rapor repo docs/ altında tutulur.
- Drive hedefi: milano sirki → **00 - Site Kod ve Codex Yönetimi**; doğrulanmış klasör ID **1Ps1MUROQO9zyDbisrAoxvwan4u15HLGj**.
- Drive klasörü: https://drive.google.com/drive/folders/1Ps1MUROQO9zyDbisrAoxvwan4u15HLGj
- Her çalışma için tarihli rapor ve gerektiğinde devir dosyası snapshot'ı eklenir; GitHub branch/commit/PR/issue ve test bağlantıları yazılır. Mevcut dosya güncellenecekse önce kimliği doğrulanır; aynı çalışmanın kopyaları gereksiz çoğaltılmaz.
- İki taraftaki yazımlar ayrı read-back ile doğrulanır; erişim hatası varsa eksik taraf açıkça raporlanır ve kayıt işi tamamlandı denmez.
- Secret/token/password, müşteri kimliği, ham bilet/QR kodu veya hassas ödeme verisi kayda eklenmez.
- Drive tarihi belge niteliğindedir; canlı doğrulanmış program/veri ve güncel GitHub kaynak önceliği değişmez.
- Aşama3 raporu Drive'a eklendi: https://drive.google.com/file/d/1rpLuPmZ6HuIo-bzjw_pabiiEnlLPDt1I/view?usp=drivesdk
- Kaynak rapor: docs/TICKERA_PAYMENT_AUDIT_20261004.md; audit/state kaynak commit d124690c6550057aeb26c0d54520474700a04731, draft PR111, issue112. Bu kural ve güncel state snapshot'ı aynı çalışma kapsamında GitHub/Drive'a kaydedilir.


## 2026-10-04 — Aşama 4: canlı kaynak sahipliği uzlaştırması

**Yönetici kararı:** PayTR sandbox/tam checkout testleri ertelendi. Aşama3 yeterli kabul edildi; Issue112 açık tarihsel takip, production blocker değildir. Bu testlere dönülmedi. Önceki kurulum/test dönemi bulgusu yeni geliştirmeleri bloke etmez.

- Main son SHA: **6e21a5e4217c085d6668f6932a874f846845a7de** (değişmedi; PR113 henüz merge edilmedi).
- Production MMC: **1.3.47**; fresh **43/43 MMC dosyası main ile exact Git blob MATCH**. Region/Sales/load order/ledger/dashboard/bridge kaynağında patch yok.
- Code Snippets: **120 toplam /43 aktif /0 code_error**; yeni #120 fatura açıklama helper dahil.
- Mevcut main'e göre aktif snippet sayıları: **MATCH1, WHITESPACE_ONLY5, LIVE_AHEAD4, GITHUB_AHEAD0, NO_REPO_SOURCE33, OBSOLETE0**.
- PR113 uzlaştırılmış branch'e göre aktif snippet sayıları: **MATCH43, WHITESPACE_ONLY0, LIVE_AHEAD0, GITHUB_AHEAD0, NO_REPO_SOURCE0, OBSOLETE0**. Main'e merge sonrası geçerli olur; şu anda main'in temizlendiği iddia edilmez.
- Özel plugin kapsamı: **18 plugin /153 dosya hash'i**. 1 okulCSV veri seti kod kapsamı dışı, port edilmedi.
- 152 plugin code/readme/asset için main: **MATCH127, WHITESPACE_ONLY2, LIVE_AHEAD2, GITHUB_AHEAD2, NO_REPO_SOURCE19**.
- PR113 aynı kapsam: **MATCH148, WHITESPACE_ONLY2, LIVE_AHEAD0, GITHUB_AHEAD2, NO_REPO_SOURCE0**. 1 live aile readme'sinin owner'ı tarihsel sürüm arşividir; ileri canonical versiyon dokümanı diye sunulmaz.
- Birleşik195 kaynak kaydı PR113: **MATCH191 / WHITESPACE_ONLY2 / LIVE_AHEAD0 / GITHUB_AHEAD2 / NO_REPO_SOURCE0**. 77 pasif snippet ayrı; 28 geçici tanı/installer OBSOLETE, diğer49 pasif tarihsel. Bunlar production drift sayısına katılmaz.
- Yapılan iş/kök neden: main'de çoğu kalıcı aktif snippet ile9 legacy plugin paketinin exact source owner'ı yoktu; OkulTanıtım1.7.9 live ahead idi. 43 exact snippet body docs/code-snippets/production altında kaydedildi; 18 missing plugin source/readme +2 canlı okuldosyası canonical path'lerine port edildi. Eski snapshotlar çalışan canlı kaynak yerine deploy edilmedi.
- Okul live1.7.9: MMC program/saha scope ve approved/manual venue doğrulaması mevcut ve korunuyor. main1.7.4 ile overwrite edilmedi.
- Main-ahead AI0.7.0 (legacy migration write module) ve aile1.1.3 (MMC fiyat lookup) **korundu/deploy edilmedi**. Canlı0.6.1/1.1.2 exact version kaynakları tarihsel arşivde. family_2_2 =2yetişkin+2çocuk, capacity_units4 değişmedi.
- Kommo canonical active owner **snippet110 MMC V2**. LegacyV1 docs/code-snippets/mdg-kommo-active-events-unified-source.php/#70 pasif referans. Fresh V2 readonly preview9program; downstream KommoAI freshness/retrieval bu aşamada yeniden test/değiştirilmedi.
- PR107:22file → **ALREADY_MAIN1 /NEEDS_PORT3 /OBSOLETE2 /HISTORICAL16**; #15/#30/#103 current bodies PR113'e port edildi, Sales current main'de. Directmerge yok; draft tarihsel arşiv olarak bırakıldı.
- CI kök nedeni: madagaskar-plugins-ci.yml'de literal backslash-n üç grep'i birleştiriyor ve eski1.7.4/1.3.30 etiketleri kalmıştı. Gerçek satır sonu ve güncel school1.7.9/MMC1.3.47 paket etiketleriyle düzeltildi; production PHP hatası değildi.
- Tests: source commit **a2ace24da441e7f2bb787ed4bc788f6b95ebeac5** üzerinde **66hash PASS /58PHP syntax PASS**, diagnostics-in-production0; archive run37176867325, ChangedPHP run37176867285, PluginsCI run37176867272 başarılı. Source kayıt işlemi için safe read-only validation; gerçek para/order/ticket/Kommo write yok.
- Son source branch/commit/PR: **codex/live-main-reconciliation-20261004 /67b38ae173ce21c9962fe4067478831d71f56e1b /PR113**. Audit/state branch **codex/current-system-audit-20261003 /PR111**.
- WordPress: kalıcı snippet/plugin kaynak değişikliği **yok**. Diagnostic **119** geçici salt-okunur reader için syntax sonrası kullanıldı; özgün kod byte-exact geri yüklendi, active=false/code_error=null. **117pasif**. Production deployment **yok**.
- GitHub dosyaları: docs/code-snippets/production/43body+README;20 canonical plugin source/readme;3 historical version captures; docs/STAGE4_SOURCE_INVENTORY_20261004.json; docs/STAGE4_PR107_DISPOSITION_20261004.json; docs/STAGE4_ACTIVE_SNIPPETS_20261004.csv; docs/STAGE4_RECONCILIATION_20261004.md; tests/validate-production-source-archive.py; production-source-archive workflow; pluginCI; audit-only docs/diagnostics/stage4-source-reader.php.
- Açık riskler: PR113 portu merge edilene kadar main source drift sayıları devam eder. 2 ileri plugin bootstrap sürümü ayrı deploymentreview bekler. school-bridge-status ability lookup404, sonraki MMC runtime function/registration denetiminde bakılacak; doğrudan fatal/kapalıplugin sonucu değildir. Source kayıtları fonksiyonel tam UI regression iddiası değildir.
- Deployment bekleyenler: aile1.1.3 MMC family fiyat kaynağı; AI0.7.0 write migration modülü (otomatik deploy/activate yok).
- Açık issue'lar: **112,105,87,82,57**;112 non-blocking/deferred.
- Sonraki öncelik: **MMC menü ve fonksiyon/ability denetimi** → WC/MMC mutabakatı → Kommo dinamik kaynak → çekiliş → operasyon/görev → dashboard → raporlama → teknikborç. Bu aşamada bu modüllerde geliştirmeye başlanmadı.
- Rollback: GitHub port/audit commitlerini revert; WordPress unchanged. Diagnostic119/117 pasif; finans/customer data rollback gerekmez.
- Drive raporu: https://drive.google.com/file/d/11fgcM2PqYfz1BJX75V0cfGvt3zhRxqDj/view?usp=drivesdk
- Drive envanteri: https://drive.google.com/file/d/1Pi6Kbkv8xb4X4BLBA8C_tI593lknaCdp/view?usp=drivesdk
- Devir dosyası snapshot'ı aynı doğrulanmış Drive klasörüne kaydedilir; final link PR111/113 açıklamalarında. Her önemli iş için GitHub+Drive kayıt standardı geçerli.

### Aşama4 final read-back — eşzamanlı canlı değişiklik
Son aktif hash kontrolünde snippet120 başka çalışma tarafından1.0.0→1.0.4 güncellendi. Yeni isim/body/hash tekrar alınıp PR113'e işlendi; diğer42 aktif hash değişmedi. WordPress overwrite yapılmadı. Sourcecommit 67b38ae173ce21c9962fe4067478831d71f56e1b. Bu değişiklik için static archive/PHP CI tekrar doğrulanır; PayTR/sandbox çalışması başlatılmaz.

### Aşama4 kapanış — doğrulanmış son durum
- Son source SHA **67b38ae173ce21c9962fe4067478831d71f56e1b** için ArchiveCI **37177289672 PASS (66hash/58PHP)**, ChangedPHP **37177289673 PASS**, PluginsCI **37177289680 PASS**.
- Son salt-okunur SQL read-back: **43/43 aktif snippet hash'i latest inventory ile MATCH**; snippet120 **1.0.4** dahil.
- **PR111 ve PR113 ready for review (draft=false)**. PR107 draft tarihsel arşiv; hiçbir PR merge edilmedi. Main SHA hâlâ6e21a5e4217c085d6668f6932a874f846845a7de.
- Uzlaştırma/port hazırlığı tamamlandı; main source-owner uyumu PR113 merge sonrasında geçerli olacak. WordPress production deployment yapılmadı;119 eski koduyla pasif/temiz.
- Drive'da rapor/envanter/devir snapshot'ları aynı dosya ID'leri korunarak güncellendi ve metadata read-back ile doğrulandı: devir https://drive.google.com/file/d/1cCipf_lqxDSfMQs-0MjVPogkrD5bIKNH/view?usp=drivesdk
- Sonraki teknik iş **MMC menü/fonksiyon/ability denetimi**; PayTR/Tickera sandbox ertelenmiş,112 non-blocking.

> **AÇILIŞ SONRASI GÜNCEL QA (13.10.2026):** Bağlı WordPress aracıyla gerçek ana/alt sayfa HTML çıktıları alındı, mobil Google PageSpeed/Lighthouse skorları **81 Performans / 95 Erişilebilirlik / 100 İyi Uygulamalar / 100 SEO**. Artık gerçek sayfa çıktısı doğrulanmış olmakla birlikte tüm istemci ağları için kesintisiz HTTP garantisi verilmez. **Önemli:** Complianz çerez banner'ı yok (banner_version 0, optout/us) ve Jetpack GA4 yükleme kodu izin altyapısı kurulana dek **geçici olarak devre dışı** bırakıldı, aynı kod/kimlik korundu. Jetpack Stats izleme/hukuki temeli ayrı kontroldür. Detay: [MILANO_PUBLIC_QA_COOKIE_CONSENT_20261013.md](MILANO_PUBLIC_QA_COOKIE_CONSENT_20261013.md).

# Uluslararası Milano Sirki — tanıtım sitesi yayına alma kaydı

**Tarih:** 13 Ekim 2026 · **Canlı site:** https://milanosirki.com · **WordPress.com blog ID:** 257916618.

## Sonuç: Tanıtım sitesi yayında, bilet satışı kapalı

WordPress.com `manage-site.set-visibility` işleminde `previous_state=coming_soon`, `new_state=public`, `success=true` döndü. Ayrı `manage-site.status` salt-okunur teyidi: `launch_status=launched`, `visibility=public`. `wpcom_user_sites`: `is_site_launched=true`, `is_coming_soon=false`; `settings.get`: `blog_public=1`, `show_on_front=page`, `page_on_front=5`.

Önceki `manage-site.launch` işlemi `already launched` yanıtı verdiğinden **tek başına** görünürlüğü açmamıştı; asıl başarılı görünürlük değişikliği `manage-site.set-visibility(visibility=public)` ile yapıldı.

**Alan adı:** `milanosirki.com` primary, WordPress.com nameserver'ları kullanılıyor, WordPress.com'a işaret ediyor, SSL durumu **active** (WordPress.com `domains.get`).

## Yayınlanan sayfalar ve yapılan değişiklikler

- Önceki kontrol ve bu çalışma arasında, altı temel Milano tanıtım sayfasının zaten `publish` olduğu doğrulandı: ana sayfa `id=5` `/`, şehirler `id=6` `/sehirler/`, iletişim `id=14` `/iletisim/`, gösterilerimiz `id=21` `/gosterilerimiz/`, sıkça sorulan sorular `id=22` `/sikca-sorulan-sorular/`, kurumsal `id=23` `/kurumsal-organizasyonlar/`. Hakkımızda `id=1` da zaten yayındaydı. Taslakları yeniden üretmek yerine mevcut sayfalar korundu.
- Statik ana sayfa önceden doğru ayarlanmıştı (`page_on_front=5`); menü `navigation id=3`, `assembler//header` içinde mevcut ve ana sayfa, gösteriler, şehirler, hakkında, sık sorulan sorular, kurumsal ve iletişim bağlantıları var. Ana sayfadaki `#gosteri-takvimi` bağlantısının gerçek `id="gosteri-takvimi"` hedefine sahip olduğu kaynak içerikle doğrulandı.
- Bu çalışmada KVKK `id=33` `/kvkk-aydinlatma-metni/`, Gizlilik `id=34` `/gizlilik-politikasi/` ve Çerez `id=35` `/cerez-politikasi/` sayfaları **publish** yapıldı; çıktılar ayrı ayrı doğrulandı.
- `assembler//footer` tasarımının mevcut içeriği korunarak **üç hukuki link** eklendi; güncelleme sonrası kaynak tekrar okundu ve üç bağlantı ile **"Bilet satışları bu sitede henüz aktif değildir"** açıklaması doğrulandı.
- Mesafeli Satış Sözleşmesi `id=36`, Ön Bilgilendirme Formu `id=37`, İptal/İade ve Bilet Teslimatı `id=38` **draft** bırakıldı: Milano checkout henüz yok ve müşteri işlemi açılmadı. Canlı sitede 10 yayımlanmış sayfa, 3 taslak sayfa var.

**Kaynak tutarlılığı:** Milano hukuki taslaklarındaki Dünya Organizasyon ticaret unvanı, ticaret sicil/VKN/MERSİS ve adres alanları mevcut Madagaskar kurumsal bilgilendirme sayfalarıyla karşılaştırıldı. Bu bir resmî sicil ya da hukukçu onayı değildir; KVKK, çerez ve gelecekteki satış metinleri işletme/hukuk yetkililerince ayrıca incelenmeli.

## Satış güvenliği

- Milano WordPress.com `plugin.list` güncel kontrolü: WooCommerce, PayTR, Tickera eklentileri **kurulu değil**; bu sitede sepet/checkout ile tahsilat fonksiyonu aktif değil.
- `Milano Sirki - Ortak Etkinlikler (Taslak)` 0.1.0 aktif, salt-okunur; GitHub PR #235 kodu canlı checkout'a dağıtılmadı. `milano-sales-freeze.php` hard-closed staging-only taslak, **canlı Milano plugin'i olarak YÜKLENMEDİ**.
- **Bir etkinlik = tek marka = tek site** sözleşmesi yürürlükte. Madagaskar'a ait etkinlikler Milano'da satışa sunulmadı, Madagaskar üretim satışı/QR/PayTR değiştirilmedi.
- Güncel WP eklenti sayımında `Complianz | GDPR/CCPA Cookie Consent` sürüm **7.5.5**, **active** olarak göründü. Kurulu ve etkin olması çerez taramasının, widget görünümünün veya tercih/opt-in davranışının doğru yapılandırıldığının kanıtı değildir.

## Sonraki teknik ve hukukî kontrol kapıları

1. **Çerez onayı:** Complianz ilk kurulum sihirbazı ve gerçek çerez taraması/tercih paneli, üçüncü taraf Jetpack/analytics ve script blocking doğrulanmalı. Ürün/izleme çerezleri varsa gerekli rıza alınmadan yüklenmemeli; ayrıntılar Çerez Politikası'na uygun güncellenmeli.
2. **Bağımsız HTTP/SEO smoke test:** Web erişim aracı `https://milanosirki.com/` ve alt yollar için erişim sağlamadı; `WordPress.com` platform durumu **public** olduğunu güvenilir biçimde doğruladı, fakat bu testte bağımsız internet tarayıcısından **HTTP 200 yanıtı ve mobil görsel görünüm teyit edilemedi**. DNS yayılımı/geçici erişim veya aracın kısıtları dışlanmadı. Manuel cihaz ve farklı internet bağlantısı kontrolü önerilir.
3. **Gerçek bilet satışı ayrı faz:** Tek marka merkezi yetkilendirme, Woo/PayTR domain/merchant, callback idempotency ve per-order atomik lock, MDG seat hold, Tickera gerçek 2+2 QR, giriş doğrulama, refund/crash/retry ve staging concurrency testleri tamamlanmadan checkout açılmayacak. `wc-failed` aile siparişlerine bağlı 54 yayımlanmış Tickera kaydı read-only denetim konusu olarak kalır; kullanıcı bileti iptal edilmedi.
4. **Gözlemleme:** WordPress `monitor.status` sorgusunda Jetpack Monitor `monitor_active=false`; istenirse kullanıcı onayıyla aktif edilebilir. Bu yayın işlemi Monitor'u açmadı.

## Kayıt bağlantıları

- GitHub PR #235: https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/235 (taslak, merge edilmedi)
- Önceki hazırlık belgesi: `docs/MILANO_LAUNCH_READINESS_20261013.md`.
- Google Drive Milano çalışma günlüğü: https://docs.google.com/document/d/1GSV-_niF39NTkB45--sqodofxDVH25S7XeKxefzUa4Q/edit

**Açılış kapsamı:** yalnız Milano tanıtım sitesi. Çevrimiçi bilet satışı hâlâ kapalı.


## Son kontrol — görünürlük değişikliği sonrası geri dönüş ve yeniden açma

WordPress.com ilk başarılı `public` yanıtını ve `blog_public=1` değerini vermesine rağmen bir sonraki kontrolde `coming_soon` / `blog_public=0` görüldü. `activity.get` salt-okunur kayıtları, **2026-10-13T03:28:46.417Z** anında `blog_public` değerinin **1'den 0'a** `openai-mcp` istemcisinden bağlı yönetici hesabıyla değiştirildiğini gösterdi. Bu işlem kim tarafından/niçin yapıldı kesinleştirilmedi; otomatik bir çerez ayarı değişikliği olduğu varsayılmamalıdır.

Kullanıcının yayın yönündeki talimatı korunarak `manage-site.set-visibility(public)` **ikinci kez** yapıldı ve `previous_state=coming_soon`, `new_state=public`, `success=true` döndü. Hemen sonraki üç bağımsız WordPress.com API okuması: `manage-site.status visibility=public launch_status=launched`, `wpcom_user_sites is_site_launched=true is_coming_soon=false`, `settings.get blog_public=1 page_on_front=5`.

**Önemli:** Görünürlüğü değiştirebilen başka yönetici/oturum/otomasyon bu değeri yeniden 0'a çekerse, alan adının tekrar coming-soon olması mümkündür. Bu kayıt **daimi kesintisiz erişilebilirlik garantisi değildir**; yayından sonraki görünürlük değişiklikleri ve cookie-izin uygulaması izlenmelidir. Harici web aracı bu yeni alan adı için sayfa HTML'sini hâlâ bağımsız doğrulayamadı.

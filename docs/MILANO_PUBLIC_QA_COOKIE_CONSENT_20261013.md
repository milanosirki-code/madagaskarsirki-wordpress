# Milano Sirki — Canlı sayfa, mobil Lighthouse ve çerez/GA4 güvenlik kontrolü

**Tarih:** 13 Ekim 2026 | **Site:** https://milanosirki.com | **WordPress.com ID:** 257916618 | **Kapsam:** yalnız Milano tanıtım sitesi. Madagaskar canlı üretim sistemine dokunulmadı.

## 1. Gerçek sayfa çıktıları — GEÇTİ

WPVibe hesabında Milano bağlantısı doğrulandı; WordPress.com sitesi "Atomic", `wpcom_user_sites` / `manage-site.status` kontrolünde `launch_status=launched`, `visibility=public`.

- `WPVibe get_page_html` JavaScript sonrası gerçek ana sayfa HTML'sini başarıyla getirdi: `title`, logo, navigasyon, ana sahne tanıtımı ve `#gosteri-takvimi` bağlantısı mevcut.
- Gerçek `main` sayfa çıktıları: `/`, `/sehirler/`, `/iletisim/`, `/kvkk-aydinlatma-metni/`, `/gizlilik-politikasi/`, `/cerez-politikasi/`; hepsi içerikle döndü, boş blog veya coming-soon sayfası değil.
- Gerçek `nav` çıktısındaki 7 bağlantı: `/`, `/gosterilerimiz/`, `/sehirler/`, `/hakkinda/`, `/sikca-sorulan-sorular/`, `/kurumsal-organizasyonlar/`, `/iletisim/`.
- Gerçek `footer` çıktısında hukuki bağlantılar, doğru WhatsApp numarası ve `Bilet satışları bu sitede henüz aktif değildir` notu mevcut.
- WP.com domain durumu: DNS WordPress.com'a yöneliyor, SSL active. Başka genel web aracının alan adını açamaması, WordPress ve Lighthouse HTML kontrollerinin başarısını geçersiz kılmaz; ancak tüm ağlardan global DNS/HTTP erişimi garantisi vermez.

## 2. Gerçek mobil Lighthouse — TEST SONUCU

WPVibe Google PageSpeed Insights mobil audit:
- **Performance 81 / 100**
- **Accessibility 95 / 100**
- **Best Practices 100 / 100**
- **SEO 100 / 100**
- Lab: LCP 4.0s, CLS 0.069, TBT 20ms, FCP 2.7s, Speed Index 3.6s.
- Öneriler: unused JavaScript ~72 KiB; erişilebilirlikte yetersiz renk kontrastı. Gerçek kullanıcı CrUX trafik örneklemesi yok; lab skorları değişkendir. Aynı turda yeniden audit yapılmadı.

## 3. Çerez/onay sorunu — AÇIK GÜVENLİK VE UYUM ENGELİ

WordPress.com/WPVibe plugin listesi: Complianz 7.5.5 kurulu ve active.

Ancak JavaScript sonrası gerçek HTML:
- `.cmplz-cookiebanner` ve `#cmplz-cookiebanner-container` DOM'da bulunmadı.
- `/complianz/v1/banner` read-only cevabı: `{"consenttype":"optout","region":"us","version":"7.5.5","forceEnableStats":false,"banner_version":0}`.
- Bu site Türkiye hedefli; `us` / `optout` varsayılanına dayanarak çerez onayı/yerel mevzuat uyumu iddia edilmez.
- `/complianz/v1/cookie_data` ve `/complianz/v1/documents` GET cevapları boş listeler.
- `/complianz/v1/manage_consent_html` rıza kategorilerini döndürüyor ama bu tek başına ziyaretçiye onay banner'ı gösterildiği anlamına gelmez.
- Eski sayfa HTML'sinde Jetpack Google Analytics `gtag/js?id=G-DD2ZXC383D` komut dosyası otomatik yükleniyordu ve `gtag('consent', 'default', ...)` veya `analytics_storage` consent etiketi HTML'de yoktu. Önceden tercih doğrulaması yokken doğrudan GA4 yükleme **potansiyel sorun**.
- Jetpack Stats `stats.wp.com/e-202642.js` dosyası mevcut. Jetpack'in resmî teknik açıklamasına göre bu hizmet ziyaretçi IP adresi, user-agent, görüntülenen URL, yönlendirme URL'si, zaman ve dil gibi veri kullanır; olay logları 28 gün tutulabilir. Sitedeki **gerçek çerez/çerez dışı izleme işlevinin rıza ve hukuki dayanak bakımından ayrıca incelenmesi gerekir**; yalnızca GA4'ü kapatmak tüm analitik veri işlemenin durduğu anlamına gelmez. Resmî kaynak: https://jetpack.com/support/jetpack-stats/ . Tam çerez uyumu iddia edilmez.

## 4. CANLI DÜZELTME — Jetpack GA4 geçici kapalı

WordPress REST `GET /jetpack/v4/settings` `jetpack_wga.is_active=true`, `code=G-DD2ZXC383D`, `anonymize_ip=true` gösterdi. Aynı kayıt geri okunarak eski değerler korunup yalnızca `is_active=false` yapmak için `POST /jetpack/v4/settings` çağrısı yapıldı.

**Doğrulama:**
1. Yeniden `GET /jetpack/v4/settings`: `jetpack_wga.is_active=false`, ölçüm kimliği **değişmeden korunuyor**, `anonymize_ip=true`.
2. Yeniden `get_page_html` ana sayfa: `googletagmanager.com/gtag/js` ve `Jetpack Google Analytics` etiketi **artık HTML'de bulunmuyor**. Jetpack Stats ise var.
3. WordPress.com `manage-site.status` yayımlanmış/visibility public kalmış.

**Etki:** Milano Google Analytics sayfa ölçümleri duraklatıldı. WordPress site istatistikleri/Jetpack Stats eklentisi ve Madagaskar Google Analytics değiştirilmedi. Bu değişiklik gerektiğinde aynı `jetpack_wga` alanının `is_active=true` yapılmasıyla GERİ ALINABİLİR ama rıza mekanizması doğrulanmadan otomatik açılmamalı.

## 5. CANLI DÜZELTME — Çerez Politikası gerçeğe uyarlandı

WordPress.com `page-sections.list / page-sections.replace` iyimser kilitleme ile **sayfa 35** üzerinde yalnız iki `core/paragraph` bölümü değiştirildi:
- `Analitik çerezler` bölümü mevcut GA4'ün geçici devre dışı olduğunu ve Jetpack stats'ın incelendiğini açıklar.
- `Çerez tercihleri` bölümü panelin ve Türkiye rıza yapılandırmasının henüz doğrulanmadığını belirtir.

Sayfa yayımlı kalmaya devam etti; gerçek `/cerez-politikasi/` HTML metni yeniden okundu ve yeni açıklamalar doğrulandı.

## 6. Net kalan adımlar — panel henüz hazır DEĞİL

1. WordPress yönetimi → **Complianz → Sihirbaz** üzerinden Türkiye ve varsa diğer hedef bölgelere uygun **önceden rıza (opt-in)** gerektiren istatistik/pazarlama kategorilerini belirle, kurulumu bitir ve banner'ı etkinleştir. Bu işlem için eldeki bağlı REST eylemleri yalnız `complianz/v1/banner` okumaya izin veriyor; **plugin ayarını güvenli şekilde değiştiren ve wizard'ı doğrulayan bir API tespit edilmedi**, yanlış API parametresiyle müdahale yapılmadı.
2. Mevcut `Jetpack Stats`, üçüncü taraf JS, analitik, güvenlik, CDN, fontlar ve pazarlama etiketlerinin gerçekte hangi çerez/çerez benzeri teknolojileri kullandığını özel pencerede kategori bazında denetle.
3. Kabul/ret/özelleştir/tercihleri yeniden aç özelliklerini önceden cookie veya local-storage kaydı bulunmayan tarayıcıda test et; reddetme durumunda ilgili analitik script/çerez yüklenmediğini doğrula.
4. Statik çerez politikası ile gerçek çerez envanteri, süreler ve sağlayıcıları uyumlu hale getir; yetkili hukuk/operasyon onayı.
5. Ancak ondan sonra Jetpack GA4'ü yeniden etkinleştir. **Milano WooCommerce/PayTR/Tickera ve satış kilidi hâlâ ayrı yayın bloğudur**.
6. Lighthouse düşük renk kontrastı öğesini somut CSS/DOM kaynağı üzerinden tespit et ve ölçülebilir tasarım düzeltmesi yap; gereksiz JS yükünün kaynağını belirle.

İlgili dokümanlar:
- KVKK resmî çerez rehberi: https://www.kvkk.gov.tr/Icerik/7353/Cerez-Uygulamalari-Hakkinda-Rehber
- KVKK Kurul kararı çerez opt-in: https://www.kvkk.gov.tr/Icerik/7595/2022-1358
- Complianz banner destek: https://complianz.io/help/wordpress/configuration/cookie-banner-does-not-appear/
- Complianz istatistik sihirbazı: https://complianz.io/help/wordpress/configuration/statistics-implementation/
- GitHub PR #235: https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/235

**Durum:** Milano bilgi sitesi yayında, online satış kapalı, Jetpack GA4 izleme geçici kapalı; ziyaretçi çerez rızası paneli henüz tamamlanmadı.

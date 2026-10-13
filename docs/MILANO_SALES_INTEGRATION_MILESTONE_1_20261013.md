# Milano Sirki — Satış entegrasyonu / Aşama 1: Kaynak marka sahipliği ve canlı envanter

**Tarih:** 13 Ekim 2026. **Durum:** Geliştirme/test işlemleri başlatıldı; **canlı Milano bilet satışı AÇILMADI**.
**GitHub:** PR #235 (open, draft), `feature/milano-exclusive-brand-routing-20261012`.

## Kesin karar

Milano ve Madagaskar ayrı marka ve ayrı müşteri sitesidir; bir fiziksel etkinlik tek markaya aittir. **Milano'dan satılan bir etkinlik Madagaskar'da satılmamalıdır**. MMC/MDG/Woo/Tickera/PayTR arka ofisi uygun kontrol/güvenceyle ortak kullanılabilir; marka domain, müşteri iletişimi, ödeme/QR/e-bilet ve mutabakat alanları üzerinde sahiplik tekil olmalıdır.

## A. Gerçek sistem envanteri — salt okunur canlı test

- Milano WordPress.com site ID `257916618`, `https://milanosirki.com`: `launched/public`, Assembler tema. **WooCommerce, PayTR, Tickera yok**. `milano-shared-events` 0.1.0 salt okunur önizleme (GitHub consumer v0.2.1 canlıya dağıtılmadı). Google Analytics, önceki çerez sorununa karşı güvenli ara çözüm olarak kapalıdır.
- Madagaskar WordPress.com site ID `255726534`, `https://madagaskarsirki.com`: **WooCommerce 11.2.1**, **PayTR iframe 3.1.2**, **Tickera 3.6.0.7** ve **Tickera Bridge for WooCommerce 1.7.8**, **Madagaskar Management Center 1.3.49**, **Madagaskar Aile Paketi 2+2 1.1.3**, MDG 3.6.3 canlı ve aktiftir.
- 13 Ekim 2026 WPVibe `db query` salt okunur gerçek MMC verisi: `wp_mmc_programs GROUP BY brand` = **13 Madagaskar Sirki / 0 Milano Sirki**. `wp_mmc_mdg_event_bridge` içinde **12 aktif köprü / 12 ayrı MDG event**. Aynı MDG event ID'sine birden fazla aktif MMC programı bağlanmış kayıt bulunmadı. Aktif köprülerin hepsi Madagaskar programına bağlı. **Henüz Milano'ya ait kanonik etkinlik yok; mevcut Madagaskar etkinlikleri otomatik Milano sayılmayacaktır.**
- Madagaskar canlı `GET /wp-json/dunya-milano/v1/sessions` salt okunur denemesi **HTTP 503**, `dmsf_not_configured`, `Feed unavailable`. Bu, canlı kaynak plugin v0.1.0'ın sunucu tokenı olmadan kapalı olduğunu doğrular; **en azından şu an Milano ile merkezi event verisi aktarımı başlamamıştır**. Secret/token kaynağını bulmak için bruteforce, iframe, browser veya repo notu kullanılmadı.

## B. Bulunan kritik kaynak kodu riski ve uygulanan DÜZELTME

GitHub kaynak feed v0.2.0 içindeki `dmsf_event_owner_brand()` önce `MMC_MDG_Bridge_Service::program_for_mdg_event(event_id)` çağırıyordu. Bu yardımcı eski MMC/MDG uyumluluğu için **ilk aktif eşlemeyi seçebilen ve eski Woo eşleşmesine fallback yapan** bir fonksiyondur; **kesin satış markası belirleyemez**.

**Aşama 1 değişikliği — sadece GitHub draft:**
- `integrations/milano/dunya-milano-session-feed/dunya-milano-session-feed.php`: marka yalnız `MMC_MDG_Bridge_Service::table()` içindeki `is_active=1`, `mdg_event_id` için **tam bir** kayıttan okunur; `SELECT program_id ... LIMIT 2` ikinci eşleşmeyi algılar. Tablo adı allowlist regex; eksik DB, program, marka, hatalı/çoklu bridge veya yetkisiz değer -> `null`, Milano feed'de görünmez.
- `dunya_milano_canonical_event_owner` WordPress filtresi ek yetkilendirme kaynağı DEĞİLDİR: doğrulanmış merkezi değeri **ancak veto edebilir**, yanlış/belirsiz markayı Milano'ya çeviremez.
- `integrations/milano/tests/feed-contract.php`: eksik / yinelenmiş / yetim bridge, legacy fallback çağrılmaması, bozuk tablo/DB, sahte filtre promosyonu ve çapraz marka veto senaryoları eklendi.
- `.github/workflows/milano-feed-contract.yml` 30 senaryolu feed test dizisine güncellendi.
- İlk CI koşusunda test kurgusunda doğrudan owner preflight sorgusunun olay SQL listesinden önce kaydolması nedeniyle `$wpdb->queries[0]` beklentisi yanlış çıktı. Test, sorguyu **SQL içeriğinden** bulacak biçimde düzeltildi. Böylece testin kendisinin yanlış varsayımı giderildi.
- Son kod commit `4aaeafe406b990888cf39204906f0ef35b0ed652`: **Changed PHP Syntax = success** ve **Milano read-only session feed contract = success**. Her ikisi izole sentetik CI; gerçek PayTR/konkürans veya WordPress canlı entegrasyon testi değildir.
- GitHub kodu **canlı Madagaskar feed v0.1.0'a / Milano consumer v0.1.0'a dağıtılmadı**; mevcut sipariş/kapasite/ticket tablolarına hiçbir yazı yapılmadı.

## C. PayTR ve çapraz alan adlı ödeme sözleşmesi

Tercih edilen mimari **Milano sayfası → Milano server-side imzalı kısa ömürlü checkout intent → Madagaskar merkezî MMC/MDG/Woo sipariş + tek MDG hold → PayTR iFrame müşteriye Milano domaininde → PayTR imzalı merkezi callback → tek final ödeme ve tek Tickera bileti/giriş hakkı**. Bu **tasarımdır**, çalışan servis değildir.

PayTR resmî iFrame API iki aşamalıdır: önce sunucu `iframe_token` alır; sonra ödeme sonucu **Bildirim URL**'ye iletilir. `merchant_ok_url` bir ödeme durumu sinyali değildir; sadece müşteri yönlendirmesidir. Belgeler tek PayTR hesabının farklı domainlerde aynı mağaza/callback ile koşulsuz kullanılabileceğini garanti etmez; Milano domaini, merchant onayı, marka/unvan, callback adresi ve 3DS dönüşleri **PayTR destek/teknik kullanıcıdan yazılı teyit** gerektirir.

Belgeler:
- https://dev.paytr.com/iframe-api
- https://dev.paytr.com/iframe-api/iframe-api-1-adim
- https://dev.paytr.com/iframe-api/iframe-api-2-adim

## D. Henüz devreye alınmaması gerekenler

- Milano için yalnızca eklenti kurmak = çalışan satış sistemi DEĞİL. PayTR production anahtarları GitHub/Drive/JS içine yazılmaz.
- Canlı Milano'ya WooCommerce/Tickera/PayTR kurup ödeme ekranını açma, canlı Madagaskar checkout hook'u, MDG kapasite, PayTR callback, Tickera QR yazma, PR merge etme **şimdi yapılmadı**.
- Madagaskar'daki mevcut 13 programın marka ataması değiştirilmedi ve mevcut satılmış biletlerin iptali/düzenlemesi yapılmadı.
- Canlıda aynı marka için iki ayrı WooCommerce kapasite/sipariş defteri açılmayacak.

## E. Sonraki sırayla teslim kapıları

1. **Milano etkinliğinin oluşturulması**: Yeni bir MMC programında kullanıcı tarafından açık `Milano Sirki` markası, tarih/yer ve sonradan eşlenmiş tek MDG event ID. Mevcut Madagaskar programını otomatik aktarma.
2. **Salt okunur ilk canary**: Yeni tokenı yalnız WordPress.com sunucu konfigurasyonunda sakla; özel API kaynağı 0/1 Milano seansı ve ownership doğrulamasıyla dönsün. Canlı kaynak/consumer pluginleri güvenli sürümünü staging testinden sonra onayla.
3. **Merkezi imzalı satış isteği ve atomik rezervasyon**: HMAC ayrı key, request nonce uniqueness, fiyat Woo/MDG server snapshot'tan, aynı seansa son bilet yarışına atomik hold; başka markayı, eski permalink’i ve Store API'yi kapat.
4. **PayTR**: merchant/domain destek teyidi, sandbox iFrame, MDG order ↔ merchant_oid unique settlement, tekrarlı/alternatif merchant_oid bildirimleri ve başarısız işlemler; müşteri başarı sayfası callback yerine geçmez.
5. **Tickera, aile 2+2, bilet ve giriş**: Canlı Madagaskar aile paketinin sanal satır + gerçek 2 yetişkin ve 2 çocuk satırı + negatif fee kuralını doğru modelle; dört ayrı QR, tek sipariş/ödeme; PDF/WhatsApp/Kommo Milano kimliği; bilet iade/kapı taraması.
6. **Uçtan uca staging test**: doğru/yanlış marka, REST/cart/checkout/order-pay, aşırı satış, 3DS/PayTR retry, QR idempotency ve cross-channel Biletinial/gişe. Yeşil test ve ayrı gerçek yayına alma onayından önce satış butonu açma.

**Güncel gerçek sonuç:** Milano tanıtım sitesi YAYINDA. Merkezi marka seçimi ve testleri geliştirme dalında güçlendirildi. **Milano bilet satış entegrasyonu hâlâ geliştirme aşamasında**, canlı satış/ödeme/QR yok.

PR #235: https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/235
Çalışma günlüğü: https://docs.google.com/document/d/1GSV-_niF39NTkB45--sqodofxDVH25S7XeKxefzUa4Q/edit

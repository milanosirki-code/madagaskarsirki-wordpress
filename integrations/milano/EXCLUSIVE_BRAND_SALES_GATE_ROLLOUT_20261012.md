# Milano / Madagaskar — Tek Marka Satış Kapıları ve Kontrollü Yayın Planı

Tarih: 12.10.2026  
Durum: **GitHub taslağı — canlıya uygulanmadı**

## Bağlayıcı iş kuralı

Her MDG etkinliğinin yalnız **tek sahibi** vardır: `Milano Sirki` veya `Madagaskar Sirki`. Milano etkinliğinin tüm görünürlüğü ve bilet satışı `milanosirki.com` ile, Madagaskar'ınki `madagaskarsirki.com` ile sınırlıdır. Merkezî Dünya Organizasyon yönetimi, raporları ve uygun koşullarda tek WooCommerce/MDG/Tickera kapasite motoru korunabilir. **İki site aynı fiziksel etkinliği satmayacaktır.**

## Gerçek sahiplik kaynağı

1. `MMC_Program_Service::get_program()` üzerindeki `brand` alanı yeni MMC programlarında zorunlu seçilir.
2. MDG event için programa giden doğrulanmış yol `MMC_MDG_Bridge_Service::program_for_mdg_event(event_id)`. Program bulunamıyor, birden fazla sahip çıkıyor veya geçerli marka değeri yoksa satış güvenli varsayılan olarak kapalıdır.
3. Eşleştirme ve doğrulama **sunucu tarafında** yapılır; URL `?brand=`, browser `Origin`/`Referer`, metin başlığı, sessionStorage veya fiyat parametresi yetki kaynağı değildir.
4. Eski satın alınmış biletlerin sahipliği ve orijinal sipariş kayıtları değiştirilmez.

## Bu taslaktaki koruma tablosu

| Kanal | Taslak | Kalan kapı |
|---|---|---|
| MMC Yeni Program Aç | Zorunlu marka seçimi; program kaydı + oluşturma denetim günlüğü | Canlı regresyon |
| MMC Program Listesi | Satış Markası sütunu, eski geçersiz marka için uyarı | Canlıya alma onayı |
| Milano şehir önizlemesi | Gelen `event_owner_brand=milano` değilse filtrelenir | Güvenli API server-token ve anonim yayın testi |
| Madagaskar Şehirler `MDG_Public_Cities::active_cities()` | `dunya_brand_event_visible_on_site` şehir kartı gruplama **öncesinde** | Yan sayfalar, arama, mağaza arşivi |
| Madagaskar `/etkinlik/{slug}` | Aynı filtre doğrudan sayfada | Eski permalink/QR dış linkleri |
| Woo klasik add-to-cart | Pilot marka sahibi kontrolü | Ürün varyasyonları, farklı checkout senaryoları |
| Woo klasik sepet / checkout | Pilot mevcut sepet ve sipariş satırı marka kontrolü | Sipariş ödeme/tekrar ödeme, finans uygulamaları |
| Woo Store API / Checkout Blocks | Pilot add-to-cart ve checkout kontrolü | Gerçek Store API hook uyumluluğu, 3DS |
| PayTR bildirimleri | Yalnız **bağımsız preflight taslağı**, hiçbir ödeme callback'ine bağlanmadı | İmzalı callback + doğrulanan marka + tutar + idempotency |
| Tickera QR / PDF / e-posta / Kommo | Tasarım kararı | Marka bazlı uçtan uca uygulama |
| Biletinial / gişe / kurumsal kampanya | Ortak kapasite/marka sözleşmesi ihtiyacı belirlendi | İlgili dış kanalların mutabakatı |

**GÜVENLİK:** `DUNYA_BRAND_GUARD_ENABLED` gerçek sunucuda bilinçli olarak `true` yapılmadıkça deneysel marka koruması mevcut ziyaretçi görünürlüğünü etkilemez. Yeni filtreler `apply_filters( ..., true, ... )` varsayılanıyla kayıtlıdır. Ancak yalnız görünürlük filtresinin açık olması **kesin alışveriş engeli değildir**; ödeme ve diğer yollar bitmeden aktif edilemez.

## Önemli çok alan adlı ödeme kararı

**Çözülmemiş mimari konu:** Madagaskar WordPress üzerindeki WooCommerce/PayTR `home_url()` değerini `madagaskarsirki.com` olarak görür. Milano'nun ayrı görünen sayfasından aynı merkezî checkout'a yönlendirilmesi, müşterinin yalnız Milano sitesinden satış yapıldığı iddiasını tek başına karşılamaz. Ayrıca host-tabanlı guard böyle bir isteği Madagaskar kanalına ait görerek Milano biletini reddeder.

Alternatifler gerçek dağıtım/PayTR/WordPress.com kabiliyet doğrulamasına bağlıdır:

**A — Doğrulanmış çok alan adlı merkezî ödeme:** Ortak WooCommerce/Tickera sipariş motoru, iki alan adında HTTPS/oturum/çerez/nonce/3DS geri dönüşleri için güvenli proxy/site yapılandırması. Merkezi rezervasyon, PayTR ve evrak/rapor testleri zorunlu.

**B — Milano arayüzü + güvenli merkezî satış aracısı:** Milano sunucusundan Madagaskar'a server-to-server çağrı ile kanonik event, marka, fiyat, kapasite ve seans yeniden doğrulanarak yalnız tek merkezî sipariş/hold yaratılması; sunucu tarafından üretilmiş süresi kısa, tek kullanımlık yetkiler; güvenli PayTR ödeme alan adı/3DS alan adı ve callback; tek Tickera QR. İkinci stok ve ikinci ödeme kaydı oluşturulmamalı.

**HİÇBİRİ HENÜZ ONAYLANMIŞ, ETKİN VEYA GERÇEK ÖDEMEYLE TEST EDİLMİŞ DEĞİLDİR.** Milano biletini Madagaskar sitesinde kabul etmek için referrer, `?site=milano` veya kullanıcıdan gelen header'a güvenilmez.

## Test sonuçları ve sınırlar

- `integrations/milano/tests/brand-public-catalog-contract.php`: 3 izole durum (Milano, Madagaskar, koruma kapalı), aynı şehirde iki markanın ayrılması, sahipsiz etkinliğin gizlenmesi ve doğrudan URL korunması.
- `integrations/milano/tests/brand-enforcement-contract.php`: ürün/varyasyon, checkout ve çakışma kontrolleri.
- `integrations/milano/tests/brand-checkout-guard-contract.php`: normal, çapraz ve belirsiz Store API/Order örnekleri.
- `integrations/milano/tests/mmc-program-brand-contract.php`: program kaydı ve zorunlu marka.
- GitHub commit `2b462d8dcc90a793097a618bf7471c0c6e98718b`: Milano feed sözleşmesi, PHP syntax ve toplam **20 GitHub Actions iş akışı başarılı**. Bu, gerçek PayTR/WordPress ile satış testinin yerine **GEÇMEZ**.

## Canlı yayın engelleri / geri dönüş

1. **13 eski MMC programı** canlı veritabanında `Madagaskar Sirki` markasına bağlıdır (12.10.2026 salt-okunur sorgu); hiçbiri Milano'ya taşınmayacak.
2. İlk Milano etkinliği için şehir/tarih ve yetkili marka atanması tamamlanmalı; başka markanın etkinliği yeniden kullanılmamalı.
3. Staging'de şehir listesi, doğrudan URL, ürün katalog, klasik sepet, Store API, PayTR/3DS, `order-pay`, PDF ve QR, Biletinial/gişe senaryoları geçmeli.
4. Geri dönüş: yalnız yeni pilot guard kapatılır, ilgili dağıtım geri alınır; mevcut MDG sipariş/QR/finans kayıtlarına dokunulmaz.
5. Kullanıcı ayrıca onay vermeden GitHub draft PR merge, canlı Woo/PayTR müdahalesi, Milano site launch veya satış açma **YOK**.

## Bağlantılar

- Geliştirme: https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/235
- Google Drive proje çalışma kaydı: https://docs.google.com/document/d/1GSV-_niF39NTkB45--sqodofxDVH25S7XeKxefzUa4Q/edit

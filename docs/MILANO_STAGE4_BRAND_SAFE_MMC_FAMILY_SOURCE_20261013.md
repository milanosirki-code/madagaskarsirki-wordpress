# Milano Sirki — Satış entegrasyonu Aşama 4
## Merkezî MMC aile fiyatı, marka güvenli taslak oluşturma ve çift eşleme koruması

**Tarih:** 13 Ekim 2026. **Durum:** Taslak geliştirme ve sentetik CI tamamlandı; **canlı Milano satış, ödeme, PayTR callback, Woo sipariş, MDG stok, Tickera/QR değişikliği yapılmadı**.

**İlgili repo/dal:** `milanosirki-code/madagaskarsirki-wordpress` / `feature/milano-retry-safe-preflight-20261013` — **[Draft PR #239](https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/239)**. Hedef PR #235 de draft/unmerged. İki marka birbirinin sitesinde aynı etkinliği satmaz.

### İlk Milano etkinliği — diğer sohbetin kontrolünde

- Yalova, **22.10.2026**, 90. Yıl Kapalı Spor Salonu, 18.00 ve 19.30; her seans **500 ayrı giriş**, serbest oturma.
- **Yetişkin 490 TL, çocuk 245 TL, 2+2 aile 1.100 TL**; 1 aile = **2 yetişkin + 2 çocuk 4 ayrı giriş/QR**, 1.470 TL bileşen tutarı − **370 TL** merkezî indirim.
- Bu turdaki WPVibe canlı salt okunur sorgu: `wp_mmc_programs` **13 Madagaskar Sirki / 0 Milano Sirki**; Yalova veya 22 Ekim tarihli MMC programı bulunmadı. **Diğer sohbetin hazırladığı ilk etkinliği bu dalda açmadık/değiştirmedik.**
- MMC programındaki `mdg_event_id` alanı gerçek canlı bağlı programlarda **NULL** olabiliyor; marka yetkisi ve tekil sahiplik `wp_mmc_mdg_event_bridge` üzerinden doğrulanmalı.

### 1. Gerçek üretim aile modülü üzerinden kaynak analizi

**Canlı, salt okunur kaynak:** `plugins/madagaskar-aile-paketi-22/madagaskar-aile-paketi-22.php` v1.1.3.

- Yaşanmakta olan Madagaskar checkout'u sanal `MDG_FAMILY_2_2` AJAX satırını iki gerçek CHILD/ADULT çifti hâline getirip negatif WooCommerce ücret/fee ile indirim yapıyor. 4 fiziksel giriş/QR üretimi buna dayanıyor.
- Bu eklentinin aile fiyatı önce `MMC_Event_Service::ticket_types()` içindeki aktif `family_2_2` satırından geliyor, **bulunamazsa global/eğlenti varsayılanına dönüyor**. Milano gibi münhasır marka için bu fallback **yetkili fiyat kaynağı değildir**.
- Canlı `wp_mmc_ticket_types` şeması `ticket_code,price,capacity_units,is_active`, `wp_mmc_events` şeması `program_id,mdg_event_id`; MDG gerçek bilet tablosu `id,session_id,code,price,capacity_units,wc_variation_id`.
- MDG'de eski aktif `AILE_2_2` **4 kapasite birimli** satırlar da var; Milano aile paketine bunları gerçek Woo/Tickera varyasyonu olarak kopyalamak yasak. Aile paketinde **iki gerçek çocuğun ve iki gerçek yetişkinin** biletlerini kullan.
- MDG bilet kodları üretimde `CHILD/ADULT` veya `COCUK/YETISKIN` olabilir. Müşteri tarafından gelen metin veya başlıktan tahmin edilmez; yalnız sınırlı bilinen kodlar + doğrulanmış central ticket type ID.

### 2. Bu dalda yapılan, CANLIYA DAĞITILMAMIŞ kod değişiklikleri

**A.** `integrations/milano/checkout-intent-protocol/milano-canonical-mmc-family-policy.php` (yeni):

- Etkinliğin daha önce doğrulanmış Milano MMC program ID'sini kullanır. Ek olarak **aynı programın bir adet aktif MMC→MDG köprüsü** olduğunu ters yönden `LIMIT 2` ile kontrol eder. Eksik/çoklu/yanlış MDG sahipliğinde fail-closed.
- İlgili program altında tam **bir** MMC etkinlik kaydı ister; `mmc_events.mdg_event_id` NULL olsa bile doğrulanmış tek aktif bridge ile devam eder. Doluysa o değerin aynı MDG event ID'ye eşit olmasını ister.
- Aynı MMC etkinliğinden tek aktif `child`, `adult`, `family_2_2` satırlarını alır; fiyatları kuruş olarak okur ve kapasite katsayılarını sırasıyla 1,1,4 bekler. Eksik/çoklu/bozuk fiyatı reddeder.
- Gerçek MDG child/adult `id`, kod, Woo fiyatı, stok, kullanılabilirlik ve kapasite türleriyle bire bir eşleştirir. Aktif bir MDG `AILE_2_2` 4-unit varyasyonu varsa aile paketini **güvenli şekilde kapalı** tutar.
- Marka ve fiyatı global WordPress option/coupon veya kullanıcı gönderiminden okumaz. Kod yalnız güvenli family snapshot alanlarını döner; sıfır write.

**B.** `integrations/milano/checkout-intent-protocol/milano-canonical-mdg-snapshot.php`:

- `canonical_owner()` artık `brand + program_id` ikilisini tek aktif MMC–MDG bağlantısından alır.
- MDG ticket snapshot'a gerçek `ticket_type_id` dahil edildi.
- Yalnız A'daki ek kanonik kontroller başarılıysa snapshot'a `family` anahtarı eklenir. Eksikse satın alma hesaplayıcısında `FAMILY22` sanal satırı kapalı kalır, normal tekil biletler etkilenmez.
- Önceden hazırlanmış `MCPI_Milano_Central_Family_Quote`: gerçek kanonik fiyattan 2+2 aileyi iki Woo bileşen satırına dönüştürür, 4 giriş sayar, negatif indirim aritmetiğiyle toplamı 1.100 TL önerir. Henüz Woo order, fee, PayTR token veya Tickera yaratmaz.

**C.** `wp-content/plugins/madagaskar-management-center/includes/class-mmc-mdg-draft-sync-service.php`:

- Yeni draft MDG başlığını sabit Madagaskar yerine **MMC programının doğrulanmış `Madagaskar Sirki` veya `Milano Sirki` markasından** üretir. Bilinmeyen marka daha MDG DB yazmadan engellenir.
- `sync_family_package_option()` yalnız **Madagaskar Sirki** marka taslağında çalışır. Milano MDG etkinlik kimliği Madagaskar'ın global aile AJAX / Woo sepet açma listesine eklenmez.
- MMC draft sync zaten `family_2_2` satırını MDG fiziksel bilet listesine eklemeyip sadece child/adult gerçek biletleri senkronize etmeyi hedefler; bu davranışı korur.

**D.** `wp-content/plugins/madagaskar-management-center/includes/class-mmc-mdg-bridge-service.php`:

- Brand-aware draft sync sınıfı yüklenememişse, eski `create_draft_from_program()` yolunun **yalnızca doğrulanmış Madagaskar Sirki** programında çalışmasına izin verilir.
- Milano veya sahipsiz/bozuk markalı programda `mmc_milano_draft_sync_required` hatası döner. Eski fallback'in Madagaskar başlığını ve gerçek 4-unit aile satırını Milano etkinliğine yazması engellenir.

### 3. Sentetik CI ve gerçek live sınırı

- Unit/snapshot testleri: gerçek MMC tablo yapısına uygun aile fiyatı; gerçek yetişkin/çocuk Woo satırı; NULL MMC event MDG ID; ters yönde tek aktif bridge, yanlış/çoklu bridge, yanlış fiyat, kayıp family, sahte MDG 4-unit türü, yanlış varyasyon/para/stok.
- Draft güvenlik testleri: Milano ve Madagaskar başlıkları, şehir/ilçe, alias/boş/geçersiz marka, eski fallback blokajı ve global Madagaskar aile ayarı izolasyonu.
- **Son geçerli kod commit `643fffe5d43f2f3f0ded1a51c003507e513fe6be`: GitHub Actions “Changed PHP Syntax” = SUCCESS; “Milano read-only session feed contract” = SUCCESS.**
- Bu sonuç yalnız **izole PHP mock/contract** testleridir. Canlı WooCommerce sepet, PayTR 3DS/merchant domain, Tickera instance üretimi veya eşzamanlı son koltuk senaryoları henüz test edilmiş değildir.

### 4. Sonraki üretim engelleri

1. Diğer sohbetteki Yalova programı kanonik **Milano** marka ile oluşturulmalı; aynı program için bir MDG event, iki seans (18.00/19.30) ve her seans 500 kişilik kapasite. MDG link'i belgeleyerek teyit edilmeli. **Mevcut 13 Madagaskar programına dokunulmaz.**
2. Brand-aware MMC draft sync ve kaynak API'nin geri alınabilir staging kurulumu / WordPress plugin yüklenme sırası; canlıya geçmeden gerçek DB şemasında dry-run. **Bu PR doğrudan canlı MMC koduna aktarılmaz**.
3. Merkezî `family` snapshot'ın gerçek Yalova `family_2_2` fiyatını, gerçek child/adult varyasyonlarını ve 1.100 TL doğruluğunu ispatlayan read-only canary.
4. PayTR resmî mağaza desteğinden `milanosirki.com` ikinci alan adı, iFrame V2/3DS ve merkezi Madagaskar callback URL yetkisi **yazılı teyit**. API anahtarları GitHub/Drive/JS'e eklenmez.
5. Gerçek Woo sipariş ve MDG kapasite hold'unu atomik, PayTR callback'i hash ve tekrar OID kilidiyle, Tickera 4 fiziksel QR ve aile fee/KDV tutarlarıyla staging testleri. Giriş, iptal, iade, Biletinial/gişe marka-kapasite izolasyonu.
6. KVKK/Complianz opt-in, yasal bilet koşulları ve ayrıca **canlı Milano ödeme/satış aktivasyonu onayı**.

Resmî PayTR belgeleri:
- https://dev.paytr.com/iframe-api
- https://dev.paytr.com/iframe-api/iframe-api-1-adim
- https://dev.paytr.com/iframe-api/iframe-api-2-adim

**Önemli:** `milanosirki.com` tanıtım sitesi herkese açık, **online satış hâlâ kapalı**. GitHub'daki PR #239 ve üst PR #235 **DRAFT/UNMERGED**; canlı Madagaskar üretim bilet/ödeme/CRM/QR akışına herhangi bir yazma yapılmadı.

# Milano Sirki / Madagaskar Sirki — Tek etkinlik, tek marka (12.10.2026)

## BAĞLAYICI İŞ KURALI
Milano Sirki ve Madagaskar Sirki **ayrı markalardır**. Bir fiziksel etkinlik (event) yalnızca **bir** markaya atanır. Milano'ya atanan etkinlik Madagaskar'ın kamuya açık katalog, bilet, kampanya, arama/SEO ve satın alma yollarında gösterilmez ve satılmaz; Madagaskar'a atanan etkinlik için de tersi geçerlidir. Her etkinlik için tek yetkili satış sitesi belirlenir.

Dünya Organizasyon arka ofis, finans, genel raporlama, Kommo ve bilet üretim motorunu ortak yönetebilir. Teknik hizmetlerin paylaşılması, bir etkinliğin iki markaya satılabileceği anlamına gelmez.

## Varsayım düzeltmesi
Önceki `integrations/milano/README.md`, feed/consumer pilotu ve PR #231, **aynı etkinliğin iki sitede görünmesini** esas alır. Bu davranış artık **geçersizdir**; kod bu iş kuralına uyarlanmadan merge, WordPress deploy veya checkout açılışı YASAKTIR. Mevcut satıştaki etkinliklerin markası veya bilet/kod/ödeme kayıtları otomatik değiştirilmez.

## Önerilen kanonik alanlar
- `event_owner_brand` = **milano** | **madagaskar** (tek bir kanonik değer, doğrudan event kaydında veya doğrulanmış 1:1 mapping tablosunda).
- `event_sales_domain` = `milanosirki.com` | `madagaskarsirki.com`, owner_brand ile sunucuda eşleşir.
- Yeni kayıt için brand zorunludur; eski/boş/ambiguous değerler Milano feed ve checkout için **fail-closed**, karantinaya alınarak manuel sınıflandırılır.
- Marka sahibi değişimi, özellikle satılmış etkinlik için, satış/finans mutabakatı ve ayrı onaya bağlıdır. Sadece görüntüleme etiketi değişimi sayılmaz.
- Müşteri siparişi ve PDF/QR üzerindeki marka, event owner'dan türetilir; tarayıcıdan gelen `brand` parametresine güvenilmez.

## Gerekli filtreler — yalnız UI gizlemesi yeterli değildir
1. Kaynak API feed: token doğrulama + event-owner kontrolü; yalnız requested brand eventlerini yayınlar. Yanıtta owner bilgisi güvenli ve tutarlıdır. Hatalı/eksik brand = sonuç yok.
2. Milano gösteri listesi: sadece `milano` eventlerini alır. Madagaskar listesi yalnız `madagaskar` eventlerini alır.
3. WooCommerce ürün/varyasyon ve seans mapping'leri: event owner / site alan adı eşleşmezse add-to-cart ve checkout reddedilir. Derin link, REST API, AJAX, kupon, yeniden sipariş, kampanya ve eski permalink yolları da doğrulanmalıdır.
4. Rezervasyon/hold, fiziksel koltuk, Tickera QR ve PayTR callback event owner'ı korunur, idempotent akışlar test edilir.
5. Marka bazlı bilet PDF, müşteri e-postası, WhatsApp/Kommo mesajı; Dünya Organizasyon birleştirilmiş finans raporu, marka bazlı alt rapor.
6. Harici satış kanalı (Biletinial) varsa o event için yetkili satıcı/satış kanalı sözleşmesi ayrıca tasarlanır; kullanıcının “tek siteden” kuralı ile çelişen görünür bilet bağlantısı açılmaz.

## Test zorunlulukları
- Milano etkinliği Milano listesinde VAR, Madagaskar listesinde YOK; checkout sadece yetkili kanalda.
- Madagaskar etkinliği ters durum.
- Markası boş/hatalı/event var ama mapping yok: iki kanalda da satış reddi.
- URL/API/ürün ID'si elle değiştirilince çapraz satış reddi.
- Ayrı tarihlerde aynı salonda etkinlikler, seans güncelleme/iptal/erteleme, daha önce satılmış bilet ve iadeler.
- Eşzamanlı 2 müşteri, son koltuk, PayTR başarısız/tamamlanan callback, çift QR üretmeme.
- Eski siparişler ve Madagaskar satışları regresyon testleri.
- Tüm testler önce ayrı ortamda; canlıya deploy, main'e merge ve Milano site launch için ayrıca onay.

## Uygulama sırası
1. Mevcut MDG/MMC veri modelindeki gerçek marka kolonu ve bağlantılı satış hook'larının envanteri.
2. Etkinlik markası için tek kanonik kaynak ve mevcut etkinlikler için manuel eşleme listesi.
3. Üretici API'sinde owner filtresi, tüketicide Milano filtresi, marka biliniyor şartı; WordPress yöneticisi salt okunur önizleme.
4. Sitede satış yolunun marka-owner yetkilendirmesi ve uçtan uca testler.
5. Kullanıcı canlı değişiklik onayıyla sınırlı yayına geçiş.

**Bu belge tasarım sözleşmesidir; çalışan entegrasyon veya canlıya alınmış kod olduğu iddia edilmez.**

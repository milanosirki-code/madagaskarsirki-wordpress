# Dünya Organizasyon: Milano / Madagaskar ortak marka mimarisi
Tarih: 12.10.2026  
Statü: Tasarım ve güvenli salt-okunur pilot — **canlı ödeme entegrasyonu yapılmadı**.

## Sabit karar
`milanosirki.com` ve `madagaskarsirki.com` farklı marka ön yüzleri. Aynı fiziksel gösteri için *tek* MMC program/event/session, *tek* MDG/Bridge mapping, *tek* WooCommerce ürün/varyasyon ve *tek* Tickera bilet havuzu korunacak.

## Kullanıcı deneyimi
- Milano ön yüzde Milano logosu, metinleri, gösteri kartları ve Milano markalı bilet hedeflenir.
- Fiyatlar ve kullanılabilirlik merkezden okunur, elle kopyalanmaz.
- Ödeme ekranının Milano alan adını koruması bir **hedef** olup PayTR/checkout destek uyumluluğu kanıtlanmadan canlıya alınamaz.
- WordPress.com Milano Atomic site Premium; 12.10.2026 yeni alan adı birincil, site `coming_soon`. Ana sayfa taslağı #5 ve şehirler taslağı #6.

## Veri hattı (hedef)
1. MMC canonical program + MDG/Tickera/WooCommerce satışa uygunluk -> dar kapsamlı, salt-okunur, cache uyumlu REST besleme.
2. Milano WordPress -> sunucu tarafı veri alımı, geçerlilik ve tarih denetimi -> Milano etiketi ile sunum.
3. Merkezi checkout API -> mevcut WooCommerce order ve ürün/varyasyonu ile atomik kapasite kontrolü -> PayTR callback -> native Tickera QR.
4. WC order meta `_dunya_sales_brand` = `milano` / `madagaskar` ve `_dunya_sales_channel` tutulur; sipariş/QR referansı tekildir.
5. PDF, e-posta, Kommo şablonları **sipariş markası** ile seçilir; marka, müşteri tarayıcı parametresinden keyfi değiştirilemez.
6. MMC raporlama, siparişi tek kez sayarak marka filtresi uygular. Maliyetler ortak etkinlik düzeyinde kayıtlı kalır.

## Pilot kabul ölçütleri
- İki ön yüzden eşzamanlı denemede ortak kapasite asla negatif olmaz.
- Bir aile paketi 4 gerçek Tickera bileti / 4 kapasite ünitesi tüketir.
- Paid/pending/failed/refunded durumlarında tekrar callback gönderilmesi ikinci sipariş/QR doğurmaz.
- Milano siparişi Milano PDF/Kommo/iletişimi, Madagaskar siparişi mevcut marka şablonunu alır.
- Aynı WooCommerce order raporlarda iki kez sayılmaz.
- Satışa kapalı, geçmiş, iptal, ertelenmiş seanslar yayımlanmaz.
- API başarısızken takvim yanlış stok veya ödeme butonu göstermez.
- Mevcut Madagaskar canlı checkout smoke testleri değişmeden geçer.

## V0.1 sınırlaması
`integrations/milano/milano-shared-events/` içindeki ilk shortcode, **yalnız yöneticilere önizleme verir**. Kaynak HTML parse yöntemi kapasite doğruluğu/gerçek zamanlılık sağlamadığı için kamuya kapalıdır. V0.2 için merkezi kaynak beslemesi yapılmadan satışa geçilmez.

## Dağıtım sırası
1. Review ve lint (GitHub PR).
2. Milano WP üzerinde sadece test sayfası / shortcode denemesi, `coming_soon` korunur.
3. Salt okunur MMC kaynak API; tarih, seans ve kapasite denetimi.
4. PayTR izolasyonlu ödeme pilotu; gerçek müşteri/ödeme değişikliği ancak ayrı onay ve geri dönüş planıyla.
5. PDF/Kommo/rapor marka katmanı.
6. Son smoke test ve kullanıcı onayı olmadan kamuya satış açılmaz.

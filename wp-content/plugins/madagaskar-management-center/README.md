# Madagaskar Management Center v1.3.8

Bu sürüm **MMC + Madagaskar Bilet Yönetimi + Okul Tanıtım yönetim ekranlarını tek Madagaskar üst menüsünde toplar**. Veri kaynakları ve çalışan motorlar ayrışık kalır; değişiklik yalnız yönetim navigasyonundadır.

## v1.3.8 — Tek Yönetim Menüsü / Navigation Hub

- WordPress yönetiminde MMC, MDG ve Okul Tanıtım için tek ana çatı **Madagaskar** olur.
- MMC yetkisi olan yöneticilerde eski `mdg-dashboard` ve `mad-okul` üst menüleri gizlenir.
- Eski MDG ve Okul Tanıtım sayfalarının slug, callback, form action ve veri tabloları değiştirilmez.
- **Bilet Yönetimi** merkezi, o anda sisteme kayıtlı tüm `mdg-dashboard` alt sayfalarını dinamik olarak listeler; ek MDG modülleri varsa ayrıca görünür.
- **Okul Tanıtım** merkezi, o anda sisteme kayıtlı tüm `mad-okul` alt sayfalarını dinamik olarak listeler.
- Eski `mdg-*` ve `mad-okul*` ekranlarında WordPress sol menü vurgusu Madagaskar çatısında kalır.
- Aktif MMC Program ID eski ekranlara taşınan bağlantılarda korunur.
- **Görevlerim** menüsü saha personelinin ayrı yetki akışı için bağımsız bırakılır.
- MMC yetkisi olmayan kullanıcıların eski menü erişimi değiştirilmez.
- MMC devre dışı bırakılırsa eski MDG ve Okul Tanıtım üst menüleri otomatik olarak geri döner.
- Bu sürümde veritabanı şeması değişmez; `MMC_DB_VERSION` 1.3.7 olarak kalır.

## Güvenlik sınırları

- WooCommerce siparişleri, ürünleri ve varyasyonları değiştirilmez.
- Tickera event/bilet/QR kayıtlarına yazılmaz.
- PayTR akışına müdahale edilmez.
- MDG `events/sessions/ticket_types/order_map` tabloları navigasyon birleşiminden etkilenmez.
- Okul Tanıtım okul, program, görev ve rota kayıtları taşınmaz veya yeniden yazılmaz.
- Birleşim yalnız WordPress admin menü görünümünü değiştirir.

---

# Madagaskar Management Center v1.3.7

Bu sürüm **MMC Program ID ile ilk Madagaskar Bilet Yönetimi motorunu kalıcı olarak birbirine bağlar**.

## v1.3.7 — MDG Bilet Motoru Köprüsü

- Yeni `mmc_mdg_event_bridge` tablosu eklendi.
- Tek gerçek yönetim kimliği MMC `program_id` olarak korunur.
- MMC Program → MMC Event → MDG Event → MDG Session → WooCommerce → Tickera → sipariş zinciri doğrulanır.
- Eski Madagaskar satış tablolarına kolon eklenmez ve kayıt yazılmaz; köprü MMC tarafında tutulur.
- Güvenli otomatik eşleştirme WooCommerce ürün/varyasyon ve Tickera event kimliklerinin ortaklığıyla yapılır.
- Yalnız il/ilçe/tarih benzerliği otomatik bağlantı için yeterli kabul edilmez.
- Bütünlük Merkezi artık MDG motor sürümünü, Program↔MDG Event bağını, il/ilçe/tarih/salon/seans saatlerini, satış nesnesi kimliklerini ve MDG↔MMC satış mutabakatını gösterir.
- MDG ve MMC satış mutabakatında sipariş, sipariş satırı, bilet adedi ve kapasite/kişi birimleri karşılaştırılır; aynı WooCommerce satışı iki kez gelir olarak yazılmaz.
- MDG ve MMC ciro değerleri görünür biçimde karşılaştırılır; MDG `line_total` vergi hariç olabildiği için ciro farkı tek başına kritik hata sayılmaz.
- MMC aktif program şeridi artık `mdg-*` yönetim ekranlarında da görünür.
- Bütünlük Merkezi ve aktif program kısayollarına **MDG Bilet** bağlantısı eklendi.
- Eşleşme kesin değilse Bütünlük Merkezi aday MDG etkinliklerini gösterir ve yönetici kontrollü manuel bağlama yapılabilir.

## Güvenlik sınırları

- MDG `events`, `sessions`, `ticket_types` ve `order_map` tabloları salt okunur kullanılır.
- WooCommerce ürünleri, Tickera etkinlikleri ve siparişler köprü kurulurken değiştirilmez.
- Bir MDG Event aynı anda iki farklı MMC Program ID ile bağlanamaz.
- Otomatik backfill yalnız güçlü ve tekil WooCommerce/Tickera kimlik eşleşmesinde bağlantı kurar.
- Belirsiz durumda sistem bağlantı oluşturmaz.

## Güncelleme

1. Eklentiyi v1.3.7 ile değiştirin.
2. WordPress eklentiyi yüklediğinde MMC veritabanı şema sürümü **1.3.7** olur ve köprü tablosu oluşturulur.
3. Madagaskar → Program Bütünlüğü ekranını açın.
4. İlgili Program ID için **MDG Bilet Motoru**, **MMC ↔ MDG Etkinlik Köprüsü**, **MDG Tarih / Salon / Seans**, **MDG / WooCommerce / Tickera Kimliği** ve **MDG ↔ MMC Satış Mutabakatı** satırlarını kontrol edin.
5. Tek güçlü aday varsa **MDG Bağını Kur** düğmesini kullanabilirsiniz; sürüm yükseltmesi sırasında güvenli eşleşmeler ayrıca otomatik backfill edilir.

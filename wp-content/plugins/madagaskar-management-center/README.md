# Madagaskar Management Center v1.3.13

Bu sürüm **Code Snippets envanterini salt-okunur analiz eder ve gerçek riskleri genel “snippet var” uyarısından ayırır.**

## v1.3.13 — Snippet Envanteri & Çakışma Merkezi

- Sistem & Yetkiler içine yeni **Snippet Envanteri** ekranı eklendi.
- Code Snippets tablosu salt-okunur taranır; hiçbir snippet otomatik kapatılmaz, silinmez veya değiştirilmez.
- Her snippet için:
  - aktif/pasif durumu,
  - Üretim / Test-Tanı / Geçici / Legacy / İncele sınıfı,
  - tanımladığı global fonksiyonlar,
  - kullandığı WordPress/WooCommerce hook'ları,
  - shortcode adları,
  - kod hash'i,
  - olası çakışmalar,
  - koru / incele / pasife alma adayı önerisi
  gösterilir.
- Aktif snippet'lerde aynı global fonksiyon veya aynı shortcode adı tespit edilirse çakışma uyarısı üretilir.
- Benzer işlev alanındaki aktif snippet'ler (Kommo, sosyal paylaşım, hukuk/iletişim, SEO, Bilet Al, Biletlerim, etkinlik vb.) inceleme adayı olarak birlikte gösterilir.
- **test / diagnostic / debug / deneme** isimli aktif snippet'ler öncelikli uyarı adayıdır.
- Eski **Madagaskar V5 Finans Güncellemesi** kodu tespit edilirse Legacy + uyarı olarak işaretlenir.
- Sağlık ekranındaki Code Snippets satırı artık yalnız gerçek risk varsa **Uyarı** olur; risk yoksa **Sağlıklı** olabilir.
- Veritabanı şeması değişmez; `MMC_DB_VERSION` **1.3.7** olarak kalır.

## Güvenlik

- Snippet kodları yalnız okunur.
- Otomatik deactivate/delete/update yoktur.
- WooCommerce, Tickera, PayTR, QR, MDG ve Okul Tanıtım verilerine yazılmaz.
- Kullanıcı snippet'i kapatmak isterse önce Code Snippets ekranında manuel olarak kodu doğrular.
- Pasif eski sürümler için Export sonrası arşiv/Trash önerisi yalnız bilgi amaçlıdır.

---

# Madagaskar Management Center v1.3.12

Bu sürüm **Kurulum & Sağlık ekranındaki Tickera doğrulamasını güçlendirir, aktif programın WooCommerce→MDG→Tickera zincirini ayrı kontrol eder ve eski MMC bildirim gürültüsünü temizler.**

## v1.3.12 — Sağlık Kontrolü Doğrulama

- Tickera artık yalnız `TC` sınıfına bakılarak değerlendirilmez.
- Tickera doğrulamasında şu sinyaller birlikte kullanılır:
  - aktif Tickera eklenti kaydı,
  - `TC` / `TC_VERSION`,
  - Tickera fonksiyon ve post-type sinyalleri,
  - aktif programdaki gerçek `tickera_event_id` eşleşmeleri.
- Aktif program için yeni **WooCommerce → MDG → Tickera Zinciri** sağlık satırı eklendi.
- Bu zincirde MMC Program ID, MDG Event ID, satış nesnesi eşleşmesi, seans sayısı ve Tickera event ID'leri karşılaştırılır.
- Kommo uyarı metni netleştirildi: API yapılandırılmadığında MMC çalışmaya devam eder, yalnız otomatik CRM/AI senkronu beklemede kalır.
- Code Snippets aktifse `wp_snippets` tablosunda Madagaskar ilişkili aktif snippet adayları salt-okunur aranır; bulunursa snippet ID/adları sağlık ekranında gösterilir.
- MMC sayfalarında yalnız bilinen iki operasyon dışı bildirim gizlenir:
  - **Madagaskar V5 Finans Güncellemesi**
  - **Tickera - Custom Forms Activation** reklam bildirimi
- Gerçek warning/error bildirimleri görünmeye devam eder.
- Veritabanı şeması değişmediği için `MMC_DB_VERSION` **1.3.7** olarak kalır.

## Güvenlik

- WooCommerce sipariş, ürün ve varyasyonlarına yazma yoktur.
- Tickera event/bilet/QR verileri değiştirilmez.
- PayTR akışına dokunulmaz.
- MDG `events/sessions/ticket_types/order_map` tabloları salt okunur sağlık doğrulaması için kullanılır.
- Code Snippets kayıtları yalnız okunur; otomatik pasife alma veya silme yapılmaz.
- Kommo yapılandırması veya token değerleri değiştirilmez.

---

# Madagaskar Management Center v1.3.11

Bu sürüm **Sistem & Yetkiler ve diğer gizlenmiş MMC ayrıntı ekranlarının WordPress erişim kaydını korur**. Sol menü sade kalır; kartlardan açılan ayrıntı sayfaları artık doğrudan erişilebilir.

## v1.3.11 — Güvenli Gizli Sayfalar & Arayüz Temizliği

- `remove_submenu_page()` ile MMC ayrıntı sayfalarının kayıt zincirini kesme yöntemi kaldırıldı.
- `mmc-roles`, `mmc-system`, `mmc-preparation`, `mmc-events`, `mmc-sales`, `mmc-field` ve diğer ayrıntı ekranları WordPress'te kayıtlı kalır.
- Bu ayrıntı ekranları yalnız sol yönetim menüsünde JavaScript ile görsel olarak gizlenir.
- Kart bağlantıları `admin.php?page=...` üzerinden mevcut callback ve capability kontrolleriyle çalışmaya devam eder.
- **Yetkiler** ve **Kurulum & Sağlık** için kullanılan `mmc_manage_settings` yetkisi değiştirilmez.
- Yönetici ve Madagaskar Yönetici rollerinin mevcut yetki modeli korunur.
- Hub sayfalarındaki test amaçlı **Güvenli menü modu** kutusu kaldırıldı.
- Rutin başarı/güncelleme bildirimleri hub ekranlarında gizlenir; warning/error mesajları görünmeye devam eder.
- Veri tabanı şeması değişmediği için `MMC_DB_VERSION` **1.3.7** olarak kalır.

## Güvenlik

- WooCommerce, Tickera, PayTR, QR ve MDG satış motoruna müdahale edilmez.
- MDG ve Okul Tanıtım veri tablolarında değişiklik yoktur.
- Eski sayfa slug, callback, nonce ve form action yapıları korunur.
- Sol menü sadeleştirme yalnız görsel katmanda yapılır.

---

# Madagaskar Management Center v1.3.10

Bu sürüm **eski Madagaskar Bilet Yönetimi modüllerini görevlerine göre doğru MMC merkezlerine otomatik dağıtır** ve merkez ekranlarını mobil kullanım için sadeleştirir.

## v1.3.10 — Akıllı Modül Dağıtımı

- Bilet Yönetimi artık yalnız bilet motorunun çekirdek operasyon ekranlarını tutar.
- Yinelenen **Genel Bakış** kartları başlık bazında tekilleştirilir.
- Eski MDG modülleri başlık/slug üzerinden güvenli biçimde sınıflanır:
  - **Satışlar ve Biletler / Satış Raporları / Müşteri-Bilet / İadeler** → Satış & Müşteri
  - **Gider ve Kârlılık / Finans** → Finans
  - **V2 Pazarlama / Marketing** → Pazarlama
  - **V3 CRM / Kommo** → Kommo & AI
  - **Entegrasyonlar / Geliştirici Araçları** → Sistem & Yetkiler
  - Genel legacy **Salonlar / Etkinlikler** → Salon & Etkinlik
- Salon & Etkinlik merkezinde legacy MDG bakım ekranları açıkça **Legacy** etiketiyle gösterilir.
- Pazarlama, Kommo & AI ve Finans artık merkez ekranlarıdır; MMC sayfası ile ilgili legacy MDG aracı aynı merkezde açılır.
- Merkez sayfalarındaki ikinci **Aktif Program** kartı kaldırıldı; üstteki MMC aktif program şeridi tek kaynak olarak kullanılır.
- Hub sayfalarında yalnız rutin başarı/güncelleme bildirimleri gizlenir; warning/error bildirimleri görünmeye devam eder.
- Dinamik olarak yakalanan MDG slug'ları `mdg-` ile başlamasa bile doğru MMC merkezinin menü vurgusu korunur.
- Veritabanı şeması değişmez; `MMC_DB_VERSION` **1.3.7** olarak kalır.

## Güvenlik

- WooCommerce sipariş, ürün ve varyasyonlarına yazma yoktur.
- Tickera event/bilet/QR verileri değiştirilmez.
- PayTR akışına dokunulmaz.
- MDG `events/sessions/ticket_types/order_map` tabloları değiştirilmez.
- Okul Tanıtım verileri ve görev/rota kayıtları taşınmaz.
- Tüm legacy sayfaların mevcut slug, callback ve form action yapısı korunur.

---

# Madagaskar Management Center v1.3.9

Bu sürüm **tek Madagaskar menüsünü sadeleştirir ve ayrıntılı ekranları iş merkezleri altında toplar**. İşlevsel sayfalar, slug'lar, callback'ler, form action'lar ve veri tabloları korunur.

## v1.3.9 — Sade Yönetim Menüsü

Görünür ana menü başlıkları:
- Kontrol Paneli
- Programlar
- Hazırlık & Bölge
- Salon & Etkinlik
- Bilet Yönetimi
- Satış & Müşteri
- Okul Tanıtım & Saha
- Pazarlama
- Kommo & AI
- Operasyon
- Finans
- Raporlar
- Program Bütünlüğü
- Sistem & Yetkiler

Ayrıntılı sayfalar ilgili merkez içinde kart olarak gösterilir:
- Hazırlık & Bölge → Program Hazırlığı, Bölge Veri Ambarı, Nüfus ve Eğitim Verisi, İş Akışı
- Salon & Etkinlik → Salonlar, Salon & Tahsis, Etkinlik & Seans, Satış Hazırlığı
- Bilet Yönetimi → mevcut MDG üretim/etkinlik araçları
- Satış & Müşteri → MMC Satış & Doluluk + MDG Satış Raporları + Müşteri/Bilet Listeleri
- Okul Tanıtım & Saha → MMC Okul/Saha + mevcut Okul Tanıtım/MEBBİS/rota/görev ekranları
- Sistem & Yetkiler → Yetkiler + Kurulum & Sağlık

## Güvenlik

- Bu sürüm yalnız WordPress yönetim navigasyonunu değiştirir.
- WooCommerce, Tickera, PayTR, QR ve MDG satış motoruna yazma yapılmaz.
- `mdg_events`, `mdg_sessions`, `mdg_ticket_types`, `mdg_order_map` değiştirilmez.
- Okul Tanıtım veri tabloları ve saha görev kayıtları taşınmaz.
- Gizlenen ayrıntı sayfaları doğrudan URL ve mevcut callback'leriyle çalışmaya devam eder.
- MMC yetkisi olmayan kullanıcıların eski menü davranışı değiştirilmez.
- Saha personelinin bağımsız **Görevlerim** menüsü korunur.
- Veritabanı şeması değişmediği için `MMC_DB_VERSION` **1.3.7** olarak kalır.

---

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

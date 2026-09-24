# Madagaskar Management Center v1.3.20

Bu sürüm **otomatik Kommo program aşama senkronunu hızlandırır, geçici API hatalarında kontrollü retry ekler ve Program Bütünlüğü ekranında Kommo kart/aşama mutabakatını görünür hale getirir.**

## v1.3.20 — Otomatik Program Aşama Senkronu & Kommo Sağlık Özeti

- MMC program/event/session/ticket/integration/salon/finans değişiklikleri mevcut `mmc_program_logged` zinciri üzerinden Kommo kuyruğuna otomatik alınmaya devam eder.
- 15 dakikalık güvenlik cron'una ek olarak değişiklik sonrası **30 saniyelik hızlı tek-seferlik Kommo kuyruk çalışması** istenir.
- WordPress cron çalışması ziyaret/istek tetiklemeli olduğundan 30 saniye mutlak gerçek-zaman garantisi değildir; ilk uygun WordPress isteğinde çalışır.
- Kommo kuyruk hataları artık türüne göre ayrılır:
  - yapılandırma/pipeline eksik → `waiting_config`
  - HTTP/network geçici hata → kontrollü retry
  - yapısal/yetki/validation hatası → doğrudan `error`
- Retry yalnız şu durumlarda yapılır:
  - WordPress HTTP network hataları,
  - HTTP 408,
  - HTTP 429,
  - HTTP 5xx.
- Retry aralıkları:
  - 1. başarısız deneme → 5 dakika
  - 2. başarısız deneme → 15 dakika
  - 3. başarısız deneme → 30 dakika
  - toplam 4 denemeden sonra kalıcı `error`.
- HTTP 400/401/403 gibi hatalar körlemesine yeniden denenmez.
- Forward-only Kommo status köprüsü v1.3.19 davranışı aynen korunur:
  - yalnız ileri aşama PATCH edilir,
  - aynı aşamada status gönderilmez,
  - Kommo kartı ilerideyse geriye çekilmez,
  - pipeline uyuşmazlığında otomatik taşıma yapılmaz.
- Yeni `queue_health()` özeti program bazında queued/running/waiting/error/done durumlarını raporlar.
- **Program Bütünlüğü → Kommo / AI** satırı artık:
  - MMC Kommo profil ID,
  - Kommo Lead ID,
  - CRM durumu,
  - AI kaynak durumu,
  - mevcut Kommo aşaması,
  - hedef MMC aşaması,
  - ileri senkron/fark durumu,
  - bekleyen kuyruk sayısı,
  - geçmiş/son kuyruk hatası
  bilgilerini tek satırda gösterir.
- Kommo kartı hedef aşamadaysa satır sağlıklı olabilir.
- Hedefe ilerleme bekliyorsa, AI kaynağı `refresh_needed` ise veya kuyruk bekliyorsa Uyarı gösterilir.
- Kommo kartı beklenen MMC Program pipeline dışında ise Kritik gösterilir.
- DB şeması değişmez; `MMC_DB_VERSION` **1.3.7** olarak kalır.

## Güvenlik

- Otomatik kuyruk mevcut Lead ID üzerinde çalışır; kopya program kartı üretmez.
- Retry yalnız geçici hata sınıflarında yapılır.
- 400 validation ve yetki hataları tekrar tekrar Kommo'ya gönderilmez.
- Mevcut satış/WooCommerce/Kurumsal pipeline'larına dokunulmaz.
- İptal durumu hâlâ manuel Kommo yönetimindedir.
- Token/secret davranışı değişmez.

---

# Madagaskar Management Center v1.3.19

Bu sürüm **MMC Program Yaşam Döngüsü → Kommo Status Köprüsü** ekler ve mevcut kartların sıradan senkronla yanlışlıkla tekrar **Hazırlık** aşamasına çekilmesini engeller.

## v1.3.19 — Program Yaşam Döngüsü → Kommo Status Köprüsü

- Kommo program kartının mevcut `pipeline_id` ve `status_id` değeri senkron öncesinde salt-okunur okunur.
- MMC program durumları 10 Kommo program aşamasına konservatif olarak eşlenir:
  - Hazırlık → Hazırlık
  - Bölge Analizi / salon araştırması / tahsis bekleme → Bölge Planlandı
  - Salon Kesinleşti / Salon Ödemeleri → Salon/Tahsis Hazır
  - Etkinlik Hazırlığı → Etkinlik/Seans Hazır
  - Satışa Hazırlanıyor / Satışta → Satış Hazır
  - Tanıtım / Reklam → Tanıtım/Saha Aktif
  - Operasyon Hazırlığı / Gösteri Günü → Operasyon Hazır
  - Finansal Kapanış → Gösteri Tamamlandı
  - Teminat İadesi Bekleniyor → Finans/Kapanış
  - Tamamlandı → Arşiv
  - İptal → otomatik status değişikliği yapılmaz; manuel yönetim
- Mevcut Kommo kartı hedef aşamadan ilerideyse **geriye alınmaz**.
- Kart MMC dışı bir status'taysa otomatik status değişikliği yapılmaz.
- Kart beklenen MMC Program pipeline dışındaysa otomatik pipeline taşıması yapılmaz ve senkron hata verir.
- Yeni program kartı, MMC durumuna karşılık gelen hedef aşamada oluşturulur.
- Mevcut kartta status PATCH yalnız **ileri hareket gerekiyorsa** gönderilir.
- Eski davranıştaki her senkron PATCH'inde varsayılan `Hazırlık` status'unu yeniden gönderme kaldırıldı.
- Kommo & AI ekranına **Kommo Program Durum Köprüsü** paneli eklendi:
  - MMC Program Durumu
  - Kommo Mevcut Aşama
  - Hedef Aşama
  - Köprü Kararı
  - ileri taşıma / aynı aşama / geriye alma engeli / manuel durum açıklaması
- Kommo kart snapshot'ı kısa süre cache edilir; gerçek senkron sırasında zorunlu yeniden okunur.
- Status hareketleri MMC log'una `kommo_program_status_advanced` olarak yazılır.
- DB şeması değişmez; `MMC_DB_VERSION` **1.3.7** olarak kalır.

## Güvenlik

- Normal senkron mevcut Kommo kartını geriye taşımaz.
- Pipeline uyuşmazlığında otomatik taşıma yapılmaz.
- İptal durumunda otomatik status hareketi yapılmaz.
- Mevcut satış/WooCommerce/Kurumsal pipeline'larına dokunulmaz.
- Lead kopyası oluşturulmaz; mevcut `kommo_lead_id` üzerinde güncelleme yapılır.
- Token/secret davranışı değişmez.

---

# Madagaskar Management Center v1.3.18

Bu sürüm **Kommo pipeline oluşturma isteğini resmi API şemasına uygun hale getirir.**

## v1.3.18 — Kommo Embedded Statuses Fix

- v1.3.17 ile hata teşhisinde görülen:
  - `Kommo API 400 [/api/v4/leads/pipelines]`
  - `FieldMissing _embedded`
  hatası giderildi.
- Yeni `MMC — Program Yönetimi` pipeline oluşturulurken 10 MMC aşaması artık ilk pipeline POST isteğinin içinde:
  - `_embedded.statuses`
  alanı altında birlikte gönderilir.
- Böylece Kommo'nun **Add pipelines** endpoint'inin zorunlu pipeline-stage yapısına uyulur.
- Pipeline zaten varsa davranış değişmez:
  - kopya pipeline oluşturulmaz,
  - yalnız eksik MMC aşamaları `/statuses` endpoint'i üzerinden tamamlanır.
- Oluşturma sonrası pipeline tekrar salt-okunur okunur ve 10/10 aşama doğrulanır.
- Başarılı doğrulama sonrası **Hazırlık** status'u varsayılan MMC Program Status olur.
- Kommo'nun resmi desteklediği stage renkleri kullanılmaya devam eder.
- Son kurulum teşhis ekranı ve admin-rights ön kontrolü korunur.
- Veritabanı şeması değişmez; `MMC_DB_VERSION` **1.3.7** olarak kalır.

## Güvenlik

- Mevcut Satış, WooCommerce, Kurumsal Talepler ve diğer pipeline'lar değiştirilmez.
- Otomatik kurulum yok; açık kullanıcı onayı gerekir.
- Lead/müşteri/sipariş oluşturulmaz.
- Token/Authorization verileri loglanmaz veya ekranda gösterilmez.

---

# Madagaskar Management Center v1.3.17

Bu sürüm **Kommo Program Pipeline kurulumunun sessiz başarısızlıklarını teşhis eder** ve pipeline/stage yazma yetkisini kurulumdan önce salt-okunur doğrular.

## v1.3.17 — Kommo Kurulum Ön Kontrol & Hata Tanı

- Kommo `/account` yanıtındaki `current_user_id` okunur.
- Mevcut tokenın bağlı olduğu kullanıcı, `GET /api/v4/users/{id}` üzerinden salt-okunur doğrulanır.
- Kullanıcının `rights.is_admin` alanı görüntülenir:
  - Yönetici doğrulandı → kurulum devam edebilir.
  - Yönetici değil → pipeline kurulum düğmesi devre dışı bırakılır.
  - Yetki endpoint'i doğrulanamazsa → durum açıkça "Doğrulanamadı" gösterilir; kurulum denemesi ayrıntılı hata kaydı üretir.
- Kommo resmi API kuralı nedeniyle pipeline/stage oluşturma işlemleri yönetici yetkisine ihtiyaç duyar.
- API 4xx/5xx hata mesajları artık endpoint yolu ile birlikte ayrıştırılır.
- Validation error içeriği varsa güvenli/sade metin olarak kullanıcıya gösterilir.
- Son pipeline kurulum denemesi WordPress option içinde yalnız güvenli teşhis bilgisiyle saklanır:
  - tarih,
  - başarı/hata,
  - hata kodu,
  - HTTP status,
  - endpoint,
  - pipeline/status ID.
- Token, Authorization header veya API response secretları saklanmaz.
- Kurulum POST yönlendirmesi aktif `program_id` değerini korur.
- Böylece kullanıcı kurulumdan sonra aynı program ekranında kalır ve sonucu görür.
- DB şeması değişmez; `MMC_DB_VERSION` **1.3.7** olarak kalır.

## Güvenlik

- Admin ön kontrolü salt-okunur GET çağrısıdır.
- Pipeline kurulumu yine yalnız açık checkbox + tam onay metni + nonce ile çalışır.
- Mevcut Kommo pipeline'ları değiştirilmez.
- Lead/sipariş/müşteri oluşturulmaz.
- Token değeri hiçbir teşhis kaydına yazılmaz.

---

# Madagaskar Management Center v1.3.16

Bu sürüm **Kommo Program Pipeline Kurulum Merkezi** ekler. Kullanıcı açık onay vermeden Kommo'da hiçbir yazma işlemi yapılmaz.

## v1.3.16 — Kommo Program Pipeline Kurulum Merkezi

- Hedef pipeline adı: **MMC — Program Yönetimi**
- MMC program yaşam döngüsü için 10 aşama:
  1. Hazırlık
  2. Bölge Planlandı
  3. Salon/Tahsis Hazır
  4. Etkinlik/Seans Hazır
  5. Satış Hazır
  6. Tanıtım/Saha Aktif
  7. Operasyon Hazır
  8. Gösteri Tamamlandı
  9. Finans/Kapanış
  10. Arşiv
- Kurulum idempotenttir:
  - aynı isimli MMC pipeline zaten varsa yeni kopya oluşturulmaz,
  - yalnız eksik MMC aşamaları eklenir,
  - tüm aşamalar mevcutsa Kommo'ya yazma yapılmaz.
- Mevcut Satış, WooCommerce, Kurumsal Talepler ve diğer pipeline'lar değiştirilmez.
- Pipeline kurulumu için:
  - WordPress yetki kontrolü,
  - nonce,
  - onay checkbox'ı,
  - tam **MMC PROGRAM PIPELINE KUR** onay metni,
  - tarayıcı confirmation
  birlikte gereklidir.
- Kurulum tamamlandığında MMC:
  - yeni pipeline ID'yi doğrular,
  - 10/10 beklenen aşamayı yeniden okur,
  - **Hazırlık** status ID'sini varsayılan program status olarak bağlar,
  - `mmc_kommo_pipeline_id` ve `mmc_kommo_status_id` ayarlarını günceller.
- Kurulum işlemi hiçbir program lead'i oluşturmaz; Kırıkkale veya başka bir programın CRM senkronu ayrıca manuel başlatılır.
- Sağlık merkezinde yeni **MMC Program Pipeline Şeması** kontrolü:
  - kurulmadıysa Bilgi,
  - eksik aşama varsa Uyarı,
  - 10/10 doğrulanırsa Sağlıklı
  gösterir.
- DB şeması değişmez; `MMC_DB_VERSION` **1.3.7** olarak kalır.

## Kommo API yaklaşımı

Kommo pipeline ve stage oluşturma işlemleri yalnız açık kullanıcı onayından sonra yapılır. Pipeline/stage katalog okuma çağrıları salt-okunur kalır. Sistem stage'leri Kommo tarafından ayrıca yönetilir; MMC yalnız program yaşam döngüsü için düzenlenebilir aşamaları oluşturur.

## Güvenlik

- Pipeline kurulumu plugin aktivasyonunda veya cron ile otomatik çalışmaz.
- Mevcut pipeline ID'leri patch/delete edilmez.
- Lead, müşteri veya sipariş kaydı oluşturulmaz.
- WooCommerce/Tickera/PayTR/MDG/Okul Tanıtım verilerine yazma yoktur.
- Kommo tokenı ekranda veya WordPress option alanında saklanmaz/gösterilmez.
- Partial hata durumunda mevcut MMC pipeline silinmez; kurulum tekrar çalıştırıldığında eksik aşamalardan devam eder.

---

# Madagaskar Management Center v1.3.15

Bu sürüm **Kommo Pipeline Keşif Merkezi** ekler. Kommo hesabındaki mevcut lead pipeline ve status'lar salt-okunur keşfedilir; MMC Program Pipeline/Status seçimi ID yazmak yerine ekrandan yapılır.

## v1.3.15 — Kommo Pipeline Keşif Merkezi

- Kommo `/api/v4/leads/pipelines` endpoint'i salt-okunur taranır.
- Pipeline listesi ve her pipeline içindeki status adları/ID'leri MMC ekranında gösterilir.
- Liste 10 dakika cache edilir; **Pipeline Listesini Yenile** düğmesi manuel salt-okunur yenileme yapar.
- MMC Program Pipeline artık dropdown üzerinden seçilebilir.
- Varsayılan Program Status, seçilen pipeline'a ait status listesinden seçilir.
- Pipeline değiştirildiğinde status dropdown'u tarayıcıda otomatik yenilenir.
- Kaydetme sırasında seçilen pipeline Kommo hesabında yeniden doğrulanır.
- Status seçilmişse gerçekten seçilen pipeline'a ait olduğu doğrulanır.
- Geçersiz/eski pipeline veya başka pipeline'a ait status kaydedilmez.
- Eski `MS_KOMMO_PIPELINE_ID` keşif listesinde **legacy sipariş pipeline adayı** olarak etiketlenir; otomatik seçilmez.
- Sağlık ekranında seçili pipeline ve status isimleri ID'leriyle birlikte gösterilir.
- Kommo hesabında hiçbir pipeline/status oluşturulmaz, güncellenmez veya silinmez.
- Veritabanı şeması değişmez; `MMC_DB_VERSION` **1.3.7** olarak kalır.

## Güvenlik

- Keşif ve doğrulama çağrıları GET/salt-okunurdur.
- Token değeri ekranda veya WordPress option alanında tutulmaz.
- Pipeline seçimi yalnız MMC'nin `mmc_kommo_pipeline_id` ve `mmc_kommo_status_id` WordPress ayarlarını değiştirir.
- WooCommerce sipariş pipeline'ı ile MMC Program Pipeline otomatik birleştirilmez.
- Lead, müşteri, sipariş, Tickera, PayTR, MDG ve Okul Tanıtım kayıtlarına yazma yoktur.

---

# Madagaskar Management Center v1.3.14

Bu sürüm **mevcut çalışan Kommo entegrasyonunu MMC'ye güvenli biçimde köprüler ve canlı API/pipeline teşhisini sağlık merkezine taşır.**

## v1.3.14 — Kommo Legacy Bridge & Connection Diagnostics

- MMC Kommo servisi önce `MMC_KOMMO_TOKEN` / `MMC_KOMMO_SUBDOMAIN` kullanır.
- Geçiş döneminde bu değerler yoksa mevcut `MS_KOMMO_TOKEN` ve `MS_KOMMO_BASE_URL` sabitlerinden salt-okunur fallback yapabilir.
- Legacy token değeri hiçbir MMC ekranında gösterilmez, veritabanına kopyalanmaz veya loglanmaz.
- Canlı bağlantı testi Kommo `/api/v4/account` endpoint'ine salt-okunur GET çağrısı yapar.
- Bağlantı sonucu 10 dakika cache edilir; sağlık ekranı her açılışta gereksiz API çağrısı yapmaz.
- Sağlık merkezinde artık ayrı kontroller vardır:
  - **Kommo API** — canlı bağlantı ve hesap doğrulaması,
  - **Kommo Secret Kaynağı** — MMC secret mı, legacy secret mı,
  - **Kommo Program Pipeline** — explicit MMC pipeline doğrulaması.
- Eski `MS_KOMMO_PIPELINE_ID` yalnız legacy bilgi/adayı olarak gösterilir; otomatik MMC Program Pipeline yapılmaz.
- Explicit Program Pipeline ID tanımlıysa Kommo API'den pipeline ve Status ID üyeliği doğrulanır.
- Kommo ayar ekranında:
  - API bağlantı durumu,
  - subdomain ve kaynağı,
  - token kaynağı (değer gizli),
  - Program Pipeline,
  - legacy pipeline adayı,
  - güvenli wp-config geçiş şablonu
  gösterilir.
- Manuel **Kommo Bağlantısını Yeniden Test Et** butonu gerçek canlı testi zorlar.
- Veritabanı şeması değişmez; `MMC_DB_VERSION` **1.3.7** olarak kalır.

## Güvenlik

- Token değeri ekranda, logda, URL'de veya WordPress option alanında gösterilmez/saklanmaz.
- Eski tokenı MMC'ye kopyalamak yerine Kommo'dan yeni/yenilenmiş uzun ömürlü token üretip `wp-config.php` içinde `MMC_KOMMO_TOKEN` olarak taşımak önerilir.
- WooCommerce sipariş pipeline'ı ile MMC program takip pipeline'ı otomatik birleştirilmez.
- Kommo API testleri salt-okunurdur; lead/pipeline kaydı oluşturmaz veya değiştirmez.
- WooCommerce, Tickera, PayTR, QR, MDG ve Okul Tanıtım verilerine yazma yoktur.

---

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

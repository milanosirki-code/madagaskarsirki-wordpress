# Milano Sirki — 13.10.2026 yayın öncesi doğrulanmış durum ve geçiş kapıları

**Kaynaklar:** WordPress.com kullanıcı site envanteri, site `settings.get`, `domains.list` / `domains.get`, `plugin.list`, `pages.list`, `pages.get`, blok `navigation.list` ve `template-parts.list` — Milano site blog ID **257916618**. Salt okunur; bu denetim sırasında site yayınlanmadı, satış açılmadı.

## A. Alan adı ve altyapı — BAŞARILI

- Birincil alan adı `milanosirki.com`, kayıt durumu **active**, WordPress.com nameservers kullanılıyor, doğru WordPress.com'a işaret ediyor, **SSL active**.
- Site dili `tr_TR`, zaman dilimi `Europe/Istanbul`, site adı `Uluslararası Milano Sirki`.
- WordPress.com site envanteri: **atomic** site, health **healthy**, yalnız tanıtım beslemesi: `Milano Sirki - Ortak Etkinlikler (Taslak)` aktif (salt okunur, satış/ödeme yok).
- Logo / favicon medya ID **7**. Instagram `@milanosirkiturkiye`, WhatsApp `0536 358 33 81`, `milanosirki@gmail.com`.
- Site hâlâ `is_site_launched=false`, `is_coming_soon=true`, `blog_public=0`.
- 10 kurulu / 6 etkin eklenti; **WooCommerce, PayTR, Tickera yok**. Milano'da bilet satın alma/checkout açılmamış.

## B. Yayını engelleyen somut sunum eksikleri

- Okuma ayarı `show_on_front=posts`, `page_on_front=0`. **Site bu ayarlarla açılsa bile hazırlanan ana sayfa otomatik görüntülenmeyecek; boş blog görünümü riski var.**
- Sayfa **5**, `ana-sayfa`, taslak; yayınlanıp **statik ön sayfa** seçilmesi gerekiyor.
- Sayfa **6**, `sehirler`, taslak; turne takvimi hazırlanıyor ve henüz Milano etkinliği satışta değil yazıyor.
- Sayfa **14**, `iletisim`, taslak; telefon, e-posta, Instagram tanıtımı var.
- Sayfa **21**, `gosterilerimiz`, taslak; hayvansız gösteri bilgisi.
- Sayfa **22**, `sikca-sorulan-sorular`, taslak; Milano satışının kapalı olduğu açık.
- Sayfa **23**, `kurumsal-organizasyonlar`, taslak.
- Sayfa **1**, `hakkinda`, yayında (site Çok Yakında engelinde).
- Blok navigasyon ID **3** yalnız `<!-- wp:page-list /-->`; yayımlı tek sayfayı listeliyor. Özelleştirilmiş Milano header yalnız marka ve Instagram/WhatsApp linkleri içeriyor; **ana sayfa, şehirler, gösteriler, iletişim ve KVKK'ya açık üst menü yok**.
- KVKK aydınlatma, gizlilik/çerez ve gerekiyorsa mesafeli bilet satışı/iptal/iade metinleri Milano sayfa envanterinde **yok**. Kullanıcıya dönük resmî yasal bilgiler doğrulanmadan uydurulmaz.
- Google arama görünürlüğü `blog_public=0` (kapatıldı/dizinleme caydırılıyor). Açılışta siteyi indekslemek için uygun ayarlar ayrıca onaylanmalı.

## C. ÜÇ AYRI YAYIN SEVİYESİ

### 1) **Tanıtım yayını** — güvenle hazırlanabilir ama henüz uygulanmadı

Önerilen net değişiklikler (WordPress.com işlemleri için kullanıcıya kapsam gösterilip **ayrıca onay alınmalı**):
1. Altı mevcut taslağı yayınla: ID **5, 6, 14, 21, 22, 23**; yararlı başlıklar ve mevcut logosu korunacak.
2. Site `show_on_front=page`, `page_on_front=5` yap.
3. Blok üst menüsünü gerçek sayfa bağlantılarıyla düzenle, menü bozuk bağlantı testi yap.
4. KVKK/gizlilik/çerez belgelerini doğrulanmış veri sorumlusu ve süreçlerle hazırla, yasal onaya göre yayınla. İletişim metnindeki şirket/işletme bilgilerini teyit et.
5. `blog_public=1` ile arama dizinine uygun görünürlük ve `manage-site.launch` ile **sadece Milano 257916618** sitesini yayına al; öncesi/sonrası canlı HTTP ve ana sayfa kontrolü yap.
6. Sitede satın al/bilet öde/PayTR/Tickera düğmeleri **aktifleşmeyecek**; takvim bilgi/WhatsApp iletişimi amaçlı kalacak.

### 2) **Milano çevrimiçi bilet satışı** — BLOKE

- Mevcut Milano site plugin listesinde WooCommerce/PayTR/Tickera yok. Merkezi satış yetkisi, iki marka arasında **tek etkinlik = tek site** kesinleştirmesi ve güvenli checkout yok.
- GitHub PR #235 **draft, unmerged**, ödeme/order slot ledger, aile paket normalleştirici staging sözleşmesinden ibaret. Çalışan üretim orkestrasyonu, son koltuk atomik kapasite, PayTR alan adı/site merchant izni, gerçek Tickera Bridge before-create yolunun doğrulaması, refund/check-in regresyonu tamamlanmadı.
- Madagaskar read-only envanterde `wc-failed` aile bileşenlerinden **14 sipariş / 54 publish Tickera instance** tespit edilmişti; başarılı PayTR `paytr_order_id` yok; kurulu `tickera_order_is_paid` filtre uygulaması doğrulanmadan bunlar fiilî giriş yetkisi sayılmaz ama ödeme durumlarının mutabakatı yüksek öncelikli.
- Bu kontroller tamamlanmadan checkout/bilet satış butonu açma; PayTR prod anahtarları taşıma ve Madagaskar üretim callback'ine müdahale **yok**.

### 3) **Madagaskar üretim sistemi** — KORUNUR

Madagaskar etkinlikleri başka markaya geçirilmez, mevcut QR/bilet/kapasite/satış verileri değiştirilmez. Kapsam yalnız Milano bilgi sitesi tanıtım yayını ile sınırlandırılır.

## Kanıt ve takip

- PR: https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/235
- Proje günlük kayıtları: https://docs.google.com/document/d/1GSV-_niF39NTkB45--sqodofxDVH25S7XeKxefzUa4Q/edit
- **Bu dosyanın hazırlanması site yayını değildir.** WordPress.com içerik, ayarlar, menü, site launch yazma işlemleri kullanıcı onayı bekler.

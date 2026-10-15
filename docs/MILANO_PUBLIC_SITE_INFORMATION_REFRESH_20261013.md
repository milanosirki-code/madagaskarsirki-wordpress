# Milano Sirki — canlı site içerik / bilgi güncellemesi

**Tarih ve saat:** 13 Ekim 2026, yaklaşık 16.10 TRT. **Hedef:** `https://milanosirki.com`, WordPress.com site ID `257916618`. **Gerçek WordPress sayfa değişiklikleri tamamlandı ve yeniden okundu.**

## Kullanıcı talebi
“siteye ilişkin blgileri güncelle”

## Değiştirilen canlı, yayınlanmış sayfalar

### 1. Ana Sayfa `/ana-sayfa/` / `https://milanosirki.com/`, page ID 5

- Gutenberg `core/group`, `id=gosteri-takvimi` bölümünde önceki **“şehir, tarih, bilet bilgilerini çok yakında paylaşacağız”** mesajı artık gerçeğe uygun değildi: `/sehirler/` içinde Yalova duyurusu mevcut.
- Bu tek bölümü **`page-sections.replace`, edit blok hash + content hash + modified optimistic lock** ile güncelledik; diğer hero, logo, Instagram, gösteri kartları, footer veya menü değiştirilmedi.
- Yayındaki yeni bilgiler:
  - **Yalova — 22 Ekim 2026 Perşembe, 90. Yıl Kapalı Spor Salonu**
  - **Bahçelievler Mahallesi, Turhan Koçal Caddesi No:13**
  - **18.00 ve 19.30**, **serbest oturma**, **500 kişi/seans**
  - **Yetişkin 490 TL**, **3–12 yaş çocuk 245 TL**, **2 yetişkin + 2 çocuk aile 1.100 TL**
  - `/sehirler/` etkinlik bilgi sayfası ve kullanıcı tarafından sağlanan `https://maps.app.goo.gl/RnN2pZFTMoMD2W237?g_st=ac` salon harita bağlantısı.
- **“Bu sitede online bilet satışı henüz aktif değildir.”** uyarısı korunmuştur. Satın al, checkout, PayTR veya QR bağlantısı oluşturulmadı.
- Yayımlanmış sayfa son doğrulama: modified **2026-10-13T16:09:06**, `status=publish`. WPVibe `get_page_html(selector="#gosteri-takvimi")` gerçek HTML'de tüm yeni Yalova bilgilerini ve satış uyarısını gösterdi.

### 2. Sıkça Sorulan Sorular `/sikca-sorulan-sorular/`, page ID 22

- Uzun `core/html` SSS metnine müdahale etmedik; editördeki blok kurtarma riskini artırmamak için **bir adet yerel Gutenberg `core/group`** bölümünü, var olan son CTA'nın önüne `page-sections.insert` ile ekledik.
- Aynı **Yalova tarihi, salon, adres, seans, 500 kapasite, 490/245/1100 TL**, 0–2 yaş ücretsiz kuralı ve çocuk refakati; `/sehirler/` bilgi sayfasına bağlantı.
- **Aktif satış olmadığına dair açık uyarı** korundu.
- Yayımlanmış sayfa son doğrulama: modified **2026-10-13T16:09:55**, `status=publish`.

### 3. İletişim `/iletisim/`, page ID 14

- Mevcut iletişim kartları/numara, Instagram ve bağlantılar korunarak bir **yerel Gutenberg `core/group` “Resmî işletme bilgileri”** bölümü ekledik.
- Marka **Uluslararası Milano Sirki**.
- Organizasyon/sağlayıcı: **Dünya Organizasyon Medya Turizm Eğitim Danışmanlık Reklam Seyahat Acenteliği Limited Şirketi**.
- Adres: Kızılay Mahallesi, Menekşe 1 Caddesi No:3 İç Kapı No:11 Çankaya / Ankara; Ticaret Sicil **479730**.
- E-posta `milanosirki@gmail.com`, WhatsApp `0536 358 33 81`, KVKK ve Gizlilik Politikası bağlantıları.
- Yayımlanmış sayfa son doğrulama: modified **2026-10-13T16:10:32**, `status=publish`. WPVibe `get_page_html(selector="main")` gerçek HTML'de tüm işletme bilgilerini gösterdi.

## Değiştirmeden kontrol edilenler

- **`/sehirler/` page 6:** Yalova duyurusu zaten kapsamlı biçimde doğru: 22 Ekim, salon, harita, seans, üç fiyat, 0–2 / 3–12 / 13+ yaş grubu, 500/seans, serbest oturma, satışın kapalı olduğu uyarısı. Bu sayfa yeniden yazılmadı.
- **WordPress genel site ayarları (okuma):** `blogname=Uluslararası Milano Sirki`, tagline `Türkiye Turnesi • Akrobasi, heyecan ve ailece unutulmaz gösteriler`, admin e-posta `milanosirki@gmail.com`, `language=tr_TR`, `timezone_string=Europe/Istanbul`, `users_can_register=false`, site public. Genel ayarlarda gereksiz write yapılmadı.
- Hukuk sayfaları `/kvkk-aydinlatma-metni/`, `/gizlilik-politikasi/`, `/cerez-politikasi/` yayında, şirket/adres bilgileri mevcut; aynen korunmuş.
- Instagram `https://www.instagram.com/milanosirkiturkiye/`; WhatsApp `https://wa.me/905363583381` doğru; aynen korunmuş.

## Canlı satış / entegrasyon kapsamı ve ertelenenler

- Bu güncelleme **yalnızca bilgilendirme amaçlı üç sayfanın metin/bölüm güncellemesidir**. Canlı üretimde Madagaskar'ın MMC, MDG, WooCommerce, Tickera, PayTR, seans, stok, sipariş, QR veya CRM tablolarına yazma yapılmadı.
- Bağlı projenin son teknik durumuna göre 13 MMC programının hepsi Madagaskar; **Milano marka/Yalova için merkezî MMC satış programı henüz mevcut değil**. İlk etkinliğin teknik hazırlanması diğer sohbette devam ediyor.
- Geliştirme kodu PR #235 / PR #239 hâlâ taslak ve deploy edilmedi. Milano online satış/ödeme/QR kesinlikle açılmadı.
- PayTR ikinci domain (Milano) yazılı onayı, gerçek Woo/MDG hold, callback ve 4 QR işlemleri için uçtan uca testler; Türkiye çerez tercih/Complianz paneli henüz doğrulanmadı. Bu kontroller tamamlanmadan “Bilet Al” aksiyonu eklenmez.
- Gutenberg'deki diğer eski `core/html` bloklarının editör kurtarma problemleri ayrıca değerlendirilmeli; bu turda doğrudan eski blokları değiştirmedik.

## Doğrulama

WordPress.com **pages.get** `context=edit` ile 5, 6, 14, 22 numaralı sayfaların yayınlı statüsü ve içerik kontrolü yapıldı. Ana sayfa ve iletişim sayfasının **gerçek HTML çıktıları** ayrıca görüntülendi.

**Canlı sayfalar:**
- https://milanosirki.com/
- https://milanosirki.com/sehirler/
- https://milanosirki.com/sikca-sorulan-sorular/
- https://milanosirki.com/iletisim/

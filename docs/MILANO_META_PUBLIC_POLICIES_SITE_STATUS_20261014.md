# Milano Sirki — Meta politikaları yayını ve site son durum (14 Ekim 2026)

## Gerçekte gerçekleştirilen WordPress değişiklikleri — CANLI TANITIM SİTESİ
- `https://milanosirki.com` WordPress admin doğrulaması: Assembler teması, WP 7.1.3, 10 önceden yayınlanmış sayfa ve markaya özgü tanıtım sitesi.
- Mevcut `/gizlilik-politikasi/` sayfası **#34**, yeni yinelenen sayfa oluşturulmadan mevcut içerikte güncellendi; Meta/Instagram'a giden linkler ve gömülü içerikler için üçüncü taraf veri işleme açıklaması, gelecekte Meta Pixel/Conversions API için gerekli rıza ve teknik şartlar, veri erişimi/silme talebi ve iade politikası bağlantısı eklendi. WordPress content/edit match-once, replaced=1; tarih 14.10.2026; status=publish ve okuma ile doğrulandı.
- Yeni `/iade-ve-iptal-politikasi/` **#115**, **publish**, WordPress GET ve render edilen sayfa HTML'iyle doğrulandı; işletme kimliği, tarihli eğlence etkinliklerinde cayma hakkı istisnasının kapsamı, etkinlik iptali, erteleme, müşteri talebi, ödeme hataları, destek ve yasal haklar dahil. Politika satışın şu an kapalı olduğunu açıkça belirtiyor.
- Canlı Assembler `assembler//footer` şablonuna tek bir `/iade-ve-iptal-politikasi/` bağlantısı eklendi. Footer HTML render testi yeni linki içeriyor; önceki KVKK/Gizlilik/Çerez linkleri korunuyor.
- Önceden canlıda var: `/kvkk-aydinlatma-metni/` #33, `/cerez-politikasi/` #35; tekrar yaratılmadılar. Ana sayfa #5, şehirler #6, SSS #22, iletişim #14 vb publish. Gizlilik ve iade sayfası public link biçiminde; platform dışı Meta bot erişim testi bu çalışmada yapılmadı.

## Mevcut gerçek biletleme aşaması
- `milanosirki.com` canlı tanıtım sitesi; WooCommerce/Tickera/PayTR canlı checkout'u bu sitede yok, satış KAPALI.
- 22 Ekim 2026 Yalova, 90. Yıl Kapalı Spor Salonu, 18:00 ve 19:30, 500'er serbest oturma, yetişkin 490, çocuk 245, aile 2+2 1.100 TL bilgi olarak yayında.
- Staging: Yalova MMC #14 / MDG #24, Woo draft 6058/6061, Tickera draft 6056, Designer #28/#29, venue QR #6047; seans 150/151 ve marka ayrımı testleri; **hiç canlı ödeme, sipariş, giriş QR veya satış açılışı yapılmadı**.
- PR #235 (brand routing), #246 (MDG branding), #247 (Milano admin workspace), #248 (Tickera last-session end) 14.10.2026 itibarıyla **open draft, unmerged**. PR #247 Milano salt okunur yönetim menüsü staging'de aktif; canlı değil.
- Gerçek PayTR ikinci alan adı/callback onayı, baştan sona staging ödeme, dört ayrı aile QR bileti ve gerçek PDF denetimi yapılmadan satış açılmamalı.

## Mevzuat dayanakları ve sınırlamalar
- KVKK aydınlatma kapsamı: https://www.kvkk.gov.tr/Icerik/2033/Aydinlatma-Yukumlulugu-
- Zorunlu olmayan analitik/reklam çerezleri açık rıza uygulaması: https://www.kvkk.gov.tr/Icerik/7595/2022-1358
- Mesafeli Sözleşmeler Yönetmeliği 15(g), tarihe bağlı eğlence sözleşmeleri için cayma istisnası: https://www.lexpera.com.tr/mevzuat/yonetmelikler/mesafeli-sozlesmeler-yonetmeligi
- T.C. Ticaret Bakanlığı mesafeli sözleşmeler tüketici bilgilendirmesi: https://tuketici.ticaret.gov.tr/yayinlar/tuketici-bilgi-rehberi/mesafeli-sozlesmeler-hakkinda-bilgilendirme

## Açık kontroller
1. Complianz çerez rızası Türkiye opt-in paneli ve gerçek çerez taraması tamamlanmalı; Meta Pixel/GA4 rızadan önce etkinleştirilmemeli.
2. Satış başladığında gizlilik/KVKK/çerez/mesafeli satış koşulları ve gerçek hizmet sağlayıcılar yeniden gözden geçirilmeli.
3. Meta reklam hesabındaki politika URL alanlarına yeni gizlilik ve iade URL'leri ilgili Meta panelinde ayrıca girilmeli (bu işlem yapılmadı).
4. İşletme/mesafeli sözleşme şartları yayından önce hukuki ve operasyonel ekip tarafından son kontrolden geçirilmeli.

**Yalnız Milano tanıtım sitesinin hukuki sayfalarına müdahale edildi. Madagaskar WooCommerce/PayTR/Tickera canlı verileri ve biletleri değiştirilmedi.**

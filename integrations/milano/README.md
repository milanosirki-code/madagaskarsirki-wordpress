# Milano Sirki — ortak etkinlik akışı (pilot)

Durum: **GitHub taslağı; Milano sitesine yüklenmedi.** Hedef: `https://milanosirki.com` (WordPress.com Premium / Atomic).

## Amaç
Madagaskar Sirki ve Milano Sirki, **aynı fiziksel program, aynı seanslar ve aynı kapasiteyi** kullanacaktır. Marka yalnız ön yüzde ve satış kanalına ait kayıtlarda ayrılmalıdır. İkinci WooCommerce ürün, bilet stoğu veya Tickera QR üretmek kesinlikle yasaktır.

## v0.1.0 uygulanan
- `[milano_ortak_etkinlikler]` shortcode — WordPress yönetici önizlemesi.
- Salt okunur, sabit URL: mevcut `/wp-json/wp/v2/pages/1311?_fields=content` yayındaki şehirler sayfası.
- Yalnız şehir, tarih, salon, seans alanlarını ayrıştırır. Fiyat, checkout bağlantısı, müşteri, sipariş, bilet ve QR verisi çekmez.
- Beş dakika başarılı yanıt önbelleği.
- **Halka açık veri akışı kapalı:** yönetici olmayanlarda "Etkinlik takvimi hazırlanıyor" mesajı. Yönetici önizlemesinde verinin *kapasite ve güncellik açısından doğrulanmadığı* açıkça gösterilir.
- Kaynak kesilirse checkout'a yönlendirme ya da sahte stok yerine güvenli mesaj.
- Kaynakta geçen diğer marka adı UI'da gösterilmez.

## Henüz yapılmayanlar
1. MMC/MDG satışa açık seanslardan **yapısal ve güncel** `GET /dunya/v1/public-sessions` tarzında yetki ve gereksiz veri sınırları belirlenmiş, salt-okunur kaynak geliştirilecek. Mevcut HTML parse yöntemi nihai kaynak kabul edilmez.
2. `sales_open`, iptal, erteleme, gerçek saat/dilimi, satış kilidi ve kalan kapasite sunucu tarafında doğrulanmadan program halka açılmayacak.
3. Milano müşteri siparişlerinde `_dunya_sales_brand=milano` gibi **değişmez sipariş-meta alanı** kaydı (öneri), payment callback ve tekrar işlemde idempotency testleri.
4. Aynı WooCommerce ürün/varyasyon, aynı Tickera event+QR, aynı kapasite servisi. Farklı stok kesinlikle oluşturulmayacak.
5. PayTR ikinci alan adı / callback / dönüş adresleri, PCI/kişisel veri, bilet PDF logo, ödeme e-postası ve Kommo gönderici kimliği ayrı pilotta incelenecek.
6. İade, aile paketi 2+2 (4 gerçek bilet), çakışan eşzamanlı satın alma, satışı kapanmış seans, başarısız ödeme için testler.
7. Milano logo ve kurumsal renkleri sağlanınca final tasarım.

## Canlıya alma güvenliği
- Bu prototip **aktif ödemeye bağlanamaz**; shortcode hiç satış yapmaz.
- GitHub ayrı branch ve draft PR üzerinden inceleme; `main`e otomatik merge veya WordPress.com otomatik deploy yok.
- Önce statik önizleme, sonra güvenilir MMC salt-okunur kaynak, en son checkout pilotu.
- Madagaskar canlı WooCommerce/Tickera/PayTR/Kommo hook'ları bu dosyadan etkilenmez.

## Kaynak notları
- `MMC_Program_Service::create_program()` yeni programlar için `brand=Madagaskar Sirki` alanı yazıyor. **Aynı fiziksel programı Milano için çoğaltmayın**; marka, satış siparişi/kaynağı seviyesinde ayrı tutulmalı.
- `MMC_Sales_Service::mappings()` MMC event/session/ticket type ile WooCommerce ürünü/varyasyonu ve Tickera kayıtlarını bağlıyor.
- Mevcut USKD entegrasyonu da kamuya açık şehirler HTML'ini okuyor; burada sadece *taslak/önizleme* amaçlı yeniden kullanıldı.

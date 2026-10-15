# Sakarya satışa açma çalışma kaydı — 15 Ekim 2026
Durum: TAMAMLANMADI; satış açılmadı. Son doğrulanmış WooCommerce ürün durumları draft.
Kapsam: Yalnız Madagaskar Sirki Sakarya/Adapazarı, 25.10.2026. Diğer etkinliklere, Milano kayıtlarına, mevcut sipariş ve biletlere yazılmadı.

## Korunan kimlikler
MMC program 15; kod PRG-2026-SAK-ADAPAZ-001; MMC etkinlik 15; MDG etkinlik 25; salon 86; salon bağlantısı 15.
MMC seansları 36/37/38, saatler 12:00/14:00/16:00, her biri 500 kişi.
MMC biletleri çocuk 43 (245 TL), yetişkin 44 (490 TL), aile 45 (1100 TL).

## Yapılan canlı işlemler
- Mevcut MDG 25 taslağı açıldı; aile modülünün etkin ve 1100 TL olduğu doğrulandı.
- Mevcut medya 6387 kapak olarak seçildi; yeni afiş yüklenmedi.
- Kısa ve uzun tanıtım, yaş/oturma/kapı/organizatör bilgileri, SEO başlık/açıklaması kaydedildi.
- Tamamen hayvansız gösteri, uluslararası sanatçılar, Etiyopya ve Tanzanya ekibi, akrobasi, denge, jonglörlük ve palyaço içeriği eklendi.
- Üretim öncesi WooCommerce Sakarya ürün sorgusu boş döndü.
- Mevcut MDG üretim motorunun dry-run kontrolü geçti; motor bir kez çalıştırıldı. Sonraki REST okuması 3 taslak ürün ve 6 varyasyon oluşturulduğunu doğruladı.

| Seans | MDG seans | WooCommerce ürün | Çocuk varyasyonu | Yetişkin varyasyonu | Tickera |
|---|---:|---:|---:|---:|---:|
|12:00|159|6390|6391|6392|6388|
|14:00|160|6393|6394|6395|6388|
|16:00|161|6396|6397|6398|6388|

Üretim anahtarı MDG-E25-fd49d467b3ce. Üç ürün draft; altı varyasyon mevcut.
Ticket Designer d_30 / tc_designer_template_id 30 ürünlerde doğrulandı.
MDG taslak düzenleme işlemi seans satırlarını kendi standart kaydetme mantığı ile yeniden yazdı: önce 156–158, sonra 159–161. MMC seans kimlikleri değiştirilmedi.
Henüz yayın olmadığından bundan sonraki adımda taslağı tekrar kaydedip mapping kaybetmekten kaçınılmalı.

## Mimari ve doğrulama sınırları
- Canlı mevcut üretici kaynak kodu 1 Tickera etkinliği + seans başına WooCommerce variable product stratejisi kullanıyor; ayrı 3 Tickera üretilmedi.
- Üç ürünün _event_name değeri 6388, _mdg_event_id 25, doğru seans kimlikleri ve _mdg_managed=1.
- Aile modülü arayüzü mevcut 2 çocuk+2 yetişkin varyasyonunu kullanarak 4 QR ürettiğini belirtiyor. Ayrı aile ürünü oluşturulmadı. Fiili sepet fiyat/kapasite testi henüz yapılmadı.
- Üretici Tickera limit_level=1 ve limit_level_value=500 yazıyor. Bunun tüm seansları 500 toplamla sınırlamaması için yayın öncesinde Bridge limit davranışı ayrıca doğrulanmalı.
- Kullanıcı 3 ayrı Tickera etkinliği istiyor; mevcut motor tek Tickera üretti. İlgili fark çözülmeden şartın tamamlandığı söylenemez.
- MMC 6/6 henüz doğrulanmadı/aktarılmadı.
- PayTR bu çalışmada canlı ödeme ekranında doğrulanmadı.
- Güvenli QR/PDF üretimi, doğru seans saati, Checkinera ve Kommo sipariş akışı test edilmedi.
- Mobil görünüm ve etkinlikler/bilet al/ana sayfa kartları henüz kontrol edilmedi.
- Hedef public URL boşta kontrolü ve yayını henüz yapılmadı: https://madagaskarsirki.com/etkinlik/madagaskar-sirki-sakarya-25-ekim-2026/
- Gerçek para tahsilatı ve test siparişi oluşturulmadı.

## Erişim engelleri
Work Cloud Browser üretim onayı sonrası 300 saniyelik zaman aşımına girdi. Sonraki okuma ve oturum sıfırlama da aynı şekilde zaman aşımına uğradı.
WPVibe günlük hesap kotası bu sırada doldu; daha fazla WPVibe çağrısı yapılmadı. Servisin verdiği yaklaşık tekrar erişim zamanı 15.10.2026 07:50 UTC / 10:50 Türkiye. Bu saat erişim garantisi değildir.
Sakarya satışa açılmadı; doğrulanmamış ödeme/QR zinciri halka sunulmadı.

## Devam sırası
1. Mevcut ürünleri ve MDG 25 mappingini tekrar oku; yeniden üretme.
2. Üç ayrı Tickera şartını mevcut motor ve örnek etkinliklerle çöz; seans PDF saatini ve ortak kapasiteyi doğrula.
3. Güvenli yönetici sepet/checkout testi: çocuk 245, yetişkin 490, aile 1100/4 kişi; başarısız ödemede QR yok.
4. MMC satış eşleştirmelerini mevcut import-legacy/servis akışıyla 6/6 tamamla.
5. MDG production readiness ve kontrollü yayın motorunu kullan; hedef slug çakışmasını kontrol et.
6. Üç seans ve katalog kartları, PayTR, QR/PDF, Checkinera, Kommo ve mobil kontrolünü tamamla. Kritik hata varsa satışı kapalı tut.
7. Bu kaydın yanına doğrulanmış sonuç kaydı ekle; bu kısmi kaydı başarı raporu gibi sunma.

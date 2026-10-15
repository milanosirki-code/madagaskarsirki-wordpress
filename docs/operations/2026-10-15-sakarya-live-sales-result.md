# Sakarya canlı satışa alma — 15 Ekim 2026
Bu kayıt, docs/operations/2026-10-15-sakarya-sales-draft-handoff.md dosyasındaki önceki kısmi durumu tamamlayan yeni çalışma kaydıdır. Önceki kayıt silinmedi.

## Gerçekleşen işlemler
- Work Cloud Browser oturumu sıfırlandı ve canlı WordPress yönetimine erişildi.
- MDG Ayarlar / Canlı Yayın Hazırlık Kontrolü: Sakarya #25 için içerik, salon/Maps/QR, 3 ürün/6 varyasyon, Designer #30, WooCommerce, Tickera, Bridge, PayTR ve atomik ortak kapasite kontrolleri hazır olarak görüldü.
- Müşteri önizlemesinden aile paketi 1 adet seçildi: 4 kişi ve 1100 TL.
- Sepette çocuk varyasyonu 2 adet + yetişkin varyasyonu 2 adet doğrulandı; ara toplam 1470 TL, aile indirimi 370 TL, toplam 1100 TL.
- Ödeme ekranına geçildi: 4 katılımcı alanı, doğru Sakarya/12:00 ürünleri ve Kredi/Banka Kartı (PayTR) görüldü. Sipariş Ver tıklanmadı; tahsilat yapılmadı.
- MDG mevcut kontrollü yayın motoruyla yalnız etkinlik #25 yayına alındı. Başarı bildirimi ve Yayındaki Etkinlikler listesinde Satışta / 0-1500 görüldü.
- MMC Satış ekranında MDG #25 önizlemesi yapıldı ve Doğrulanan 6 Satış Bağını Kur işlemi uygulandı. Eşleştirme 6/6, PayTR aktif, WooCommerce aktif, Tickera algılandı.
- Son 1 gün siparişleri mevcut HPOS uyumlu servisle tarandı: 32 sipariş, Sakarya ile eşleşen 0 satış satırı. Sakarya kapasitesi 500+500+500, satılan 0.
- Canlı sayfada 14:00 ve 16:00 seçilip çocuk+yetişkin bileti eklendi; her seans için 735 TL hesaplandı ve doğru saatli ürünler sepette görüldü.
- Deneme sepetindeki yalnız Sakarya satırları kaldırıldı; Alışveriş çantanız boş sonucu doğrulandı.
- /bilet-al/ listesinde Sakarya kartı, tarih, üç seans, 245 TL başlangıç fiyatı ve doğru canlı bağlantı doğrulandı.
- /sehirler/ listesinde Sakarya YAKLAŞAN GÖSTERİ / SATIŞTA, üç seans ve bağlantı doğrulandı.
- Masaüstü canlı bilet seçim/fiyat ekranı görsel kontrolü yapıldı ve ekran görüntüsü kaydedildi.
- Ticket Designer #30, Sakarya adına ve 9 ticket object (3 parent+6 varyasyon) atamasına sahip. Open PDF ile Sample Event adlı örnek PDF blob sekmesi üretildi. Tarayıcı güvenlik politikası blob sekmesinin açılmasını reddetti; alternatif yoldan erişim denenmedi.

## Canlı adres
https://madagaskarsirki.com/etkinlik/madagaskar-sirki-sakarya-adapazari-25-ekim-2026/
Mevcut motorun oluşturduğu adres budur; talepteki kısa adrese yeni sayfa/yönlendirme eklenmedi. Kısa slug şartı henüz tamamlanmış sayılmaz.

## Kimlikler
MMC program/event 15; MDG event 25; salon 86; medya 6387.
|Seans|MMC seans|MDG seans|Ürün|Çocuk|Yetişkin|Tickera|Kapasite|
|---|---:|---:|---:|---:|---:|---:|---:|
|12:00|36|159|6390|6391|6392|6388|500|
|14:00|37|160|6393|6394|6395|6388|500|
|16:00|38|161|6396|6397|6398|6388|500|
Designer 30; production key MDG-E25-fd49d467b3ce.
MMC eşleştirme: 6/6; aile ayrı ürün değil, 2 çocuk+2 yetişkin gerçek varyasyonu kullanır.

## Açıkça belirtilmesi gereken sınırlamalar
- Üretim motoru tek Tickera etkinliği+üç seans ürünü desenini kullanır. Manisa örnekleri de aynı desenle doğrulandı. Kullanıcının üç ayrı Tickera isteği uygulanmadı; mimari değiştirilmedi.
- Gerçek ödeme / başarısız ödeme / gerçek QR instance / gerçek Sakarya PDF'si / Checkinera taraması / siparişin Kommo'ya fiili aktarımı test edilmedi.
- PDF önizlemesi üretildi ama güvenlik engeli nedeniyle incelenemedi; doğru tarih/salon/seans basıldığı iddia edilmemeli.
- Mobil cihaz boyutunda gerçek render kontrolü tamamlanamadı; masaüstü görsel ve bilet seçim işlemleri kontrol edildi.
- Ana sayfa mevcut tasarımı yalnız en yakın 3 gösteriyi gösteriyor (Uşak, Didim, Aydın). Sakarya henüz bu üç kart arasında değil. Şehirler ve Bilet Al listesinde var. Ana sayfa düzenine dokunulmadı.
- Kommo MMC program lead #28694130 synced; API bağlı. 11:11:32 işlerinin done olduğu ve son senkron 11:12:15 görüldü. Program ekranında sales_prep ve kaynak güncelliği uyarısı vardı; birleşik AI kaynak akışı değiştirilmedi, müşteri mesajı gönderilmedi.
- Mevcut salon snapshot adı Atatürk Spor Salonu; açıklama ve afişte Atatürk Kapalı Spor Salonu yazıyor. Adres aynı No:118 Adapazarı. Mappingi korumak için canlı MDG taslağı yeniden kaydedilmedi.
- WPVibe kotası kontrolünde 539/500 görüldü; yeni yaklaşık erişim 09:00 UTC/12:00 Türkiye. Sonraki işlemler yalnız tarayıcıdan yapıldı.
- Başka Madagaskar/Milano etkinliklerine veya mevcut müşteri sipariş/biletlerine yazılmadı.

## Sonuç
Ana hedef gerçekleştirildi: Sakarya 25 Ekim 2026 üç seans canlı satışta, bilet seçimi/sepete aktarım ve PayTR checkout görünümü çalışıyor; MMC 6/6.
Uçtan uca gerçek ödeme sonrası bilet ve mobil doğrulama kapsamı tamamlandı şeklinde sunulmamalı.

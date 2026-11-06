# A0 — Canlı katalog ve kaynak doğrulaması
Kontrol: 2026-11-05 05:31:39 UTC. Hedef: madagaskarsirki.com. Saat dilimi: Europe/Istanbul.
Kaynak main: 83c04d8857ed9d312c872533ae653e6a1fc07393.
Durum: A0 incelemesi tamamlandı; Keşan durum eşleşmesi ve etkileşimli aile paketi doğrulaması açık. Yeni asistan yazılımı uygulanmadı.

## Sonuç
Mevcut sistemin üzerine katalog adaptörü ve hesaplama servisi geliştirilebilir. Gelecekteki 8 Madagaskar etkinliğinin 17 MDG seansı, 34 çocuk/yetişkin varyasyonu ve 8 tekil aktif MMC↔MDG köprüsü kontrol edildi. Kontrol edilen 34 varyasyonun tamamında WooCommerce fiyatı MDG bilet fiyatıyla eşleşti; publish, purchasable=true ve instock okundu. Bunlar anlık, kimliği doğrulanmış API bulgularıdır; ödeme tamamlanması veya misafir kanalının tüm kuralları test edilmedi.

Yeni asistan için 7 etkinlikte temel marka/durum/katalog uyumu var. Keşan'ın 2 seansı ise durum çelişkisi çözülene kadar yeni öneri katmanında kapalı kalmalı. Bu öneri mevcut sitedeki satışı kapatma talimatı değildir.

## Canlı sürümler ve kaynak sahipliği
Aktif: AI Abilities 0.7.0; MMC 1.3.49; MDG 3.6.3-ticket-invalidation-dry-run; Aile Paketi 1.1.3; Kommo Automation 0.1.1; WooCommerce 11.2.2; PayTR 3.1.2; Tickera 3.6.0.9.

AI modül option'ı 18 modül içeriyor; program-venue-event-sales aktif listede, legacy write migration modülü listede yok. Bu bilgi eski 0.6.1/kapalı modül arşivlerinden daha yeni canlı okumadır.
Snippet #70 ve #101 pasif; #110 Unified Source V2 ve #186 kaynak tazelik ekranı aktif. #110 kodunun SHA256'sı, main V2 kaynağından yalnız PHP açılış satırı çıkarıldığında birebir eşleşti: fb6865b17e3953419030947de7d049739c8e4fabbd11b61f199e421661b4074a; 20.689 bayt.

Canlı class-mdg-live-sales.php (426 satır), class-mdg-public-event.php (365 satır), aile eklentisi ana dosyası (735 satır) numaralı kaynak okumalarından yeniden kuruldu ve main satır içerikleriyle eşleşti. Bu, tüm eklentilerin dosya bazında eşitliğini veya ham dosya hash attestation'ını kanıtlamaz. WPVibe bilinen gizli kalıpları maskeleyebilir; bu üç çıktıda maskeleme göstergesi görülmedi.

## Katalogda bulunan farklar
1. events-list(status=sales_open) 26 kayıt döndürüyor; geçmiş tarihli ve Milano markalı kayıtlar da var. Kırıkkale programı iptal olduğu halde bağlı MMC etkinlik satırı sales_open kalmış. Yönetim durumu, tek başına güncel satılabilir katalog değildir.
2. Gelecekteki Madagaskar programları: Tire, Ödemiş, İzmir/Konak, Çorlu, Çerkezköy, Kırklareli, Edirne/Merkez ve Keşan. Milano/Tekirdağ aynı merkezi MDG içinde 3 seansa sahip; Madagaskar önerisine dahil edilmez.
3. Keşan: program29 venue_confirmed, MMC event29 configured; köprü MDG39'a bağlı, MDG39 ve seans229/230 onsale. Sayfa açık, dört çocuk/yetişkin varyasyonu purchasable. MMC satış hazırlığı ready=true olsa da bu, satışın açıldığı anlamına gelmez. Durumlar otomatik değiştirilmedi.
4. Yeni MMC etkinliklerinde mdg_event_id alanının 0 olması bağlantı yok demek değil; gerçek bağlantı mmc_mdg_event_bridge tablosunda mevcut. Kimlik eşlemesi bridge üzerinden yapılmalı.
5. MDG UTC seans saatleri kamu sayfasında İstanbul saatiyle sunuluyor. Örnek: Tire session235 09:00 UTC → 12:00 İstanbul. Saat farkı hata olarak “düzeltilmemeli”.

## Aile paketi için doğru adaptör
17 seansın MDG kataloğunda yalnız çocuk/yetişkin satırları var. Aile paketi ayrı WooCommerce varyasyonu değildir; aktif 1.1.3 eklentisi sanal family_2_2 satırını dört kapasite birimiyle işleyip 2 çocuk + 2 yetişkin gerçek bileşene açar. Yeni asistan aile ürünü yok diye paketi otomatik elememeli veya yeni bir normal ürün oluşturmamalı.

Sunucudaki fiyat seçimi: köprü üzerinden aktif MMC family_2_2 fiyatı varsa onu al; yoksa MDG etkinliğine özgü aile eklentisi ayarına, ardından varsayılana git.
İzmir MMC family satırı 1.100 TL fakat pasif; gerçek fallback etkinlik ayarı 1.300 TL. Dolayısıyla bu pasif satırı etkin fiyat sanmak yanlış olur. Diğer yedi etkinliğin aktif MMC aile fiyatı 1.100 TL; bunların etkinlik ayarları da aynı.

Paketin 1+3 ve 2+1 uygunluğu ile en ucuz geçerli birleşim politikası 3 Kasım kaydından korunur. Bu oturumda paket sepete eklenmedi, 4 QR üretilmedi; alternatif aile bileşimlerinin gerçek giriş süreci yeniden test edilmedi.

## Kamu sayfaları ve kanıt sınırı
8 gerçek etkinlik URL'sinin #bilet-secimi HTML'i okundu; çocuk/yetişkin kod ve fiyatları eşleşiyor. Sayfa kaynağı ve sunucu yanıtı, JavaScript ile eklenen aile satırının gerçek tarayıcıda göründüğünü kanıtlamaz. Aile DOM satırı bu araç çıktısında görülmedi; bu bulgu “paket müşteride eksik” hatası olarak sınıflandırılmadı. Etkileşimli tarayıcı smoke testi açık.

Eventpal 1.0.0 GET beslemesi de bulundu. Tam yanıt ~187 bin karakter; connector çıktı sınırında kesiliyor. İç içe _fields projeksiyonu total=53 yanında events=[] verdi. Bu yüzden feed'in boş olduğu veya 53 kaydın tamamının doğrulandığı söylenemez. Birincil katalog olarak henüz kabul edilmedi.

## A1 için uygulanacak sözleşme
- Aktif ve tekil bridge'den marka ve kimlikleri çöz; MMC inline mdg_event_id alanına veya başlığa güvenme.
- Program/etkinlik/MDG satış durumunu birlikte kontrol et. Keşan gibi uyuşmazlıklarda otomatik öneriyi durdur ve inceleme nedeni döndür.
- Europe/Istanbul bağlamını koru; gerçek seans başlangıcını, satış penceresini ve kapanma kuralını denetle.
- Her seansın gerçek variation id, native purchasable/in-stock ve güncel fiyatını doğrula. Kapasite için MDG kapasite kaynağını kullan; Woo stock_quantity=null sınırsız salon demek değildir.
- family_2_2 için mevcut sanal paket enable/readiness/price davranışını yeniden kullan; aktif çok birimli MDG bilet çakışmasını ve bileşen eşlemelerini kontrol et.
- Hesap kuruş cinsinden deterministik olacak. Kullanıcı sayısı ile kapasite birimi ayrı tutulacak.
- İlk yayın mevcut doğrulanmış satış ekranına yönlendirecek; stok rezervasyonu, sepet/ödeme değişikliği ve idari AI erişimi eklemeyecek.

## Yapılanlar, açık işler ve geri dönüş
Yalnız connector GET, kaynak okuma, option get ve SELECT sorguları kullanıldı. İş verisi yazan bir çağrı, müşteri mesajı, sepete ekleme, sipariş, ödeme, iade veya bilet işlemi başlatılmadı. Kimlik doğrulama/okuma logları ve olası teknik cache etkileri için sıfır değişiklik iddiası yok; veritabanı before/after eşitliği ölçülmedi.

A0 istisnaları: Keşan durum uyumu; JS aile satırı/gerçek etkileşim; tam modül/dosya eşitliği; guest-channel checkout; pilot öncesi miktar/kapasite yarış koşulları. Bunlar tüm geliştirmeyi durdurmaz: A1 izole adaptör/hesaplayıcı geliştirilebilir, canlı pilot bu kapılardan sonra açılır.

Yeni kod olmadığından PHP lint veya uygulama testi çalıştırılmadı. Fiyat/kimlik karşılaştırmaları canlı yanıtlar üzerinden hesaplandı; 34/34 eşleşme ve #110 hash eşitliği doğrulandı.
Geri dönüş yalnız bu doküman/snapshot değişikliğini geri almaktır; canlı rollback gerektiren değişiklik yapılmadı.

GitHub: https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/304
Drive: https://docs.google.com/document/d/1ji2wr6hxwdIr875azZDYHullsEwZjyu735dhR4v3ozQ/edit?usp=drivesdk

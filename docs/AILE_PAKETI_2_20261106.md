# Aile Paketi 2 — 6 Kasım 2026

Kullanıcı talebi: mevcut 2 yetişkin + 2 çocuk paketi korunarak ikinci seçenek olarak 2 yetişkin + 4 çocuk satışı. İzmir merkez 1500 TRY, diğer Madagaskar programları 1250 TRY. Tire/Ödemiş mevcut fiyat ayrımına göre diğer programlar grubundadır; bu varsayım kullanıcıya çalışma başında bildirildi. Bu belge fiyatı otomatik şehir adından üretmez: etkinlik bazında açık ayar gerekir.

## Kaynak incelemesi ve kök neden

Canlı 2+2 modülü 1.1.3 kişi sayısını iki çocuk/iki yetişkin olarak sabitliyor. UI dört kapasite birimi gösteriyor; AJAX gerçek çocuk/yetişkin varyasyonlarını ikişer artırıyor. Aktif #178/#179 finans düzeltmeleri yalnız `_mdg_family22` ve `Aile Paketi 2+2` indirimlerini okuyor. #180 eski etkinliklerde 2+2 fiyatını yeniliyor. Başlığı veya fiyatı değiştirmek 2+4 üretmez ve finansı yanlış raporlayabilir.

## Değişiklik

Yeni bağımsız `madagaskar-aile-paketi-24` eklentisi. Eski 2+2 dosyası ve snippet'leri değişmez. Yalnız 2+4 içeren istekler priority 0 adaptörüyle gerçek çocuk/yetişkin satırlarına açılır. Böylece eski priority 1 adaptörü yalnız native satırlar görür; 2+2-only işlemler aynen eski kodda kalır. Karışık paketler tek grup manifestinde kişi başına bir kez indirim alır. Native MDG nonce/durum/seans/Woo mapping/fiyat/stok/kapasite kontrolleri korunur; gerçek çok kişilik MDG bilet türü eklenmez.

Yeni paket için toplam dört çocuk + iki yetişkin, altı kapasite birimi ve native Tickera üzerinden altı ticket instance hedeflenir. Native tür başına 20 adet sınırı nedeniyle tek seçimde en fazla beş yeni paket alınabilir; tekil biletler ve eski paketlerle toplam sınır ayrıca doğrulanır. Sepetten bir bileşen azaltılırsa yalnız kalan tam paketler indirim alır. Büyük paketler önce, küçükler sonra ayrılır.

Yeni sipariş satırları `_mdg_family24`, indirim satırları `_mdg_family24_group` taşır. Priority 80 finans uzlaştırması yalnız aynı sipariş/grubun mevcut MMC ledger ve MDG order-map kayıtlarını günceller. Kuruş bazında tahsilat toplamını korur; yeni sipariş/bilet üretmez, eski siparişleri taramaz, ödeme bilgisine dokunmaz. Eski 2+2 uzlaştırmaları yeni meta/fee adını tanımadığı için yeni gruba ikinci indirim uygulamaz.

Kommo read-source kaynak dosyasına aynı `offer()` politikasını kullanan katalog uzantısı eklendi. Harici mesaj gönderimi veya Kommo API write yok. Aktif #110 deployment'ı kaynak eşleşmesiyle ayrıca yapılmalıdır. Staging web-asistanının henüz main'e alınmamış 305–308 dalları ayrı adaptör güncellemesi gerektirir; bu PR bunları topluca kurmaz.

## Ayarlar

`mdg_family_package_24_v1 = {events: {MDG_EVENT_ID: PRICE}}` tek yeni-paket fiyat ve etkinleştirme kaynağıdır. Kurulum varsayılan olarak kapalıdır. Araçlar → Madagaskar Aile Paketi 2 formu `manage_woocommerce`, nonce ve tam veri doğrulaması ister. Diğer markalar (Milano dahil), 2+2 allowlist'inde olmayan olaylar ve MMC–MDG satış durumu çelişen olaylar fail closed.

6 Kasım canlı incelemesinden önerilen seçim: MDG10 İzmir merkez=1500; MDG35 Çorlu,36 Çerkezköy,37 Kırklareli,38 Edirne,40 Tire,41 Ödemiş=1250. MDG39 Keşan, MMC program29 durumu `venue_confirmed` iken MDG `onsale` olduğundan yeni pakete dahil edilmez; durum sahibi tarafından çözülmelidir. Geçmiş tarihli `onsale` kayıtlar topluca etkinleştirilmez.

## Test ve risk

Yüksek risk: checkout adaptörü ve finans. PHP7.4/8.4 fixture testleri ve gerçek native JavaScript ile DOM testleri yeni workflow'da çalışır. Testler: 6 gerçek adet, karışık paket, iki fiyat bölgesi, aşırı/negatif/ondalıklı miktar, bilet azaltma, tam indirim hesabı, kuruş toplamları, yanlış marka/seans, çok kişilik legacy tür, marker/fee metadata, finans toplamı, tekrar hook ve kısmi iade.

Fixture testleri gerçek ödeme, gerçek Tickera PDF/QR üretimi veya eş zamanlı MySQL kapasite yarış testinin yerine geçmez. Deployment öncesi izole staging'de native sepet→checkout, altı farklı Tickera bilet/QR,6 kapasite düşümü, ödeme callback tekrarı, iptal/iade ve gerçek ledger eşleşmesi ayrıca doğrulanmalıdır. Klon staging'de gerçek ödeme veya müşteri bildirimleri çalıştırılmaz.

## Kontrollü kurulum ve geri dönüş

1. Yalnız yeni modülün ZIP'ini staging'e kur; 2+2 ve MMC/MDG eklentilerini topluca değiştirme.
2. Staging İzmir MDG10 için yeni ayarda1500 tanımla; yukarıdaki smoke testleri uygula.
3. Canlıya geçmeden kaynak/sürüm, native eski 2+2 ve #110 eşleşmesini yeniden kontrol et. Yeni modülü önce ayarları boşken kur; doğrulanan Madagaskar etkinliklerini açık fiyatlarla etkinleştir. Ödeme/sipariş kayıtlarını geri doldurma.
4. Yalnız yeni modülü devre dışı bırakmak, yeni paket seçim satırını kaldırır. Eski2+2 eklentisi ve çalışan modüller açık kalır. Ancak yeni modülle oluşturulmuş sepet/sipariş varsa indirim ve finans hook'larını kaybetmemek için önce yeni paket allowlist'ini boşalt; modülü derhal kapatmadan açık sepet/siparişleri uzlaştır. Mevcut meta/ledger/biletleri silme.

## Durum

Kod ve otomatik test hazırlığı sürüyor. Canlıya kurulum veya satış açılışı bu belgenin ilk yazımında yapılmadı. Son doğrulama sonuçları ayrı kanıt ekiyle kaydedilecektir.

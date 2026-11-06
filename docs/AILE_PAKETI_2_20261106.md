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

Kod e19072d8ea1db1a349998a46d91e1a0eb79c134f, PR310 draft/open. PHP7.4 ve8.4 üzerinde218'er kontrol, native JS DOM testleri ve15/15 GitHub workflow çalışması SUCCESS. İlk ZIP output yolu hatası düzeltildi; çalışma zamanı testleri ilk denemede de başarılıydı.

Yeni modül yalnız özel staging257930487'ye kuruldu ve active0.1.0 doğrulandı. Kurulu üç kaynak dosyasının satır metni GitHub ile eşleşti (yalnız son transport satır sonu hariç). Son staging ayarı yalnız MDG10=1500; mevcut 2+2 ayarları değiştirilmedi. Gerçek İzmir88/89/90 kataloğunda4çocuk+2yetişkin,normal2330,indirim830,sonuç1500,istenen6birim doğrulandı. Ankara7/seans60 eski katalog kaydında1250 hedefi,2000 normal–750 indirim doğrulandı; bu geçici test ayarı kaldırıldı. Hiçbir audit sipariş/hold/bilet üretmedi. Yanlış seans409; üretimde yeni audit route404. Ayrıntı: `docs/evidence/aile-paketi-2-20261106.json`.

Özel staging etkinlik sayfası giriş ekranına dönüyor ve bu oturumda yetkili tarayıcı aracı yok. Bu nedenle gerçek sepet→checkout→ödeme,6benzersizQR/PDF ve6kapasite düşümü testi AÇIK. Canlıya modül kurulmadı ve yeni paket satışa açılmadı. Aktif Kommo #110 değişmedi; uzantısı yalnız PR'da hazır. PR305–308 özel web-asistanı dalları yeni paketi okuyacak ayrı adaptör güncellemesi bekliyor. Repository AGENTS.md kuralındaki kontrollü akış ve checkout doğrulaması tamamlanmadan doğrudan canlı yazma yapılmadı.

Yetkili test sayfası: https://staging-f321-milanosirki-lgajf.wpcomstaging.com/etkinlik/madagaskar-sirki-izmir-08-kasim-2026/ . Ayarlar: aynı sitede `/wp-admin/tools.php?page=mdg-family-package-24`.

## Devam talebi sonrası güncel sonuç — CANLI / 6 Kasım

Bu bölüm önceki staging-only durumun devamıdır. Kullanıcının “Devam” talebiyle sunucu ve gerçek ziyaretçi testleri tamamlandı; PR310 ana dala eece8a201985354f02f544466920b5eaefa125de ile alındı. Yalnız native `madagaskar-aile-paketi-24`0.1.1 canlıya kuruldu/aktif doğrulandı. Ayar açıkça MDG10=1500;35,36,37,38,40,41=1250. Eski2+2 option'ı değişmedi. Üç canlı kaynak dosyası dağıtılan kodla satır metni olarak eşleşti. Keşan39 durum çelişkisi nedeniyle dışarıdadır. Yeni programlar ayrıca açık fiyatla etkinleştirilir. Program promotion/operations/show_day aşamasına ilerlediğinde paket kapanmaz; venue_confirmed gibi satışla çelişen aşamalar kapalı kalır.

Gerçek390px mobil Chromium: İzmir ve Tire yeni satırları görünür, yatay taşma/JS hatası yok. İzmir gerçek anonim sepeti4çocuk+2yetişkin,2330−830=1500TL; ödeme ekranı görselinde1500TL. Tire anonim sepeti4çocuk+2yetişkin,1960−710=1250TL ve ödeme ekranı1250TL doğrulandı. Sipariş gönderilmedi. Sepeti boşaltma sonucu doğrulanmadığından bunun için başarı iddia edilmez; testler yalnız yeni anonim ziyaretçi oturumlarıdır. Node/Playwright request köprüsü yapılandırılmış CA ile TLS doğrulamasını korudu; Chromium TLS denetimi kapatılmadı. Üçüncü taraf izleme çağrıları testte engellendi.

Özel staging'de tek seferlik sentetik sipariş6166:1500TL,6yayınlıTickera bileti,6farklı bilet kodu. Seans88 satılan4/ayrılan0 →4/6 →10/0; MDG completion tekrarı10/0 kaldı. MMC ledger gross/net1500 veV5 line_total1500. Gerçek ödeme gateway'i çağrılmadı, para alınmadı; e-posta/HTTP ve harici yayıncılar istek kapsamında engellendi. Test etiketi ve sipariş kalıcı kanıt olarak yalnız özel staging'de tutuldu. Lab0.2.1 iş sonunda devre dışı bırakıldı; canlıya kurulmadı. İlk lab'daki yönetici kayıtlı sepet birleşmesi tam eşleşmeli temizlikle geri alındı; anonim bellek sepeti ve callback temizliğiyle giderildi. İlk HTTP500 sonuçları başarı sayılmadı; sonraki lab JSON taşıması200 ve nonce/mixed/eski paket kontrolleri geçti.

Asistan0.3.0 yalnız staging'e kuruldu. PR311(308'e bağımlı)2+4 paketini native offer() üzerinden okur;2+2,2+4 ve tekilleri bağımsız optimum fiyat kontrolüyle karşılaştırır.2+4=1500 ve6birim gerçek REST yanıtında doğrulandı.886'şar PHP7.4/8.4 kontrolü ve DOM testleri başarılı. Native0.1.1 için221'er kontrol ve15/15CI başarıdır.

Kommo#110: hazır yedi satırlık uzantıyı canlı kaynak snippet'ine uygulama girişimi otomatik onay denetimi tarafından reddedildi. Gerekçe: bildirilen canlı kapsamı yalnız yeni eklenti kurulumu olarak değerlendirilmişken aktif üretim Kommo kodunun değiştirilmesi yüksek riskli/onaysız bulundu. Yazma uygulanmadı; kod eski kaynakla aynı ve active=true doğrulandı. Kullanıcıdan açık onay istendi, henüz alınmadı. Engelin çevresinden dolaşılmadı. Bu nedenle web satışı AÇIK, mevcut Kommo bilgi kaynağına yeni paketin eklenmesi ONAY BEKLİYOR. Fiziksel QR okutma ve gerçek PayTR tahsilatı yapılmadı.

Güncel makine kanıtı: `docs/evidence/aile-paketi-2-live-20261106.json`. Canlı ZIP Drive: https://drive.google.com/file/d/16IWaIv0Iy633NB3-slg8MF7EhQv6V-CM/view . Staging asistan ZIP: https://drive.google.com/file/d/150Aq6QGIT1wUT6Ms2Ad-sg2UsfppLwkm/view . İzmir tarayıcı kanıtı: https://drive.google.com/file/d/1678gK7WzxeDjT8bpu4gNBEa6h-dHOWJd/view .

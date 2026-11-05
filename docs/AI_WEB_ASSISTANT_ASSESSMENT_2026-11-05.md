# Madagaskar Sirki — Web Yapay Zekâ Asistanı: Durum ve Uygulama Planı
Tarih: 5 Kasım 2026
Durum: Kaynak incelemesi ve geliştirme taslağı; canlıya alınmış yeni özellik değildir.

## 1. Karar özeti
Mevcut WordPress/MMC/MDG, WooCommerce, Tickera, PayTR ve Kommo yapısının üzerine web öneri asistanı eklenmesi önerilir. İlk sürümün işi ziyaretçinin şehir, tarih, saat ve aile bileşimine göre güncel gösterileri bulması, geçerli bilet birleşimlerini karşılaştırması ve kullanıcının seçtiği seansın mevcut bilet ekranına yönlendirmesidir.

Sıfırdan CRM veya ödeme altyapısı kurulması gerekmiyor. “AI Abilities” adlı mevcut eklenti, idari işlevleri araç olarak sunan bir katmandır; tek başına halka açık sohbet veya öneri ürünü bulunduğunu kanıtlamaz. Bu incelemede kamuya açık web asistanının çalışan uçtan uca uygulaması doğrulanmadı.

Kullanıcı GitHub ve mevcut Drive kurumsal hafızasının okunmasına ve yapılanların bu iki kanala kaydedilmesine izin verdi. Kalıcı kodun ana kaynağı GitHub, rapor ve operasyon arşivi Drive olarak korunur.

## 2. Kanıt ve güncellik
GitHub main inceleme noktası: 83c04d8857ed9d312c872533ae653e6a1fc07393.
3 Kasım aile/yanıt kaydı: PR #303, c386c9bc60c766dd3cc39ad44e03eaf033a2e396; inceleme anında open ve unmerged.

Doğrulanmış kaynak bulguları:
- README: program/süreç MMC; salon ve okul kendi ana kayıtlarında; satış WooCommerce + Tickera + PayTR; CRM Kommo.
- MDG_Public_Event::render(), MDG_Sessions::by_event() ve ticket_catalogue_for_event() üzerinden etkinlik seanslarını ve aktif bilet verilerini topluyor.
- Mevcut public-event.js gerçek event_id/session_id ve bilet code/qty ile native sepet işleyicisine gider. Yeni asistan ilk aşamada bu işleyiciyi yeniden yazmamalı.
- main'deki AI Abilities bootstrap sürümü 0.7.0; modüller option ile seçilir ve varsayılan olarak kapalıdır. Kurulu canlı sürüm veya etkin modüller bu oturumda okunmadı.
- Kommo otomasyon kaynağında HTML toplama yolu ve uzak kaynak güncellemesini varsayılan kapatan filtre var. Kaynakta bir fonksiyonun bulunması o yolun bugün aktif olduğunu ispatlamaz.
- Ayrı Unified Source V2 dosyası MMC program/salon/seans/bilet kayıtlarını okur; aile fiyatı için legacy option fallback'i de içerir. Bu dosya yeni halka açık satış kataloğu olarak doğrudan kullanılmamalı.
- Issue #82 checkpoint'i 5 Ekim tarihli: snippet #110 kanonik kaynak, plugin saatlik HTML collector ve manuel uzak doğrulama ayrımını kaydeder. Eski HTTP 404 bulgusu bugünün canlı hatası sayılmaz; main V2 kaynağında status_header(200) zaten vardır.
- Issue #242, manuel kaynak doğrulamasının otomatik uzak AI yeniden indeksleme ile aynı olmadığını ve kanalların ayrı doğrulanmasını kaydeder.
- PR #303, 3 Kasım Kommo kaynak/yönerge kayıtlarını ve önizleme sonuçlarını içerir; bütün gerçek müşteri konuşmalarında teslimat garantisi değildir.

Drive V5 ajan talimatı tarihsel bir kaynaktır. Kapı açılışı ve paket uygunluğu gibi konularda daha yeni, kapsamı açık 3 Kasım kaydı dikkate alınır. Eski şehir/tarih/fiyatlar yeni asistanın sabit verisi yapılmaz.

Kanıt sınırları: Canlı admin/eklenti sürüm eşitliği, Kommo'nun bugünkü ayarları, satın alınabilirlik, kalan kontenjan, ödeme/QR zinciri, mobil görünüm ve erişilebilirlik test edilmedi. Önceki halka açık içerik okumalarında bazı sayfalar HTTP 429 verdi; bu tek başına müşterilerin erişim sorunu yaşadığını göstermez. Yerel git clone yapılandırılmış proxy'ye bağlanamadı; inceleme GitHub/Drive connector okumalarıyla yapıldı. Yerel PHP testi veya canlı sağlık testi başarı iddiası yoktur.

## 3. Korunacak iş kuralları
- 0–2 yaş ücretsiz, 3–12 çocuk, 13+ yetişkin; yalnız gerekli yaş aralığı sorulur.
- 3 Kasım kayıtlı politika: sistemde ürün adı Aile Paketi 2+2, kod family_2_2, kapasite tüketimi 4. Kullanım 2 yetişkin + 2 çocuk, 1 yetişkin + 3 çocuk ve 2 yetişkin + 1 çocuk için geçerli; üçüncü yetişkin çocuk hakkı sayılamaz.
- Paket her durumda en ucuz değildir. Tekil bilet, uygun paket, ek bilet ve çoklu paket birleşimleri gerçek fiyatlarla karşılaştırılır; kullanılmayan çocuk hakkını doldurmak zorunlu değildir.
- Fiili kişi sayısı ile ürünün kapasite tüketimi ayrı hesaplanır. Paket eksik kişiyle kullanıldığında bile mevcut ürün kapasite kuralı değiştirilmez.
- Özel okul davetiyesi yalnız etkinlik/kampanya kapsamı ve doğrulanmış koşulları uygunsa hesaplanır. Bir ücretli yetişkin yanında en fazla iki davetiyeli çocuk kuralı ile paket/diğer indirimlerin birlikte kullanımı ayrıca doğrulanır; birleştirilebilirlik varsayılmaz.
- Madagaskar ve Milano ayrı markalardır. Şehir adı tek başına kimlik değildir; marka + program + etkinlik + seans + doğrulanmış satış bağlantısı kullanılır.
- Kapılar için son kayıtlı genel kural seans başlangıcından 30 dakika öncedir; gişe satış başlangıcıyla karıştırılmaz. Onaylı bir etkinlik istisnası varsa kaynağıyla gösterilir.
- Geçmiş bir şehir iptali, aynı şehirdeki sonraki tarihli etkinliği otomatik iptal etmez.
- Fiyatlar modele ezberletilmez. Öneri satın alma anında native sistemde yeniden doğrulanır; rezervasyon/ödeme garantisi verilmez.

3 Kasım test fiyatları yalnız regresyon örneğidir, güncel fiyat taahhüdü değildir:
standart yetişkin 490 / çocuk 245 / paket 1.100 TL; İzmir Halkapınar 585 / 290 / 1.300 TL.
Sentetik örnek beklentileri: standart 1+2 = tekil 980; 1+3 ve 2+1 = uygun paket 1.100. İzmir 2+3 = paket + çocuk 1.590; 2+4 = paket + iki çocuk 1.880. Canlı fiyat ve ürün uygunluğu ayrıca doğrulanmalıdır.

## 4. Önerilen mimari ve ilk kapsam
Önerilen akış:
Ziyaretçi → web formu/sohbet → sunucu orkestrasyonu → marka kapsamlı güncel katalog + kural/hesap servisi + onaylı bilgi kaynağı → açıklanabilir öneri → kullanıcının seans seçimi → mevcut bilet ekranı.

Üç ayrı görev:
1. Veri adaptörü: MMC program/salon ve MDG seans/ürün/WooCommerce eşleşmesini doğrular. Durum, satış kapanma zamanı, satın alınabilirlik ve aile ürünü bulunurluğu kontrol edilir. Belirsiz veya çoklu eşleme öneri üretmez.
2. Kural motoru: bilet birleşimleri, uygunluk, kapasite ve toplamları sunucuda kuruş cinsinden hesaplar. Dil modeli para hesabı veya uygunluk kararının tek otoritesi olmaz.
3. Dil modeli: serbest Türkçe talebi yapılandırır, eksik gerekli bilgiyi sorar ve servisten dönen sonucu açıklar. Sonuçta olmayan fiyat, kontenjan, adres veya URL üretemez.

Yeni servis için önerilen sözleşmeler; mevcut endpoint oldukları iddia edilmez:
- catalog: brand, program_id, mmc_event_id, mdg_event_id, session_id, venue, starts_at, timezone, sales_status, ticket options, verified_url, fetched_at, source_revision.
- quote: doğrulanmış session_id + yetişkin/çocuk/0–2 adetleri + varsa doğrulanmış davetiye kapsamı; çıktı geçerli birleşimler, önerilen kalemler, total_minor, savings_minor, attendance_count, capacity_units, rule_version, price_revision ve expires_at.
- answer: onaylı bilgi kimliği, içerik sürümü ve destek bağlantısı; sipariş/ödeme verisi public yanıta dahil edilmez.

Kartlar ve seçiciler sohbetin yanında kullanılmalı: Şehrim, Tarih, Kişiler, Davetiyem var, Bana uygun seanslar. Kullanıcı istediği saati değiştirebilmeli; asistan kullanıcı adına kesin seans seçmemeli. Model yanıt veremediğinde normal bilet listesi ve destek bağlantısı çalışmaya devam etmeli.

Dinamik fiyat/stok için eski metin indeksine güvenilmez. Kaynak okunamazsa güncel uygunluk iddiası ve fiyat önerisi durdurulur. Kısa cache'in süresi/yenilemesi ölçülür; ödeme öncesi native yeniden kontrol zorunludur. İlk sürüm yalnız mevcut satış sayfasına yönlendirir; sepet/checkout yazma değişikliği ayrı aşamadır.

Public asistan, genel idari AI Abilities erişimini veya yönetici tokenını devralmaz. Yalnız izin verilen salt okunur servisleri çağırır. API anahtarı sunucuda tutulur; hız, istek boyutu, zaman aşımı ve maliyet limitleri konur. Sipariş sorgulama daha sonra kullanıcı doğrulamasıyla ele alınır. Müşteri sohbetleri ve kişisel değerler GitHub'a kaydedilmez.

## 5. Sıralı geliştirme işleri
A0 — Canlı/kaynak eşleştirme:
Kurulu ilgili plugin sürümleri, etkin snippet/modül sahipliği, seans/ürün eşlemeleri ve aile ürünü uygunluğu salt okunur doğrulanır. main ile canlı arasında fark varsa önce hedef dosyalar belirlenir; eski paket topluca yüklenmez.
Kabul: her veri alanının sahibi, güncel durumu ve son kontrol zamanı belli.

A1 — Katalog ve hesaplama servisi:
Mevcut domain servislerinin önüne sınırlı adaptör ve bağımsız hesaplayıcı hazırlanır. Fiyat/paket/davetiye kuralları kaynak gösterilerek sürümlenir.
Kabul: marka karışması, iptal/geçmiş seans ve eksik fiyat öneri üretemez; aynı girdi deterministik sonuç verir.

A2 — Web asistanı:
Önce seçiciler ve öneri kartları, ardından serbest metin yorumlama eklenir. Mevcut ödeme yolu korunur. Sağlayıcı, veri işleme ve korumalı anahtar kurulumu bu aşamada kesinleştirilir; mevcut anahtarlar okunmaz/çoğaltılmaz.
Kabul: dil modeli sonucunda yalnız katalog/hesaplayıcıdan doğrulanmış alanlar görünür; normal gezinme çalışır.

A3 — Kontrollü pilot:
Önce tek doğrulanmış etkinlikte sınırlı yayın, ardından karşılaştırmalı ölçüm ve diğer etkinliklere açılım.
Ölçümler: doğru öneri oranı, yanlış fiyat/uygunluk olayı, bilet sayfasına geçiş, satın alma tamamlama, insana aktarım, yanıt süresi ve oturum başına maliyet. Başlangıç değerleri henüz ölçülmedi; satış artışı taahhüt edilmez.

A4 — Kurumsal talep asistanı:
Mevcut formdan kurum, şehir, tarih, mekân ve katılımcı bilgilerini toparlar; ekibe taslak özet hazırlar. Fiyat/uygunluk sözü vermez. CRM kaydı ve bildirim yazıları ayrı incelenir.

Süre ve bütçe A0 sonrası verilir. Önceki 3–6 hafta tahmini ilk görüşme varsayımıydı; doğrulanmış entegrasyon teklifi değildir.

## 6. Doğrulama ve geri dönüş
Geliştirmede gereken davranış testleri:
- Yaş sınırları 2/3/12/13; yetişkinsiz çocuk talebi; bilinmeyen yaş.
- Tekil biletin ucuz olduğu durum; 2+2, 1+3, 2+1; ek çocuk/yetişkin; çoklu paket; paket kapalı/yok; davetiye geçersiz ve birlikte kullanım belirsiz.
- Kuruş hassasiyeti ve toplam/tasarruf; fiili kişi ile kapasite farkı.
- Europe/Istanbul gün/saat sınırı, başlayan/iptal edilmiş seans, kontenjan değişimi, eksik/çoklu eşleme, yanlış marka.
- Katalog zaman aşımı, eski fiyat sürümü, model hatası, uydurma bağlantı girişimi, sınırlama ve erişim kontrolleri.
- Mobil/klavye/ekran okuyucu; öneri kartından doğru etkinlik/seans ekranına geçiş; kapalı asistanla normal bilet akışı.

Bu oturum yalnız inceleme ve dokümantasyon içerir; yukarıdaki testler henüz uygulanmış değildir.
Geliştirme ayrı branch + ilgili testler + draft PR ile yürür. Canlı ödeme zincirine değişiklik gerektiğinde uygun staging/smoke/rollback kapsamı ayrıca tamamlanır.
Pilot geri dönüşü yalnız yeni asistan özelliğini kapatıp mevcut bilet yönlendirmesini bırakmaktır; ödeme, bilet veya çalışan MMC modülleri kapatılmaz. Bu doküman için geri dönüş yalnız belge değişikliğini geri almaktır.

## 7. Kurumsal hafıza ve devam noktası
Kalıcı kaynak: https://github.com/milanosirki-code/madagaskarsirki-wordpress
Teknik Drive arşivi: https://drive.google.com/drive/folders/196yJBRMajDa1B4TJUwYQVU0NahsndFTM
Ana kaynak rehberi: https://docs.google.com/document/d/1kZN16Djtw-dyX4zw1ZoPNE0QvpP3zjxj6figFnoumhk/edit
Çalışma/yedek rehberi: https://docs.google.com/document/d/1Q6-mOKbbkYZgWVcxFYK3yy9zLFjqEZEwKTJ14Z1PiZI/edit
Tarihsel ajan V5: https://drive.google.com/file/d/1UuvlQv1h_Q6POHHpJOOXVkJNdXYPeDJs/view
Güncel aile/yanıt kaydı: https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/303
Kaynak tazelik takibi: https://github.com/milanosirki-code/madagaskarsirki-wordpress/issues/82
Kurumsal Kommo devir kaydı: https://github.com/milanosirki-code/madagaskarsirki-wordpress/issues/242

Sonraki teknik adım A0'dır: canlı kaynak eşleştirmesi ve satın alınabilir ürün doğrulaması. Bu plan henüz uygulama, merge, deploy veya gerçek müşteri testi anlamına gelmez.

Her sonraki çalışma kaydı şu alanları tutmalı: tarih, kapsam, kullanılan kaynak commit'i, değişiklik/PR, doğrulama kanıtı, canlıya alındı mı, kalan işler, geri dönüş ve iki arşiv bağlantısı. Tarihsel notların üzerine bugünkü durum yazılmaz; yeni kontrol tarihi ve kanıtı ayrıca eklenir.

Bu çalışmanın Drive raporu: https://docs.google.com/document/d/1ji2wr6hxwdIr875azZDYHullsEwZjyu735dhR4v3ozQ/edit?usp=drivesdk

## A0 canlı kontrol tamamlandı — 5 Kasım 2026

Ayrıntılar: [A0 raporu](AI_WEB_ASSISTANT_A0_2026-11-05.md) ve [sanitize snapshot](AI_WEB_ASSISTANT_A0_2026-11-05.snapshot.json).
8 etkinlik/17 seans/34 çocuk-yetişkin varyasyonu incelendi; fiyatlar 34/34 eşleşti. Keşan MMC ve MDG durumları çelişkili; yeni asistan önerisinde ayrı çözüm gerektirir. Aile sanal paketinin gerçek tarayıcı etkileşimi açık. A0 bulguları önceki varsayımların üstüne geçer; canlı iş verisi değiştirilmedi.

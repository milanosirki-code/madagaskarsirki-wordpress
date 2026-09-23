# Madagaskar Management Center v1.3.3


## v1.3.1 — Tek Salon Kaynağı

- Salon ana kaydı artık doğrudan **Madagaskar → Salonlar** bölümünden okunur.
- MMC içinde ikinci/tekrarlı salon kaydı oluşturulmaz.
- Program → Salon & Tahsis ekranı mevcut Madagaskar salonlarını listeler; program iliyle aynı ildeki salonları önceliklendirir.
- Salon adı, il/ilçe, adres, kapasite, harita, iletişim ve operasyon notları Madagaskar salon kaynağından gelir.
- MMC yalnız programa özgü tahsis, kira, teminat, dilekçe ve kesin salon bağlantısını tutar.
- Eski MMC salon kayıtları veri kaybı olmaması için yalnız geriye uyumluluk fallback'i olarak korunur.
- Kurulum & Sağlık ekranı salon kaynağının bağlı olup olmadığını ve aktif salon sayısını gösterir.

Madagaskar Sirki için program yaşam döngüsünü tek WordPress yönetim merkezinde toplamak üzere geliştirilen özel eklenti.

## Bu sürümde bulunan modüller

- Sistem omurgası: Program ID, durumlar, görevler, roller, audit log
- Bölge Veri Ambarı: TÜİK/MEB kaynaklı metrik ve okul CSV içe aktarma
- Program Hazırlık Dashboardu: hedef ilçe/tanıtım havzası ve veri kalitesi
- Salon & Tahsis: alternatif salonlar, tahsis dilekçesi, onay/red, kira ve teminat
- Etkinlik & Seans: etkinlik, seans, ortak kapasite, bilet türleri ve kanal fiyatları
- Satış Hazırlığı: WooCommerce, Tickera, PayTR ve Biletinial kanal kontrolü
- **Satış & Doluluk v0.5:** WooCommerce sipariş senkronu, PayTR ödeme yöntemi tanıma, seans/bilet eşleştirme, iade-net ciro hesabı, aile paketi kişi kapasitesi, seans doluluk dashboardu
- Mevcut eski Madagaskar Bilet Yönetimi (`MDG_DB`) algılanırsa eski ürün/varyasyon/Tickera eşleştirmelerini MMC'ye içe alma aracı

## v0.5 satış mimarisi

1. MMC seansı + bilet türü, WooCommerce ürün/varyasyonuna eşlenir.
2. WooCommerce sipariş durumu/ödeme/iade değiştikçe ilgili sipariş satırı MMC satış defterine senkronlanır.
3. Sipariş ödenmişse net bilet adedi ve gerçek kişi kapasitesi hesaplanır.
4. Aile Paketi 2+2, kapasiteden 4 kişi tüketir.
5. İadeler net ciro ve net kişi sayısından düşülür.
6. Ödeme yöntemi PayTR olarak algılanırsa PayTR entegrasyonu canlı doğrulama durumuna geçirilir.
7. WooCommerce + Tickera + PayTR doğrulanınca program Satışta aşamasına geçebilir.

## Güvenlik yaklaşımı

- v0.5 canlı WooCommerce ürünü veya Tickera etkinliği **oluşturmaz, silmez veya yayınlamaz**.
- Var olan satış nesnelerini yalnız ID ile eşler ve WooCommerce CRUD/sipariş API'si üzerinden satış verisini okur.
- Sipariş sorguları HPOS uyumlu `wc_get_orders()` API'si üzerinden yapılır.
- Canlı siteye geçmeden önce staging ortamında test edilmelidir.
- Eklenti devre dışı bırakıldığında operasyon verisi silinmez.


## v0.6.0 — Kommo & AI
- Program başına sabit, tokenlı Kommo AI kaynak URL'si
- Program/etkinlik/seans/fiyat değişikliklerinden otomatik senkron kuyruğu
- Kommo API bağlantı testi ve isteğe bağlı Program pipeline lead senkronu
- Kommo AI URL source ekleme (API token/subdomain yapılandırıldığında)
- Kaynak değişince `refresh_needed` uyarısı; stale duplicate source oluşturmaz
- Onaylı WhatsApp şablonlarının program bazlı doğrulama takibi
- Arama kelimeleri / Türkçe karakter varyasyonları otomatik üretimi

### Kommo kimlik bilgileri
Token veritabanına kaydedilmez. `wp-config.php` içine ekleyin:
```php
define('MMC_KOMMO_SUBDOMAIN', 'hesabiniz');
define('MMC_KOMMO_TOKEN', 'LONG_LIVED_TOKEN');
```
Kommo AI public API'de URL kaynak ekleme desteklenir. Var olan URL kaynağını yeniden tarama için dokümante edilmiş bir public AI refresh endpoint bulunmadığından MMC değişiklikleri `refresh_needed` olarak işaretler; kaynak URL'sinin kendisi her zaman güncel veriyi üretir.


## v0.7.0 — Afiş / Sosyal Medya / Meta
- Program Dosyasından otomatik afiş briefi, Instagram gönderisi, Story, Reels, geri sayım, çekiliş taslağı ve Meta reklam metni üretimi
- İçerik sürümleme; onaylanmış/yayınlanmış içerik veri değişince sessizce ezilmez
- Tarih/salon/seans/fiyat değişikliğinde `Güncelleme Gerekli` durumu ve pazarlama görevi
- Program iptalinde içerikleri pasife alma ve aktif Meta reklamını durdurma için kritik görev
- Program Hazırlık Dashboardundaki hedef ilçelerden Meta hedef coğrafya özeti
- Kampanya adı, bütçe, tarih, UTM, harici Campaign/Ad Set/Ad kimlikleri ve performans alanları
- Harcama + satın alma + atfedilen cirodan CPA ve ROAS hesabı
- Canlı Meta reklam oluşturma/başlatma bu sürümde kapalıdır; API yetkileri doğrulanınca yönetici onaylı ayrı aşamada açılacaktır.


## v0.8.0 — Okul & Saha Tanıtımı
- Hazırlık Dashboardunda seçilen hedef ilçelerdeki aktif okulları Program Dosyasına snapshot olarak aktarma
- Program bazlı hedef okul, hedef öğrenci, ziyaret edilen okul ve tanıtım kapsamı KPI'ları
- Okulları WordPress kullanıcısına veya personel adına toplu atama
- Uygun olmayan okulları silmeden `Kapsam Dışı` durumuna alma ve yeniden planlamaya döndürme
- KML dışa aktarımı; koordinatı olan okullar nokta, diğerleri adres olarak dosyaya yazılır
- Circuit / Spoke / Google Maps / özel rota bağlantılarını program veya personel bazında saklama
- WordPress hesabı gerektirmeyen süreli saha portalı paylaşım linki; ham token veritabanında tutulmaz, yalnız SHA-256 özeti saklanır
- Personel portalından okul ziyaret sonucu, ziyaret saati, fiilen ulaşılan öğrenci, dağıtılan materyal ve not kaydı
- `Ziyaret edildi` durumunda okul tabela fotoğrafını zorunlu doğrulama olarak yükleme
- Fotoğraf yüklemede JPG/PNG/WEBP ve 8 MB sınırı
- Saha portalında program/personel bazlı okul listesi, rota bağlantıları ve KML indirme
- Son saha ziyaretleri ve fotoğraf doğrulamalarını yönetici ekranında izleme
- İlçe hedef tablosuna `is_selected` alanı eklenerek pazarlama hedef coğrafya sorgusu ile şema uyumu

### Saha kullanım modeli
1. Hazırlık Dashboardunda tanıtım ilçelerini seçin.
2. `Okul / Saha` ekranında hedef ilçelerdeki okulları saha havuzuna aktarın.
3. Gerekirse bazı okulları `Kapsam Dışı` yapın.
4. Okulları personele dağıtın; KML ve/veya Circuit/Spoke rota bağlantılarını ekleyin.
5. Personel için 1–90 gün geçerli paylaşım linki oluşturun.
6. Personel bağlantıdan okul listesini görür; ziyaret kaydında tabela fotoğrafı yükler.
7. Dashboard hedef okul, ziyaret, hedef öğrenci, ziyaret edilen okul havuzu ve fiilen bildirilen öğrenci erişimini ayrı ayrı izler.

### Gizlilik ve güvenlik
- Personelin anlık/gerçek zamanlı konumu toplanmaz.
- Paylaşım tokenının yalnız hash'i saklanır; ham bağlantı oluşturulduğu anda bir kez gösterilir.
- Portal sayfaları arama motorlarına kapalıdır ve referrer göndermez.
- Ziyaret fotoğrafı yalnız tanımlı görsel dosya türleriyle ve boyut sınırıyla kabul edilir.
- Canlı siteye geçmeden önce staging ortamında okul listesi, fotoğraf yükleme ve token iptal senaryoları test edilmelidir.


## v0.9.0 — Operasyon & Lojistik
- Program kesinleştikçe otomatik operasyon planı ve görev oluşturma
- Operasyon tipi: henüz belirlenmedi / günübirlik / konaklamalı / sonraki şehre devam
- Ankara çıkış, salon giriş, kurulum, prova, kapı açılışı, söküm ve dönüş/sonraki şehir zaman planı
- Araç, personel, sanatçı, ekipman ve hizmet/tedarik ana kaynak kayıtları
- Kaynakları Program Dosyasına görev/rol ve durumla atama
- Hareket öncesi, salon/kurulum, gösteri günü ve gösteri sonrası olmak üzere dört fazlı operasyon kontrol listesi
- Araç/şoför, ekip/sanatçı, kostüm/ekipman, salon giriş, ses/ışık, kulis, POS, QR/check-in, gişe, güvenlik, yönlendirme ve seans satış kontrolü
- Gösteri sonrası seyirci tahliyesi, söküm, kostüm/ekipman sayımı, araç yükleme, temizlik, salon teslimi, satış mutabakatı ve dönüş/sonraki şehir hareketi
- Etkinlik seanslarından otomatik günlük akış / run-sheet oluşturma
- Seans bazlı satış, kalan kapasite, doluluk ve ciroyu operasyon ekranında gösterme
- Günübirlik operasyonda konaklama kontrolünü otomatik `Gerekli Değil` yapma; konaklamalı operasyonda yeniden zorunlu takibe alma
- Operasyon planındaki otel, yemek, ulaşım/yakıt ve diğer bütçeleri mevcut finans tablosuna `planlanan gider` olarak bağlama
- Salon çıkış teslimi tamamlandığında gelir-gider mutabakatı ve teminat iade süreci için finans görevleri oluşturma
- Zorunlu hareket öncesi + salon kurulum kontrolleri tamamlanınca Programı `Operasyon Hazırlığı` aşamasına ilerletebilme
- Zorunlu gösteri sonrası kontroller tamamlanınca Programı `Finansal Kapanış` aşamasına taşıma
- v0.8 verileri korunarak yükseltme; açık programlar için operasyon planı/checklist backfill

### Operasyon kullanım modeli
1. Salon/etkinlik kesinleştiğinde MMC operasyon planını otomatik oluşturur.
2. Operasyon tipini seçin; hareket, salon giriş, prova, kapı açılışı ve dönüş saatlerini girin.
3. Araç, sanatçı, personel, ekipman ve hizmetleri kaynak havuzundan programa atayın.
4. Konaklama/yemek/ulaşım planını ve bütçelerini girin; bunlar finans modülüne planlanan gider olarak yansır.
5. Dört fazlı kontrol listesini sorumlularla ilerletin; sorunları `Sorun Var` olarak işaretleyin.
6. Etkinlik/seans akışını yenileyin; gösteri saatleri otomatik run-sheet'e gelir.
7. Gösteri sonrası salon teslimi ve satış mutabakatını kapatın.
8. Sistem finansal kapanış ve teminat iade görevlerini otomatik açar.

### Operasyon veri ilkeleri
- Kaynak ana kayıtlarında operasyon için gerekli asgari bilgi tutulur; pasaport/kimlik gibi hassas personel belgeleri bu modülde saklanmaz.
- Günübirlik/konaklamalı kararını sistem tahmin etmez; yönetici seçer.
- Operasyon bütçeleri önce `planned` finans kaydıdır; gerçek ödeme/mahsup finans modülünde tamamlanacaktır.
- Canlı kullanım öncesinde staging ortamında plan → checklist → run-sheet → finans tetikleyicisi akışı test edilmelidir.

## v1.0.0 — Finans & Teminat Kapanış Modülü

Bu sürüm Program Dosyasının finansal yaşam döngüsünü tamamlar.

### Finans defteri
- WooCommerce / PayTR web satış geliri satış defterinden otomatik okunur; ikinci kez manuel gelir yazılmaz.
- Web dışı gelirler: gişe nakit, gişe POS, Biletinial, havale/EFT, yiyecek-mısır, oyuncak, sponsor/destek ve diğer gelir.
- Giderler: salon kirası, temizlik/güvenlik, sanatçı/personel, Meta reklam, afiş/tanıtım, ulaşım/yakıt, otel/konaklama, yemek, bilet/POS komisyonu, izin/resmî ödeme, diğer operasyon ve diğer gider.
- Operasyon modülündeki bütçeler ve Meta gerçek harcaması aynı finans defterine bağlanır.
- Planlanan ve gerçekleşen giderler ayrı izlenir.

### Teminat
- Salon teminatı gider değildir; `deposit_asset` olarak ayrı tutulur.
- Ödenmiş teminat için otomatik iade takip kaydı açılır.
- Teminat iade dilekçesi program + salon bilgilerinden otomatik taslak üretilir.
- İade / kısmi iade / kesinti takibi yapılır.
- Kesinti varsa yalnız kesinti tutarı `deposit_deduction` gideri olarak yazılır.
- Teminat yatırılmamışsa veya iadesi sonuçlanmamışsa finansal kapanış engellenir.

### Fatura kuyruğu
- Başarılı WooCommerce siparişleri Mikro e-Portal çalışma kuyruğuna alınabilir.
- Fatura bekliyor / kesildi / alındı / gerekli değil / iptal durumları takip edilir.
- Fatura numarası, tarih ve Drive/Mikro bağlantısı saklanabilir.
- v1.0 otomatik Mikro faturası kesmez; mevcut manuel süreç güvenli biçimde takip edilir.

### Nihai kârlılık
- Toplam gelir
- Gerçekleşmiş gider
- Net kâr / zarar
- Kâr marjı
- Web seyirci kapasitesi
- Web dışı ücretli seyirci
- Davetli / ücretsiz seyirci
- Toplam gerçek seyirci
- Kişi başı gelir / gider
- İade bekleyen teminat
- Bekleyen fatura sayısı

### Kapanış kuralı
Program yalnız şu şartlar tamamlandığında `Tamamlandı` durumuna geçebilir:
1. Planlanan/bekleyen gelir-gider kalemi kalmamalı.
2. Yatırılması gereken teminat ödenmiş olmalı.
3. WooCommerce fatura kuyruğunda bekleyen kayıt kalmamalı.
4. Teminat iade veya kesinti sonucu tamamen çözülmüş olmalı.

Gelir-gider kapanışı tamamlanmış ancak teminat açık ise program `Teminat İadesi Bekleniyor` aşamasında kalır. Son teminat sonucu işlendiğinde sistem nihai snapshot alır ve Program Dosyasını finansal olarak kapatır.

## v1.1.0 — Yönetici Dashboardu

- Ana ekran, Madagaskar yönetim standardındaki 7 ana göstergeye göre yeniden tasarlandı.
- Bugünkü sipariş/ciro, bilet/kişi kapasitesi, Meta harcama, CPA/ROAS, başarısız ödeme/iade ve sıradaki gösteri tek görünümde.
- Aktif Müşteri/Lead kartı için yanıltıcı proxy kullanılmaz. Gerçek Kommo müşteri-lead metriği `mmc_dashboard_active_leads` filtresiyle bağlanana kadar değer `—` gösterilir; senkron Program Lead sayısı açıklama olarak görünür.
- Program Kontrol Merkezi; faz, satış/doluluk, saha %, operasyon %, finansal kâr/marj, Meta ve riskleri program satırında birleştirir.
- Risk motoru: kritik/gecikmiş görev, yaklaşan gösteride düşük doluluk, eksik saha, eksik operasyon, operasyon problemi, Meta stop_required, Kommo AI refresh/error, bekleyen fatura ve teminatı işaretler.
- Dashboard salt okunurdur; açılması satış/finans/operasyon kayıtlarını değiştirmez.


## v1.3.0 — Gece Rapor Motoru + Gmail

- Varsayılan alıcı: `milanosirki@gmail.com`.
- Varsayılan gece çalışma saati: Türkiye/WordPress site saatine göre 02:00.
- Her gece önceki takvim gününün satış/sipariş/bilet/ciro verisini raporlar.
- Önceki gün ile sipariş ve ciro karşılaştırması yapar.
- Başarısız ödeme, iade adedi ve iade tutarını raporlar.
- WooCommerce satış defteri ile fatura kayıtlarını doğrudan karşılaştırarak faturası eksik veya `pending` sipariş sayısını çıkarır; rapor üretirken finans kayıtlarını değiştirmez.
- Program bazında toplam satış, kapasite, doluluk, seans dolulukları, saha ilerlemesi, operasyon hazırlığı, finans/kârlılık, Meta ve risk durumunu e-postaya ekler.
- Meta aktif kampanyalarının toplam harcama/satın alma/CPA/ROAS verisini ve önceki başarılı gece raporu snapshot'ına göre harcama farkını gösterir.
- Kritik/yüksek riskleri ve ilk 3 yönetim önceliğini otomatik ekler.
- E-postanın sonunda sabah ChatGPT akışının kolay okuyabilmesi için `CHATGPT` uyumlu yapılandırılmış metin bölümü bulunur.
- Konu standardı: `MADAGASKAR GECE RAPORU — DD.MM.YYYY`.
- Test e-postası ayrı `MMC E-POSTA TESTİ` konusu kullanır; sabah otomasyonunun gerçek gece raporuyla karıştırması önlenir.
- `Gece Raporları` yönetim ekranında alıcı, saat, sonraki cron zamanı, test gönderimi, manuel gece raporu gönderimi ve rapor geçmişi görülebilir.
- Her raporun HTML gövdesi ve veri snapshot'ı `mmc_report_runs` tablosunda saklanır; günlük karşılaştırmalar için önceki snapshot kullanılabilir.
- Aynı tarih için cron tekrar çalışırsa daha önce başarıyla gönderilmiş raporu ikinci kez otomatik göndermez. Yönetici manuel gönderimde bilinçli olarak yeniden gönderebilir.

### WP-Cron notu
WordPress WP-Cron trafik tabanlıdır. Site 02:00'de ziyaret almıyorsa görev 02:00'den sonraki ilk WordPress isteğinde çalışabilir. Dakik zamanlama istenirse sunucuda gerçek cron ile `wp-cron.php` düzenli çağrılmalıdır. MMC bunun dışında ek bir ücretli servis gerektirmez.

### Sabah ChatGPT zinciri
Site gece raporunu üretir → Gmail'e gönderir → mevcut sabah ChatGPT otomasyonu konusu `MADAGASKAR GECE RAPORU` ile başlayan son mesajı okur → satış, reklam, saha, operasyon, finans, risk ve ilk öncelikleri yönetici özeti olarak sunar.

## v1.3.2 — Okul Tanıtım Ana Kaynak Köprüsü
- Okul listeleri için tek ana kaynak ilkesi: Madagaskar → Okul Tanıtım.
- MMC okul adı/adres/koordinat bilgisini ayrı bir yönetim kaynağı olarak tekrar tutmaz; mevcut saha ilişkileri için salt-okunur uyumluluk cache'i kullanır.
- Okul Tanıtım kaynağı otomatik tablo/kolon tespitiyle bağlanabilir; özel kurulumlarda `mmc_school_source_config` filtresiyle açık eşleştirme verilebilir.
- Hazırlık Dashboardu okul sayısını Okul Tanıtım listesinden hesaplar; öğrenci sayısı yalnız ana kaynakta mevcutsa kullanılır.
- Okul / Saha ekranında hedef ilçeler Okul Tanıtım listesinden Programa bağlanır; ilk hedef öğrenci sayısı snapshot olarak korunur.
- Bölge Veri Ambarında haricî Okul Tanıtım kaynağı bağlıysa ikinci okul CSV içe aktarımı gizlenir.
- Kurulum & Sağlık ekranı Okul Ana Kaynağı durumunu ve okunabilen okul sayısını gösterir.

## v1.3.3 — Otomatik İl / İlçe Nüfus Kaynağı
- İl ve ilçe toplam nüfusunun tek ana kaynağı **Madagaskar 2025 Nüfus Verisi** eklentisidir.
- MMC `population_total` değerini kendi Bölge Veri Ambarına kopyalamaz; nüfusu doğrudan kaynak eklentinin sorgu fonksiyonlarından okur.
- 2025 veri seti hazır olduğunda 81 il ve 973 ilçe sağlık ekranında doğrulanır.
- Hazırlık Dashboardu seçilen tanıtım havzasındaki ilçelerin nüfusunu otomatik toplar ve kapsama oranını gösterir.
- İl geneli nüfus referansı aynı otomatik kaynaktan gelir.
- İlçe seçim listeleri nüfus eklentisindeki 973 ilçe kaydından beslenebilir.
- `Toplam nüfus` için manuel tek metrik / CSV kaydı engellenir; çift kayıt ve veri farklılığı önlenir.
- Nüfus kaynağı 0–14 yaş kırılımı sağlamadığı için MMC bu alanı tahmin etmez; yalnız doğrulanmış ayrı veri varsa gösterir.
- Nüfus eklentisinin eski DOM/JavaScript hazırlık köprüsü MMC aktifken devre dışı bırakılır; nüfus sunucu tarafında PHP ile okunur.

# Madagaskar Sirki — Claude Değişiklik Kaydı — 1–2 Ekim 2026

**Güncelleme:** 2 Ekim 2026, 12:00 TSİ
**Kapsam:** `madagaskarsirki.com` üzerinde Claude'un WordPress.com bağlantısıyla yaptığı içerik ve şablon değişiklikleri ile işletme sahibinin Code Snippets'e yapıştırdığı snippet güncellemeleri.
**Drive karşılığı:** `Madagaskar + USKD Codex Çalışma ve Yedek Rehberi` (Doc ID `1Q6-mOKbbkYZgWVcxFYK3yy9zLFjqEZEwKTJ14Z1PiZI`), "Claude" başlıklı bölümler.

Bu dosya canlıda olanın kaydıdır. Buradaki snippet kaynakları plugin koduna taşınmamıştır; `docs/live-captures/2026-10-02/` altında canlı kopya olarak durur.

## 1. Çalışma biçimi ve sınırlar

- Claude'un Code Snippets'e, WooCommerce günlüklerine ve WPVibe'a erişimi yoktur. Snippet kodunu Claude yazdı, işletme sahibi Code Snippets'e yapıştırdı.
- Claude canlı siteyi yalnızca giriş yapmamış ziyaretçi olarak, sayfa metni üzerinden okuyabildi. Görsel doğrulamalar işletme sahibinin ekran görüntülerine dayanır.
- Hiçbir snippet gerçek WordPress üzerinde otomatik test edilmedi. Hepsi PHP 8.4 sözdizimi denetiminden geçti; karar mantığı sahte WordPress/WooCommerce fonksiyonlarıyla çalıştırıldı (`docs/live-captures/2026-10-02/mantik-testleri/`).
- Sipariş, ürün, ödeme, Tickera ve Kommo verisine yazan hiçbir değişiklik yapılmadı.

## 2. Snippet değişiklikleri

| Snippet | Önceki | Canlıdaki | Kaynak dosya | Canlı doğrulama |
|---|---|---|---|---|
| MS Global Alt Bilgi (envanterde "#21 global alt bilgi"; ID canlıda doğrulanmadı) | V3 | V3.3 | `ms-global-alt-bilgi-v3.3.php.txt` | Alt bilgi bağlantıları ekran görüntüsüyle, sekiz bağlantılı yasal satır ziyaretçi okumasıyla doğrulandı. Çerez bildirimi ana sayfada ekranda göründü (ekran görüntüsü, 2 Ekim 11:55). |
| #35 MS Anasayfa (`[ms_anasayfa_v2]`) | V3 | V3.2 | `snippet-35-ms-anasayfa-v3.2.php.txt` | "BUGÜN" rozeti, dört yeni soru ve krem arka plan ekran görüntüsüyle doğrulandı. |
| MS Bilet Sorgulama (yeni) | — | V1 | `ms-bilet-sorgulama-v1.php.txt` | Form `/biletlerim/` sayfasında bir kez görünüyor. Form gönderimi gerçek siparişle denenmedi. |
| MS Yarım Kalan Ödeme Kaydı, deneme modu (yeni) | — | V1 | `ms-yarim-kalan-odeme-kaydi-v1-deneme.php.txt` | İşletme sahibi eklediğini bildirdi. Dışarıdan doğrulanamaz; günlük kayıtları kontrol edilmedi. |

Değişiklikten önceki iki kaynak ve alt bilginin ara sürümü V3.2 `docs/live-captures/2026-10-02/onceki/` altındadır.

### 2.1 MS Global Alt Bilgi V3 → V3.3

- **Amaç:** Görünen alt bilgide eksik bağlantıları tamamlamak ve çerez bildirimini görünür kılmak.
- **Değişenler:**
  - Hızlı Bağlantılar'a Kurumsal, Blog ve Sık Sorulan Sorular eklendi.
  - Yasal satıra Ön Bilgilendirme Formu, İptal, İade ve Bilet Teslimatı, Kullanım Koşulları ve Veri Silme Talimatları eklendi. İptal/İade bağlantısı daha önce çıkmıyordu; snippet sayfayı var olmayan adreslerle arıyordu, gerçek adres `iptal-iade-ve-bilet-teslimati` eklendi.
  - Çerez bildirimi bu snippet'e taşındı (`ms-cerez-banner`, `ms-cerez-accept`, `ms-cerez-reject`). Tercih anahtarı aynı: `kvkk_cookie_consent`.
- **Hook'lar:** `wp_head` (99) stil; `wp_footer` (99) alt bilgi; `wp_footer` (100) çerez bildirimi.
- **Bağımlılık:** Yok. `ms_footer_find_page_url()` aynı snippet içindedir.
- **Bilinen sınır:** Çerez bildirimi yalnızca tercihi tarayıcıya kaydeder. "Reddet" seçildiğinde herhangi bir çerezi veya izleme kodunu engelleyen mantık yoktur; önceki sürüm de böyleydi.
- **V3.3 ile gelenler (2 Ekim, 11:50):**
  - Sitede iki gizlilik sayfası var ve işletme sahibinin kararıyla ikisi de kalıyor: `/gizlilik-ve-cerez-politikasi/` sitenin çerez ve gizlilik politikasıdır; `/gizlilik-politikasi/` Meta / Instagram çekiliş sistemi için tutulur.
  - Yasal satır sekiz bağlantı oldu: Gizlilik ve Çerez Politikası, KVKK Aydınlatma Metni, Mesafeli Satış Sözleşmesi, Ön Bilgilendirme Formu, İptal, İade ve Bilet Teslimatı, Kullanım Koşulları, Gizlilik Politikası (Sosyal Medya), Veri Silme Talimatları. V3.2'de "Gizlilik Politikası" etiketi Meta sayfasına gidiyordu ve sitenin çerez politikası satırda yoktu.
  - Çerez bildirimindeki bağlantı artık `/gizlilik-ve-cerez-politikasi/` adresine gider; metni "Gizlilik ve Çerez Politikamızı".
- **Geri alma:** Snippet içeriğini `onceki/ms-global-alt-bilgi-v3.2.php.txt` (bir önceki sürüm) veya `onceki/ms-global-alt-bilgi-v3.php.txt` (ilk hal) ile değiştirmek.

### 2.2 #35 MS Anasayfa V3 → V3.2

- **Amaç:** Gösteri günü olan kartı işaretlemek, ana sayfadaki soruları en çok sorulanlarla değiştirmek, masaüstünde görünen kırmızı arka planı kapatmak.
- **Değişenler:**
  - Yeni `ms_home_v3_city_is_today()` fonksiyonu ve kartta "BUGÜN" rozeti.
  - Dört soru: bilet fiyatları, 0–2 yaş, okul davetiyesi, kapı açılış saati. Yanıtlar SSS sayfası (ID 112) 5., 12., 14. ve 7. sorularla aynı kuralları taşır. Sabit fiyat yazılmadı.
  - `body.home` ve ana sayfanın içerik kapsayıcısı için arka plan `#f5f0e6`.
- **Hook'lar:** Yalnızca `add_shortcode( 'ms_anasayfa_v2' )`; değişmedi.
- **Bağımlılık:** `ms_city_v2_live_cities()` (MS Şehirler Dinamik V2). Bu fonksiyonun döndürdüğü alan adları Claude'da bilinmiyor. `ms_home_v3_city_is_today()` önce `date`, `date_raw`, `event_date`, `start_date`, `date_ymd`, `ymd` alanlarında `YYYY-AA-GG` arar; bulamazsa `date_label` içindeki "2 Ekim 2026" biçimini Türkçe ay adlarıyla okur ve `wp_date( 'Y-m-d' )` ile karşılaştırır. Tarih anlaşılamazsa rozet gösterilmez. Canlıda `date_label` okuması çalıştı.
- **Bilinen sınır:** Sayfa önbelleğe alındığı için rozet gece yarısından sonra önbellek yenilenene kadar gecikebilir.
- **Geri alma:** Snippet içeriğini `onceki/snippet-35-ms-anasayfa-v3.php.txt` ile değiştirmek.

### 2.3 MS Bilet Sorgulama V1 (yeni)

- **Amaç:** Misafir olarak bilet alan müşterinin biletine WhatsApp'a yazmadan ulaşabilmesi.
- **Davranış:** `/biletlerim/` sayfasının sonuna form ekler; ayrıca `[ms_bilet_sorgu]` kısa kodunu tanımlar. Sipariş numarası ve telefonun son 10 hanesi, durumu `processing` veya `completed` olan bir `shop_order` ile birlikte eşleşirse `ms_biletlerim_url()` çıktısını gösterir.
- **Hook'lar:** `template_redirect` (1) önbellek başlıkları; `init` (20) kısa kod; `the_content` (30) form.
- **Bağımlılık:** `ms_biletlerim_url()` (MS Biletlerim snippet'i; kaynağı bu depoda yok). Fonksiyon yoksa veya boş dönerse müşteriye "bağlantı oluşturulamadı" mesajı gösterilir.
- **Güvenlik:**
  - Hiçbir veri yazmaz.
  - Tek genel hata mesajı vardır; hangi bilginin yanlış olduğu söylenmez. Ödenmemiş sipariş de aynı mesajı verir.
  - Sınır: aynı IP'den 15 dakikada 5 hatalı deneme, aynı sipariş numarasına saatte 20 hatalı deneme (transient).
  - Dizi olarak gönderilen alanlar yok sayılır; sipariş numarası 9 haneyle sınırlıdır.
  - Bilerek nonce kullanılmadı: sayfa önbelleğe alındığında giriş yapmamış ziyaretçinin nonce'u eskiyip formu bozar; işlem salt okunurdur ve oturuma bağlı değildir.
- **Doğrulanması gereken:** `REMOTE_ADDR` WordPress.com üzerinde gerçek ziyaretçi IP'sini veriyor mu? Vermiyorsa IP sınırı bütün ziyaretçileri birlikte sayar.
- **Geri alma:** Snippet'i devre dışı bırakmak.

### 2.4 MS Yarım Kalan Ödeme Kaydı V1 — deneme modu (yeni)

- **Amaç:** Ödemesi tamamlanmayan siparişler için hatırlatma göndermeden önce, kime gönderileceğini günlükte görmek.
- **Davranış:** İçinde gönderim kodu yoktur. Kommo'ya yazmaz, siparişi ve sipariş notlarını değiştirmez. Sipariş `pending` veya `failed` durumunda 20 dakika kaldıysa WooCommerce günlüğüne "GÖNDERİLİRDİ" ya da nedeniyle "GÖNDERİLMEZDİ" yazar. Günlük kaynağı: `madagaskar-odeme-hatirlatma`.
- **Hook'lar:** `woocommerce_new_order`, `woocommerce_order_status_pending`, `woocommerce_order_status_failed` (hepsi 99) yalnızca Action Scheduler ile kontrol planlar; planlanan kanca `ms_oh_kontrol`, grup `madagaskar-odeme-hatirlatma`. Bu kancalardaki her hata yutulur.
- **Bağımlılık:** WooCommerce, Action Scheduler (yoksa WP-Cron).
- **Atlanan durumlar:** ödenmiş veya iptal; `checkout-draft`; ödeme sayfasından açılmamış sipariş; geçerli telefon yok; aynı telefon bu siparişten sonra başka bir siparişi ödemiş.
- **Henüz yok:** Etkinliğin satışı kapandı veya seans başladı kontrolü (ürün–etkinlik eşlemesi gerekir) ve gönderim aşaması. Gönderim, işletme sahibinin mesaj metni onayına bağlıdır ve Kommo tarafında WhatsApp gönderen bir adım gerektirir; mevcut otomasyon yalnızca Kommo kartına alan yazıyor.
- **Risk:** Sipariş oluşturma anında çalışan kancalara bağlanır. Canlıda ödeme akışı deneme siparişiyle doğrulanmalıdır; bu doğrulama yapılmadı.
- **Geri alma:** Snippet'i devre dışı bırakmak. Planlanmış `ms_oh_kontrol` eylemleri kanca kalkınca etkisiz kalır.

## 3. WordPress.com üzerinden yapılan içerik ve şablon değişiklikleri

### 1 Ekim 2026

- **Header şablon parçası (`assembler//header`):** WhatsApp butonu `https://wa.me/903129113710` adresine yönlendirildi.
- **Tema alt bilgisi (`assembler//footer`):** Yasal bağlantılar eklendi. Bu şablon parçası canlıda gizlidir (bkz. bölüm 4); değişiklik ziyaretçiye görünmüyor.
- **Çöpe taşınan sayfalar (geri alınabilir):** 1320, 1321, 1322, 1323, 1324, 1325, 1253, 236, 238, 239, 235. Dört eski adres için 301 yönlendirmeleri PR #71 ile eklendi.
- **SSS sayfası (ID 112):** 26 soru olarak yeniden yazıldı; okul davetiyesi kuralı eklendi.
- **Blog yazıları:** 3601–3605 yayınlandı; 2691, 2680, 2685, 2700 güncellendi; 13 eski yazının SEO alanları dolduruldu.

### 2 Ekim 2026

- **Üst menü (navigation ID 1357):** 10 öğeden 6 öğeye indirildi: Gösteriler, Şehirler, Kurumsal, SSS, İletişim, Bilet Al. Çıkarılanlar: Anasayfa, Galeri, Hakkımızda, Blog. Bunların hepsi görünen alt bilgide bağlantı olarak duruyor. Kurumsal sabah çıkarılmıştı; işletme sahibinin isteğiyle aynı gün geri eklendi.
- **Tema alt bilgisi (`assembler//footer`):** "Keşfet" sütunu eklendi. Gizli olduğu için ziyaretçiye görünmüyor; snippet devre dışı kalırsa görünür.
- **Şehir sayfaları:** `/sehirler/` altındaki 11 statik sayfanın içeriği ve özeti yeniden yazıldı (ID 37 Ankara, 41 Bursa, 45 İzmir, 261 İstanbul, 264 Antalya, 266 Adana, 268 Gaziantep, 270 Konya, 272 Mersin, 274 Kayseri, 276 Eskişehir). Yeni içerik tarih, salon veya fiyat yazmaz; güncel bilgi için `/sehirler/` sayfasına yönlendirir. Eski içerikten yalnızca Ankara ve Kayseri değiştirilmeden önce okundu: Ankara 26 Eylül gösterisini "biletler satışta" diye tanıtıp o tarihin ürün sayfasına gönderiyordu; Kayseri'de örnek tarihler, sirk çadırı, 2 saatlik süre ve example.com görseli vardı. Diğer dokuz sayfa okunmadan değiştirildi; WordPress sayfa revizyonlarında durmaları beklenir, kontrol edilmedi.
- **Ankara sayfası (ID 37) SEO alanları:** Jetpack başlık ve açıklama alanları dolduruldu; çıktıyı değiştirmedi (bkz. K7).

## 4. Bu çalışmada ortaya çıkan bulgular

- **İki alt bilgi:** MS Global Alt Bilgi snippet'i temanın alt bilgisini `display:none` ile gizleyip kendi alt bilgisini `wp_footer` ile basıyor. Sayfa kodunda ikisi de duruyor. Çerez bildirimi gizlenen tema alt bilgisinin içinde olduğu için görünmüyordu.
- **Genel arka plan:** Tema ayarlarında site arka planı `theme-2` (`#dd4c3c`). İçeriğin kaplamadığı her yerde bu renk görünür. Ana sayfa V3.2 yalnızca ana sayfada krem yapar; diğer sayfalara bakılmadı.
- **Header'da iki "Bilet Al":** Menüde bağlantı ve menünün dışında ayrı bir buton bloğu var. Butonların telefonda görünüp görünmediği doğrulanamadığı için menüdeki bağlantı bırakıldı.
- **Yasal bağlantılar iki kez:** Alt bilginin üstündeki sarı şerit (kaynağı başka bir snippet) ve alt bilgideki yasal satır aynı bağlantıları tekrar ediyor.
- **Çekiliş Sonuçları yalnızca alt bilgide:** İşletme sahibinin kararı. Çekiliş sosyal medyada yapılıyor, sitede yalnızca sonuç yayımlanıyor; menüye eklenmeyecek.
- **Deneme siparişi #3907 (2 Ekim):** Ödemesi tamamlanmayan sipariş için Kommo'da "Order#3907" kartı 10:28'de "Beklemede olan siparişler" aşamasında açıldı, sipariş `failed` olunca 11:01'de "Closed – lost" aşamasına taşındı (Kommo ekran görüntüsü). Yani ödenmemiş siparişin kartı hatırlatma için kullanılabilecek halde Kommo'da zaten oluşuyor.
- **Başarısız sipariş e-postası:** WooCommerce müşteriye "siparişiniz başarısız oldu" e-postası gönderiyor. E-posta kırmızı zemin üzerinde koyu kırmızı yazıyla geliyor ve okunmuyor, ödeme bağlantısı içermiyor, telefon bölümünün başlıkları İngilizce. Değiştirilmedi.
- **Yatay kaydırma çubuğu:** 2 Ekim 11:55 masaüstü ekran görüntüsünde ana sayfanın altında yatay kaydırma çubuğu var; bir öğe sayfa genişliğini aşıyor. Kaynağı belirlenmedi.
- **Metin okumasından gelen yanlış bulgu:** "Ana sayfadaki boş sepet kutusu" ve Biletlerim sayfasındaki "boş sepet tablosu", header'daki WooCommerce mini sepet bloğunun metne yansımasıydı; hata değildir.

## 5. İş listesi durumu

| İş | Durum |
|---|---|
| K1 — Bilet sorgulama formu | Canlıda (snippet V1). Gerçek siparişle deneme bekliyor. |
| K2 — Yarım kalan ödeme hatırlatması | Deneme modu canlıda bildirildi; günlük satırı henüz görülmedi. Gönderim aşaması yazılmadı; işletme sahibinden Kommo otomasyon ekranı, karttaki telefon bölümü, "Ödeme Linki" alanı ve mesaj metni onayı bekleniyor. |
| K3 — Etkinlik sayfasını sadeleştirme | Başlanmadı. Kaynak depoda yok. |
| K4 — Şehirler ve Bilet Al listesinin birleşmesi | Kaynak bulundu: tarihsel MS Şehirler Dinamik V2 kaynağı `docs/recovered-sources/2026-10-02/` altına alındı. Canlı Snippet #30 exact capture ve Issue #87 tarih guard doğrulaması WPVibe açılınca yapılacak. |
| K5 — Ana sayfa | Canlıda (snippet V3.2). |
| K6 — Alt bilgi snippet'i | Canlıda (snippet V3.3). |
| K7 — Ankara sayfasının başlık ve açıklaması hâlâ "26 Eylül 2026" | Açık. Değeri bir snippet basıyor; envanterdeki #25/#26/#27 veya #15 olası kaynak. |
| K8 — 26 Eylül ürün sayfası hâlâ açılıyor | Açık. `/urun/madagaskar-sirki-ankara-26-eylul-2026-1200-bileti/` fiyat göstererek açılıyor; satın alma denenmedi. |
| K9 — Kırmızı arka plan | Ana sayfada kapatıldı. Site geneli karar işletme sahibinde. |
| K10 — Üstte iki "Bilet Al" | Açık. Telefon görünümü doğrulanınca menü düzeltilecek. |

## 6. Codex için

1. Bu dosyadaki dört snippet (alt bilgi V3.3 dahil) canlıda Code Snippets içinde duruyor; plugin koduna taşınırken kaynak olarak `docs/live-captures/2026-10-02/` kullanılabilir.
2. K1 ve K2 için canlı smoke test yapılmadı. Yapılacaklar: gerçek bir misafir siparişiyle bilet sorgulama; bir deneme siparişiyle ödeme akışı; 20 dakika sonra `madagaskar-odeme-hatirlatma` günlüğü.
3. `ms_city_v2_live_cities()` kaynağı recovery dosyasından bulundu. Public geçmiş-event sızıntısı için Issue #87 açıldı; exact live Snippet #30 ve legacy session start_at/end_at WPVibe açılınca doğrulanmalı. `ms_home_v3_city_is_today()` mevcut `date` alanını okuyabiliyor.
4. K7 ve K8 Claude'un erişimiyle çözülemiyor.

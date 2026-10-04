# Geçmiş seanslara satışın kapatılması — 4 Ekim 2026

**Durum:** Taslak. Canlıya alınmadı. Canlıda hiçbir dosya, snippet veya veri değiştirilmedi.
**Issue:** #116
**Etkilenen eklenti:** `wp-content/plugins/madagaskar-bilet-yonetimi` (canlı sürüm `3.6.3-ticket-invalidation-dry-run`)
**Hazırlayan:** Claude, işletme sahibinin isteğiyle.

## 1. Sorun

Etkinlik sayfası ve sepete ekleme adımı seans saatine bakmıyor.

Canlı gözlem, 4 Ekim 2026, giriş yapmamış ziyaretçi:

| Saat (TSİ) | Sayfa | Görülen |
|---|---|---|
| 15:25 | `/etkinlik/madagaskar-sirki-yenimahalle-04-ekim-2026/` | 12:00, 14:00, 16:00 seanslarının üçü de seçilebilir. 12:00 ve 14:00 bitmişti. |
| 15:27 | `/etkinlik/madagaskar-sirki-sincan-03-ekim-2026/` | Bir gün önceki gösteri "Satışta"; üç seans ve bilet seçici açık. |
| 15:21 | `/`, `/sehirler/`, `/bilet-al/` | Doğru: Yenimahalle için yalnızca 16:00; Sincan yok. |

Satın alma denenmedi. Sunucunun bitmiş seans için siparişi gerçekten kabul ettiği canlıda doğrulanmadı; aşağıdaki kaynak incelemesi kabul edeceğini gösteriyor.

## 2. Kök neden

Kaynak dosyalar canlıyla aynıdır (`docs/STAGE4_SOURCE_INVENTORY_20261004.json` içindeki SHA256 değerleriyle eşleşti).

- `MDG_Public_Event::render()`: seanslar `MDG_Sessions::by_event()` ile zaman filtresi olmadan alınır.
- `MDG_Live_Sales::add_to_cart()`: etkinlik durumu, seans durumu, ürün, fiyat ve kapasite doğrulanır; seans saati doğrulanmaz.
- `MDG_Live_Sales::reserve_or_throw()`: ödeme öncesinde yalnızca seans durumuna bakar.
- Listeler ayrı kod yolundadır ve `end_at >= şimdi` uygular (`MDG_Public_Tickets`, `MDG_Public_Cities`, snippet #30). Snippet #30 ayrıca Türkiye yerel gününe göre geçmiş başlangıç günlerini eler (Issue #87).

Bitmiş bir etkinliğin satışı bugün yalnızca elle kapanır: V4 satış kapatma veya etkinlik durumunun değiştirilmesi.

## 3. Karar ve kural

**İşletme sahibi kararı (4 Ekim 2026, 18:46 TSİ): satış seans başladığında kapanır.**

Kural tek yerde: `MDG_Sessions::sales_closed_by_time( $session, $now_ts = null )`.

1. Başlangıç saati geldiyse veya geçtiyse (`start_at <= şimdi`) seans kapalıdır.
2. Başlangıç okunabiliyor ve gelecekteyse seans açıktır; `end_at` değeri bozuk olsa bile yaklaşan bir gösterinin satışı durmaz.
3. Başlangıç okunamıyorsa bitişe bakılır: `end_at < şimdi` ise kapalıdır.
4. İkisi de okunamıyorsa satış kapanmaz; bugünkü davranış sürer.

Listeler de aynı kurala çekildi: bir seans yalnızca başlamadıysa (`start_at > şimdi`) listelenir. Böylece liste ile etkinlik sayfası aynı şeyi gösterir. Önceki kural `end_at >= şimdi` idi; yani seans sürerken liste onu hâlâ "satışta" gösteriyordu.

Tarih ayrıştırması bilerek istisna üretmeyen biçimde yazıldı (düzenli ifade + `checkdate` + `gmmktime`): ilk CI çalıştırmasında, Xdebug yüklü PHP 8.3.6 ortamında bozuk bir tarih değeri yakalanamayan bir hataya dönüştü; ödeme akışında bozuk veri hiçbir koşulda hata fırlatmamalı.

### Müşteri açısından sonuç

- Seans saatinde ve sonrasında o seansa bilet alınamaz; etkinlik sayfasında o seans görünmez.
- Günün son seansı başladığında etkinlik listelerden çıkar ve sayfasında "Bu gösterinin bilet satışı sona erdi" bildirimi çıkar.
- Sepetine başlamadan önce bilet ekleyip ödemeye seans başladıktan sonra geçen müşteri, ödeme adımında "bilet satışı sona erdi" mesajını görür; sipariş ödemeye geçmez.
- Siparişi seans başlamadan önce oluşmuş ve PayTR ekranında bekleyen müşterinin ödemesi etkilenmez.

## 4. Değişen dosyalar

| Dosya | Değişiklik |
|---|---|
| `includes/class-mdg-sessions.php` | Yeni `sales_closed_by_time()` ve özel `utc_timestamp()`. Mevcut fonksiyonlara dokunulmadı. |
| `includes/class-mdg-public-event.php` | Canlı sayfada başlamış seans seçicide gösterilmez; ilk açık seans seçili gelir. Bütün seanslar başladıysa seçici, bilet satırları ve sepet düğmesi yerine "Bu gösterinin bilet satışı sona erdi" bildirimi ve `/bilet-al/` bağlantısı çıkar; sepet yapılandırması kapalı gelir, sepet nonce'u üretilmez, yapılandırılmış veriden "stokta" teklifi çıkar. Taslak önizlemede (yönetici) saat kontrolü yapılmaz. |
| `includes/class-mdg-live-sales.php` | `add_to_cart()` ve `reserve_or_throw()` başlamış seansı Türkçe bir mesajla reddeder. İkincisi doğrudan ürün sayfasından (`/urun/...`) sepete eklenen biletleri de ödeme öncesinde yakalar. |
| `includes/class-mdg-public-cities.php` | Liste sorgusu: `s.end_at >= %s` → `s.start_at > %s`. |
| `includes/class-mdg-public-tickets.php` | Liste sorgusu aynı şekilde; seans etiketleri `sales_closed_by_time()` kullanır. |
| `tests/ticket-sales-cutoff/regression.php` | 73 izole kontrol. |
| `.github/workflows/ticket-datetime-stage.yml` | Yeni testin CI'da çalıştırılması. |

Değişmeyenler: veritabanı şeması, etkinlik ve seans durumları, WooCommerce ürünleri, Tickera, PayTR, V4 satış kapatma, kapasite hesabı, JS ve CSS dosyaları, eklenti sürüm numarası. Hiçbir veri yazılmaz; kural her istekte hesaplanır.

### Bu PR'ın dışında kalan liste kaynağı

Ana sayfa, `/sehirler/` ve `/bilet-al/` listelerini canlıda **Code Snippet #30** (`ms_city_v2_event_sessions`, `ms_city_v2_live_cities`) üretir; o da `end_at >= şimdi` kullanır. Aynı kurala çekilmiş hali `docs/code-snippets/staged-2026-10-04/snippet-030-seans-baslayinca-v1.php.txt` dosyasındadır (main). Snippet #30 güncellenmezse liste, seans sürerken etkinliği göstermeye devam eder; satın alma yine de reddedilir.

### Hook

Yeni filtre: `mdg_session_sales_closed_by_time` (`$closed`, `$session`, `$now_ts`). Kuralı eklenti dosyasına dokunmadan değiştirmek için. Örnek: belirli bir gösteride satışı seans bitene kadar sürdürmek.

```php
add_filter( 'mdg_session_sales_closed_by_time', function ( $closed, $session, $now_ts ) {
    return strtotime( $session->end_at . ' UTC' ) < $now_ts;
}, 10, 3 );
```

Filtre etkinlik sayfasını, sepeti ve `/bilet-al/` seans etiketlerini etkiler; liste sorgularındaki `start_at > şimdi` koşulunu etkilemez.

### Bağımlılıklar

`apply_filters()`. `MDG_Live_Sales` içindeki çağrılar `class_exists( 'MDG_Sessions' )` ile korunur; sınıf yüklenemezse bugünkü davranış sürer. Kural UTC zaman damgalarını karşılaştırır; site saat diliminden bağımsızdır.

## 5. Doğrulama

Yapılan:

- `php -l`: eklentinin bütün PHP dosyaları, PHP 8.4.
- `php tests/ticket-sales-cutoff/regression.php`: 73 kontrol geçti. Aynı test yamasız kodda başarısız oluyor.
- `php tests/ticket-datetime/regression.php`: mevcut testler geçmeye devam ediyor.
- GitHub CI (PHP 8.3.6): sonuç PR sayfasındadır.

Testler gerçek `MDG_Sessions`, `MDG_Live_Sales`, `MDG_Public_Event` ve `MDG_Public_Tickets` kaynaklarını sahte WordPress/WooCommerce fonksiyonlarıyla çalıştırır. Kapsam: karar kuralı (sabit saatle, 4 Ekim 15:25 dahil; tam başlangıç anı, bir dakika öncesi, süren seans, bozuk tarihler), sepete ekleme reddi, ödeme öncesi red, sayfa çıktısı (karışık, son seansı süren, tamamı bitmiş, tamamı açık, taslak önizleme, seanssız etkinlik), liste etiketleri ve liste sorguları.

Yapılmayan:

- Gerçek WordPress üzerinde çalıştırma.
- Gerçek sepet → ödeme denemesi.
- Sayfanın tarayıcıda görsel kontrolü. Bildirim mevcut `mdg-preview-note` sınıfını kullanır; CSS eklenmedi.

## 6. Canlıya alma öncesi smoke test planı

Bu değişiklik aktif ödeme akışındadır (AGENTS.md öncelik 1). Canlıya almadan önce ve hemen sonra:

1. Gelecek bir etkinlik sayfası (ör. Denizli 8 Ekim): bütün seanslar görünüyor, ilk seans seçili, "Satışta" yazıyor.
2. Aynı sayfada bilet seç → "Biletleri Sepete Ekle" → sepet → ödeme sayfası → PayTR formu açılıyor. Ödeme tamamlanmadan bırakılabilir; oluşan sipariş not edilir.
3. Gösteri günü olan bir etkinlikte, başlamış seans seçicide yok; başlamamış seanslar seçilebiliyor.
4. Bütün seansları başlamış veya bitmiş bir etkinlik (ör. Sincan 3 Ekim): "Bu gösterinin bilet satışı sona erdi" bildirimi var; seçici ve sepet düğmesi yok; "Güncel Gösteriler" bağlantıları `/bilet-al/` sayfasına gidiyor.
5. Başlamış seansın ürün sayfası (`/urun/...`) üzerinden sepete ekleme denenirse ödeme adımında "bilet satışı sona erdi" mesajı çıkıyor ve sipariş ödeme sayfasına geçmiyor.
6. `[mdg_sehirler]` ve `[mdg_bilet_al]` kısa kodlarıyla üretilen şehir ve bilet listeleri kullanılıyorsa: başlamış seans listelenmiyor, gelecek etkinlikler eksiksiz.
7. Yönetici taslak önizlemesi (`?mdg_event_preview=`) eskisi gibi açılıyor.
8. PHP hata günlüğünde yeni uyarı yok; MMC sistem sağlığı 16/16.
9. Deploy'dan sonraki ilk gerçek ödenmiş sipariş: bilet üretildi, seans saati biletin üzerinde doğru.

## 7. Canlıya alma

Beş dosya değişir. Canlı dosyaların değişiklik öncesi SHA256 değerleri (4 Ekim envanteri):

| Dosya | SHA256 (önce) |
|---|---|
| `includes/class-mdg-sessions.php` | `67c1d25366b9f86fff709514a0576060ce265154bf730534a1e374710a6c00e2` |
| `includes/class-mdg-public-event.php` | `6c884499ffa9a5ca6fa22254c494af38e01df9dea638a1236ccf35bd75456cc0` |
| `includes/class-mdg-live-sales.php` | `54d376a1b76c9106557adf1061821f71dd551eba7f582cf63645b44b332c0eb4` |
| `includes/class-mdg-public-cities.php` | `9a1459fbd2c039b3f56981c5550c11788df1ea5364f679a114c468552c8e59cc` |
| `includes/class-mdg-public-tickets.php` | `9c7794e0ffe0e1bb1b3a1fd5f7ed98d91d9d641992b65fecb3578fe9775fc767` |

Öneri: 3 Ekim'de kullanılan hash kontrollü atomik dosya değişimi; WordPress dosya düzenleyicisi kullanılmamalı (3 Ekim'de bootstrap dosyasını boşalttı). Canlı hash yukarıdakilerle eşleşmiyorsa durulmalı ve fark incelenmeli.

Sıra: **önce `class-mdg-sessions.php`**, sonra diğer dördü. `class-mdg-public-event.php` ve `class-mdg-public-tickets.php` yeni metodu doğrudan çağırır; ters sırada kısa bir süre metot bulunamaz ve sayfa hata verir.

Birleştirildikten sonra, canlıya alınana kadar bu beş dosya envanterde "GitHub ileride" durumundadır.

## 8. Geri alma

Beş dosyayı önceki halleriyle değiştirmek (yukarıdaki hash'ler). Veri yazılmadığı için veri geri alması gerekmez. Acil durumda yalnızca etkinlik sayfası ve sepetteki kuralı etkisizleştirmek için:

```php
add_filter( 'mdg_session_sales_closed_by_time', '__return_false', 99 );
```

Bu filtre liste sorgularını geri almaz; onlar için dosyaların geri yüklenmesi gerekir.

## 9. Kalıcı çözüm gelene kadar

- Biten her gösterinin ürünleri V4 satış kapatma ile elle kapatılmalı. 4 Ekim 15:27 itibarıyla Sincan 3 Ekim açıktı.
- Geçici köprü: `docs/code-snippets/staged-2026-10-04/ms-gecmis-seans-satis-kilidi-v1.php.txt` (aynı kural; eklenti dosyasına dokunmaz).
- 3 Ekim Sincan ve 4 Ekim Yenimahalle için seans başladıktan sonra oluşmuş ödenmiş sipariş olup olmadığı kontrol edilmeli: `ms-gecmis-seans-denetimi-v1.php.txt`. Bu kontrol yapılmadı.

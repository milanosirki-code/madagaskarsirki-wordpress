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
- Listeler ayrı kod yolundadır ve zaten `end_at >= şimdi` uygular (`MDG_Public_Tickets`, `MDG_Public_Cities`, snippet #30). Snippet #30 ayrıca Türkiye yerel gününe göre geçmiş başlangıç günlerini eler (Issue #87).

Bitmiş bir etkinliğin satışı bugün yalnızca elle kapanır: V4 satış kapatma veya etkinlik durumunun değiştirilmesi.

## 3. Değişiklik

Kural tek yerde: `MDG_Sessions::sales_closed_by_time( $session, $now_ts = null )`.

Bir seans şu iki durumda saat nedeniyle kapalıdır; ikisi de listelerin bugün uyguladığı kurallardır:

1. Seans bitti: `end_at < şimdi`.
2. Seansın yerel başlangıç günü bugünden önce (hatalı veya eski `end_at` değerlerine karşı).

Okunamayan, boş veya sıfır tarih satışı kapatmaz; bu durumda bugünkü davranış aynen sürer.

| Dosya | Değişiklik |
|---|---|
| `includes/class-mdg-sessions.php` | Yeni `sales_closed_by_time()` ve özel `utc_timestamp()`. Mevcut fonksiyonlara dokunulmadı. |
| `includes/class-mdg-public-event.php` | Canlı sayfada bitmiş seans seçicide gösterilmez; ilk açık seans seçili gelir. Bütün seanslar bittiyse seçici, bilet satırları ve sepet düğmesi yerine "Bu gösterinin bilet satışı sona erdi" bildirimi ve `/bilet-al/` bağlantısı çıkar; sepet yapılandırması kapalı gelir, sepet nonce'u üretilmez, yapılandırılmış veriden "stokta" teklifi çıkar. Taslak önizlemede (yönetici) saat kontrolü yapılmaz. |
| `includes/class-mdg-live-sales.php` | `add_to_cart()` ve `reserve_or_throw()` bitmiş seansı Türkçe bir mesajla reddeder. İkincisi doğrudan ürün sayfasından (`/urun/...`) sepete eklenen biletleri de ödeme öncesinde yakalar. |
| `tests/ticket-sales-cutoff/regression.php` | 54 izole kontrol. |
| `.github/workflows/ticket-datetime-stage.yml` | Yeni testin CI'da çalıştırılması. |

Değişmeyenler: veritabanı şeması, etkinlik ve seans durumları, WooCommerce ürünleri, Tickera, PayTR, V4 satış kapatma, kapasite hesabı, JS ve CSS dosyaları, eklenti sürüm numarası. Hiçbir veri yazılmaz; kural her istekte hesaplanır.

### Hook

Yeni filtre: `mdg_session_sales_closed_by_time` (`$closed`, `$session`, `$now_ts`). Kuralı eklenti dosyasına dokunmadan sıkılaştırmak veya gevşetmek için. Örnek: satışı seans başlayınca kapatmak.

```php
add_filter( 'mdg_session_sales_closed_by_time', function ( $closed, $session, $now_ts ) {
    return $closed || strtotime( $session->start_at . ' UTC' ) <= $now_ts;
}, 10, 3 );
```

### Bağımlılıklar

`wp_timezone()` (site saat dilimi `Europe/Istanbul` olmalı), `apply_filters()`. `MDG_Live_Sales` içindeki çağrılar `class_exists( 'MDG_Sessions' )` ile korunur; sınıf yüklenemezse bugünkü davranış sürer.

## 4. Açık karar

Satış ne zaman kapansın?

- **Seans bitince** (bu taslaktaki varsayılan; listelerle aynı kural). Kapıda telefondan bilet alan geç gelenler etkilenmez.
- **Seans başlayınca.** Yukarıdaki filtreyle ya da kuralın kendisinde tek satırla yapılır.

Karar işletme sahibindedir. Karar değişirse listelerin de aynı kurala çekilmesi gerekir; yoksa liste ile etkinlik sayfası yine farklı gösterir.

## 5. Doğrulama

Yapılan:

- `php -l`: eklentinin bütün PHP dosyaları, PHP 8.4.
- `php tests/ticket-sales-cutoff/regression.php`: 54 kontrol geçti. Aynı test yamasız kodda başarısız oluyor.
- `php tests/ticket-datetime/regression.php`: mevcut testler geçmeye devam ediyor.

Testler gerçek `MDG_Sessions`, `MDG_Live_Sales` ve `MDG_Public_Event` kaynaklarını sahte WordPress/WooCommerce fonksiyonlarıyla çalıştırır. Kapsam: karar kuralı (sabit saatle, 4 Ekim 15:25 dahil), sepete ekleme reddi, ödeme öncesi red, sayfa çıktısı (karışık, tamamı bitmiş, tamamı açık, taslak önizleme, seanssız etkinlik).

Yapılmayan:

- Gerçek WordPress üzerinde çalıştırma.
- Gerçek sepet → ödeme denemesi.
- Sayfanın tarayıcıda görsel kontrolü. Bildirim mevcut `mdg-preview-note` sınıfını kullanır; CSS eklenmedi.

## 6. Canlıya alma öncesi smoke test planı

Bu değişiklik aktif ödeme akışındadır (AGENTS.md öncelik 1). Canlıya almadan önce ve hemen sonra:

1. Gelecek bir etkinlik sayfası (ör. Denizli 8 Ekim): bütün seanslar görünüyor, ilk seans seçili, "Satışta" yazıyor.
2. Aynı sayfada bilet seç → "Biletleri Sepete Ekle" → sepet → ödeme sayfası → PayTR formu açılıyor. Ödeme tamamlanmadan bırakılabilir; oluşan sipariş not edilir.
3. Gösteri günü olan bir etkinlikte, bitmiş seans seçicide yok; kalan seanslar seçilebiliyor.
4. Bütün seansları bitmiş bir etkinlik (ör. Sincan 3 Ekim): "Bu gösterinin bilet satışı sona erdi" bildirimi var; seçici ve sepet düğmesi yok; "Güncel Gösteriler" bağlantıları `/bilet-al/` sayfasına gidiyor.
5. Bitmiş seansın ürün sayfası (`/urun/...`) üzerinden sepete ekleme denenirse ödeme adımında "bilet satışı sona erdi" mesajı çıkıyor ve sipariş ödeme sayfasına geçmiyor.
6. Yönetici taslak önizlemesi (`?mdg_event_preview=`) eskisi gibi açılıyor.
7. PHP hata günlüğünde yeni uyarı yok; MMC sistem sağlığı 16/16.
8. Deploy'dan sonraki ilk gerçek ödenmiş sipariş: bilet üretildi, seans saati biletin üzerinde doğru.

## 7. Canlıya alma

Yalnızca üç dosya değişir. Canlı dosyaların değişiklik öncesi SHA256 değerleri (4 Ekim envanteri):

| Dosya | SHA256 (önce) |
|---|---|
| `includes/class-mdg-sessions.php` | `67c1d25366b9f86fff709514a0576060ce265154bf730534a1e374710a6c00e2` |
| `includes/class-mdg-public-event.php` | `6c884499ffa9a5ca6fa22254c494af38e01df9dea638a1236ccf35bd75456cc0` |
| `includes/class-mdg-live-sales.php` | `54d376a1b76c9106557adf1061821f71dd551eba7f582cf63645b44b332c0eb4` |

Öneri: 3 Ekim'de kullanılan hash kontrollü atomik dosya değişimi; WordPress dosya düzenleyicisi kullanılmamalı (3 Ekim'de bootstrap dosyasını boşalttı). Canlı hash yukarıdakilerle eşleşmiyorsa durulmalı ve fark incelenmeli. Sıra: önce `class-mdg-sessions.php`, sonra diğer ikisi; ters sırada kısa bir süre `sales_closed_by_time()` bulunamaz ve etkinlik sayfası hata verir.

Birleştirildikten sonra, canlıya alınana kadar bu üç dosya envanterde "GitHub ileride" durumundadır.

## 8. Geri alma

Üç dosyayı önceki halleriyle değiştirmek (yukarıdaki hash'ler). Veri yazılmadığı için veri geri alması gerekmez. Acil durumda yalnızca kuralı etkisizleştirmek için:

```php
add_filter( 'mdg_session_sales_closed_by_time', '__return_false', 99 );
```

## 9. Kalıcı çözüm gelene kadar

- Biten her gösterinin ürünleri V4 satış kapatma ile elle kapatılmalı. 4 Ekim 15:27 itibarıyla Sincan 3 Ekim açık.
- 3 Ekim Sincan ve 4 Ekim Yenimahalle için seans bitişinden sonra oluşmuş ödenmiş sipariş olup olmadığı kontrol edilmeli. Bu kontrol yapılmadı.

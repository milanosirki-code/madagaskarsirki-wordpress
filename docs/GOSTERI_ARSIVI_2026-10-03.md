# Gösteri arşivi sayfası — 2026-10-03

## Amaç

Bilet Al altında, gerçekleşmiş gösterileri şehir/tarih/salon olarak
listeleyen bir arşiv sayfası.

## Kapsam (kullanıcı onayı)

- Konum: Bilet Al'ın alt sayfası (`/bilet-al/arsiv/`), üst menüde ayrı
  görünmez.
- Liste: yalnızca gerçekleşmiş (tamamlanmış) gösteriler. İptal edilip
  hiç oynanmamış etkinlikler listelenmez.
- İçerik: sadece şehir, tarih, salon — fiyat veya galeri yok.

## Yeni dosya

`docs/code-snippets/ms-gosteri-arsivi-v1.php.txt`

- `ms_gosteri_arsivi_past_events()`: MDG `events` + `sessions`
  tablolarını okur, `s.end_at < now` olan (yani bitmiş) oturumları
  şehir bazında gruplar, en son oynanan tarihe göre azalan sırada
  döner.
- İptal edilip hiç oynanmamış etkinlikler (`e.id <> 6` Eskişehir,
  `e.id <> 15` Kırıkkale) snippet #30 ile aynı desende hariç
  tutulur — bkz. `docs/KIRIKKALE_HOMEPAGE_EXCLUSION_2026-10-03.md`.
- `ms_gosteri_arsivi_render_page()`: `[ms_gosteri_arsivi]`
  shortcode'unu kayıtlı eder, kendi scoped CSS'i (`.msa-page`) ile
  sitenin mevcut renk paletini (`--ms-red`, `--ms-black`,
  `--ms-cream`) yeniden kullanır.

## Bağımlılıklar

- `MDG_DB` sınıfı ve `events`/`sessions` tabloları (snippet #30 ile
  aynı kaynak).
- `ms_city_v2_date_label()` fonksiyonu varsa (snippet #30 yüklüyse)
  Türkçe tarih biçimi için yeniden kullanılır; yoksa ham `Y-m-d`
  döner — snippet #30 aktif olmadan da sayfa kırılmaz.

## Canlıya alma

1. WP Admin → Code Snippets → yeni snippet oluştur, kaynağı
   `docs/code-snippets/ms-gosteri-arsivi-v1.php.txt` ile birebir aynı
   yap, aktif et (global, normal öncelik).
2. Yeni sayfa oluştur: üst (parent) sayfa **Bilet Al** (id 311),
   slug `arsiv`, içerik `[ms_gosteri_arsivi]` (shortcode bloğu).
   Taslak olarak oluşturulup önizlendikten sonra yayınlanmalı.
3. İsteğe bağlı: Bilet Al sayfasına "Gösteri arşivini gör" linki
   eklenebilir (ayrı, küçük bir değişiklik).

## Rollback

Snippet'i pasifleştirmek sayfayı boş/kırık bırakır (shortcode
render edilmez); sayfayı da taslağa almak veya silmek yeterli.
Başka hiçbir satış, ödeme veya mevcut sayfa akışını etkilemez —
yalnızca okuma yapan, yeni ve bağımsız bir shortcode'tur.

## Test

`php -l` ile sözdizimi doğrulandı (başa geçici `<?php` eklenerek).
Canlı MDG tablolarına karşı çalıştırılmadı; veritabanı yoksa/boşsa
fonksiyon güvenli şekilde boş dizi döner ve sayfa "Henüz arşivlenmiş
gösteri yok." mesajını gösterir.


## 7 Ekim 2026 temiz yeniden taşıma

Eski PR #109 güncel `main` dalından 164 commit geride olduğu için doğrudan birleştirilmemelidir. Kaynak güncel `main` üzerine temiz branch ile yeniden taşındı.

Güncel sorgu güvenliği:
- etkinlik yalnızca **bütün seanslarının** `end_at` değeri geçmişteyse arşive girer (`HAVING MAX(s.end_at) < now`);
- `cancelled`, `postponed` ve `draft` durumları genel olarak hariç tutulur;
- tarihsel olarak hiç oynanmamış ve eski durum verisi güvenilmez kalabilen Eskişehir MDG #6 ve Kırıkkale MDG #15 ayrıca açıkça hariç tutulur;
- sorgu yalnız okur; sipariş, bilet, kapasite, etkinlik veya seans verisi yazmaz.

Bu değişiklik **production deploy değildir**. `/bilet-al/arsiv/` sayfasının oluşturulması ve snippet'in canlıya alınması işletme sahibi onay kapısında kalır.

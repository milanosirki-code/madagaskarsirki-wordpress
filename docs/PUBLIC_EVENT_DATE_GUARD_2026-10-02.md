# Public Event Date Guard — 2 Ekim 2026

## Gözlem

Public şehir ve bilet listelerinde geçmiş legacy event'lerin görünme riski doğrulandı.

İlgili paylaşılan kaynak:
- `MS Şehirler Dinamik V2`
- `ms_city_v2_live_cities()`
- `ms_city_v2_event_sessions()`

`ms_city_v2_event_sessions()` mevcut kodu:
- MDG sessions tablosunu okur,
- `end_at >= current_time('mysql', true)` uygular,
- UTC → WordPress timezone dönüşümü yapar,
- fakat local `$date` için `$date >= today` koruması yapmaz.

`MDG_Public_Cities::active_cities()` de `s.end_at >= now_utc` üzerinden seçer.

Bu nedenle legacy `end_at` yanlış/stale ise eski başlangıç tarihli bir session public listede kalabilir.

## Live edit öncesi zorunlu teşhis

WPVibe açıldığında önce:
1. Snippet #30 exact source + active state capture.
2. Bartın/Çubuk/Bolu MDG session `start_at/end_at` sorgusu.
3. Kırıkkale same-day session değerleri karşılaştırması.
4. Gerçek veri kusuru kanıtlandıktan sonra minimum patch.

## Minimum patch

Local date elde edildikten sonra:

```php
$today = function_exists( 'wp_date' )
    ? wp_date( 'Y-m-d' )
    : date( 'Y-m-d' );

if ( $date < $today ) {
    continue;
}
```

Mevcut `end_at >= now_utc` kontrolü korunur.

## Kabul kriterleri

- 25 Eylül Bartın listede yok
- 26 Eylül Çubuk listede yok
- 27 Eylül Bolu listede yok
- 2 Ekim Kırıkkale same-day mantığı bozulmaz
- 3 Ekim Sincan görünür
- 4 Ekim Yenimahalle görünür
- Denizli, Mamak, Eskişehir, İzmir görünür
- homepage ilk 3 current/future
- /sehirler/ current/future
- /bilet-al/ current/future

## Rollback

Patch öncesi Snippet #30 source capture GitHub'a yazılacak. Sorunda yalnız #30 önceki kaynağa dönecek; MDG event status veya satış nesnelerine dokunulmayacak.

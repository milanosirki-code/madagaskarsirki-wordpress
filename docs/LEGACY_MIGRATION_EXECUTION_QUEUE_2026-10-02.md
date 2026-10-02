# Legacy MDG → MMC Execution Queue — 2 Ekim 2026

## Başlangıç koşulları

Canlı write migration'a geçmeden önce:

1. Snippet #101 durumu okunacak ve aktifse pasifleştirilecek.
2. Sistem sağlığı çalıştırılacak.
3. Sincan Program #8 bridge/dashboard/sales read-only doğrulanacak.
4. Public liste tarih sorunu (Issue #87) ayrı tutulacak; migration ile karıştırılmayacak.
5. Her legacy event tek transaction içinde işlenecek.
6. Bir event başarısız olursa sonraki event'e geçilmeyecek.

## Sıra

| Sıra | MDG Event | Yer | Tarih | Salon | Seanslar | Public fiyat başlangıcı | Durum |
| ---: | ---: | --- | --- | --- | --- | ---: | --- |
| 1 | 7 | Ankara / Yenimahalle | 2026-10-04 | Yenimahalle Spor Kompleksi Salonu | 12:00, 14:00, 16:00 | 250 TL | bekliyor |
| 2 | 12 | Denizli / Pamukkale | 2026-10-08 | Denizli Büyükşehir Belediyesi Kongre ve Kültür Merkezi Özay Gönlüm Salonu | 17:30, 19:30 | 250 TL | bekliyor |
| 3 | 9 | Ankara / Mamak | 2026-10-10 | Prof Dr. Necmettin Erbakan Kongre Merkezi | 12:00, 14:00, 16:00, 18:00 | 250 TL | bekliyor |
| 4 | 13 | Eskişehir / Odunpazarı | 2026-10-11 | Porsuk Kapalı Spor Salonu | 12:00, 14:00, 16:00 | 250 TL | bekliyor |
| 5 | 10 | İzmir / Konak | 2026-11-08 | Halkapınar Spor Salonu | 12:00, 14:00, 16:00 | 300 TL | bekliyor |

Not: Denizli public source metninde "Özay Gönlüm Sakonu" yazım hatası görülmüştür. Migration salon kimliğini MDG venue ID üzerinden kullanmalı; metin eşleşmesine güvenilmemelidir.

## Her event için preflight

- preview: `safe_for_later_write=true`
- suggested_action: `create_program_then_bridge`
- mevcut bridge yok
- aynı il/ilçe/tarih MMC program çakışması yok
- tarih bugün veya gelecek
- her seansın WooCommerce product ID'si mevcut
- her seansın Tickera event ID'si mevcut
- aktif ticket-code seti seanslar arasında tutarlı
- fiyatlar seanslar arasında tutarlı
- salon MDG master kaydı mevcut

## Her event için commit öncesi

- mapping coverage complete
- bridge linked=true
- bridge stale=false
- province_match=true
- district_match=true
- date_match=true
- venue_match=true
- session_time_match=true
- identity_expected=identity_matched
- satış varsa reconciliation ok=true
- missing_in_mmc=[]
- extra_in_mmc=[]
- revenue_diff=0

## Her event için commit sonrası

- program status
- MMC ledger summary
- bridge status
- sales reconciliation
- dashboard görünümü
- system health
- public event page
- /sehirler/
- /bilet-al/
- ana sayfa

## Public liste tarih guard

Issue #87 migration işinden bağımsızdır.

Merkezi `MS Şehirler Dinamik V2` kaynak kodunda `ms_city_v2_event_sessions()` yalnız `end_at >= now_utc` ile güveniyor. Legacy `end_at` yanlış kalırsa eski tarihli kart sızabilir.

Planlanan minimum guard:

```php
$today = function_exists( 'wp_date' )
    ? wp_date( 'Y-m-d' )
    : date( 'Y-m-d' );

if ( $date < $today ) {
    continue;
}
```

Bu guard yalnız public liste sonucunu etkiler; sipariş, ürün, Tickera, Kommo ve event status değiştirmez.

## İzleme

- Issue #81 — kalan legacy migration
- Issue #87 — geçmiş event public liste guard
- Issue #82 — Kommo source limit
- Issue #77 — transition/hotfix plugin source audit

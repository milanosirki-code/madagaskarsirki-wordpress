# Legacy MDG → MMC Migration Preview — 1 Ekim 2026

## Canlıda doğrulanan mimari boşluk

Kamuya açık `/sehirler/` sayfasında 12 gelecekteki satış etkinliği bulunuyor. MMC program tablosunda ise 7 program bulunuyor.

MMC zinciri dışında kalan gelecekteki legacy MDG etkinlikleri:

| MDG Event | Yer | Tarih | Durum |
| ---: | --- | --- | --- |
| 7 | Ankara / Yenimahalle | 2026-10-04 | onsale |
| 9 | Ankara / Mamak | 2026-10-10 | onsale |
| 10 | İzmir / Konak | 2026-11-08 | onsale |
| 11 | Ankara / Sincan | 2026-10-03 | onsale |
| 12 | Denizli / Pamukkale | 2026-10-08 | onsale |
| 13 | Eskişehir / Odunpazarı | 2026-10-11 | onsale |

Geçmiş Bartın (#3), Çubuk (#4) ve Bolu (#5) MDG tablosunda hâlâ `onsale` olsa da bu migration kapsamına alınmamalıdır.

Ayrıca MMC Program #1, Aydın / Efeler için 2026-10-16 tarihli eski/ayrı bir kayıt olarak bulunuyor ve aktif MDG bridge kaydı yok. Kamuya açık satıştaki Efeler event'i 2026-10-17 ve MMC Program #5 → MDG #21 olarak doğru köprülenmiş durumda. Program #1 ayrıca incelenmelidir; otomatik silinmemelidir.

## Staging modülü

Madagaskar AI Abilities v0.6.0 staging paketine:
`legacy-mdg-mmc-preview`

modülü eklenmiştir.

Ability:
`madagaskar/legacy-mdg-mmc-migration-preview`

Varsayılan filtre:
- future_only=true
- unbridged_only=true

Modül salt-okunurdur. Program, salon, event, seans veya bridge oluşturmaz.

Her MDG event için:
- yerel tarih/seanslar,
- WooCommerce product ID,
- Tickera event ID,
- aktif ticket type / variation ID,
- MDG salon ana kaydı,
- aynı il/ilçe/tarihte mevcut MMC programı,
- mevcut bridge,
- önerilen aksiyon,
- güvenlik uyarıları

raporlanır.

Önerilen aksiyonlar:
- `already_bridged`
- `bridge_existing_program`
- `create_program_then_bridge`
- `conflict_or_incomplete`

## Canlıya alma sırası

1. v0.6.0 paketini canlıya yükle; yeni module gate kapalı kalsın.
2. Yalnız `legacy-mdg-mmc-preview` gate'ini aç.
3. Explicit ID listesi `[7,9,10,11,12,13]` ile read-only preview çalıştır.
4. Her satırda tarih, salon, seans, ürün ve Tickera kimliklerini canlı satış sayfasıyla karşılaştır.
5. Ancak preview temizse ayrı bir kontrollü write/migration yeteneği tasarla.
6. Migration write aşamasında eventleri tek tek işle, her biri sonrası bridge + sales reconciliation + system health smoke test yap.
7. Geçmiş MDG eventlerine ve MMC Program #1'e otomatik müdahale etme.

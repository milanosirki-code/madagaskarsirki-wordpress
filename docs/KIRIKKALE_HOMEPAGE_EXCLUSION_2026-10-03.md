# Kırıkkale homepage/şehirler listing exclusion — 2026-10-03

## Amaç

Kırıkkale (2 Ekim 2026, MDG Event **15**, MMC Program **2**) 2 Ekim'de iptal
edildi ve tam iade yapıldı (bkz. `docs/KIRIKKALE_CANCELLATION_REFUND_2026-10-02.md`,
PR #92): 11/11 WooCommerce siparişi iade edildi (₺13.750), 46/46 bilet
geçersiz kılındı, ilgili WooCommerce ürün/varyasyonları `purchasable=false`.

Buna rağmen ana sayfa ve `/sehirler/` listesi etkinliği "SATIŞTA" göstermeye
devam etti, çünkü bu liste MMC program durumuna değil yalnızca MDG
`events.status` alanına ve oturum bitiş saatine bakıyor. Ayrıca aynı gün
içinde MMC Program 2 durumu bir entegrasyon doğrulama adımı tarafından
`cancelled` → `sales_open` olarak kendiliğinden geri çevrildi (MMC log #424,
13:53 TSİ), bu nedenle legacy MDG etiketi hiç düzeltilmemiş olabilir ve bu
geri dönüş tekrar yaşanabilir (bkz. `docs/CLAUDE_SITE_REVIEW_2026-10-03.md`,
madde 4.1).

Bu patch, kök nedeni (entegrasyon doğrulamasının iptal edilmiş bir programı
yeniden açabilmesi) düzeltmez — sadece halka açık listeleme tarafında aynı
görünürlük sorununun tekrarını engeller.

## Değişiklik

`docs/live-captures/2026-10-02/snippet-30-ms-dinamik-sehirler-ve-biletler-v1-live-20261002.php.txt`
içindeki `ms_city_v2_live_cities()` sorgusuna, 3 Ekim Eskişehir
hariç tutmasıyla (`e.id <> 6`) aynı desende yeni bir satır eklendi:

```sql
AND e.id <> 15 /* 2 Ekim 2026 Kırıkkale: iptal + tam iade (PR #92) */
```

Bu, Kırıkkale'yi hem ana sayfadaki "Yaklaşan gösteriler" kartlarından hem de
`/sehirler/` ve `/bilet-al/` sayfalarındaki listeden kalıcı olarak çıkarır —
MDG `events.status` alanı ne olursa olsun.

## Bağımlılıklar

- `ms_city_v2_live_cities()` fonksiyonu hem ana sayfa snippet'i (#35,
  `ms_home_v2_render_page`) hem de şehirler sayfası tarafından çağrılıyor;
  tek kaynaklı değişiklik her ikisini de kapsar.
- Etkinlik detay sayfasındaki iptal duyurusu (Snippet #104) bu değişiklikten
  etkilenmez, ayrı bir mekanizma.

## Canlıya alma

Bu dosya, canlıda aktif olan Code Snippet **#30** ("MS Dinamik Şehirler ve
Biletler V1") için sadece repodaki kayıttır. Canlıya almak için WP Admin →
Code Snippets → #30 içine aynı tek satır elle eklenmeli (WPVibe/Code
Snippets üzerinden; bu oturumdan doğrudan canlı yazma erişimi yok).

## Rollback

Eklenen tek satırı silmek yeterli; başka hiçbir sorguyu, program durumunu
veya satış/ödeme akışını etkilemez.

## Kalıcı (henüz yapılmamış) düzeltme

`docs/CLAUDE_SITE_REVIEW_2026-10-03.md` madde 4.1'de önerildiği gibi,
entegrasyon doğrulama adımının `cancelled` durumundaki bir programı
`sales_open`'a geri çevirmesini engelleyen asıl düzeltme ayrı bir iş
olarak kalıyor; bu dosya yalnızca görünürlük belirtisini kapatır.

# Hazır snippet kaynakları — 4 Ekim 2026 (canlıda DEĞİL)

Bu klasördeki iki dosya Issue #116 (geçmiş seanslara satış) için Claude tarafından yazıldı. **İkisi de canlıda yok.** Code Snippets gövdesi biçimindedir: başlarında `<?php` etiketi yoktur, `.php.txt` uzantısı sayesinde hiçbir şey kendiliğinden yüklenmez.

`docs/code-snippets/production/` yalnızca canlıdaki snippet'lerin kaydıdır; buradaki dosyalar canlıya alınırsa oraya ve `docs/STAGE4_SOURCE_INVENTORY_20261004.json` benzeri envantere ayrıca işlenmelidir.

Yapılacaklar listesi: `docs/TODO_2026-10-04.md`
Kalıcı çözüm: PR #117, `docs/SESSION_SALES_CUTOFF_2026-10-04.md` (o PR'ın içinde)

| Dosya | Tür | Yazar mı? | Ödeme akışına dokunur mu? |
|---|---|---|---|
| `ms-gecmis-seans-denetimi-v1.php.txt` | Salt okunur yönetici ekranı | Hayır | Hayır |
| `ms-gecmis-seans-satis-kilidi-v1.php.txt` | Geçici köprü | Hayır | **Evet** (satın alınabilirlik filtresi) |

## 1. `ms-gecmis-seans-denetimi-v1` — salt okunur denetim

- **Amaç:** (1) Bütün seansları bittiği halde hâlâ "satışta" olan etkinlikleri, (2) satıştaki etkinliklerin bitmiş seanslarını ve ürünlerinin o an satın alınabilir olup olmadığını, (3) seansı bittikten sonra açılmış siparişleri ve ödenip ödenmediklerini göstermek.
- **Ekran:** Araçlar > Geçmiş Seans Denetimi. Yetki `manage_woocommerce`. `?gun=14` ile geriye dönük gün sayısı (1–60, varsayılan 7).
- **Hook:** `admin_menu`. Ziyaretçi tarafında hiçbir şey çalışmaz.
- **Bağımlılık:** `wp_mdg_events`, `wp_mdg_sessions`, `wp_mdg_order_map`; WooCommerce (`wc_get_order`, `wc_get_product`); V4 kilit metası `_mdg_v371_sales_closed` (yalnızca okunur).
- **Güvenlik:** Üç `SELECT` sorgusu. Yazma yok. Müşteri adı, telefonu, e-postası, adresi okunmaz ve gösterilmez; yalnızca sipariş numarası, durum, adet, kalem tutarı ve saat.
- **Sınır:** Yalnızca `wp_mdg_order_map` içinde kaydı olan siparişleri görür. Geç sipariş kararı WooCommerce sipariş oluşturma saatine göre verilir; eşleme tablosundaki kayıt tarihi sonraki senkronlarda değişebildiği için kullanılmaz. Sayfa başına en çok 2.000 kalem okunur.
- **Kullanım:** Etkinleştir → ekranı aç → sonucu (müşteri verisi olmadan) Issue #116'ya yaz → devre dışı bırak.
- **Geri alma:** Snippet'i devre dışı bırakmak.

## 2. `ms-gecmis-seans-satis-kilidi-v1` — geçici köprü

- **Amaç:** PR #117 canlıya alınana kadar, seansı bitmiş bilet ürünlerinin satın alınmasını eklenti dosyalarına dokunmadan durdurmak. Elle satış kapatmayı beklemeden çalışır.
- **Nasıl:** `woocommerce_is_purchasable` ve `woocommerce_variation_is_purchasable` filtrelerinde (öncelik 98), ürünün `wp_mdg_sessions.wc_product_id` ile bağlı olduğu bütün seanslar bittiyse `false` döner. V4 satış kilidi (öncelik 99) aynı filtrelerle çalışmaya devam eder; bu snippet V4 metasına dokunmaz.
- **Kural:** Listelerle ve PR #117 ile aynı: `end_at < şimdi` ya da yerel başlangıç günü bugünden önce. PR #117 canlıdaysa doğrudan `MDG_Sessions::sales_closed_by_time()` kullanılır.
- **Hata davranışı:** Yalnızca `true` değerini `false` yapar; kapalı ürünü açmaz. Tarih okunamazsa, seans bulunamazsa, veritabanı hata verirse hiçbir şeyi kapatmaz.
- **Kapsam dışı:** Etkinlik sayfasındaki seans düğmelerini gizlemez. Bitmiş seans seçilip sepete eklenmek istenirse mevcut "… şu anda satın alınamıyor" mesajı çıkar. Yönetim ekranlarında (AJAX dışı) devreye girmez.
- **Maliyet:** Ürün başına istek içinde bir kez, indeksli tek `SELECT` (`KEY wc_product_id`).
- **Bağımlılık:** `wp_mdg_sessions`, WooCommerce.
- **Geri alma:** Snippet'i devre dışı bırakmak. Veri yazılmadığı için başka adım gerekmez.
- **PR #117 canlıya alınınca:** Bu snippet devre dışı bırakılmalı; aynı kuralın iki sahibi olmamalı.

### Etkinleştirmeden önce ve sonra (smoke test)

Bu snippet aktif ödeme akışındadır (AGENTS.md öncelik 1).

1. Önce denetim ekranında bölüm 2'yi kaydet: hangi bitmiş seans ürünleri "EVET" (satın alınabilir) görünüyor.
2. Etkinleştir. `code_error` boş olmalı.
3. Denetim ekranını yenile: aynı ürünler artık "hayır" görünmeli; V4 kilidi sütunu değişmemeli.
4. Gelecek bir etkinlikte (ör. Denizli 8 Ekim) bilet seç → sepete ekle → sepet → ödeme sayfası → PayTR formu açılıyor. Ödeme tamamlanmadan bırakılabilir.
5. Bitmiş bir seansta sepete ekleme denenince "şu anda satın alınamıyor" mesajı çıkıyor; sepete ürün girmiyor.
6. Sistem sağlığı 16/16; PHP hata günlüğünde yeni satır yok.
7. Etkinleştirmeden sonraki ilk gerçek ödenmiş siparişte bilet üretildi.

Adım 4 başarısız olursa snippet hemen devre dışı bırakılır.

## Testler

`php tests/past-session-snippets/regression.php` (47 kontrol) ve `MS_TEST_DELEGATE=1 php tests/past-session-snippets/regression.php` (PR #117 kuralına devretme, 3 kontrol). Testler snippet gövdelerini sahte WordPress/WooCommerce fonksiyonlarıyla çalıştırır; gerçek WordPress üzerinde çalıştırılmadı.

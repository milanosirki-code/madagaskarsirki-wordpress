# Hazır snippet kaynakları — 4 Ekim 2026 (canlıda DEĞİL)

Bu klasördeki üç dosya Issue #116 için Claude tarafından yazıldı. **Hiçbiri canlıda yok.** Code Snippets gövdesi biçimindedir: başlarında `<?php` etiketi yoktur, `.php.txt` uzantısı sayesinde hiçbir şey kendiliğinden yüklenmez.

**Kural (işletme sahibi kararı, 4 Ekim 2026, 18:46 TSİ): satış seans başladığında kapanır.** Üç dosya da bu kurala göredir. Önceki `-v1` dosyaları ("seans bitince") kaldırıldı; kullanılmamalı.

`docs/code-snippets/production/` yalnızca canlıdaki snippet'lerin kaydıdır; buradaki dosyalar canlıya alınırsa oraya ve envantere ayrıca işlenmelidir.

Yapılacaklar listesi: `docs/TODO_2026-10-04.md`
Kalıcı çözüm: PR #117, `docs/SESSION_SALES_CUTOFF_2026-10-04.md` (o PR'ın içinde)

| Dosya | Tür | Yazar mı? | Ödeme akışına dokunur mu? |
|---|---|---|---|
| `ms-seans-denetimi-v2.php.txt` | Salt okunur yönetici ekranı | Hayır | Hayır |
| `ms-seans-satis-kilidi-v2.php.txt` | Geçici köprü | Hayır | **Evet** (satın alınabilirlik filtresi) |
| `snippet-030-seans-baslayinca-v1.php.txt` | Canlı Snippet #30'un güncel hali | Hayır | Hayır (yalnızca listeler) |

## Kuralın tam hali

1. Başlangıç saati geldiyse veya geçtiyse (`start_at <= şimdi`) seansın satışı kapalıdır.
2. Başlangıç okunabiliyor ve gelecekteyse açıktır; `end_at` bozuk olsa bile.
3. Başlangıç okunamıyorsa bitişe bakılır: `end_at < şimdi` ise kapalıdır.
4. İkisi de okunamıyorsa hiçbir şey kapanmaz.

Listelerde karşılığı: bir seans yalnızca `start_at > şimdi` ise listelenir.

## 1. `ms-seans-denetimi-v2` — salt okunur denetim

- **Amaç:** (1) Bütün seansları başladığı halde hâlâ "satışta" olan etkinlikleri, (2) satıştaki etkinliklerin başlamış seanslarını ve ürünlerinin o an satın alınabilir olup olmadığını, (3) seansı başladıktan sonra açılmış siparişleri ve ödenip ödenmediklerini göstermek.
- **Ekran:** Araçlar > Seans Denetimi. Yetki `manage_woocommerce`. `?gun=14` ile geriye dönük gün sayısı (1–60, varsayılan 7).
- **Hook:** `admin_menu`. Ziyaretçi tarafında hiçbir şey çalışmaz.
- **Bağımlılık:** `wp_mdg_events`, `wp_mdg_sessions`, `wp_mdg_order_map`; WooCommerce (`wc_get_order`, `wc_get_product`); V4 kilit metası `_mdg_v371_sales_closed` (yalnızca okunur).
- **Güvenlik:** Üç `SELECT` sorgusu. Yazma yok. Müşteri adı, telefonu, e-postası, adresi okunmaz ve gösterilmez; yalnızca sipariş numarası, durum, adet, kalem tutarı ve saat.
- **Sınır:** Yalnızca `wp_mdg_order_map` içinde kaydı olan siparişleri görür. Geç sipariş kararı WooCommerce sipariş oluşturma saatine göre verilir. Seans başlamadan önce oluşturulup sonra ödenen sipariş geç sayılmaz. Sayfa başına en çok 2.000 kalem okunur.
- **Kullanım:** Etkinleştir → ekranı aç → sonucu (müşteri verisi olmadan) Issue #116'ya yaz → devre dışı bırak.
- **Geri alma:** Snippet'i devre dışı bırakmak.

## 2. `ms-seans-satis-kilidi-v2` — geçici köprü

- **Amaç:** PR #117 canlıya alınana kadar, seansı başlamış bilet ürünlerinin satın alınmasını eklenti dosyalarına dokunmadan durdurmak. Elle satış kapatmayı beklemeden çalışır.
- **Nasıl:** `woocommerce_is_purchasable` ve `woocommerce_variation_is_purchasable` filtrelerinde (öncelik 98), ürünün `wp_mdg_sessions.wc_product_id` ile bağlı olduğu bütün seansların satışı kapandıysa `false` döner. V4 satış kilidi (öncelik 99) aynı filtrelerle çalışmaya devam eder; bu snippet V4 metasına dokunmaz.
- **Kural:** Yukarıdaki. PR #117 canlıdaysa doğrudan `MDG_Sessions::sales_closed_by_time()` kullanılır.
- **Hata davranışı:** Yalnızca `true` değerini `false` yapar; kapalı ürünü açmaz. Seans bulunamazsa, tarih okunamazsa, veritabanı hata verirse hiçbir şeyi kapatmaz.
- **Kapsam dışı:** Etkinlik sayfasındaki seans düğmelerini gizlemez. Başlamış seans seçilip sepete eklenmek istenirse mevcut "… şu anda satın alınamıyor" mesajı çıkar. Yönetim ekranlarında (AJAX dışı) devreye girmez.
- **Maliyet:** Ürün başına istek içinde bir kez, indeksli tek `SELECT` (`KEY wc_product_id`).
- **Bağımlılık:** `wp_mdg_sessions`, WooCommerce.
- **Geri alma:** Snippet'i devre dışı bırakmak. Veri yazılmadığı için başka adım gerekmez.
- **PR #117 canlıya alınınca:** Bu snippet devre dışı bırakılmalı; aynı kuralın iki sahibi olmamalı.

### Etkinleştirmeden önce ve sonra (smoke test)

Bu snippet aktif ödeme akışındadır (AGENTS.md öncelik 1).

1. Önce denetim ekranında bölüm 2'yi kaydet: hangi başlamış seans ürünleri "EVET" (satın alınabilir) görünüyor.
2. Etkinleştir. `code_error` boş olmalı.
3. Denetim ekranını yenile: aynı ürünler artık "hayır" görünmeli; V4 kilidi sütunu değişmemeli.
4. Gelecek bir etkinlikte (ör. Denizli 8 Ekim) bilet seç → sepete ekle → sepet → ödeme sayfası → PayTR formu açılıyor. Ödeme tamamlanmadan bırakılabilir.
5. Başlamış bir seansta sepete ekleme denenince "şu anda satın alınamıyor" mesajı çıkıyor; sepete ürün girmiyor.
6. Sistem sağlığı 16/16; PHP hata günlüğünde yeni satır yok.
7. Etkinleştirmeden sonraki ilk gerçek ödenmiş siparişte bilet üretildi.

Adım 4 başarısız olursa snippet hemen devre dışı bırakılır.

## 3. `snippet-030-seans-baslayinca-v1` — listelerin aynı kurala çekilmesi

- **Amaç:** Ana sayfa, `/sehirler/` ve `/bilet-al/` listelerinin, satışı kapanmış (başlamış) seansı göstermemesi. Bu listeleri canlıda Snippet #30 üretir; Snippet #35 (ana sayfa) aynı fonksiyonu kullandığı için ayrıca değişmez.
- **Değişiklik:** Canlı Snippet #30 gövdesinin (`docs/code-snippets/production/snippet-030.php.txt`, 4 Ekim kaydı) iki satırı ve başlıktaki bir not. Başka hiçbir satır değişmedi.

  | Fonksiyon | Önce | Sonra |
  |---|---|---|
  | `ms_city_v2_event_sessions()` | `AND end_at >= %s` | `AND start_at > %s` |
  | `ms_city_v2_live_cities()` | `AND s.end_at >= %s` | `AND s.start_at > %s` |

- **Korunanlar:** Eskişehir istisnası (`e.id <> 6`), Türkiye yerel gün koruması (Issue #87), bütün görünüm kodu.
- **Sonuç:** Seans başladığı anda listeden çıkar. Günün son seansı başladığında etkinlik kartı listelerden ve ana sayfadan kalkar; "BUGÜN" rozeti de onunla birlikte.
- **Hook / bağımlılık:** Değişmedi.
- **Uygulama:** Önce canlı Snippet #30 gövdesinin `production/snippet-030.php.txt` ile aynı olduğu doğrulanmalı (4 Ekim'den sonra değiştiyse bu dosya üzerine yazılmamalı; yalnızca iki satır elle değiştirilmeli). Sonra gövde bu dosyayla değiştirilir ve önbellek temizlenir.
- **Smoke:** `/`, `/sehirler/`, `/bilet-al/` açılıyor; gelecek etkinliklerin hepsi listede; o gün başlamış seans görünmüyor; PHP hatası yok.
- **Geri alma:** Gövdeyi `production/snippet-030.php.txt` ile değiştirmek.
- **Ödeme akışı:** Etkilenmez. Bu snippet yalnızca okur ve listeler.

Snippet #30 güncellenmeden satış kilidi veya PR #117 canlıya alınırsa zarar olmaz: liste seans sürerken etkinliği göstermeye devam eder, ama satın alma reddedilir. Sıranın tersi de zararsızdır.

## Testler

`php tests/past-session-snippets/regression.php` (61 kontrol) ve `MS_TEST_DELEGATE=1 php tests/past-session-snippets/regression.php` (PR #117 kuralına devretme, 3 kontrol). Testler snippet gövdelerini sahte WordPress/WooCommerce fonksiyonlarıyla çalıştırır; Snippet #30 için canlı kayıtla farkın yalnızca iki koşul ve başlık notu olduğunu da denetler. Gerçek WordPress üzerinde çalıştırılmadı.

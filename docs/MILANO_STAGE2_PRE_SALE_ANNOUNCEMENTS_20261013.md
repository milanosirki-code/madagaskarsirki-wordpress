# Milano Sirki — Aşama 2.2: Satışı açmadan şehir ve seans duyurusu

**Tarih:** 13 Ekim 2026. **Durum:** GitHub PR #235 draft / CI contracts. **Canlı Milano satış, PayTR, kapasite, bilet/QR açılmadı.**

## Neden ayrı takvim beslemesi?

Mevcut merkezî /sessions beslemesi yalnız MDG satışta olan (ONSALE) etkinlikleri ve WooCommerce/Tickera haritalaması ile anlık kapasiteyi verir. Böylece henüz satışa açılmamış ama onaylanmış bir Milano gösterisinin tarihi/salonu güvenli biçimde yayımlanamaz. Taslak /upcoming yolu ödeme, ürün ve stoktan tamamen ayrı bir **announcement_only** katalog sözleşmesidir.

## Canlıdan doğrulanan bulgular

- Milano /sehirler/ kamuya açık gerçek HTML kontrolünde eski yönetici örnek kartları görülmedi. REST API'nin yetkili `content.rendered` alanındaki dinamik önizleme ile genel ziyaretçi çıktısı farklıdır; sayfadan silme yapılmadı.
- Madagaskar `GET /dunya-milano/v1/sessions` => `503 dmsf_not_configured` (token eksik; fail-closed). `GET /dunya-milano/v1/upcoming` => `404 rest_no_route` (yeni kod **canlıda yok**).
- MMC salt-okunur SQL `SELECT brand, COUNT(*) ... GROUP BY brand` => **13 Madagaskar / 0 Milano**. Eski etkinlikler devredilmedi.

## GitHub taslağındaki yaklaşım

1. `dunya-milano-upcoming.php`: /upcoming REST endpoint. Merkezî sunucuda `DUNYA_MILANO_UPCOMING_FEED_ENABLED === true`, elle onaylanmış tam sayı `DUNYA_MILANO_ANNOUNCED_EVENT_IDS` allowlist ve uzun gizli feed tokenı **birlikte** zorunludur. HTTP parametreleri marka, kimlik veya izni değiştiremez.
2. MDG DRAFT veya ONSALE durumundaki gelecekteki seans, ancak **tam tek aktif MMC–MDG köprü** üzerinden `event_owner_brand = milano` olduğu doğrulanırsa aday olabilir. Başka marka, onaysız MDG ID, iptal, geçmiş seans veya eksik salon reddedilir.
3. Sadece şehir/ilçe/salon/tarih/saat/kimlik bilgileri ve `catalog_mode=announcement_only`, `read_only=true` verilir. WooCommerce fiyatı, bilet türü, stok, PayTR veya QR alanları döndürülmez. Cache-Control private/no-store, robots noindex.
4. Milano consumer v0.3.0 genel ziyaretçiye sadece `DUNYA_MILANO_PUBLIC_CATALOG_ENABLED === true` ve güvenli `/upcoming` cevabı doğrulandıktan sonra duyuru kartı gösterir; varsayılan kapalı. Public kartlarda stok miktarı, fiyat, ödeme, checkout veya satış CTA yoktur.
5. Geliştirme çatışmasında aynı işi yapan geçici ikinci `dunya-milano-announcements.php` dosyası kaldırıldı. Kanonik taslakta **yalnız `/upcoming`** akışı tutuldu.

## Bu çalışma sırasında giderilen test açığı

- `upcoming-contract.php` ilk CI koşusu, doğru merkezî SQL'i değil `queries[0]` ile önceki owner sorgusunu test ediyordu. Sorgu indeksine bağlı varsayım kaldırıldı; onaylı ID sınırlamasını içeren SQL içerikten tespit ediliyor.
- `dunya-milano-upcoming.php` içinde sonuç satırlarına ikinci UTF/UTC biçimi + gelecekte olma kontrolü eklendi. Yanlışlıkla geçmiş satır dönse dahi duyuruya girmez.
- `upcoming-feed-contract.php` ile ek 16 sentetik güvenlik testi ve ilgili GitHub Actions job'u yazıldı: eksik anahtar/izin, yanlış marka, başka event, geçersiz tablo/veri, geçmiş/bozuk yer/saat, zararlı HTML, stok/fiyat sızıntısı.
- Son değişiklikler GitHub Actions `Milano read-only session feed contract` SUCCESS. Diğer otomatik denetimler son commit kontrolüne bağlı izlenir. CI başarısı gerçek WP/PayTR/MDG staging testi değildir.

## Satışa geçiş kilitleri

- İlk Milano programının **gerçek il/ilçe, kesin salon, tarih, saat ve kapasitesi** doğrulanmadan MMC/MDG canlı kaydı yaratılmayacak. Mevcut Madagaskar satıştaki 13 programın markası değiştirilmez.
- MMC marka seçimini canlıda destekleyen servis/admin/AI Abilities sürüm yükseltmesi ve uyumluluk testleri tamamlanmalı.
- İlk Milano MDG taslak ve tam tek MMC–MDG köprüsü kurulduktan sonra yalnız onaylı event ID ile `/upcoming` server-side yapılandırılmalı; ayrı test geri alma/rollback.
- Çerez CMP, PayTR ikinci domain merchant callback/3DS, merkezi Woo order ve stok atomik hold, aile 2+2 dört QR, iade, eşzamanlı son koltuk, Store API/kampanya doğrudan URL marka güvenliği tamamlanmadan satış **açılmamalı**.

GitHub PR #235: https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/235
Drive çalışma defteri: https://docs.google.com/document/d/1GSV-_niF39NTkB45--sqodofxDVH25S7XeKxefzUa4Q/edit

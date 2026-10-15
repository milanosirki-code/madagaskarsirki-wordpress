# Dünya Organizasyon — Etkinlik Başına Tek Marka: Satış Koruma Taslağı

**Kapsam:** Bu dizindeki PHP modülü SADECE GitHub taslağıdır. Madagaskar'ın çalışan WordPress'ine yüklenmedi/etkinleştirilmedi. Milano sitesi satışa kapalı.

## Kesin iş kuralı
- Bir fiziksel etkinlik `milano` ya da `madagaskar` markalarından **yalnız birine** aittir.
- Milano etkinliği yalnız `milanosirki.com` sitesinden satılır. Madagaskar etkinliği yalnız `madagaskarsirki.com` sitesinden satılır.
- Dünya Organizasyon'un merkezi MDG/WooCommerce/Tickera/PayTR altyapısı ortak olabilir; iki sitenin katalogları/checkout erişimleri ayrıdır.

## Kanonik sahiplik kaydı (tasarım)
Güncel kaynak, doğrulanmış etkinlik → **tek etkin MMC programı** bağlantısıdır: `MMC_MDG_Bridge_Service::table()` içinde `mdg_event_id`, `is_active=1`, tek `program_id`; ardından `MMC_Program_Service::get_program()->brand` değeri normalleştirilir. Önceden önerilen `dunya_event_owner_brand_map_v1` seçenek haritası **kaldırıldı**: iki farklı marka kaynağının çelişmesine izin verilmez. Boş/çift aktif bağlantı, kayıp program, bilinmeyen marka güvenli varsayılan olarak engellenir. Modül hiçbir marka ataması yapmaz.

- **Hiçbir etkinlik bu sürümce otomatik sınıflandırılmaz.** Özellikle geçmiş satışta olan programların markası etiket/il/tarih adına bakılarak değiştirilemez.
- Boş/bilinmeyen/çelişkili sahiplik = satış izni yok. Bu kural mevcut Madagaskar ürünlerini de engelleyebilir. Bu sebeple **tüm geçerli etkinlikler elle gözden geçirilip eşlenmeden canlıya alma yasak**.
- Bir etkinlikte mevcut ödemeler/QR biletler varsa marka değişimi finans, dış satış kanalları ve hukuki belge denetimi olmadan yapılmamalı.
- Doğrulanmış sahiplik gelecekte kalıcı `mdg_events` alanına veya kontrollü 1:1 tabloya taşınabilir. Şimdilik bu seçenek, *taslak* kanonik adapter tasarımıdır.

## Mevcut kodun yaptığı korumalar (yalnız taslak)
- `dunya_milano_canonical_event_owner` filter'ına event ID ile sahiplik sağlar. Böylece Milano üretici API'si yalnız `milano` etkinliklerini seçebilir.
- MDG ticket variation -> MDG session -> MDG event eşleşmesini kontrol eder. Aynı Woo parent birden fazla event ile eşleşiyorsa işlemi reddeder.
- Madagaskar'daki klasik `woocommerce_add_to_cart_validation`, sepet kontrolleri, Store API cart ve her iki checkout order işleme yolunda başka markayı ve sahipsiz/çelişkili event'i engellemek için koruma ekler.
- Doğrudan `/etkinlik/{slug}` bağlantısını `template_redirect` öncesinde kapatır.
- İlgisiz WooCommerce ürünleri için mevcut işleyişi korur.
- Ticari kayıt, kapasite, sipariş, PDF/QR, iade veya ödeme üzerinde yazma yapmaz.

## Önemli kalan açıklar / dağıtım engelleri
1. Madagaskar dinamik Şehirler listesi `MDG_Public_Cities::active_cities()`, mevcut halinde marka filtresi kullanmıyor. Ayrı bir kod değişikliği/izolasyon testi gerekir.
2. Madagaskar'ın diğer eski etkinlik sayfaları, kampanya/snippet, manuel sipariş, Tickera / Woo ürün katalogları ayrı ayrı taranıp korunmalı. Kullanıcının **tek site** kuralı yalnız bu dosya ile eksiksiz sağlanmış sayılmaz.
3. `milanosirki.com` üzerinde güvenli Woo ödeme tasarımı; MDG alan adına müşteriyi göndermeden PayTR ve 3DS dönüşü gerekir. Henüz yok.
4. Yetkili marka atama paneli, denetim günlüğü, geri alma planı ve mevcut satış/kanal mutabakatı yapılmalı.
5. Biletinial, gişe ve dış kanallar için tek-marka satış politikası ayrıca onaylanmalı.
6. Eklenti yüklenmiş olsa bile kendi hook'larını yalnız sunucu tarafı `DUNYA_BRAND_GUARD_ENABLED === true` açıkça ayarlanırsa kaydeder; bu, dağıtım onayı veya canlı etkinleştirme izni değildir.

Bu kod kesinlikle **canlı siteye doğrudan kurulamaz**; geçmiş/aktif MDG event sahipliği yoksa çalışan bilet satışı engellenir.
7. Kontroller izolasyonlu PHP fixture testleridir; gerçek Woo/PayTR regresyon testi değildir.

## Testler
`integrations/milano/tests/brand-enforcement-contract.php` ile 13 senaryo: Milano biletini MDG checkout'ta engelle, mevcut Madagaskar biletini izinli bırak, ilgisiz ürün, marka boş/hatalı, ortak ürün belirsiz, variation-parent uyumsuz, parent üzerinden kaçış, eski sepet, checkout, Store API, upstream hata koruma.

GitHub CI: `.github/workflows/milano-feed-contract.yml`.

## Canlı veri sınıflandırmasına hazırlık — 12.10.2026
Salt okunur MDG sorgusunda gelecekte satışta bulunan etkinlikler:
- #19 Uşak / 15.10 / 2 seans / MDG sold_units=77
- #22 Aydın-Didim / 16.10 / 2 seans / MDG sold_units=16
- #21 Aydın-Efeler / 17.10 / 3 seans / MDG sold_units=34
- #23 Manisa / 18.10 / 3 seans / MDG sold_units=20
- #10 İzmir / 08.11 / 3 seans / MDG sold_units=16

Toplam MDG sold_units 163. Bu rakam Biletinial ve gişe biletlerinin tamamına eşit olduğu doğrulanmış değildir. Etkinlik adları Madagaskar görünse bile yeni tekil marka sistemi kurulurken sahiplik kuralı açık kayıt/onay ile belirlenecektir.

**Aktif etkinlikleri Milano'ya taşımayın; yeni Milano programının şehir/tarihi kullanıcı tarafından belirlenmesini bekleyin.**

Referanslar:
- [GitHub yeni PR #235](https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/235)
- [Drive Milano çalışma kaydı](https://docs.google.com/document/d/1GSV-_niF39NTkB45--sqodofxDVH25S7XeKxefzUa4Q/edit)

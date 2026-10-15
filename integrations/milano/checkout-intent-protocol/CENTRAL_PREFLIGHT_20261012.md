# Milano — Merkezî Satış Ön Kontrolü (Geliştirme Taslağı)

Tarih: 12.10.2026
Durum: **SADECE GitHub'da taslak. Canlı WooCommerce, PayTR, MDG ve Tickera'ya müdahale edilmedi.**

## İş kuralı

Milano ve Madagaskar ayrı satış markalarıdır. Her etkinliğin tek markası vardır; Milano etkinliği yalnız **milanosirki.com**, Madagaskar etkinliği yalnız **madagaskarsirki.com** üzerinden satılır. Ortak yönetim/finans/kapasite motoru bir etkinliği iki sitede satışa açmaz.

## Yeni kod ve amaç

1. `checkout-intent-protocol/milano-checkout-intent.php`: Milano PHP sunucusundan gelen imzalı, 90 saniyelik işlem isteği. Kullanıcı beyanına dayalı toplam fiyat, sepet vergi tutarı veya QR bilgisi içermez.
2. `checkout-intent-protocol/milano-central-eligibility.php`: **yan etkisiz merkezî doğrulama sözleşmesi**. Yalnız merkezi MMC→MDG→Woo verisinden oluşturulmuş canlı anlık veri görüntüsü ile çalıştırılabilir.
3. `checkout-intent-protocol/milano-order-claim-registry.php`: `request_id` birincil anahtarı ve `order_id` tekillik şartıyla atomic claim taslağı. Gerçek tablo, order/hold yaratmaz.
4. `checkout-intent-protocol/milano-central-preflight.php`: imza → merkezî doğrulama → claim sırasını birleştirir. Çıktı `claimed`, `processing`, `existing_order`, `conflict`, `blocked`, `rejected` veya `unavailable`. İlk worker'a claim token ve **quote_only** detay döner; bunlar hiçbir şekilde bilet ya da ödeme yetkisi değildir.
5. İzole testler: `tests/central-eligibility-contract.php` (**39 senaryo**) + `tests/central-preflight-contract.php` (**14 senaryo**).

## Merkezî doğrulamanın reddettiği durumlar

- Etkinliğin sahibi `milano` değil / boş / taslak / event_id uyuşmuyor.
- Seans farklı etkinliğe bağlı / satış dışı / geçmiş / satışa zaman nedeniyle kapalı; etkinlik veya seans satış penceresi kapanmış.
- Seansın kanonik Woo product veya Tickera event kimliği yok; Woo product yayımlı değişken ürün değil.
- Seçilen Woo variation yayımlı ürünün varyasyonu değil, satılabilir değil veya stokta değil.
- MDG bilet tipi fiyatı ile Woo variation fiyatı **tam kuruş** düzeyinde farklı. Fiyatlar merkezi `price_cents` tam sayısıdır; istemci fiyatı alınmaz.
- MDG_Capacity'deki kullanılabilir birim, seçilen bilet tiplerinin toplam `capacity_units × quantity` ihtiyacını karşılamıyor.
- Ek veya hatalı şema alanları, miktar aşımı, tamamı ücretsiz ödeme talebi. Ücretsiz davetiye/kurumsal biletler ayrı iş akışıdır.

## Aile paketi ve gerçek koltuk/bilet adedi

- Mevcut kurala göre **1 aile paketi (2 yetişkin + 2 çocuk)** fiyatı 1100 TL olabilir; merkezi MDG türünde `capacity_units=4` tanımlıysa uygunluk hesabında 4 birim tüketir.
- Alternatif olarak 2 yetişkin (490 TL) + 2 çocuk (245 TL) = 1470 TL üzerinden 4 giriş birimi olur. Paket fiyatı ile standart bilet toplamı aynı şey değildir.
- Bu sözleşme **4 giriş hakkı gerekliliğini** doğrular, fakat Tickera'da 4 ayrı native QR gerçekten yaratıldığını kanıtlamaz. O kontrol merkezde daha sonra yapılmalıdır.

## Kritik güvenlik sınırı

Yeni `MCPI_Central_Eligibility::validate($intent,$snapshot,$now)` işlevindeki `$snapshot`, sadece Madagaskar **merkez sunucu** üzerindeki gerçek MMC/MDG/Woo modellerinden güvenilir adapter tarafından elde edilmelidir; browser, Milano görüntü önbelleği, REST feed snapshot'ı, request body veya API header'ı kullanılmaz. Bu dosya snapshot'ın kaynağını teknik olarak **kanıtlayamaz**; uygulamaya alırken adapter, kimlik doğrulaması ve DB sözleşmesi ayrıca kurulmalıdır.

`MCPI_Central_Preflight::begin()` başarısız imzayı merkezi okuma yapmadan reddeder, markası yanlış/stoğu olmayan/fiyatı uyuşmayan istek için claim oluşturmaz. Tekilleştirme aynı request_id üzerinde yapılır. Fakat bu **ön kontrol**dür: Woo sipariş ve `MDG_Capacity::hold` arasında fiyat/kapasite değişebilir. Gerçek sipariş işleyicisi atomik hold akışında yeniden doğrulama yapmalı ve yalnız tek Woo order yaratmalıdır.

`claimed` durumundaki kayıt Woo order oluşup `attach_order` güncellemesi gerçekleşmeden işlem kesilirse otomatik silinmez. Bunun için Woo order meta + MDG holds + PayTR kayıtlarının güvenli idempotent **reconciliation** mekanizması gerekir. Henüz yazılmadı.

## İlerleme ve test kapsamı

- `central-eligibility-contract.php` 39 izole senaryo.
- `central-preflight-contract.php` 14 izole senaryo.
- `.github/workflows/milano-feed-contract.yml` içine PHP syntax ve tüm senaryolar eklendi.
- GitHub Actions, gerçek MySQL, PayTR/3DS, WooCommerce sipariş ve Tickera QR testinin yerine geçmez.
- Gerçek checkout REST route, DB migration, Woo order create, gerçek MDG hold, PayTR token/merchant callback, PDF/QR ve Kommo markalaması bu sürümde **yoktur**.

## Sıradaki güvenli geliştirme

1. MMC/MDG/Woo üzerinden merkezi `$snapshot` üreten sadece Madagaskar sunucusunda çalışan **güvenilir PHP adapter** geliştir ve staging/mock MySQL'de doğrula.
2. Gerçek InnoDB tabloda UNIQUE(`request_id`) migration, iki eşzamanlı istek yarış testi, Woo order meta ile crash sonrası reconciliation testi.
3. Tek sipariş yaratıp atomik `MDG_Capacity::hold` çağıran staging order worker. Bu adım öncesi marka ve fiyatı tekrar kontrol et.
4. PayTR'nin **milanosirki.com** iFrame/3DS ve callback için hesabı onayladığı yazılı teyit; PayTR hash/callback tekrarı ve 2+2 dört ayrı QR testi.
5. Kullanıcının ayrı canlı yayın/onayı olmadan üretim sitelerinde ödeme veya satış değişikliği yapma.

PR: https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/235
Drive: https://docs.google.com/document/d/1GSV-_niF39NTkB45--sqodofxDVH25S7XeKxefzUa4Q/edit

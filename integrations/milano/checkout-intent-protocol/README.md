# Milano Sirki — Ayrı Alan Adı, Merkezî Bilet ve PayTR Ödeme Sözleşmesi (Taslak)

**Tarih:** 12.10.2026  
**Durum:** Yalnız GitHub çalışma taslağı. Henüz ödeme, sepet, PayTR callback, bilet veya stok işlemi YOK.  
**Kesin marka ilkesi:** Milano'ya ait etkinlik yalnız `milanosirki.com`, Madagaskar'a ait etkinlik yalnız `madagaskarsirki.com` üzerinden satılır. Dünya Organizasyon merkezi kaynak ve muhasebe altında kalabilir.

## 1. Resmî PayTR entegrasyon davranışı

PayTR iFrame API sunucudan sunucuya **ilk adımda iframe_token** oluşturur; müşterinin ödeme formu, merchant tarafından yetkilendirilmiş alan adındaki iFrame içinde gösterilir. **İkinci adımda ödeme sonucu ayrı bir sunucu bildirimi (Callback/Bildirim URL) ile alınır**. `merchant_ok_url` ve `merchant_fail_url` yalnız müşteriye gösterilen yönlendirme sayfalarıdır; ödeme tamamlandı kanıtı değildir.

Kaynaklar:
- https://dev.paytr.com/iframe-api
- https://dev.paytr.com/iframe-api/iframe-api-1-adim
- https://dev.paytr.com/iframe-api/iframe-api-2-adim

PayTR aynı `merchant_oid` için bildirimi yeniden gönderebilir. Merkezi işlem ancak **doğrulanmış hash/imza**, kayıtlı özgün sipariş numarası, ödeme durumu ve tutar sözleşmesine göre idempotent olarak işlenmeli; ikinci bildirim ikinci bilet/QR/kapasite düşümü yaratmamalı. Resmî dokümana göre toplam tahsilat (`total_amount`) taksit/alternatif yöntemlerle ödemeyi başlatırken iletilen tutardan farklı olabilir; bu nedenle gelişi güzel sabit eşitlik kontrolü yerine PayTR dokümantasyonu ile uyumlu, açıklaştırılmış ödeme mutabakatı gereklidir.

**Alan adı ve ticari hesap teyidi yapılmadı:** Mevcut PayTR mağaza hesabının iki ayrı marka ve alan adında kullanılmasına, bildirim URL'sine, iframe gösterimine ve ödeme sonrası yönlendirmeye izin verilip verilmediği PayTR mağaza destek/teknik kullanıcı üzerinden doğrulanmalı. Kuralları varsayarak yeni siteye ödeme açma.

## 2. Tercih edilen teknik akış — yalnız hedef tasarım

1. Milano müşterisi yalnız Milano sitesindeki kendi etkinliğine girer. Site kanonik sahibi `milano` olan seansları listeler; Madagaskar biletleri görünmez.
2. Milano PHP sunucusu, kullanıcının seans ve bilet tipi seçimini **fiyat bilgisi taşımadan** 90 saniyelik `v1` HMAC işlem isteğine dönüştürür. Alıcı merkezi adres kodda sabittir; kullanıcı URL'sinden türetilmez. Marka `milano` değerinden başka bir şey olamaz.
3. Milano'dan merkez sunucuya sadece HTTPS ve ayrı server-side checkout API anahtarıyla imzalı istek gelir. `request_id`, `issued_at`, `expires_at`, `event_id`, `session_id`, `items[code,quantity]` içerir. **Hiçbir ödeme tutarı, PayTR parolası, müşteri adı veya fiyat beyanı taşımaz.**
4. Merkezi API mesajı zaman/imza/şema yönünden doğrular ve **veritabanında UNIQUE(request_id)** ile atomik tekrarlı işlem engeli uygular. Aynı request_id tekrar gelirse aynı özgün işlem sonucu dönmeli; farklı içerikle gelirse reddedilmeli.
5. Merkezi sistem MMC→MDG etkinlik markasını **yeniden** `milano` olarak doğrular. Aynı etkinliğin farklı markalı checkout'a girmesine izin verilmez. MDG seans açık mı, satış penceresi uygun mu, Woo varyasyonları/bilet tipi aynı mı, kanonik fiyat ve kapasite uygun mu kontrol eder.
6. Merkezi WooCommerce üzerinde **yalnız bir sipariş** oluşturulur; `source_brand=milano`, etkinlik ve seans eşlemesi güvenilir sunucu kayıtlarına işlenir. Rezervasyon `MDG_Capacity::hold` ile yalnız merkezde ve atomik yapılır. 2+2 aile paketi = **4 ayrı Tickera giriş/QR bileti**; stok/bilet adedi buna göre hesaplanır.
7. PayTR desteklediği ve yetkilendirildiği teyit edilmişse Milano sitesi üzerinde iFrame gösterilir. Müşteri başka bir markanın mağaza checkout sayfasına gönderilmez. Merkez yalnız ödeme sağlayıcısıyla doğrulanmış ticari hesap/alan adı üzerinden token alır. İframe tokenını veya işlem kimliğini URL/JS üzerinden sipariş sahibi kanıtı olarak kabul etme.
8. PayTR'nin **sunucu bildirimi** merkezde doğrulanır; aynı `merchant_oid` için idempotent ödeme onayı veya iptal/iadeyi yönetir. WooCommerce order state + MDG hold/commit + Tickera üretimi **tek işlem kimliği** üstünden ilerler. Callback'in `OK` yanıtı doğru işlendikten sonra döner. Müşteri dönüş sayfası callback yerine geçmez.
9. Milano sitesindeki satın alma, bilet PDF/e-posta/WhatsApp/Kommo mesajı Milano marka bilgisine göre hazırlanır. Finans raporu markayı ayırır, merkezi gelir/stoğu iki kez saymaz.

## 3. Bu turda gerçekten yapılan: yalnız imzalı istek protokolü

- `integrations/milano/checkout-intent-protocol/milano-checkout-intent.php`
- `integrations/milano/tests/checkout-intent-contract.php`
- `.github/workflows/milano-feed-contract.yml` içine 27 senaryolu protokol sözleşmesi

PHP dosyası WordPress'te hiçbir endpoint/hook oluşturmuyor; sipariş başlatmıyor, PayTR credentials kullanmıyor ve canlı veri okumuyor. Yalnız `mcpi_make_intent()`, `mcpi_sign()`, `mcpi_verify()`, `mcpi_schema_valid()` işlevleri var.

İmza HMAC-SHA256, amaçla ayrılmış versiyon etiketi, sunucuda güvenli saklanan **en az 32 karakterlik ayrı bir anahtar** kullanıyor. Gerçek anahtar üretilmedi/Drive/GitHub'a yazılmadı. Aynı anahtar salt okunur etkinlik beslemesiyle paylaşılmamalı. Mesaj yalnız `milano` markasında üretilebilir. 90 saniye TTL, 30 saniye saat farkı izni. İstek kimliği `random_bytes(16)` ile üretilir. Satır sayısı ve adetler üst sınırla kısıtlanır. Gelişigüzel ek alan, alıcı adresi, quoted price/total, PII ve müşteri tanımlayıcısı desteklenmez.

**Kritik:** HMAC ve kısa süre tek başına replay engellemez. Testlerde aynı geçerli istek iki kez kriptografik doğrulamadan geçebilir; gerçek endpoint'te merkez tarafı UNIQUE(request_id), işlem durum makinesi ve atomik işleme zorunlu. Bunlar henüz kodlanmadı. Bu protokol doğrulaması bir kullanıcıya bilet alma yetkisi, ödeme başarılı sonucu veya kontenjan ayırma işlemi sağlamaz.

## 4. Canlıya çıkmadan önce kapatılacak güvenlik/uyumluluk işleri

- [ ] PayTR destek biriminden Milano alan adı, iframe ve callback kullanımı; mağaza/hesap ve fatura markası teyidi.
- [ ] WordPress.com Atomic sunucularında gizli anahtarların güvenli yönetimi (PHP configuration/env); tarayıcı/JS/Drive/GitHub'da sırrı saklama YASAK.
- [ ] Merkez REST checkout-intent endpoint güvenliği: HTTPS, bağımsız imza ve anahtar, origin/site yetkilendirmesi, sabit URL, rate limit, gövde boyutu sınırı, log PII temizliği.
- [ ] Merkez **UNIQUE(request_id)** ve idempotent order creation + retry/error state; event-owner/server-checkout policy.
- [ ] Gerçek merkez WooCommerce ve MDG rezervasyon yarış koşulu/son koltuk/iptal/iade/başarısız ve tekrar callback.
- [ ] Merkezi ödeme kaydında brand, original order id, merchant_oid tekilleştirmesi ve tutar/para birimi/gateway doğrulaması.
- [ ] Woo Store API, klasik checkout, `order-pay`, kampanya, eski Woo ürün linkleri, Ajax ve PayTR redirect döngüsünde marka sızıntısı testleri.
- [ ] Milano alan adında gerçekten görüntülenen iframe, kullanıcı oturumu, HTTPS/çerez, 3DS ve mobil ödeme denemeleri.
- [ ] Aile paketi 2+2 için dört ayrı bilet/QR, tekrarlı callback'te sıfır ilave QR.
- [ ] Biletinial/gişe dış kanal kapasite mutabakatı; markalara ait biletlerin yanlış siteden satılmaması.
- [ ] Staging testleri ve ayrı kullanıcı onayı; bundan önce siteyi satışa açma veya canlı sisteme yeni ödeme kodu kurma **YOK**.

## 5. Mevcut canlı durum

- Madagaskar, normal satışlarına devam ediyor. 13 MMC programının marka değeri `Madagaskar Sirki` olarak salt-okunur doğrulandı.
- Milano WordPress tanıtım sitesi **herkese açık**, çevrimiçi bilet satışı **kapalı**. WooCommerce, PayTR ve Tickera Milano'da henüz kurulu değil.
- Bu PR #235 **draft**. Henüz birleştirilmedi ve canlı ödeme/QR/Woo siparişleri değiştirilmedi.

GitHub: https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/235  
Drive: https://docs.google.com/document/d/1GSV-_niF39NTkB45--sqodofxDVH25S7XeKxefzUa4Q/edit

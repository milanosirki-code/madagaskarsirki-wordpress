# Kampanya kodu temizliği — 10 Ekim 2026

## Tamamlanan kapsam

- Açık main belgelerinde gerçek kodlar ve kurum adları sentetik demo etiketleriyle değiştirildi. Bu etiketler canlı kod değildir.
- PHP test fixture'larında canlı kod ve kurum adları yerine sentetik değerler kullanılır.
- Kampanya admin formunun örnek kodu sentetik hale getirildi. Bu yalnız placeholder değişikliğidir; fiyat, kayıt, kod çözümleme, sepet, ödeme veya bilet davranışı değişmez.
- CI güncel Git ağacındaki metin dosyalarını tarar: bilinen açığa çıkmış kodları özet üzerinden, demo/test dışı kodlu URL'leri biçim üzerinden engeller. Bulunan değerleri log'a yazmaz. Bu denetim bütün yeni kodları ya da Git geçmişini tespit ettiğini iddia etmez.

## Salt okunur canlı kapsam

Canlı registry'de beş aktif kayıt var. Açık main'deki bilinen üç kod bu kayıtlardan üçüyle eşleşiyor. Bir kayıt üç ile, iki kayıt birer ile bağlı. Yeni değerler bu belgeye yazılmaz.

Registry veya fiyat, ürün, sipariş, bilet, ödeme ya da kurum mesajı bu temizlik kapsamında değiştirilmez. Dağıtılmış eski kodların bugün geçersiz hale gelmesi satış/kurum iletişimini etkileyebilir; gerçek kod yenileme ayrı işletme sahibi kararını bekler.

## Canlı kod yenileme için hazır plan

1. Yalnız açığa çıkmış üç kaydı kapsam olarak onaylatın; diğer iki kaydı değiştirmeyin.
2. Yeni tahmin edilmesi güç kodları özel yönetim ekranında oluşturun; mevcut isim, il kapsamı, fiyatlar, aktiflik ve tarihler aynen taşınsın.
3. Eski kodları yeni satışa kapatın. Mevcut ödenmiş siparişleri ve biletleri değiştirmeyin. Açık sepetler eski kampanya anahtarına bağlı olduğu için yeni kodla tekrar teklif/sepet gerektirebilir.
4. Eski kod reddi, yeni kod/il/fiyat eşleşmesi ve normal satış akışını doğrulayın. Gerçek ödeme veya bilet üretimi yapmayın.
5. Yeni kodları yalnız işletme sahibi yetkili kurumlara iletir. GitHub, Drive ve CI loglarına kod değerleri yazılmaz.

## Sınırlar

Güncel dosya ve PR açıklaması temizliği eski commitleri, eski dalları, yorumları, kopyaları veya dağıtılmış mesajları geri almaz. Depo geçmişini yeniden yazmak bu değişiklik kapsamında değildir. Üç eski kod yenilenmeden erişim riski tamamen kapanmış sayılmaz. Issue214 bu nedenle açık kalır.

## Doğrulama

11 sentetik tarayıcı kontrolü; kampanya katalog/teklif/sepet, rapor, admin ve çoklu-il regresyonları; PHP syntax; PR CI. Canlı placeholder kaynağı canonical repo ile karşılaştırılır. Canlı registry öncesi/sonrası aynı kalmalıdır.

# Milano marka izolasyonu — ödeme / kapasite / QR akışı denetimi
Tarih: 12 Ekim 2026. Kaynak: mevcut GitHub kodu; canlı ödeme işlemi yapılmadı.

## Doğrulanmış kod akışı
1. `wp-content/plugins/madagaskar-bilet-yonetimi/includes/class-mdg-live-sales.php`:
   - `woocommerce_store_api_checkout_order_processed` öncelik 5 → `store_api_order_processed()` → `reserve_or_throw()` ile seans kapasitesi hold.
   - `woocommerce_payment_complete` öncelik 5 → `payment_complete()` → `MDG_Capacity::commit_order( $order_id )` + `sync_order_map_status( ..., true )`.
   - `woocommerce_order_status_processing` ve `woocommerce_order_status_completed` aynı `payment_complete()` işleyicisini çağırıyor; başarısızlıkta `release_order()` kullanılıyor. **Birden fazla hook'un çalışması mümkündür; `commit_order` idempotency ayrıca gerçek kod/test ile doğrulanmalı.**
   - `reserve_or_throw()` önce `holds` tablosunda aynı sipariş için `held/sold` satırlarını kontrol ediyor; mevcutsa yeni hold açmadan `sync_order_map()` ile dönüyor.
   - `upsert_order_map_row()`, `order_item_id` üzerinden insert/update yapıyor; bu defterin tekilleştirme yöntemidir, Tickera QR üretiminin tekilleştirildiğine dair kanıt DEĞİLDİR.
2. `wp-content/plugins/madagaskar-management-center/includes/class-mmc-sales-service.php`:
   - `woocommerce_payment_complete` öncelik 20 → `sync_order()`, ayrıca status changed / refunded hook'ları.
   - MMC adımı önceki MDG kapasite commit'inden sonra tetiklenebilir; burada ret uygulamak gecikmiş olabilir.
3. Canlı WordPress plugin.list: PayTR iFrame API v3.1.2, WooCommerce 11.2.1, Tickera 3.6.0.7, Tickera Bridge 1.7.8, eski Milano feed 0.1.0 aktif. PayTR'nin vendor callback kaynak kodu bu repoda erişilebilir değil; **HMAC, callback order/amount kontrolleri ve Tickera ticket hook sırası doğrulanamadı.**

## Karar ve güvenli ilerleme
- PR #235 kapsamındaki marka koruması ödeme öncesi klasik/Store API sipariş satırı denetimini hedefler; varsayılan kapalıdır, **canlıya deploy edilmedi**.
- Kötü niyetli istemcinin WooCommerce ürün ID, varyasyon ID veya brand parametresini değiştirmesi satış izni oluşturmamalı.
- `woocommerce_payment_complete` hook'una yalnız geç bir `throw` eklemek, PayTR'den para tahsil edildikten sonra müşteriyi ödeme tamamlandı göstermeden bırakabilir; **bu yaklaşım kullanılmamalı**.
- Önce sipariş markası ve site sahibi teyit edilmelidir. PayTR callback için gerçek eklenti kodu / belgeleri temin edilip imza + tutar + idempotent işleyiş ve Tickera bilet üretim sırası incelenmelidir.
- Testler: doğru marka; çapraz marka; aynı sepette iki marka; yanlış ürün/varyasyon; ödeme öncesi hold; başarılı callback tekrarı; processing/completed çift tetikleme; başarısız/iptal/iade sonrası tekrar bildirim; Tickera tek QR; son koltuk eşzamanlı denemesi.
- Geliştirme dalında yalnız **salt okunur statik akış sözleşmesi** yazılabilir; gerçek callback'e müdahale ancak staging ve açık canlı onayı sonrasında düşünülmeli.

## Açık önemli riskler
- MDG merkezinin `commit_order` fonksiyonunun SQL transaction/idempotency kontrolü bu denetimde henüz ayrıntılı incelenmedi.
- Tickera vendor otomasyonunun hangi sipariş statüsünde PDF/QR ürettiği hâlâ belirsiz.
- Milano'nun ayrı domain WooCommerce siparişi ile Madagaskar'ın merkez MDG kapasite defterinin nasıl eşleşeceği planlanmalıdır. Bir siteye bağlı ödeme siparişi diğer sitenin checkout'u değildir.

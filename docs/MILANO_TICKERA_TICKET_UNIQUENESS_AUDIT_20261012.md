# Milano / Madagaskar — Tickera ticket uniqueness audit (2026-10-12)

## Evidence verified in the repository
- The previous MDG atomic capacity fix CI commit `bd7a42c89ebf3741f2a15768a70c19104ee27b18` has **success** in PHP syntax, Plugins CI, and Milano contracts. This validates isolated tests, not two-worker database concurrency or live PayTR retries.
- `MDG_Live_Sales::payment_complete` handles `woocommerce_payment_complete`, `woocommerce_order_status_processing`, and `woocommerce_order_status_completed`, calling capacity commit and order-map status synchronization. These hooks are *not themselves a proof* that Tickera issues one ticket.
- `MDG_Ticket_Template_QR_Binder` concerns **venue/location QR embedded in a Ticket Designer template**. Its documentation explicitly says admission QR is not changed. It cannot serve as ticket instance uniqueness protection.
- MDG V4 references `tc_tickets_instances` posts and their `ticket_code` metadata for ticket audits, migration/rollback previews. This is evidence of the ticket instance data model, not its vendor creation hooks.
- Active live plugins include Tickera 3.6.0.7, WooCommerce Tickera Bridge 1.7.8, PayTR iframe 3.1.2; the vendor implementation of ticket creation is not present in the inspected repository paths.

## Safety rule for implementation
Do not intercept or delete `tc_tickets_instances` blindly, and do not use venue QR code as a customer admission code. An actual idempotent ticket issuance gate needs a verified **order ID + order item ID + ticket unit index** identity, and vendor-compatible creation and email mechanisms.

## Read-only diagnostics required
1. On staging, for a controlled single test order with quantity 2, list ticket-instance IDs and hashed (not plaintext) ticket codes grouped by WooCommerce order and line item.
2. Re-send the *same valid, signed* PayTR callback sequentially and concurrently, then compare expected number of ticket instances, capacity `sold_units`, and outgoing ticket emails.
3. Trigger both `processing` and `completed` status hooks. Check for duplicate or missing instances, including manually resent notifications and webhook retries.
4. Check `tc_tickets_instances` parent/meta relationship to WC order/line item in the installed Tickera/Bridge version before declaring a unique key.
5. Validate wrong-brand and missing owner orders produce **zero** admission tickets; refunds and canceled orders do not produce new instances.
6. Only after verified API hooks are identified, implement idempotency with server-side transaction/uniqueness, repeatable tests, rollback, and explicit release approval.

## Production status
No vendor callback or Ticket Designer code changes. No ticket was created, disabled, or deleted. Milano remains unlaunched; Madagaskar production sales unchanged.


## 12 Ekim 2026 — canlı Tickera veri modeli doğrulandı (salt okunur)

**Kaynak:** Yetkili WPVibe `read_file(scope="wp-content")` ile kurulu `tickera-event-ticketing-system/includes/classes/class.ticket_instance.php`, `class.tickets_instances.php`, `class.order.php`; ayrıca WordPress'te yalnız `SELECT` kullanan WP-CLI toplu sorgular. Kişisel bilgiler veya gerçek QR/bilet kodları alınmadı; şema/özet sayılar görüldü.

### Veri modeli kanıtı

- Kurulu `TC_Tickets_Instances::get_tickets_instances_fields()` içinde `ticket_code`, `ticket_type_id` ve `post_parent` alanları tanımlı.
- Mevcut `tc_tickets_instances` kayıtlarının WordPress `post_parent` alanı, WooCommerce sipariş kimliğiyle eşleşiyor; HPOS altında WordPress post karşılığı `shop_order_placehold` görünüyor.
- Bilet `item_id` metası **WooCommerce `woocommerce_order_items.order_item_id`** ile eşleşiyor; bu anahtar bilet sayısı ve sipariş satırı doğrulaması için uygundur.
- Her bilet örneğinde `event_id` ve `ticket_type_id` meta anahtarları mevcut; bunların farklı markalar altında çapraz eşleşip eşleşmediği ayrıca staging'de test edilmelidir.

### 12.10.2026 anlık salt-okunur sayım (değişebilir)

| Denetim | Sonuç |
|---|---:|
| Tüm `tc_tickets_instances` post kayıtları | 1.322 |
| `publish` biletler | 1.251 |
| `trash` biletler | 71 |
| Tüm kayıtların benzersiz `ticket_code` sayısı | 1.322 |
| Yayımlanmış biletlerin benzersiz `ticket_code` sayısı | 1.251 |
| Eksik `ticket_code`, `event_id`, `ticket_type_id` (yayımlanmışlar) | 0 |
| WooCommerce `order_item_id` ile eşleşen tüm biletler | 1.322 / 1.322 |
| Üst Woo siparişine ait `post_parent` kimliği doğru olanlar | 1.322 / 1.322 |
| `publish` biletler altında denetlenen Woo sipariş satırları | 779 |
| `publish` bilet adedi ve Woo `_qty` ile farklılık gösteren satırlar | 0 |
| Yayımlanmış beklenen / gerçekleşen toplam | 1.251 / 1.251 |

**Yorum sınırı:** 0 tekil kod veya miktar sapması tespiti tarihi kayıtların temel bütünlüğü için olumlu kanıttır. Gelecek Milano satışlarında eşzamanlı PayTR callback, 3DS, Biletinial, aile paketi 2+2 ve MDG `capacity_units` için otomatik garanti **değildir**. Geçmiş Woo `_qty` miktarları tekil Tickera girişle örtüşmüş olsa da **aile paketi 2+2 → 4 giriş/QR** eşleştirmesi yeni merkezi checkout iş kuralında ayrıca yapılacaktır.

### Çekirdek kaynakta gözlemlenen özel uyarı

`TC_Ticket_Instance::get_attendance_records()` bazı eski check-in alanlarını okurken meta taşıma işlemi yapabilir; bu nedenle veri denetimlerinde bu fonksiyon **salt-okunur değildir** ve çağrılmadı. Toplu `SELECT` sorguları kullanıldı.

### Bu aşamadaki yeni kod, yalnız GitHub taslağı

- `integrations/milano/checkout-intent-protocol/milano-ticket-issuance-ledger.php`: Sipariş kimliği + Woo `order_item_id` + giriş sıra numarası `(order_id,order_item_id,unit_index)` üzerinde InnoDB PRIMARY KEY önerisi. Ayrıca `ticket_instance_id` için UNIQUE. Bu DDL **uygulanmadı**.
- `integrations/milano/tests/tickera-issuance-ledger-contract.php`: aile paketinden dört tekil admission slotu, yanlış marka, ödeme yokken bilet üretmeme, ikinci işçi/tekrar işleme, çökme sonrası otomatik devralmama, farklı bilet örneğine aynı ID bağlamama için izole senaryolar.
- Ledger **Tickera bilet oluşturma işlemini yapmaz**. `claim_token` yalnız trusted server worker tarafından kullanılabilir; tarayıcıya verilmez. Crash window durumunda manuel/denetimli mutabakat zorunlu.
- Farklı PayTR denemelerinin aynı siparişi sonuçlandırması, Tickera üretim hook'u, merkezi MDG son koltuk transaction'ı, kupon/indirim, 4 fiziksel aile QR bileti ve marka izolasyonunun canlı kanıtları henüz yok. Bunlar bitmeden canlıya çıkarılmaz.
- **Bridge ana dosyası** `bridge-for-woocommerce.php` yaklaşık 297 KB olduğu için mevcut WPVibe `read_file` 64 KiB sınırı altında tam incelenemedi. Bu nedenle gerçek Tickera Bridge ticket creation hook'u **hâlâ doğrulanmadı**.

Envanter sorguları müşteri adı, e-posta, ham `ticket_code` veya QR içermeyecek şekilde yalnız COUNT / GROUP BY ile çalıştırıldı. Madagaskar WordPress verileri değiştirilmedi.

# Tickera Bridge canlı kaynak keşfi — 12.10.2026

## Salt okunur, doğrulanmış
- WPVibe site_info, Madagaskar Sirki sitesine yönetici bağlantısını ve Tickera Bridge ana yolunu doğruladı: `plugins/bridge-for-woocommerce/bridge-for-woocommerce.php`. Aynı envanterde `plugins/tickera-event-ticketing-system/tickera.php` ve `plugins/paytr-sanal-pos-woocommerce-iframe-api/paytr-sanal-pos-woocommerce-iframe-api.php` var.
- WPVibe list_files, Bridge ana PHP dosyasının **297.2 KB** olduğunu gösterdi.
- WPVibe read_file bu dosyayı **64 KiB sınırı** nedeniyle reddetti; satır aralığı vererek tekrar denemek de sınırı aşmıyor. Bu, kodun gerçekten incelendiği anlamına gelmez.
- Önceki PR commit `864021fdafd837976e1d0f7a601501d15ce4c2f4` GitHub testleri başarılı: Milano run 38302348066, PHP 38302347771, Madagaskar Plugins CI 38302347760.

## Sonuç
Bridge'in hangi callback/hook altında ticket instance yarattığı, order_item_id kimliğini nerede tuttuğu ve tekrar eden ödemeyi nasıl tekilleştirdiği **kanıtlanmadı**. Bunu uydurulmuş bir hook adıyla kapatmak tehlikelidir. Ana dosya için güvenli ve yetkili kaynak inceleme yolu (ör. staging'de geliştiriciye ait lokal kopya veya vendor dokümantasyonu) gereklidir.

Milano checkout'unu veya Madagaskar Tickera/PayTR üretim davranışını değiştirmeyin. Gerçek order-line kimliği netleşmeden sahte data eşleştirmesini canlıya almayın.

## Açık iş
1. Bridge ana PHP dosyasında ticket instance yaratma metodunun adı ve tetiklendiği WC hook'ları.
2. Sipariş satırına bağlama: order ID, line item ID ve unit index meta anahtarları.
3. Tekrarlı callback için idempotency ve e-posta yeniden gönderimi.
4. Staging'de aynı siparişin iki callback ile ticket sayısı değişmeden kaldığının test edilmesi.
5. Denetimden sonra ayrı bir onayla güvenli canlıya alma.

Canlı biletler ve pluginler değiştirilmedi.

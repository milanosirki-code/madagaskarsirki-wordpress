# Web gelirlerinin iki kez sayılması — 8 Ekim 2026

Canlı finans ekranı ve salt-okunur kaynak kontrolü doğruladı: otomatik paid order_map web toplamı, aynı kanalın eski WooCommerce gelir snapshot kayıtlarıyla yeniden toplanıyordu. Program özeti, aylık işletme/program raporu ve bilet gelirinden seyirci tahmini etkileniyordu.

Minimal düzeltme: yalnız açık WooCommerce kanalı (büyük/küçük harf ve boşluk normalize edilerek) manuel toplamdan çıkarılır. Eski kayıtlar ve belgeler korunur; program ayrıntısında toplama ayrıca eklenmediği belirtilir. Biletinial, gişe, POS, kantin ve kurumsal gelirler korunur. Ödeme/iade mutabakatı bu değişikliğin dışında; eksik giderler tahmin edilmez.

İzole test: otomatik web + legacy web snapshot + gerçek diğer tahsilatlar + bekleyen tahsilat; web bir kez sayılır, bekleyen ayrı kalır ve giriş kayıtları değişmez. Yanlış kanal eşleştirmesi yapılmaz. PHP lint ve PHP7.4 CI gereklidir.

Canlı kabul: değişiklik öncesi/sonrası gelir ve gider kayıtları eşit; eski WooCommerce satırı görünür; program kanallarında web bir kez; aylık ve seyirci raporları uyumlu. Tek dosya tam kaynak yedeği ve geri dönüş sağlanır. Checkout/sipariş hookları değişmez.

Okul canlı veri kontrolü: 22.873 kurum; öğrenci geçmişi ve fiziksel baskı stok tabloları henüz boş. Kullanıcıdan doğrulanmış öğrenci ve gerçek stok adetleri gereklidir. Kod hazırlığı verilerin tamamlandığı anlamına gelmez.

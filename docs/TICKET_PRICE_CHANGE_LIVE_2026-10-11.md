# Bilet fiyat değişikliği — 11 Ekim 2026

Canlı site: https://madagaskarsirki.com
GitHub inceleme kaydı: https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/227
Kullanıcı talebi üzerine canlıya uygulandı.

| Kapsam | Yetişkin | Çocuk | Aile 2+2 |
|---|---:|---:|---:|
| Eskişehir, Uşak, Aydın/Efeler, Aydın/Didim, Manisa | 490 TL | 245 TL | 1100 TL |
| İzmir | 585 TL | 290 TL | 1300 TL |

## Uygulama ve kontrol
- 6 MDG etkinlik (10,13,19,21,22,23), 16 seans, 32 MDG/WooCommerce varyasyonu: fiyat eşleşmesi 32/32.
- MMC ana bilet kataloğunda 12 yetişkin/çocuk satırı güncellendi; kimlikler, aktiflikler, kapasite birimleri ve aile satırları korundu.
- Açıklama, SSS ve SEO fiyat metinleri aynı kapsamda güncellendi. İzmir aile fiyatı metinlerde de 1300 TL olarak doğrulandı.
- Önceden mevcut 204 sipariş kaleminin parasal kayıtları ve 334 biletin QR/etkinlik/sipariş ilişkisi hash karşılaştırmasında aynı.
- Ücretli, ücretsiz ve protokol örnek biletlerinin eski PDF fiyat veri çıktıları aynı.
- Aile paketi testlerinde standart 1/2 paket 1100/2200 TL; İzmir 1/2 paket 1300/2600 TL. Eksik paket miktarında indirim verilmemesi doğrulandı.
- Manisa müşteri ekranında 245/490/1100; İzmir ekranında 290/585/1300 doğrulandı.
- Manisa aile paketi gerçek sepete aktarıldı: 1470 TL brüt, 370 TL indirim, paket neti 1100 TL. Sadece testte eklenen Manisa satırları kaldırıldı.
- Mevcut tarayıcı sepetindeki geçmiş Denizli seansı ödeme doğrulamasını engelledi. Önceden bulunan satırlar korundu. Gerçek ödeme/sipariş oluşturulmadı; ödeme akışının tamamı bu oturumda doğrulanamadı.
- Biletinial harici panel fiyatları ve kanal kayıtları değiştirilmedi. Web sitesi fiyat değişikliği dış panelin otomatik güncellendiği anlamına gelmez.

## Canlı kod ve geri dönüş
- Code Snippets 180: aile fiyat geçiş koruması aktif. Eski aile sepetinin paket fiyatını yeni birim fiyatlarla doğru hesaplar. Sipariş/bilet/ödeme yazmaz.
- Geçici Code Snippets 181 ve 182: işlem sonrası pasif, code_error null.
- Yedek seçenek: mdg_price_change_20261011_backup (catalogue/Woo/MMC/kanal ve eski kayıt kontrol snapshotları).
- Metin yedeği: mdg_price_text_20261011_backup.
- Geri dönüş gerekiyorsa eski Woo regular_price ve MMC bilet fiyatlarını kendi Woo REST ve MMC ticket-type-update API'leriyle; MDG kataloğunu yalnız aynı 32 kimlikte yedekten geri yükle. Eski satış kayıtlarını değiştirme. Katalog geri alındığında aile koruması da yeniden değerlendirilir.
- Kaynak kod aynaları docs/code-snippets/mdg-family-price-transition-20261011.php, mdg-ticket-price-operation-20261011.php ve mdg-ticket-price-text-20261011.php dosyalarında. Ana dal değiştirilmedi; kayıt draft PR 227'de.

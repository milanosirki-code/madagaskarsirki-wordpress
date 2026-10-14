# Madagaskar Sirki — Normal Satış Toplu PDF

**Tarih:** 2026-10-14  
**Canlı site:** https://madagaskarsirki.com  
**Durum:** CANLI / doğrulandı

## Amaç

Normal ücretli WooCommerce/Tickera siparişlerinde, müşterinin ayrı ayrı bilet PDF'lerini açmak zorunda kalmadan aynı siparişe ait tüm geçerli biletleri tek çok sayfalı PDF içinde açabilmesi.

## Canlı modül

Code Snippets:
- **#188 — MDG Normal Satış Toplu PDF 20261014**
- aktif
- code_error: yok

## Davranış

- Mevcut WooCommerce siparişi okunur.
- Yalnız processing / completed siparişler kabul edilir.
- Protokol siparişleri kapsam dışıdır.
- Sipariş altındaki mevcut native Tickera ticket instance kayıtları kullanılır.
- Yeni bilet oluşturulmaz.
- Yeni QR üretilmez.
- Kapasite yazılmaz.
- Bilet numarası değişmez.
- _mdg_invalidated işaretli kayıtlar dışarıda bırakılır.
- En az 2 geçerli bilet varsa toplu PDF üretilir.
- Tek bilette toplu PDF işlemi devreye girmez.
- Üst sınır 100 bilettir.

## Güvenlik

Mevcut Biletlerim güvenli token sistemi kullanılır:
- order_id
- HMAC tabanlı token

Müşteri yalnız kendi güvenli Biletlerim bağlantısından toplu PDF açabilir.

## Biletlerim ekranı

İki veya daha fazla geçerli bileti olan siparişte yeni kutu gösterilir:

- “X bilet tek PDF”
- “Tüm Biletleri Tek PDF Aç / İndir”

Tekil “Bileti Görüntüle / İndir” akışı korunur.

## PDF

Normal Tickera bilet şablonları kullanılır. Protokol layout'u kullanılmaz.

Her mevcut bilet ayrı sayfadır. Mevcut:
- QR
- salon / seans
- misafir
- işlem tarihi
- bilet no
- fiyat / KDV
alanları normal bilet filtreleriyle korunur.

## Smoke test

### Sipariş #6086
- durum: processing
- aile paketi 2+2
- 4 native bilet
- ticket IDs: 6091, 6092, 6093, 6094
- ticket codes: 0000002776, 0000002777, 0000002778, 0000002779
- toplu PDF: geçerli
- PDF boyutu: 2,288,894 byte
- yeni bilet üretimi: yok

### Sipariş #6128
- durum: processing
- 3 native bilet
- toplu PDF: geçerli
- PDF boyutu: 2,285,807 byte

### Sipariş #6067
- 1 geçerli bilet
- toplu PDF reddedildi
- beklenen davranış

## Not

İlk incelemede #6095 aile işaretli görünmesine rağmen WooCommerce siparişi failed durumundaydı. Bu nedenle güvenlik gereği toplu PDF testinde kullanılmadı. Testler ödenmiş processing siparişlerle tamamlandı.

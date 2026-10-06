# Protokol PDF düzeni — 6 Ekim 2026
Sorun: Denizli A9 davetiyesinde salon ve adres çakışıyordu; koltuklu ücretsiz protokol bileti normal satışın numarasız/fatura metnini gösteriyordu.

Çözüm: snippet128 MDG_Protocol_PDF, native Designer template nesnesini yalnız bellekte kopyalayıp 595×420 pt düzen uygular. Ortak veritabanı şablonu kaydedilmez. Native QR elementi ticket_id payload alanı ve gerçek bilet kodu korunur. Salon konumu QR görseli aynen kullanılır, etrafına okunabilir beyaz boşluk eklenir. Salon/adres/misafir alanları ayrılır; event_terms ticari metni yerine protokol giriş notları yazılır. Numarasız biletlerde serbest oturma notu kullanılır.

Snippet126 Denizli mevcut davetiyeleri ve snippet127 genel protokol menüsü bu renderer'ı çağırır. Helper yoksa önceki renderer'a döner.126 sınıfı güvenli tekrar yükleme için koşullu tanıma alındı.

Doğrulama:
- PHP syntax başarılı; mevcut53 protokol regresyon kontrolü ve10 PDF layout kontrolü başarılı.
- Canlı iki seansın mevcut20 PDF'si geçerli; seans saatleri ve A2–A11 koltukları doğru; gerçek kodlar korundu.
- A9/19.30 PDF son sürümü PNG'ye çevrildi ve görsel olarak kontrol edildi: çakışma/kırpılma yok.
- Son raster üzerindeki giriş ve konum QR kodları yazılımla çözüldü. Giriş payload hash native resolve verisiyle eşleşti; konum hash mevcut venueQR hash ile eşleşti.
- Ortak template19 değişmedi; normal satış ürünleri/fiyatları/şablonları değiştirilmedi.
- Yeni sipariş/bilet üretilmedi; mesaj gönderilmedi. Fiziksel salon okuyucusu ile tarama yapılmadı.
- Snippet126/127/128 aktif, code_error null.
PR: https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/161

Kullanım: mevcut online davetiye bağlantısından PDF'yi tekrar açın/indirin. Önceden telefona indirilmiş eski PDF kendiliğinden güncellenmez.
Geri alma:128 devre dışı bırakılır;126/127 fallback ile eski PDF düzenini kullanır. Hiçbir gerçek bilet/order silinmez.
Teşhis: mdg-protocol-pdf/v1/diagnostics manage_woocommerce korumalı GET; mevcut A9 PDF'sini salt okunur üretir; snippet128 devre dışı kalırsa route kapanır.
Kayıtta gerçek QR payloadları, paket anahtarları veya kişi telefonları bulunmaz.

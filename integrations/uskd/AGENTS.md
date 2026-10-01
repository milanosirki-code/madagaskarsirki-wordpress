# AGENTS.md — USKD Integration Staging

Bu klasör uskdernegi.org için GitHub'a alınan geçici kanonik kaynakları içerir.

## Hedef

USKD kodları ayrı bir `uskdernegi-wordpress` repository'sine taşınana kadar:
- canlıda çalışan entegrasyonları yedeklemek,
- yeni kodu test edilebilir biçimde hazırlamak,
- Madagaskar kodundan mantıksal olarak ayırmak.

## Kurallar

- USKD canlı sayfasını değiştirmeden önce mevcut sayfa içeriğini yedekle.
- Madagaskar'dan sadece gerekli kamuya açık etkinlik alanlarını çek: şehir, tarih, salon, seans.
- USKD kartlarında bilet fiyatı gösterme.
- Madagaskar checkout/ürün/sipariş verisine USKD tarafından yazma.
- Cross-site entegrasyonda secret/token kullanma.
- Harici kaynağa erişilemezse kontrollü fallback göster.
- DOM/CSS seçicileri tek başına uzun vadeli API sözleşmesi kabul etme; mümkün olduğunda yapılandırılmış REST endpoint tercih et.
- Production değişiklikleri önce GitHub kodu + smoke test planıyla hazırlanmalı.

Bu klasör ileride ayrı USKD repository'sine taşınmak üzere düzenlenmiştir.

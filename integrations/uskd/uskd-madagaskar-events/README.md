# USKD Madagaskar Etkinlik Akışı

Shortcode: `[uskd_madagaskar_events]`

## Davranış

- Madagaskar Sirki Page ID 1311 REST içeriğini server-side okur.
- Yalnız `msc-live-card` kartlarını parse eder.
- Şehir, tarih, salon ve seans gösterir.
- Fiyat ve bilet bağlantısını bilinçli olarak göstermez.
- Kaynağı 5 dakika transient ile cache'ler.
- Kaynak veya parse hatasında güvenli fallback gösterir.

## Canlıya alma öncesi test

1. PHP 8.4 syntax.
2. USKD staging/draft üzerinde plugin activation.
3. Shortcode çıktısındaki kart sayısını Madagaskar /sehirler ile karşılaştır.
4. En az ilk, orta ve son kartta şehir/tarih/salon/seans birebir doğrula.
5. HTML'de fiyat veya WooCommerce price sınıfı olmadığını doğrula.
6. Madagaskar kaynağı geçici erişilemezken fallback davranışını doğrula.
7. Mevcut Page 392 içeriğini yedekli tut.

Canlı sayfayı shortcode'a geçirmek ayrı deployment adımıdır ve kullanıcı açık onayı gerektirir.

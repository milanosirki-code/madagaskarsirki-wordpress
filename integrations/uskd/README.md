# USKD WordPress Integration Staging

Bu klasör, `uskdernegi.org` için ayrı GitHub repository açılana kadar geçici kaynak alanıdır.

## Canlı durum — 1 Ekim 2026

USKD `Yaklaşan Etkinlikler` sayfası:
- Page ID: `392`
- URL: `https://uskdernegi.org/etkinlik-takvimi/`
- Kaynak: Madagaskar Sirki `Şehirler` sayfası, Page ID `1311`
- Kaynak REST: `https://madagaskarsirki.com/wp-json/wp/v2/pages/1311?_fields=content`
- Canlı istemci tarafı seçicileri:
  - `.msc-live-card`
  - `h3`
  - `.msc-live-date`
  - `.msc-live-venue`
  - `.msc-session-row span`

USKD tarafı fiyatı ve bilet satın alma bağlantısını kopyalamaz; yalnız şehir, tarih, salon ve seans bilgilerini gösterir.

## Neden yeni eklenti hazırlanıyor?

Mevcut sayfa tarayıcı tarafında başka domaine `fetch()` yapıp render edilmiş HTML'i DOM seçicileriyle ayrıştırıyor. Bu yaklaşım:
- CORS politikasına,
- kaynak HTML/CSS sınıf adlarına,
- tarayıcı tarafı JavaScript'in çalışmasına
bağımlıdır.

`uskd-madagaskar-events` eklentisi aynı bilgiyi sunucu tarafında okuyup shortcode olarak üretmek için hazırlanmıştır.

## Planlanan geçiş

1. Eklenti kodu GitHub'da syntax/review kontrolünden geçer.
2. USKD üzerinde staging/draft veya güvenli plugin yükleme yöntemiyle test edilir.
3. `[uskd_madagaskar_events]` çıktısı canlı sayfayla karşılaştırılır.
4. Şehir, tarih, salon ve seanslar birebir doğrulanır; fiyat görünmediği kontrol edilir.
5. Mevcut Page 392 içerik yedeği korunur.
6. Kullanıcı açıkça canlıya alma onayı verdiğinde sayfa shortcode'a geçirilir.
7. Sorun olursa Page 392 yedeği ile geri dönülür.

## Ayrı repo hedefi

Hedef repository adı: `milanosirki-code/uskdernegi-wordpress`.

Mevcut GitHub bağlantısı yeni repository oluşturma aksiyonu sunmadığı için bu klasör geçici staging alanıdır.

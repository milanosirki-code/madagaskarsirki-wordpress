# Madagaskar Checkout Customizations

Bu plugin Bilet Al, checkout, Tickera PDF ve Biletlerim ile ilgili kalıcı Code Snippets davranışlarını gated modüllere taşır.

Plugin aktive edildiğinde hiçbir modül otomatik yüklenmez.

Option:
`mdg_checkout_customizations_modules`

## Modüller

- ticket-type-short ← #5
- checkout-phone-required ← #8
- ticket-buy-cache-clear ← #56
- ticket-pdf-address-fix ← #57
- whatsapp-ticket-access-fix ← #59
- ticket-buy-page-v4 ← #51

## Bilinçli olarak dahil edilmeyen #58

`Madagaskar PayTR Bekleme Kilidi V1`:
- Bartın 25 Eylül 2026 ve Çubuk 26 Eylül 2026 için yazılmıştır.
- doğrudan MDG events/sessions tablolarına write yapar,
- ürün satış kilidi metasını değiştirir.

Bu nedenle genel checkout plugin'ine taşınmaz.

## #57 dönüşümü

Canlı snippet içindeki Bartın Tickera event #2124 tek seferlik düzeltme bloğu kalıcı plugin'den çıkarılmıştır.
Gelecekteki `_mdg_managed` Tickera etkinliklerinde `event_terms` başındaki fazla `Adres:` etiketini temizleyen genel hook korunmuştur.

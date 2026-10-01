# Checkout / Bilet Akışı Canlı Snippet Envanteri — 1 Ekim 2026

Bu dosya canlı Code Snippets kayıtları #5, #8, #51, #56, #57, #58 ve #59 için birebir kaynak yakalama ve risk envanteridir.

Secret taramasında gömülü JWT, token, parola, API key veya Basic Auth literal tespit edilmedi.

## Kaynaklar

| ID | Ad | SHA1 | Bytes | Risk |
|---:|---|---|---:|---|
| 5 | Madagaskar Bilet Tipi Kısaltma | f9badcb8c87bd9ea6f3fa3b10e2a7b534993e5ae | 852 | düşük |
| 8 | WooCommerce Telefon Zorunlu | 01fa35926b38be513b24d8613ac60c0b8c312b7c | 216 | düşük |
| 51 | MS Bilet Al Sayfası V4 | 26cb61ce9cc0cf9c78c3d49388ef2a83c1ff029e | 40445 | yüksek |
| 56 | Madagaskar Bilet Al Cache Temizleme V1 | 5650152f7b62bbe4c7eb8ade2ec3cbdc98312688 | 1312 | orta |
| 57 | Madagaskar Bilet PDF Adres Fix V1 | b411c13dc98b74cda584f276eae747413578f66d | 2697 | orta |
| 58 | Madagaskar PayTR Bekleme Kilidi V1 | 0d730cfb801385ca12abf1a4ff1f21c3e3d0acfa | 14870 | çok yüksek |
| 59 | MS WhatsApp ve Biletlerim Erişim Düzeltmesi V1 | ec0de42f8df391bd88448ef843ed189096b80a2a | 2242 | orta |

Ham kaynaklar `docs/live-captures/2026-10-01/` altında tutulur.

## Hook envanteri

### #5 Bilet Tipi Kısaltma
- filter: `tc_ticket_type_element`
- sipariş/ödeme write yok

### #8 Telefon Zorunlu
- filters:
  - `option_woocommerce_checkout_phone_field`
  - `default_option_woocommerce_checkout_phone_field`
- sipariş write yok
- checkout davranışını etkiler

### #51 Bilet Al Sayfası V4
- actions: `init`, `wp_head`
- filter: `body_class`
- shortcode: `[madagaskar_bilet_al]`
- doğrudan sipariş/ödeme write yok
- büyük UI/render akışı olduğu için canlı sayfa regresyon testi zorunlu

### #56 Bilet Al Cache Temizleme
- actions: `template_redirect`, `init`
- URI bazlı cache-control davranışı
- veri write yok

### #57 Bilet PDF Adres Fix
- actions:
  - `init`
  - `added_post_meta`
  - `updated_post_meta`
- Tickera event terms/adres alanını senkron tutar
- ödeme/checkout write yok; bilet PDF içeriğini etkileyebilir

### #58 PayTR Bekleme Kilidi
- actions:
  - `admin_post_ms_paytr_wait_lock_v1`
  - `admin_menu`
- doğrudan `$wpdb` write içerir
- satış kapanış/açılış durumuna dokunur
- diğer snippetlerle birlikte topluca migrate edilmez

### #59 WhatsApp ve Biletlerim Erişim
- action: `init`
- filters: `render_block`, `the_content`
- shortcode: `[ms_biletlerim]`
- veri write yok
- müşteri bilet erişim görünümünü etkiler

## Önerilen migration sırası

1. #5 — ticket-type-short
2. #8 — checkout-phone-required
3. #56 — ticket-buy-cache-clear
4. #57 — ticket-pdf-address-fix
5. #59 — whatsapp-ticket-access-fix
6. #51 — ticket-buy-page-v4
7. #58 — paytr-wait-lock — ayrı plugin/modül, ayrı regresyon planı

## Güvenlik kuralı

#58 hariç ilk altı kayıt için hedef plugin gate modeli kullanılabilir:
- plugin dosyası önce yüklenir,
- modül gate kapalıdır,
- eski snippet kapatılır,
- modül gate açılır,
- ilgili frontend/checkout/read-only smoke yapılır,
- hata halinde gate çıkar + eski snippet geri açılır.

#58 için canlı satış açıkken doğrudan write smoke testi yapılmaz.

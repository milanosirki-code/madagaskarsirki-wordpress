# Hazır snippet kaynağı — 5 Ekim 2026 (canlıda DEĞİL)

Görev: `docs/CODEX_TALIMAT_2026-10-05.md`, T2. Sorun kaydı: Issue #140.

## `ms-liste-onbellek-v1.php.txt`

- **Amaç:** Ana sayfa, `/sehirler/` ve `/bilet-al/` saate bağlı liste gösterir; bu sayfaların eski kopyadan sunulmasını önlemek ve bir kopyanın yaşını dışarıdan görünür kılmak.
- **Ne yapar:** Yalnızca bu üç sayfada `DONOTCACHEPAGE` tanımlar, `nocache_headers()` gönderir ve `<head>` içine `<meta name="ms-sayfa-uretim" content="2026-10-05T14:50:03+03:00">` yazar.
- **Hook'lar:** `template_redirect` (1), `wp_head` (1).
- **Bağımlılık:** Yok. Sayfa kısa adları `sehirler` ve `bilet-al` olmalı; ana sayfa `is_front_page()` ile bulunur.
- **Dokunmadıkları:** Veri, ürün, sipariş, bilet, ödeme akışı, etkinlik sayfaları, yönetim ekranları, `/sehirler/` altındaki şehir sayfaları.
- **Doğrulanmadı:** Platformun kenar önbelleğinin bu başlıklara uyup uymadığı. 3 Ekim'de `nocache_headers()` gönderen Kırıkkale duyurusu değiştikten sonra anonim okumalar bir süre eski metni döndürmüştü. Etkinleştirdikten sonra her adresi iki dakika arayla iki kez oku; `ms-sayfa-uretim` değeri ilerlemiyorsa çözüm platform ayarında ya da farklı bir başlıktadır.
- **Maliyet:** Bu üç sayfa her istekte yeniden üretilir. Yanıt süresi belirgin artarsa önbelleği tamamen kapatmak yerine kısa süreli (en çok 5 dakika) tutmak tercih edilmeli.
- **Geri alma:** Snippet'i devre dışı bırakmak.

Test: `php tests/list-cache/regression.php` (6 kontrol). Gerçek WordPress üzerinde çalıştırılmadı.

# Yüz Boyama POS ödeme + blog kayıtları — 24–29 Ekim 2026

Durum: **Canlıda (hotfix olarak doğrudan yüklendi).** Bu kayıt, canlı değişikliği repository'ye geri işlemek için hazırlandı.

GitHub: PR #299 — https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/299 (dal `claude/yuzboyama-pos-2026-10-29`)

## 1. Yüz Boyama — POS benzeri hızlı ödeme (29.10.2026)

### Amaç
Etkinlik alanında yüz boyama ücretinin POS cihazı gibi alınması: kişi sayısı → kart ekranı → işlem kodu. Müşteriden fatura/iletişim bilgisi istenmez.

### Yapılanlar
| Adım | Ayrıntı |
|---|---|
| Sayfa tasarımı | `/yuzboyama/` (sayfa ID 9013). Mobil öncelikli kart tasarımı, büyük −/+ düğmeleri, toplam kutusu, "3 adımda yüz boyama", 3 soruluk SSS, WhatsApp yardım düğmesi. Stiller sayfadaki ilk Özel HTML bloğunda, `body.page-id-9013` ile sınırlı. |
| Yeni eklenti | `wp-content/plugins/madagaskar-yuzboyama-pos/` v1.0.0 — WordPress.com eklenti yükleme ile canlıya yüklendi ve etkinleştirildi. |
| Sayfa kısa kodu | `[mdg_yuzboyama]` → `[mdg_yuzboyama_pos]` |

### Eklenti nasıl çalışır
- Kısa kod `[mdg_yuzboyama_pos]`: ürün **9012** (Yüz Boyama, 100 TL) için kişi sayısı formu; 1–50 kişi.
- `template_redirect` (öncelik 1): POST `mdg_ybpos_buy` gelince `wc_create_order` ile **pending** sipariş oluşturur, PayTR ağ geçidini seçer, sabit fatura bilgilerini yazar ve `get_checkout_payment_url()` (order-pay) adresine yönlendirir. `/odeme/` blok checkout'una girmez.
- Sabit fatura bilgileri: Ad "Yüz Boyama Müşterisi", e-posta `madagaskarsirkiturkiye@gmail.com`, telefon 0312 911 37 10, adres "Etkinlik Alanı - Yüz Boyama", Ankara / TR.
- Sipariş meta: `_mdg_yb_pos = yes`, `_mdg_yb_pos_kod = YB-####`; sipariş notuna kişi sayısı ve kod yazılır.
- `woocommerce_order_email_verification_required`: bu siparişlerde misafir e-posta doğrulaması kapalı.
- Order-pay sayfasında PayTR otomatik seçilir (küçük JS).
- `woocommerce_before_thankyou`: ödendiyse yeşil "ÖDEME ALINDI" kartı ve büyük kod; ödenmediyse "onay bekleniyor".
- Yönetim panelinde siparişte "Yüz boyama kodu" gösterilir.
- Kötüye kullanım sınırı: IP başına 10 dakikada 15 deneme (transient).

### Dokunulmayanlar
- `/odeme/` blok checkout ve bilet akışı değişmedi.
- Eski eklenti **Madagaskar Yüz Boyama 1.0.0** (`[mdg_yuzboyama]`) etkin ve değiştirilmedi. Kaynağı bu repoda yok; canlıdan alınıp eklenmeli.

### Doğrulama
- `php -l` temiz.
- Canlı sayfa render: form, "Kartla öde" düğmesi, adımlar ve SSS görünüyor; ham kısa kod yok (WebFetch, 29.10.2026 10:20).
- **Yapılmadı:** "Kartla öde" → order-pay → PayTR iframe → teşekkür sayfası uçtan uca testi. Gerçek kartla 100 TL'lik bir deneme ödemesiyle doğrulanmalı.

### Smoke test planı
1. Telefondan `/yuzboyama/` aç, 2 kişi seç, toplam 200 TL görünmeli.
2. "Kartla öde" → order-pay sayfası açılmalı, PayTR seçili olmalı.
3. Sözleşme onayı + "Siparişi öde" → PayTR kart ekranı.
4. Ödeme sonrası teşekkür sayfasında yeşil kart ve `YB-####` kodu.
5. Yönetim panelinde sipariş: durum işleniyor/tamamlandı, kod ve not görünmeli.
6. `/odeme/` üzerinden normal bir bilet sepetinin değişmediğini kontrol et.

### Geri alma
1. Sayfa 9013'teki kısa kod bloğunu `[mdg_yuzboyama]` yap.
2. "Madagaskar Yüz Boyama POS" eklentisini devre dışı bırak.

## 2. Blog çalışmaları (24.10.2026)

- **Çankırı blog yazısı yayınlandı:** `https://madagaskarsirki.com/madagaskar-blog/cankiri-cocuk-etkinlikleri-28-ekim-2026/` — 28 Ekim 2026, Hasan Fehmi Gökşen Spor Salonu, 17.00/19.00. Kategoriler: aile rehberi + Türkiye Turnesi. Jetpack SEO başlık/açıklama, seans başına Event + FAQPage şeması.
- Aile paketi (2+2, 1.100 TL) online satışta olduğu doğrulandı.
- **Drive "Blog Yönetimi" klasörü** oluşturuldu:
  - `01 - Blog Kuralları ve Hedefler` (şehir yazısı şablonu, sabit bilgiler, SEO, Milano kuralları, yayın akışı)
  - `02 - Yayınlanan Blog Yazıları` (48 yazı: Madagaskar 36, Milano 12)
  - `03 - Blog SEO ve İçerik Taslakları` (eski "01 - Blog SEO ve İçerik Taslakları" klasörü taşındı ve yeniden adlandırıldı)
- Açık iş: Milano İzmir yazıları (Menemen, Tire, Ödemiş, İzmir hub) satış kapandığı halde yayında — gözden geçirilmeli.

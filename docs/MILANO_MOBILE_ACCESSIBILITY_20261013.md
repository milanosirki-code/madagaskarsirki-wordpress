# Milano Sirki — Mobil erişilebilirlik takip notu

13 Ekim 2026 | Site: https://milanosirki.com

Önceki turdaki onarım sonucu 10 yayındaki sayfada WordPress kurtarma uyarıları kaldırılmış, içerikler `core/html` bloklarıyla korunmuştu.

Bu turda WordPress.com Site Düzenleyici → Global Styles kullanıcı override'ına özel CSS olarak mobil navigasyon iyileştirmeleri uygulandı:
- Menü bağlantıları, aç/kapat ikonları için min. 44 × 44 CSS piksel hedefler
- Açık mobil menünün lacivert arka plan (#091746) / beyaz metin (#fff) kontrastı
- Klavye odak çerçevesi (altın #F5C04A)
- Site başlığı, logo ve odaklanmış içeriğe atla bağlantılarında en az 44 px hedef yüksekliği

CSS kaynağı: [assets/milano-mobile-accessibility-20261013.css](../assets/milano-mobile-accessibility-20261013.css).

Canlı global CSS kaydı WordPress.com global-styles.get ile doğrulandı. HTML head'de CSS metni görüldü. Site logo/menu/Instagram/WhatsApp bağlantıları HTML'de mevcut.

## Mobil PageSpeed/Lighthouse

İlk ölçüm (önceki onarım oturumu): Performans 82 / Erişilebilirlik 91 / En iyi uygulamalar 100 / SEO 100. Kontrast ve dokunmatik hedef uyarıları mevcuttu.

Mobil nav kontrast/44px iyileştirmesi sonrası: Performans 82 / Erişilebilirlik 95 / En iyi uygulamalar 100 / SEO 100. **Kontrast uyarısı kalktı.**

Site başlığı/skip-link hit area düzenlemesi sonrası yapılan son bağımsız mobil lab koşusunda: Performans 98 / Erişilebilirlik 95 / En iyi uygulamalar 100 / SEO 100. LCP 2.3 sn, CLS 0.001, TBT 0 ms, FCP 1.0 sn, Speed Index 2.2 sn. Bu performans puanı bağımsız lab koşuları arasında oynar; gerçek kullanıcı (CrUX) verisi bulunmuyor.

**Kalan sorun:** Lighthouse'ta [target-size] uyarısı devam ediyor. WPVibe denetimi öğe bazında seçicileri sağlamadığı için uyarının tam DOM düğümü doğrulanamadı; körlemesine kapsamlı CSS müdahalesi yapılmadı. Hedef bazlı ek test önerilir.

## Emniyet ve geri dönüş

Öncesi global styles, üst/alt şablon ve ana sayfa içeriğinin yedeği: [a11y-prechange.json](../backups/milano/2026-10-13-a11y-prechange.json)
Bu turda Milano dışındaki site ve Madagaskar satış kapasitesi/ödeme alt sistemleri değiştirilmedi. Geri alım gerektiğinde önceki yedekte bulunan `global_styles.styles.css` değeri kullanılabilir (başlangıçta CSS yoktu). Sayfa içeriğine ayrıca müdahale yapılmadı.

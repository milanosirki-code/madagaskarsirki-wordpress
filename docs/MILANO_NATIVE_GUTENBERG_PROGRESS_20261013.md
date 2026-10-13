# Milano Sirki — Yerel Gutenberg bloklarına aşamalı dönüşüm

Tarih: 13 Ekim 2026. Alan adı: https://milanosirki.com

## Gerçekleştirilen işler

1. **Ana Sayfa /, sayfa #5:** Alt bölüm “Şehrinizdeki gösteriyi keşfedin”, `core/html` yerine `core/group` + yerel `core/heading` ve `core/paragraph` bloklarıyla yeniden yazıldı. `#gosteri-takvimi` ankrajı, tüm metin, marka renkleri ve padding korundu. Sıradaki canlı kontrol: başlık ve bölüm göründü. `wp/v2/pages/5` tekrar kayıt kontrolü WordPress blok doğrulama uyarısı göstermedi. Jetpack Instagram galerisi altı gerçek gönderiyle çalışmaya devam etti.
2. **Gösterilerimiz /gosterilerimiz/, sayfa #21:** “Gösteri Hakkında” bilgi bölümü artık Gutenberg grup + başlık + paragraf. Alttaki “Şehirler ve Gösteriler” / “WhatsApp ile Bilgi Al” bölümü de Gutenberg grup ve düğme bloklarına dönüştürüldü.
3. **Sıkça Sorulan Sorular /sikca-sorulan-sorular/, sayfa #22:** Aynı alt yönlendirme bölümü Gutenberg grup ve düğmelerine dönüştürüldü.

Dört (4) üst seviye `core/html` bloğu yerel `core/group` bloklarına dönüştürüldü. Yerinde değişiklikler mevcut sayfaların URL, ID ve `publish` durumlarını korudu. WordPress.com `page-sections.replace` yanıtında `_content_warnings: []` alındı; son `page-sections.list` kontrolleri ilgili bölümlerin `core/group` ve `has_inner_blocks:true` olduğunu doğruladı. Son `pages.get context=view` sonuçlarında iki alt sayfa da yayında ve HTML olarak metin/düğmeleri gösteriyor.

### Emniyet ve geri dönüş

Her düzenlenen bölümün önceki HTML kaydı:
- [Ana Sayfa CTA](../backups/milano/2026-10-13-home-cta-before-native-gutenberg.html)
- [Gösterilerimiz bilgi bölümü](../backups/milano/2026-10-13-gosteri-hakkinda-before-gutenberg.html)
- [Gösterilerimiz CTA](../backups/milano/2026-10-13-page-21-cta-before-gutenberg.html)
- [SSS CTA](../backups/milano/2026-10-13-page-22-cta-before-gutenberg.html)

Instagram Jetpack galerisi ve 6 fotoğrafı, Milano–Madagaskar ortak bilet sistemi, ödeme, fiyat, kontenjan ve QR değişmedi. WordPress bağlantısı WPVibe'da günlük kullanım limitine eriştiği için kalan işlemlerde WordPress.com edit ve read API üzerinden devam edildi; tüm sayfalarda yeni bir Lighthouse/cihaz testi bu aşamada yürütülmedi.

### Kalan işler

- Ana sayfanın büyük tasarım bölümleri (hero, performans, kartlar, Instagram tanıtım) ve diğer sayfaların daha karmaşık özel HTML blokları aşamalı olarak yerel Gutenberg bloklarına aktarılabilir. Tek seferde toplu dönüşümden kaçınılmalı.
- Mobil Lighthouse'ta daha önce raporlanan dokunma hedefi `target-size` uyarısı sürüyordu; öğe bazında yeni denetim ve güvenli CSS düzeltmesi yapılmadı.
- Instagram feed bağlantısı başarıyla aktif ve 6 gönderi canlı; ek yetkilendirme gerekmez.

# Milano Sirki — Gutenberg "Kurtarma" Uyarıları Onarım Kaydı
Tarih: 13 Ekim 2026
Site: https://milanosirki.com
Kapsam: YALNIZCA Milano WordPress sayfa içerikleri; Madagaskar ve ortak satış sistemine müdahale yok.

## Sorun
WordPress blok editörü, elle oluşturulmuş karmaşık Gutenberg serialized blok işaretlemeleri ve HTML uyuşmazlıkları nedeniyle "Beklenmeyen/geçersiz içerik — Kurtarmayı dene" mesajları gösteriyordu. Ana sayfa HTML kaynağında 11 iç içe p etiketi (p > p) bulundu. Kontrol sırasında img src içindeki escape edilmemiş & karakteri ayrıca doğrulama uyarısı oluşturdu.

## Güvenli geri dönüş
İşe başlamadan canlı yayımlanmış 10 sayfanın HAM Gutenberg kaynakları ve değişiklik zamanları JSON olarak yedeklendi:
backups/milano/2026-10-13-pages-before-block-repair.json
Dal: fix/milano-block-recovery-20261013
Ayrıca WordPress post revisions korunur; silme yapılmadı.

## Yapılan düzeltme
10 mevcut sayfa, aynı post ID, URL ve yayın durumu korunarak güncellendi. Tasarımlı karmaşık grup bölümleri WordPress'in kendi `core/html` (Özel HTML) bloklarına dönüştürüldü. Dış DOM, sınıflar, inline stil ve metinler büyük ölçüde korundu. Şehirler sayfasındaki `[milano_ortak_etkinlikler]` shortcode dinamik Gutenberg kısa kod bloğu olarak korundu; ana sayfada `jetpack/instagram-gallery` ayrı dinamik blok olarak bırakıldı (Instagram OAuth ayrı engel). Ana sayfada 11 çift p etiketi tek p olarak normalleştirildi. img/src ve a/href içindeki ham & karakterleri HTML-escaped yapıldı.

Onarılan sayfalar:
- #5 / — Ana Sayfa
- #6 /sehirler/ — Şehirler ve Gösteriler
- #14 /iletisim/ — İletişim
- #23 /kurumsal-organizasyonlar/ — Kurumsal Organizasyonlar
- #22 /sikca-sorulan-sorular/ — Sıkça Sorulan Sorular
- #21 /gosterilerimiz/ — Gösterilerimiz
- #1 /hakkinda/ — Hakkımızda
- #35 /cerez-politikasi/ — Çerez Politikası
- #34 /gizlilik-politikasi/ — Gizlilik Politikası
- #33 /kvkk-aydinlatma-metni/ — KVKK Aydınlatma Metni

## Doğrulama
Her sayfanın kayıt sonucu publish durumundadır ve WPVibe Block validation sonucunda uyarı çıkmamıştır. Ana sayfa için doğrulama önce ham & karakterini yakalamış, düzeltme sonrası uyarı kalmamıştır.
Canlı HTML kontrolü: on sayfanın tümü main içeriği ve başlıklarıyla render oldu. Ana sayfada hero/logo, sahne, kartlar, Instagram ve gösteri takvimi göründü. Şehirler sayfası Yalova etkinliği ve harita/seans bilgisini gösteriyor, shortcode literal metin olarak görünmüyor. Mevcut header/footer de canlı HTML'de render oluyor.

## Düzenleme / Limitasyonlar
Karmaşık tasarım bölümleri artık WordPress editöründe Özel HTML bloklarıdır. Bu, otomatik kurtarma uyarısını durdurur ve HTML koduyla düzenlenebilir; ancak tek tek alt öğelerin sürükle-bırak Gutenberg blokları halinde düzenlenmesi için ileride yeniden blok dizaynı gerekir. Varsayılan başlık ve şablonlar korunmuştur.

Instagram canlı galeri halen @milanosirkiturkiye WordPress.com OAuth connection #32263575 status=broken nedeniyle gerçek fotoğraf göstermemektedir; yeniden yetkilendirme kullanıcı tarafından yapılmalıdır. Profil bağlantısı görünüyor. Bilet satışı için yeni ödeme, stok veya QR işlevi açılmadı.

## Güvenlik sınırı
Madagaskar web sitesi; PayTR, WooCommerce, MMC, Tickera, QR, ortak etkinlik/kontenjan ve Kommo altyapıları değiştirilmedi.

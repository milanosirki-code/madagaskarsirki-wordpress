# MDG Finans Yönetici Pilotu 0.1.1 (salt okunur)

Bu eklenti Madagaskar Sirki'nin V5/MMC finans verilerini değiştirmeden gösteren ayrı bir WordPress yönetici panelidir. Eklenen menü: Madagaskar > Finans Pilot (Önizleme).

## v0.1.1 düzeltmeleri (17.10.2026)
- Eski V5 menü filtrelemesinde yalnız pilot alt menüsünün gizlenmesini önler. Mevcut diğer menülere dokunmaz.
- WooCommerce ve Kırıkkale #15 Biletinial iade anaparası V5 ile aynı biçimde gider hesabından dışlanır.
- Gelir ve maliyet kaydı olmayan bir etkinlikte 'Veri yok' gösterir. Sabit/ortak payı olan fakat satışı kaydedilmeyen etkinlikleri yanlışlıkla toplamdan çıkarmaz.
- Manuel tahsilat bekleyen tutarı ayrı bilgi sütununda gösterir; gerçekleşmiş gelire katmaz.
- Sipariş, PayTR, Tickera, WooCommerce, MMC/V5 finans defteri, belge ve veri tabanı üzerinde yazma yapmaz.

## Dikkat
Kayıtlı gelir, gerçekleşmiş banka tahsilatı ve kesin muhasebe kârı birbirinden farklıdır. Ortak giderler, vergi farklılıkları ve eksik eşleşmeler kâr sonuçlarını etkileyebilir. Marka filtresi yalnız doğrulanmış MMC köprüsü üzerinden çalışır. 'Tüm markalar' seçeneğinde ortak gider kutusu işletme geneline aittir; ayrıca program toplamına eklenmez.

## Doğrulama
PHP sözdizimi, JS sözdizimi, refund istisnası testleri. Staging ve yetkili wp-admin görünümü için canlı smoke test gereklidir. Staging onayı olmadan canlı sürüm yükseltmeyin.

Rollback: Etkin v0.1.0 ZIP'i geri yükleyin. Finans kayıtlarına müdahale edilmez.

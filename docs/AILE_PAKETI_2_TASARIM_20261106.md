# Aile Paketi 2 — tasarım ve kazanç gösterimi

Tarih: 2026-11-06
Durum: Canlı, native eklenti 0.1.2 aktif.
Kod PR: https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/314
Kod commit: ec56569b4a5fb25348337e518e231974137fef22
Merge: 39a45ec087c806f3469d9149895c5cfb722925fe
ZIP SHA256: c56cdd7610952bbc8d37e9c5665df9d193aaaa44409de1c1fad07d28ae3b6765

## Sonuç

Başlık Aile Paketi 2 olarak sadeleştirildi. 2 yetişkin + 4 çocuk ve 6 ayrı giriş QR alt satırda. Kazanç rozeti ve Ayrı ayrı → Paket karşılaştırması eklendi. İki aile kartı art arda; mobil boşluk ve satır düzeni iyileştirildi.

Kazanç, sayfadaki güncel yetişkin ve çocuk fiyatlarından hesaplanır: 2 × yetişkin + 4 × çocuk − paket. İzmir: 585×2+290×4=2.330 TL; paket 1.500 TL; kazanç 830 TL. 490/245 tarifeli program: 1.960→1.250 TL; kazanç 710 TL. Fiyat verisi eksik veya fark pozitif değilse rozet gizlenir.

## Doğrulama

14 GitHub workflow başarılı; Family Package 2 ve Changed PHP Syntax dahil. Yerel DOM testleri ve JS syntax başarılı. 830, 710, 750 TL senaryoları ve pozitif olmayan kazanç kontrolü test edildi.

Gerçek canlı varlıklarla İzmir ve Tire sayfaları 320, 390, 1440 px genişlikte doğrulandı: yatay taşma yok, rozet görünür, kart sırası 2+2 / 2+4 / tekil biletler, bir paket 6 kişi ve toplam 1.500/1.250 TL. Gerçek sipariş veya ödeme oluşturulmadı. Canlı fiyat ayarı öncesi/sonrası birebir aynı (7 etkinlik).

Sepet indirimi, ödeme, kapasite, QR ve Kommo kodu değiştirilmedi. Geri dönüş: yalnız native madagaskar-aile-paketi-24 eklentisinin önceki 0.1.1 ZIP'ini yükle; etkinlik/fiyat ayarlarını değiştirme.

# Keşan Aile Paketi 2 aktivasyonu — 2026-11-06

Kullanıcı talebiyle Keşan / 15 Kasım 2026 için MDG39 paket ayarına 1250 TL eklendi. Mevcut diğer 7 etkinlik fiyatı ve eski 2+2 ayarı korunmuştur. Kod değişmedi; native eklenti 0.1.2.

Neden: MDG39 onsale iken MMC program29 venue_confirmed idi; fail-closed paket kapısı teklifi gizliyordu. Köprü 4/4 kimlik, salon/tarih ve 18:00/19:30 seans eşleşmeleri doğrulandı. event-sales-readiness(event_id=29) ready=true, issues=[] sonucunu verdi. Program Service'in madagaskar/program-set-status yeteneğiyle program29 sales_open durumuna alındı; işlem günlüğüne gerekçe eklendi. Okuma ile sales_open doğrulandı. Koruma kodu gevşetilmedi.

Canlı anonim Chromium mobil kontrolü: Aile Paketi 2 görünür; 2 yetişkin + 4 çocuk / 6 giriş QR. 490×2+245×4=1960 TL, paket1250 TL, kazanç710 TL. Eski2+2 1100 TL korunuyor. Bir paket6 kişi; JS hatası ve yatay taşma yok. Sepette gerçek çocuk4/yetişkin2, indirim710 ve toplam1250; ödeme ekranında1250 ve hata yok. Sipariş gönderilmedi, para çekilmedi. Test anonim sepetinin boşalması doğrulanamadı (test_cart_emptied=false); oturum kapatıldı.

Sayfa: https://madagaskarsirki.com/etkinlik/madagaskar-sirki-kesan-15-kasim-2026/

Geri dönüş: yalnız mdg_family_package_24_v1.events.39 fiyatını kaldırarak yeni paketi kapat. Program durumunu otomatik geriye alma; mevcut satış ve yaşam döngüsünü sahibiyle değerlendir.

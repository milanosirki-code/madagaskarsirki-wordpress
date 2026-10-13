# Milano Sirki — İlk Etkinlik Kaydı: Yalova, 22 Ekim 2026

**Durum:** Duyuru sitesi yayında, merkezî MMC/MDG canlı program ve bilet entegrasyonu henüz oluşturulmadı. **Satış kapalı.**

## Kullanıcının 13 Ekim 2026 tarihinde paylaştığı Milano Sirki afişindeki kesin bilgiler

| Alan | Değer |
|---|---|
| Satış / organizasyon markası | **Milano Sirki** (Madagaskar değil) |
| Etkinlik ili | Yalova |
| İlçe | Merkez (MDG salon kataloğu eşleşme adayı) |
| Salon (afişte) | **90. Yıl Kapalı Spor Salonu** |
| Adres (afişte) | Bahçelievler Mahallesi, Turhan Koçal Caddesi No:13, Yalova |
| Tarih | **2026-10-22**, Perşembe |
| 1. seans | **18:00**, Europe/Istanbul |
| 2. seans | **19:30**, Europe/Istanbul |
| Gösteri | Uluslararası Milano Sirki — Etiyopya ve Tanzanya ekibi |
| Tanıtım web adresi | https://milanosirki.com |
| İletişim | 0536 358 33 81 / https://wa.me/905363583381 |
| Bilet fiyatı | **Henüz doğrulanmadı**, Madagaskar tarifesi otomatik uygulanmaz |
| Satış kapasitesi | **Henüz doğrulanmadı**; salon kayıtlarındaki varsayılan sayı bilet kontenjanı değildir |
| Oturma düzeni / numaralı koltuk | **Henüz doğrulanmadı** |
| Satış kanalı | Sadece **milanosirki.com**, mevcut Madagaskar etkinliklerinde satış yok |

## 13 Ekim 2026 canlı salt-okunur ön kontroller

- Madagaskar MMC tablosunda Yalova veya 22 Ekim eşleşen program **yok** (0 sonuç).
- Madagaskar MDG etkinlik tablosunda Yalova/90. Yıl eşleşmesi **yok** (0 sonuç).
- 22 Ekim tarihli MDG seansı **yok** (0 sonuç).
- Ana MDG salon kataloğu: **ID 181**, `90. Yıl Spor Salonu`, Yalova / Merkez, etkin. `address` boş; **90. Yıl Kapalı Spor Salonu ile eşleşme adayı**, adres/salon teyidi gereklidir. `default_capacity=500` sadece master salon varsayılanıdır, güvenli 22 Ekim seans kontenjanı olarak kabul edilmedi.
- Canlı MMC `create_program()` markayı hâlen otomatik `Madagaskar Sirki` yapıyor. `madagaskar/program-create` API şemasında `brand` yok. Bu iki canlı kod güncellenmeden `create_program` çağrılırsa yanlış marka yaratır; **çalıştırmayın**.
- GitHub PR #235 taslağı MMC marka seçimini `Milano Sirki` şeklinde zorunlu yapan servis, admin ve AI Ability değişikliklerini içeriyor. PR merge/deploy edilmedi.
- **Milano WordPress kamuya açık `/sehirler/` sayfasına** 13 Ekim'de kırmızı-altın-lacivert mobil uyumlu Yalova seans duyuru kartı eklendi; gerçek HTML'de 18.00, 19.30, 90. Yıl Kapalı Spor Salonu, adres ve “satış kapalı” yazısı doğrulandı. Kartın id classı `milano-yalova-20261022`. Ödeme veya QR tetiklenmez.

## Canlı merkezî MMC programı açmak için minimum işlemler

1. **Marka uyumlu MMC yayınını** staging ortamında test et; WordPress/PHP/yedek/rollback planını ve eski API tüketicilerinin yeni `brand` zorunluluğuna uyumunu doğrula; onaylı paket halinde servisi/admin/API'yi canlıya al.
2. Yönetim Merkezi `create_program` içinden `brand: Milano Sirki, province_name: Yalova, district_name: Merkez, planned_date: 2026-10-22` ile **hazırlık** kaydı oluştur; doğrudan SQL INSERT veya Madagaskar marka defaults kullanma. Program ID'sini ve kodunu kayıt altına al.
3. Salonu ID 181 ile **yalnız resmi adres eşleşmesi doğrulandıktan sonra** programa bağla. `venue_name` / poster farklılığını kayıtla.
4. MMC etkinlik taslağına tam iki seans **18:00 / 19:30 (Europe/Istanbul)** oluştur; `default_capacity=500` kullanma, işletmenin kesinleştirdiği gerçek satılabilir kapasiteyi gir.
5. Milano için ayrıca doğrulanmış yetişkin, çocuk, aile paketi fiyatını ve koltuk düzenini belirle. Madagaskar'ın canlı fiyatlarını **otomatik kopyalama**.
6. MDG taslak etkinlik, tek aktif MMC–MDG köprü ve Milano `event_owner_brand` kontrolü; önce **yalnız duyuru akışı** canary, sonra PayTR merchant/domain/3DS/callback, atomik son koltuk hold, Woo, Tickera QR/PDF, aile 2+2 dört giriş ve iade testleri.
7. Kullanıcının açık satış açma onayı ve başarılı üretim öncesi kontroller olmadan hiçbir kanalda **satışa açma**.

## Uyarı

Afiş şehir/salon/seans için kaynak kabul edilmiştir; **afişin kendisi salon işletmecisi teyidi, sözleşme, satılabilir kapasite veya fiyat doğrulaması değildir**. Doğrudan checkout, QR ve Woo stok mutasyonlarını engelleyin.

Güncel çalışma bağlantıları:

- Milano canlı şehirler: https://milanosirki.com/sehirler/
- Taslak entegrasyon PR: https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/235
- Çalışma günlüğü: https://docs.google.com/document/d/1GSV-_niF39NTkB45--sqodofxDVH25S7XeKxefzUa4Q/edit

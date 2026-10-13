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
| Yetişkin bileti | **490 TL** (kullanıcı teyidi, 13.10.2026) |
| Çocuk bileti | **245 TL** (kullanıcı teyidi, 13.10.2026) |
| Aile paketi (2+2) | **1.100 TL**, iki yetişkin + iki çocuk = dört ayrı fiziksel giriş hakkı |
| Para birimi | TRY (Türk lirası) |
| 18.00 seans satılabilir kapasitesi | **500 kişi** (kullanıcı teyidi) |
| 19.30 seans satılabilir kapasitesi | **500 kişi** (kullanıcı teyidi) |
| İki seans toplam brüt kapasite | **1.000 ayrı seans giriş hakkı**; tek gösteride aynı bileti iki kez saymayın |
| Salon Google Haritalar bağlantısı | https://maps.app.goo.gl/RnN2pZFTMoMD2W237?g_st=ac (kullanıcının gönderdiği URL; hedefin bağımsız doğrulaması yapılmadı) |
| Oturma düzeni / numaralı koltuk | **Henüz doğrulanmadı** |
| Satış kanalı | Sadece **milanosirki.com**, mevcut Madagaskar etkinliklerinde satış yok |

## 13 Ekim 2026 canlı salt-okunur ön kontroller

- Madagaskar MMC tablosunda Yalova veya 22 Ekim eşleşen program **yok** (0 sonuç).
- Madagaskar MDG etkinlik tablosunda Yalova/90. Yıl eşleşmesi **yok** (0 sonuç).
- 22 Ekim tarihli MDG seansı **yok** (0 sonuç).
- Ana MDG salon kataloğu: **ID 181**, `90. Yıl Spor Salonu`, Yalova / Merkez, etkin. `address` boş; **90. Yıl Kapalı Spor Salonu ile eşleşme adayı**, adres/salon teyidi gereklidir. `default_capacity=500` sadece master salon varsayılanıdır, güvenli 22 Ekim seans kontenjanı olarak kabul edilmedi.
- Canlı MMC `create_program()` markayı hâlen otomatik `Madagaskar Sirki` yapıyor. `madagaskar/program-create` API şemasında `brand` yok. Bu iki canlı kod güncellenmeden `create_program` çağrılırsa yanlış marka yaratır; **çalıştırmayın**.
- GitHub PR #235 taslağı MMC marka seçimini `Milano Sirki` şeklinde zorunlu yapan servis, admin ve AI Ability değişikliklerini içeriyor. PR merge/deploy edilmedi.
- **Milano WordPress kamuya açık `/sehirler/` sayfasına** 13 Ekim'de kırmızı-altın-lacivert mobil uyumlu Yalova seans duyuru kartı eklendi. Sonradan fiyatlar (490/245/1100 TL), 500 kişi/seans ve kullanıcıdan gelen Google Haritalar URL'si eklenerek gerçek public HTML'de doğrulandı. Kartın classı `milano-yalova-20261022` ve fiyat alt bölümü `milano-yalova-tickets-v1`; satış kapalıdır, ödeme veya QR tetiklenmez.

## Canlı merkezî MMC programı açmak için minimum işlemler

1. **Marka uyumlu MMC yayınını** staging ortamında test et; WordPress/PHP/yedek/rollback planını ve eski API tüketicilerinin yeni `brand` zorunluluğuna uyumunu doğrula; onaylı paket halinde servisi/admin/API'yi canlıya al.
2. Yönetim Merkezi `create_program` içinden `brand: Milano Sirki, province_name: Yalova, district_name: Merkez, planned_date: 2026-10-22` ile **hazırlık** kaydı oluştur; doğrudan SQL INSERT veya Madagaskar marka defaults kullanma. Program ID'sini ve kodunu kayıt altına al.
3. Salonu ID 181 ile **yalnız resmi adres eşleşmesi doğrulandıktan sonra** programa bağla. `venue_name` / poster farklılığını kayıtla.
4. MMC etkinlik taslağına tam iki seans **18:00 / 19:30 (Europe/Istanbul)** oluştur; **her seans için kullanıcıca onaylı 500 fiziksel kişi kapasitesini** gir. Bu değer master salondaki tesadüfî varsayılandan değil, 13 Ekim'de verilen açık kullanıcı teyidinden gelir.
5. Milano Yalova bilet tarifesi **yetişkin 490 TL, çocuk 245 TL, aile paketi 1.100 TL (2 yetişkin + 2 çocuk)** olarak kullanıcıca onaylandı. **Aile paketi tek ticari satır olsa da dört fiziksel bilet, dört kapasite birimi ve dört QR giriş hakkıdır**; toplam seans kapasitesi 500'ü aşamaz. Oturma düzeni/numaralı koltuk, yaş sınırı ve varsa servis ücretleri ayrıca netleştirilmeden canlıya açılmamalı.
6. MDG taslak etkinlik, tek aktif MMC–MDG köprü ve Milano `event_owner_brand` kontrolü; önce **yalnız duyuru akışı** canary, sonra PayTR merchant/domain/3DS/callback, atomik son koltuk hold, Woo, Tickera QR/PDF, aile 2+2 dört giriş ve iade testleri.
7. Kullanıcının açık satış açma onayı ve başarılı üretim öncesi kontroller olmadan hiçbir kanalda **satışa açma**.

## Uyarı

Afiş şehir/salon/seans için kaynak kabul edilmiştir; 13 Ekim'de ayrıca **kullanıcı 500 kişi/seans ve 490 / 245 / 1.100 TL fiyatlarını açıkça onaylamıştır**. Harita bağlantısı kullanıcının verdiği linktir; salon işletmecisi sözleşmesi/oturma planı ayrıca doğrulanmalıdır. Doğrudan checkout, QR ve Woo stok mutasyonlarını engelleyin.

Güncel çalışma bağlantıları:

- Milano canlı şehirler: https://milanosirki.com/sehirler/
- Taslak entegrasyon PR: https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/235
- Çalışma günlüğü: https://docs.google.com/document/d/1GSV-_niF39NTkB45--sqodofxDVH25S7XeKxefzUa4Q/edit

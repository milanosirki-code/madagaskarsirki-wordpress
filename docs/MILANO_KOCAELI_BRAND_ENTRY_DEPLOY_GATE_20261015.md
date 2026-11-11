# Milano Kocaeli 21–24 Ekim — marka güvenli program açma kapısı

15 Ekim 2026. **Hazırlık kaydı; satış açılışı değildir.** Yalnızca marka seçimini mevcut MMC ve AI program yaratma girişlerine ekleyen hedefli PR; Madagaskar canlı eklenti koduna henüz yüklenmedi.

## Kullanıcı onayı ve canlıda yapılanlar
- Afişlerdeki ad/adresler ve **her seans 500 kişi** onaylandı.
- Milano fiyatları: 3–12 yaş 245 TL, 13+ 490 TL, 2 yetişkin + 2 çocuk aile 1.100 TL; aile paketi **4 kişilik giriş ve dört kapasite birimi**. 0–2 yaş ücretsiz. Hizmet bedeli varsa toplamı ödeme öncesi gösterilecek.
- Merkezi MDG Salonlar ana kaynağına üç **yeni**, diğer salonlara dokunmadan kayıt açıldı:
  - `#196` Atatürk Kapalı Spor Salonu, 4 Temmuz Mah. 115 Nolu Sokak, Karamürsel; 21.10 17.30/19.30.
  - `#197` Eray Şamdan Kapalı Spor Salonu, Bağlarbaşı Mah. Özbay Sk. No 67, Darıca; 23.10 17.30/19.30.
  - `#198` Derince Kapalı Spor Salonu, Yenikent Mah. Serpil Sk. No 2, Derince; 24.10 12.00/14.00/16.00.
  - Default salon kapasitesi 500, süresi 60 dk; seanslar ve satılabilir kapasite bu kayıtlarla otomatik oluşmaz.
- Eski salonlar `#152` Karamürsel, `#59` Darıca ve `#60` Derince güncellenmedi, yeni kayıtlarla birleştirilmedi; geçmiş kullanım korunur.
- Milano WordPress sayfaları `#125`, `#126`, `#127` ve turne sayfası `#6` 490 / 245 / 1.100 TL olarak güncellendi. Meta excerptleri de güncellendi. Yalova fiyatları korunur.

## Doğrulanan kök neden
- Canlı MMC v1.3.49 `MMC_Program_Service::create_program` explicit `brand` gönderildiğinde Milano'yu doğru kabul edip veritabanına yazar, fakat `brand` eksikse **Madagaskar Sirki** varsayılanını kullanır.
- Canlı admin `Yeni Program Aç` formunda `brand` seçimi yoktu.
- Canlı `madagaskar/program-create` AI ability input şeması/execute callback içinde `brand` yoktu; buradan Milano programı açılması **risklidir**.
- Bu dar kapsamlı PR admin formuna zorunlu marka menüsü ekler, AI ability'ye **isteğe bağlı** doğrulanmış `brand` ekleyip callback'e iletir. Legacy Madagaskar AI çağrıları eski varsayılanı kullanmaya devam eder.
- **Yeni Milano programları açılırken `brand: "Milano Sirki"` açıkça belirtilmelidir; yanıt ve DB markası Milano değilse sonraki işlemler durdurulur.**

## Uygulama sonrası güvenli sıra (üretim dağıtımı yok)
1. Bu PR'da PHP sözdizimi ve statik sözleşme testleri yeşil olmalı.
2. Staging'de aynı iki dosyanın yedeği, checksum ve rollback alınmalı. Staging admin program formunda iki marka listelenmeli.
3. Staging `madagaskar/program-create` ile `brand: "Milano Sirki"` göndererek tek deneme programı oluştur; yanıt `program.brand` tam Milano, DB markası Milano olmalı. Sahte kapasite, ödeme, bilet veya iletişim bilgisi oluşturma.
4. Eski marka parametresi olmayan çağrıların Madagaskar varsayılanına devam ettiği doğrulanmalı. Yönetim formu elle açık seçim olmadan gönderilememeli.
5. Ayrı onaylı kurulum ile canlıya alınır; tüm canlı dosyalar ve mevcut Madagaskar programları/siparişleri korunur.
6. Karamürsel, Darıca, Derince programlarını **ayrı** oluştur; `brand=Milano Sirki`, `Kocaeli`, ilgili ilçe/tarih. Her programda önce yukarıdaki doğru salon ID'sini bağla.
7. MMC event başlığı, yer, tarih, 7 adet seans ve 500'er kapasite; Woo taslak bilet ürünü; merkezi MDG bridge ve Tickera; marka izolasyonu. Her seans için fiyat/kapasite doğrulaması ve dört ayrı QR üreten aile paketi staging E2E testi.
8. PayTR callback doğrulaması, gerçek sepete ekleme/ödeme/iade testi, müşteri sayfası yönlendirmesi ve tekil bilet kayıtları kanıtlanmadan `onsale` veya herkese açık ödeme açma. Madagaskar'a ait etkinlikler Milano'da satılmaz.

## Canlı durum
- Yalova MMC program `#14` ve MDG `#24` mevcut, Kocaeli üç programı **henüz oluşturulmadı**.
- Milano handoff central sales toggle açık olması, Kocaeli'ye sahiplik ve bilet ürünü sağlamaz.
- Bu dosya ile PR geliştirme aşamasındadır. Live değişiklikler yalnız onaylanan yeni 3 salon ve Milano yayın fiyatlarıdır.

# Madagaskar Sirki — Claude İnceleme Kaydı — 3 Ekim 2026

**İnceleme zamanı:** 2 Ekim 2026 22:55 – 3 Ekim 2026 04:20 TSİ
**Kapsam:** 2 Ekim öğleden sonra GitHub ve Drive'a işlenen çalışmaların gözden geçirilmesi ve `madagaskarsirki.com` sitesinin giriş yapmamış ziyaretçi olarak kontrolü.
**Önceki kayıt:** `docs/CLAUDE_SITE_CHANGES_2026-10-02.md`
**Drive karşılığı:** `Madagaskar + USKD Codex Çalışma ve Yedek Rehberi`, "Claude — 3 Ekim İnceleme Kaydı" bölümü.

Bu dosya yalnızca tespit kaydıdır. Bu incelemede canlıda, kodda veya veride hiçbir değişiklik yapılmadı.

## 1. Yöntem ve sınırlar

- Canlı sayfalar giriş yapmamış ziyaretçi olarak, sayfa metni üzerinden okundu. Renk, yerleşim ve 2 Ekim'de görülen yatay kaydırma çubuğu bu kontrolün dışındadır.
- Menü, sayfa içerikleri, Jetpack Monitor, Jetpack Scan ve etkinlik günlüğü WordPress.com bağlantısıyla salt okunur olarak kontrol edildi.
- Code Snippets, WooCommerce günlükleri, MMC ekranları ve Kommo paneli okunamadı. Bunlarla ilgili satırlar GitHub ve Drive kayıtlarına dayanır ve öyle işaretlenmiştir.
- Satın alma denenmedi; sepete ürün eklenmedi.

## 2. Canlıda doğrulananlar (3 Ekim, 04:17–04:20 TSİ)

| Sayfa | Sonuç |
|---|---|
| Ana sayfa | Sincan kartında "BUGÜN" rozeti (3 Ekim). Dört soru, altı öğeli menü, çerez bildirimi yerinde. Kırıkkale yok. |
| Alt bilgi (MS Global Alt Bilgi V3.3) | Yasal satırda sekiz bağlantı doğru sırada; çerez bildirimi `/gizlilik-ve-cerez-politikasi/` adresine bağlı. |
| `/sehirler/` ve `/bilet-al/` | İki liste aynı 10 gösteriyi veriyor: Sincan, Yenimahalle, Denizli, Mamak, Eskişehir, Uşak, Didim, Aydın/Efeler, Manisa, İzmir. Kırıkkale, Pursaklar, Bartın, Çubuk, Bolu yok. |
| Sincan etkinlik sayfası | Bilet seçici açık; 12:00, 14:00, 16:00 seansları "Satışta"; 250 / 500 / 1.100 TL. |
| `/biletlerim/` | "Biletimi Bul" formu bir kez görünüyor. Form gönderimi denenmedi. |
| Üst menü (navigation 1357) | Gösteriler, Şehirler, Kurumsal, SSS, İletişim, Bilet Al. |
| Şehir sayfaları (11 adet) | 2 Ekim sabahındaki içerik duruyor; sonradan değiştirilmemiş. |

Site durumu: Jetpack Monitor **up**. 2 Ekim 22:37'de açılan #4065 numaralı sipariş 22:40'ta ödendi (750 TL); yani 2 Ekim'deki eklenti güncellemeleri ve yeni snippet'lerden sonra ödeme akışı en az bir gerçek siparişle çalıştı.

## 3. Canlıda görülen sorunlar

| # | Sorun | Ayrıntı | Kaynak |
|---|---|---|---|
| S1 | Kırıkkale duyurusu eski bilgi veriyor | `/etkinlik/madagaskar-sirki-kirikkale-02-ekim-2026/` hâlâ "Bilet bedeli iade talepleri oluşturulmuştur. İadeler onay ve ödeme kuruluşu işlemlerinin ardından tamamlanacaktır." diyor. PR #92'deki rapora göre 11 iade 2 Ekim 14:08–14:10'da tamamlandı. | Snippet #104 |
| S2 | Kırıkkale duyuru sayfasında menü ve alt bilgi yok | Sayfa `wp_die()` ile basılıyor; ziyaretçi için siteye dönüş, iletişim veya WhatsApp bağlantısı yok. | Snippet #104 |
| S3 | Denizli salon adında yazım hatası | Ana sayfada ve `/bilet-al/` listesinde "Özay Gönlüm Sakonu". `/sehirler/` listesinde kısa ad göründüğü için hata orada çıkmıyor. | MDG salon verisi |
| S4 | 26 Eylül ürün sayfası hâlâ açık (K8) | `/urun/madagaskar-sirki-ankara-26-eylul-2026-1200-bileti/` 250–500 TL fiyat ve bilet türü seçiciyle açılıyor. Satın alma denenmedi; geçmiş tarihe satış yapılıp yapılamadığı doğrulanmadı. | WooCommerce ürünü |
| S5 | Ankara sayfasının başlığı ve açıklaması hâlâ "26 Eylül 2026" (K7) | `/sehirler/ankara/` içeriği doğru; `<title>` ve meta açıklama eski tarihi basıyor. Ankara'da şu an üç gösteri satışta (Sincan, Yenimahalle, Mamak). | Bilinmeyen bir snippet; Jetpack SEO alanlarını eziyor |

Önerilen duyuru metni (S1): "Bilet bedeli iadeleri başlatılmıştır. Tutarın kartınıza yansıması bankanıza göre birkaç iş günü sürebilir." Paranın kartlara yansıdığı doğrulanmadığı için "iade edildi" denmemeli. S2 için duyuruya ana sayfa ve WhatsApp bağlantısı eklenmesi yeterli.

## 4. Kayıtların incelenmesinden çıkanlar

Bu bölümdeki bilgiler GitHub ve Drive kayıtlarından alınmıştır; Claude bunları canlıda doğrulayamadı.

1. **İptal durumu kendiliğinden geri açıldı; kök neden duruyor.** MMC Program #2 (Kırıkkale) 2 Ekim 13:53'te `cancelled` durumundan `sales_open` durumuna döndü (MMC log #424, not: "WooCommerce, Tickera ve PayTR gerçek satış entegrasyonu doğrulandı"). Snippet #104'e yalnızca Program #2 için bir koruma eklendi. İptal edilmiş bir programı entegrasyon doğrulamasının yeniden satışa açabilmesi genel bir hatadır; başka bir iptalde tekrarlanır. Kalıcı düzeltme: doğrulama adımı `cancelled` durumundaki programın durumunu değiştirmemeli.
2. **Kırıkkale kayıtları birbirini tutmuyor.** Drive'daki "Kırıkkale iptal ve iade operasyonu" bölümü "tamamlanan iade: 0 sipariş / 0 TL" diyor; PR #92'deki raporun son bölümü 11/11 iadenin tamamlandığını, 13.750 TL iade edildiğini ve 46 biletin geçersiz kılındığını yazıyor. PR #92 taslak ve main'e alınmadı.
3. **K2 hatırlatması iptal edilen gösteriye de giderdi.** Kırıkkale'nin altı başarısız siparişi var (3934, 3924, 3907, 3836, 3820, 3467). `ms-yarim-kalan-odeme-kaydi-v1-deneme` snippet'inde "etkinlik iptal edildi veya satışa kapalı" kontrolü yok; bu siparişler için "GÖNDERİLİRDİ" yazar. Gönderim aşamasından önce bu kontrol eklenecek (Claude'da).
4. **Canlı MMC ile depo ayrışmış.** Canlı 1.3.47, `main` 1.3.30; 41 dosyanın 21'i farklı (Issue #72). 2 Ekim'de `class-mmc-integrity-service.php` ve `class-mmc-kommo-service.php` doğrudan canlıda yamalandı; depoya işlenmedi.
5. **Kommo program metni sınırda.** Unified Source V2 program metni 1.903 / 1.950 karakter. Yeni bir etkinlik eklendiğinde sınırı aşabilir. Kommo panelinde 1334998 ve 1334988 kaynaklarının elle güncellenmesi ve eski kaynakların silinmesi bekliyor (Issue #82).
6. **Kommo sohbet butonu uyarısı sürüyor.** Jetpack Scan, eklenti 1.3.3'e güncellendikten sonra da aynı açığı gösteriyor (`fixable: false`). İşletme sahibinin kararı: buton açık kalacak. Uyarı metni "<= 1.3.1" dediği için yanlış alarm olabilir; doğrulanmadı.
7. **Depodaki kayıtlar geride.**
   - `docs/LEGACY_MIGRATION_EXECUTION_QUEUE_2026-10-02.md` Denizli, Mamak, Eskişehir ve İzmir'i hâlâ "bekliyor" gösteriyor; Drive'a göre dördü de taşındı (Program #10–#13).
   - Eklenti güncellemeleri taslak PR #95'te; main'de yok.
   - 2 Ekim 22:51–22:56'da üç eklenti kapatıldı: İlçe Bazlı SKU Hotfix, SKU Hotfix V2 (Issue #77'de kayıtlı) ve "Manuel Salon Seçimi Hotfix" 1.0.0. Sonuncusu hiçbir kayıtta yok.
8. **İptal/İade sayfası ile yeni Excel şablonu.** `docs/EXCEL_TEMPLATE_STANDARD_2026-10-02.md` içindeki kurallar `/iptal-iade-ve-bilet-teslimati/` sayfasıyla (ID 1572, son güncelleme 28 Ağustos) çelişmiyor. Yalnız "kullanılmayan bilet geçersizdir, iade veya seans değişikliği yapılmaz" kuralı o sayfada yazmıyor; yeni etkinlik sayfalarında yazacak.

## 5. Codex için sıralı iş listesi

| Öncelik | İş |
|---|---|
| 1 | S1 ve S2: Snippet #104 duyuru metnini güncelle, siteye dönüş ve WhatsApp bağlantısı ekle. |
| 2 | Madde 4.1: `cancelled` programın entegrasyon doğrulamasıyla `sales_open` olmasını engelleyen kalıcı düzeltme. |
| 3 | S4 (K8): 26 Eylül ürününün satın alınabilir olup olmadığını doğrula; açıksa V4 satış kapatma ile kapat. Geçmiş tarihli diğer ürünler için de aynı kontrol. |
| 4 | S3: Denizli salon adını MDG salon kaydında düzelt ("Salonu"). |
| 5 | S5 (K7): Ankara sayfasının başlığını basan snippet'i bul; şehir sayfalarında sabit tarih basmayı kaldır. |
| 6 | Madde 4.2 ve 4.7: Drive'daki Kırıkkale bölümünü güncelle; PR #92 ve PR #95'i sonuçlandır; sıra belgesini güncelle; "Manuel Salon Seçimi Hotfix" kapatılmasını kaydet. |

Claude tarafında bekleyenler: K2 gönderim aşaması (işletme sahibinden Kommo otomasyon ekranı, karttaki telefon bölümü, "Ödeme Linki" alanı ve mesaj metni onayı bekleniyor) ve bu aşamadan önce iptal/satışa kapalı etkinlik kontrolünün eklenmesi.

# Kommo kaynaklarında iptal programın yeniden görünmesi — 24.10.2026

## Sonuç
24 Ekim 2026 07:26:42 Europe/Istanbul canlı doğrulaması: MMC canonical Kommo beslemesinde dört program kaldı: Milano Derince (24 Ekim), Madagaskar Sakarya (25 Ekim), Çankırı (28 Ekim), İzmir/Konak (8 Kasım). Torbalı hem program hem konum metninden çıktı.

## Kök neden ve en küçük düzeltme
Canlı aktif kaynak üreticisi Code Snippets #110, `mdg_kommo_unified_v2_collect()`. Program durumu cancelled/completed olanları zaten eliyor; geçmiş tarihleri ve uygun olmayan etkinlik durumlarını da eliyor. Torbalı #22 ana MMC programında sales_open, bağlı etkinlikte sales_open kaldığından 30 Ekim programı yeniden kaynağa giriyordu. Kommo'da metni değiştirmek bu yerel ana veri uyuşmazlığını düzeltmiyordu. Kod hatası kanıtlanmadığı için kalıcı PHP değişikliği yapılmadı.

Sistemin mevcut `madagaskar/program-set-status` ability'si (MMC_Program_Service::set_status, uygulama günlüğü dahil) ile kullanıcının daha önce bildirdiği iptaller ana programlara işlendi:
- #22 Torbalı: sales_open → cancelled; 24.10.2026 07:25:56
- #19 Menemen: venue_confirmed → cancelled; 07:26:13
- #20 Tire: venue_confirmed → cancelled; 07:26:23
- #21 Ödemiş: venue_confirmed → cancelled; 07:26:33

Dört ability sonucu updated=true ve status_label=İptal döndürdü. İşlem yalnız program yaşam döngüsü kayıtlarını ve servis günlüğünü hedefledi; event status, ürün, sipariş, ödeme, iade, QR/PDF ve SalesBot yapılandırması değiştirilmedi. Kommo kuyruğundaki mevcut uygulama hook'ları çalışabilir; gerçek müşteri mesajı başlatılmadı.

## Canlı kabul kanıtı
`madagaskar/kommo-unified-source-v2-preview` generated_at=2026-10-24 07:26:42, count=4. Program ID'leri [18,15,23,13]. program_text ve locations_text içinde Torbalı bulunmuyor. İptal program ID'leri [19,20,21,22] aktif events içinde bulunmuyor. Bu çıktı mevcut kaynak üreticisinin canlı çalışmasıdır; sahte ortam testi değildir.

Önceki aynı oturumdaki Kommo AI #350584 güncellemesi: mevcut Program, Konum ve Resmî Bilet metinleri kaydedildi, iptal kılavuzu güncellendi. Önizlemede Çankırı 28 Ekim, 17:00/19:00, Hasan Fehmi Gökşen ve 1 yetişkin+1 çocuk=735 TL doğrulandı; Sakarya açık adres ve kayıtlı Maps bağlantısı doğru döndü. İlk iptal denemesi doğrulayamıyorum/bağlantı yönlendirmesi verdi; kılavuz düzeltildikten ve yeni önizleme başlatıldıktan sonra Torbalı/Menemen/Tire/Ödemiş iptal ve satış yok cevabı doğrulandı. Gerçek WhatsApp/Instagram teslimatı bu testlerin kapsamı değildir.

## Arşivden doğrulanan çalışma yolu
- 13 Ekim kurumsal hafıza ve 1 Ekim migration kaydı: kalıcı madagaskar-kommo-automation v0.1.1 aktif; eski #12/#66/#67/#70 pasif. Canlı envanter de #70 pasif, #110 ve #186 aktif doğruladı. 30 Eylül #70 düzenleme talimatını bugünün dağıtım yolu olarak kullanmayın.
- Drive KOMMO_SOURCE_FRESHNESS_MONITOR_2026-10-12.md ve GitHub draft PR #230: admin-only #186 tazelik kontrolüdür. Hash farkı yerel uyarı üretir, Kommo uzak indeksini otomatik güncellemez.
- Kommo AI text-source update için önceki PATCH/PUT denemeleri 404; desteklenen otomatik uzak metin güncelleme API'si bu çalışmada doğrulanmadı. Yeni tahmini endpoint denenmedi, yeni bilgi kaynağı oluşturulmadı.
- Bu düzeltme yerel beslemenin doğruluğunu giderir; otomatik uzak Kommo indeksleme kurulduğu anlamına gelmez. Sonraki gerçek program/fiyat/adres değişiminde mevcut iki Kommo metni UI'den yenilenip test edilmelidir. Zenginleştirilmiş Kommo metinleri kanonik kısa metinle byte-eşit olmadığı için #186 doğrulama hash'i taklit edilmedi.

## Operasyon ve geri alma
İptal kararı her zaman ana MMC programına işlenmeli; yalnız bilet satışını kapatma veya AI metnini elle değiştirme yeterli değildir. Kayıtlar silinmez. Yanlış iptal kararı yeni kullanıcı talimatıyla düzeltilirse önceki status aynı servis üzerinden kontrollü geri alınabilir; bu, iptal programın kaynakta yeniden görünmesine neden olur. İptal programı satışa taşıyabilecek sonraki toplu satış/sağlık senkronları ayrıca incelenmeden çalıştırılmamalıdır.

Kod dağıtımı, main merge veya yeni CI iddiası yoktur. Bu PR yalnız doğrulanan operasyon kaydıdır. Kimlik bilgisi, tokenlı URL ve müşteri verisi içermez.

## Kaynaklar
- Drive teknik devir: https://docs.google.com/document/d/1CZCpZXuxGVf2DxdzX5yNU3_XRT6yUkMVkJuTKLEqVxM/edit
- Drive kurumsal hafıza: https://drive.google.com/file/d/1FGmDuN0B-X4UQmlsQQU1Dt9TGskQkU2T/view
- Drive monitor notu: https://drive.google.com/file/d/1Z0h0l8vYDach8uxrcOsule2cM51Ntgit/view
- GitHub monitor PR: https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/230
- Kaynak üreticisi: docs/code-snippets/mdg-kommo-unified-source-v2.php

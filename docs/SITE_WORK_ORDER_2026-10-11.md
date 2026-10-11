# Site çalışma sırası — 11 Ekim 2026, 19:00 TSİ

Kaynaklar: GitHub main e4144b14a4815c5571eacbb1b9b56aaf7eb75cd8; açık iş/PR kayıtları; #140/#212/#219/#220 yorumları; Drive çalışma rehberi ve 11 Ekim Kommo/bilet fiyat kayıtları; yeni canlı sağlık ve cookiesiz HTTP kontrolü.
Eski checkpointler güncel durum kabul edilmedi. Bu çalışma canlı domain/finans/sipariş/bilet/ödeme verisini değiştirmedi; yalnız inceleme ve iş kaydı mutabakatı.

## İlk kontrol tamamlandı
- Sağlık: 16 OK / 0 uyarı / 0 kritik.
- Cookiesiz normal URL okumaları: ana sayfa, şehirler, bilet listesi, Manisa, İzmir HTTP200 ve no-store/no-cache, edge MISS.
- Bilet listesinde beş gelecek etkinlik: Uşak, Didim, Efeler, Manisa, İzmir. Biten Denizli, Mamak ve Eskişehir satış listesinde yok.
- Manisa 245/490 ve İzmir 290/585 fiyatları anonim HTML'de doğru. Aile paketinin JavaScript ile eklenen seçim alanı önceki canlı browser testiyle doğrulandı; salt HTML okuması tam JS sepet testi değildir.
- Boş anonim sepetle ödeme URL'si normal biçimde sepete döndü. Yeni dolu sepet → checkout → PayTR formu testi henüz bu incelemede yapılmadı. Gerçek ödeme yapılmayacak.
- AI Abilities 0.7.0, Aile 1.1.3, MMC 1.3.49, Okul Tanıtım 1.8.4 aktif. Eski rehberdeki AI/Aile yükseltmeleri tekrar iş listesine alınmadı.
- Legacy Redirects klasöründeki pasif V4 kopyası hâlâ var; aktif doğru V4 ve yönlendirme eklentisi korunuyor.

## Uygulama sırası
| Sıra | İş | Somut kabul ölçütü | Bağımlılık/sınır |
|---|---|---|---|
| 1 | Yeni fiyatlarla temiz satış akışı | Gelecek seansın tekli ve aile sepeti; doğru toplam; checkout ve PayTR formu; eski kayıt/QR korunması | Ön inceleme tamam; yeni dolu sepet testi kalan adım. Ödeme/sipariş üretmeden doğrula |
| 2 | Kommo etkinlik hatırlatma ve memnuniyet | 15 saat önce seans datetime alanı; tüm uygun siparişlerde veri doluluğu; ilk teslimat kanıtı; anket gerçek seans sonrası doğru pipeline | #219/#218; botun kaydı teslimat kanıtı değildir. Yeni mesaj gönderimini ayrıca somutlaştır |
| 3 | Kommo destek/iade/ödeme botlarını tamamla | V2 Meta onayı; literal placeholder yerine bağlı alan; doğru olay ve tekrar koruması | Drive 11 Ekim kaydı: V2 incelemede; bazı botlar kaydedilmiş fakat otomatik tetik kapalı. Gerçek iade/iptal/mesaj çalıştırma yok |
| 4 | Finans mutabakatının kalan kısmı | PayTR komisyon/iade/bankaya geçen net; dönem ekstresi; hesap başlangıcı; belgeli geçmiş teyit; MMC/V5 kaynak sahipliği | #220; #221/#222 zaten canlı. Ekstre olmadan banka mutabakatı tamamlandı sayılmaz. Kullanıcı ödemesi beyanı banka kanıtı değildir |
| 5 | Okul baskı, dağıtım, kalan stok ve geçmiş | Basılan/dağıtılan/kalan gerçek adetleri; sonraki yıl karşılaştırması; PDF/Excel çıktılarının aynı kapsamı | #211 ve PR210 kayıtları: Aydın48/16.407, Manisa89/37.367 öğrenci kapsamı zaten uzlaştırılmış. Yeni fiili adet olmadan planlananı basılmış sayma |
| 6 | Güvenlik ve kampanya erişimi | Eski Kommo anahtarını servis tarafında revoke/rotate; aktif bağlantı doğrula; açığa çıkan üç kampanya kodunun kontrollü yenilemesi | #166/#214. Kodların depodan temizlenmesi iptal değildir. Kurumlara dağıtılmış kodları genel inceleme yetkisiyle değiştirme |
| 7 | GitHub-canlı kayıtlarını eşleştirme | PR223 açıklamasındaki eski source-only durumu düzelt; PR227 ve diğer açık kayıtların canlı/depo ayrımını koru; uygun arşivler review/merge sürecine | PR223 canlı snippet177/trigger kaydı Drive'da mevcut; bu tur PR açıklaması düzeltilir. Otomatik merge yapılmaz |
| 8 | Düşük öncelikli bakım ve USKD | #185 yalnız pasif dosya; #112 ödenmemiş kodların ayrı karar takibi; #57 ayrı USKD depo organizasyonu | Çalışan eklenti klasörünü silme; geçmiş sipariş/biletleri toplu değiştirme. Tek dosya kontrollü erişimi önce doğrula |

## Tekrar yapılmayacak tamamlanan işler
- #140 cache çakışması patchi ve 10 Ekim edge purge; bugünkü anonim yeniden kontrol başarılı.
- #212 çekiliş manuel yayın/doğrulanmış aday kontrolü canlı; Mamak sonuçları önceki açık kullanıcı talebiyle yayımlanmış.
- Finans merkezi ve Mutabakat ekranı (#221/#222) zaten canlı.
- Ortak gider yüzde dağıtımı korunuyor; Didim satış eşleştirmesi #209 kaydı mevcut.
- Çoklu ilçe öğrenci geçmişi/Excel düzeltmesi #211 canlı; PR210 11 Ekim mutabakatı bunu doğruluyor.
- Bilet fiyat geçişi #227: 32/32 kataloğu ve eski 204 kalem/334 bilet kontrolü önceki işlemde doğrulandı.

## Yeni inceleme ile kapanmayan sınırlar
Kommo uzaktaki bot/şablon durumu bu tur yeniden okunmadı; son Drive kaydı kaynak gösterildi. Finans toplamlarının yeni banka mutabakatı, dış Biletinial fiyat paneli, gerçek müşteri mesajı teslimatı ve dolu sepet ödeme testi tamamlandı iddiası yok.
Sonraki iş: sıra 1 dolu sepet doğrulaması, ardından #219/#218 canlı veri/teslimat ve anket saat kapsamı.

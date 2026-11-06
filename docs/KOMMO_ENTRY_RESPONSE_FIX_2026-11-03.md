# Kommo — Salon girişi ve yanıt doğruluğu düzeltmeleri
Tarih: 3 Kasım 2026 (Europe/Istanbul).

## Sonuç ve kapsam
Kullanıcının açık onayıyla iki mevcut bilgi kaynağı ve dört mevcut AI yönergesi güncellendi; ayrıca yalnız aritmetik doğrulama için bir yönerge eklendi. Kaydetme sonrası sayfalar yeniden yüklenerek kalıcılık ve hedef dışı metinlerin korunması doğrulandı. Bu kayıt, Kommo'da tamamlanmış işlemin belgesidir; yeni kod dağıtımı değildir.

20 Ekim–3 Kasım dönemi konu bazlı tarandı; 17 konuşma ayrıntılı incelendi, sekiz metin kaynağı okundu. Dönemdeki bütün konuşmaların eksiksiz denetimi yapıldığı iddia edilmez. Müşteri kimlikleri, sohbet dökümleri, telefonlar, sipariş bağlantıları ve tokenlar bu kayda alınmadı.

## Sorun ve yeniden üretim
19:30 seansının salon giriş saatini soran müşteriye giriş saati bilinmiyor yanıtı verildi. Aynı hata değişiklik öncesi AI önizlemesinde tekrarlandı. 30 dakika önce kapı açılışı bilgisi davetiye kaynağında vardı; o kaynak temsilcinin bağlı kaynak listesinde görünmüyordu. Genel kaynakta kapı açılışı açıkça yazmıyordu; gişe açılış saati uydurmama kuralı bulunuyordu. Desteklenen düzeltme, mevcut bağlı genel kaynakları güncellemek oldu; yeni/mükerrer kaynak veya toplu kaynak bağlama yapılmadı.

Diğer örneklerde Türkçe konuşmada İngilizceye geçme, yıl düzeltmesini gösteri numarası sanma, geçmiş aile paketi uygunluk/fiyat hataları ve çocukların oturması hakkında yanlış yanıt görüldü. Bir kısmı 2 Kasım kurallarıyla zaten ele alınmıştı; eski yanıtlar bugünün kesin davranışı kabul edilmedi.

## Canlı değişiklikler
- Temel bilgiler: salon kapılarının her seanstan 30 dakika önce açıldığı ve 19:30→19:00 örneği eklendi. Gişe satış başlangıcı ayrı tutuldu. Tarih/kapsam başlığı kısaltılarak metin 4.997/5.000 karakterde kaldı. Geri dönüşümlü metin karşılaştırması, yalnız bu iki hedef değişikliğin yapıldığını doğruladı.
- MADAGASKAR SİRKİ – RESMÎ BİLET SATIŞ SAYFASI: yalnız salon girişi açıklaması başa eklendi. Önceki metnin tamamının eklenen önekten sonra birebir kaldığı doğrulandı.
- 21 mevcut yönergeden yalnız 1, 9, 10 ve 18 numaralı sıfır-tabanlı alanlar güncellendi: konuşma/tarih bağlamı; süre ve kapı açılışı; okunamayan içerikte dil; fiziksel satış ile salon girişinin ayrımı.
- Ayrı aritmetik yönergesi eklendi; toplam yönerge sayısı 22 oldu. Birim fiyatı yuvarlamadan güncel kaynaktan alma, kişi adedi × birim fiyat ve tekil toplam eksi önerilen toplam hesabını doğrulama şartı kondu. Paket kullanım/öneri politikasını değiştirme talimatı eklenmedi; mevcut kuralları aynen koruma açıkça yazıldı.

## Aynen korunan kurallar
Kullanıcı fiyat ve aile paketi öneri kurallarının değiştirilmemesini özellikle istedi. Asistanın rol metni ve diğer 17 eski yönerge birebir korundu; aile paketi yönergeleri bu korunan gruptadır. Program kaynağı değiştirilmedi.
- Sistemdeki ad: Aile Paketi 2+2; family_2_2 ve bilet/kapasite yapısı değişmedi.
- Paket 2 yetişkin + 2 çocuk veya 1 yetişkin + 3 çocuk için kullanılabilir; 2 yetişkin + 1 çocuk da kullanabilir.
- Geçerli tekil/paket/ek bilet/çoklu paket birleşimleri karşılaştırılır ve en ucuz seçenek önerilir. Paket her durumda ucuz kabul edilmez.
- Standart kaynak fiyatları: yetişkin 490 TL, çocuk 245 TL, aile paketi 1.100 TL.
- İzmir Halkapınar: yetişkin 585 TL, çocuk 290 TL, aile paketi 1.300 TL.
- Üçüncü yetişkin çocuk hakkı sayılmaz; kullanılmayan çocuk hakkını doldurmak zorunlu değildir.
Bu rakamlar 3 Kasım kaynak okumasının kaydıdır; ilerideki fiyat değişikliklerinde güncel doğrulanmış program kaynağı esas alınır.

## Doğrulama
Yeniden yükleme sonrası dört hedef yönerge, yeni aritmetik yönergesi ve iki kaynak metni kaydedilmiş olarak okundu. Rolün değişmediği ve korunan 17 yönergenin önceki değerleriyle aynı olduğu karşılaştırıldı.
- Salon girişi: Kırklareli 13 Kasım 19:30 → kapılar 19:00; başlangıçtan 30 dakika önce. Önizleme doğru yanıt verdi.
- Çerkezköy 1 yetişkin + 3 çocuk: Aile Paketi 2+2, 1.100 TL; tekil 1.225 TL, avantaj 125 TL.
- Çerkezköy 2 yetişkin + 1 çocuk: Aile Paketi 2+2, 1.100 TL; tekil 1.225 TL, avantaj 125 TL.
- Çerkezköy 1 yetişkin + 2 çocuk: tekil 980 TL, 1.100 TL paketten daha uygun.
- İzmir 2 yetişkin + 3 çocuk: paket + 1 çocuk = 1.590 TL.
- İzmir 2 yetişkin + 4 çocuk: paket + 2 çocuk = 1.880 TL.
İlk beşli testte paket birleşimleri/toplamları doğruydu, fakat son iki seçeneğin tekil toplamında 10 TL aritmetik hata görüldü. Ek aritmetik yönergesi kaydedildikten sonraki tekrar testinde İzmir 2+3 için tekil 2.040 TL / avantaj 450 TL ve 2+4 için tekil 2.330 TL / avantaj 450 TL doğru döndü.

## Kanıt sınırı, etkilenmeyen alanlar ve geri alma
Bunlar gerçek Kommo editörü readback ve AI önizleme sonuçlarıdır; yerel simülasyon değildir. Yeni gerçek müşteri konuşmasındaki teslimat bu düzeltme sonrasında ayrıca gözlenmedi. Dil/tarih yönergelerinin kaydı doğrulandı; ayrı uçtan uca müşteri testi iddia edilmez. CI veya PHP testi çalıştırılmadı; bu görevde kod değişikliği yoktur.

Bilet ürünleri, QR/PDF şablonu, WooCommerce/PayTR, siparişler, iadeler, kapasite, çalışan bilet botu ve diğer otomasyonlar değiştirilmedi; müşteri mesajı veya toplu gönderim başlatılmadı.

Geri alma yalnız iki kaynak metnindeki giriş ekini ve dört hedef yönergeyi önceki değerlerine döndürmek, yeni aritmetik yönergesini kaldırmaktır. Fiyat/paket kuralları ve bilet zincirine dokunulmaz. GitHub kaydı ayrı belge dalı/draft PR'dır; main birleştirme veya kod deploy anlamına gelmez.

# Kommo Aile Paketi 2 kaynak dağıtımı — 6 Kasım 2026

Kullanıcının “Kommo kaynaklarında 2 yetişkin + 4 çocuk paketini öner; devam” talimatıyla, daha önce kapsam dışında görülerek reddedilen kaynak dağıtımı tamamlandı. Bu oturumdaki yazma başarılıdır.

## Uygulanan değişiklik ve doğrulama

Mevcut aktif Code Snippets #110 yalnız main'deki docs/code-snippets/mdg-kommo-unified-source-v2.php ile eşleştirildi. Yedi satırlık ekleme native MDG_Family_Package_24::offer() politikasını okur; fiyatlar hardcode edilmedi. Kodun tam readback'i main ile eşleşti; active=true, code_error=null. Öncesi geri dönüş kopyası yerel çalışma alanına alındı.
Kaynak preview zamanı: 2026-11-06 09:41:10 Europe/Istanbul. Sekiz programın yedisinde family_2_4, adults=2, children=4, capacity_units=6 doğrulandı: İzmir/Konak1500; Ödemiş/Tire/Çorlu/Çerkezköy/Kırklareli/Edirne1250 TRY. Süleymanpaşa yeni paket içermiyor, Keşan kaynakta yok. Yeni ticket çıkarıldığında önceki tüm program/seans/konum ve tekil/2+2 ticket verileri birebir korundu.
Mevcut dağıtım commit'inin connector tarafından döndürülen14 workflow'u success (önceki runbook toplam15 workflow kaydetmişti); yeni CI çalıştırıldığı iddia edilmez. PHP/checkout kodunda yeni geliştirme yok, test edilmiş ve PR310'da birleştirilmiş kaynak eklemesi dağıtıldı.

## Kommo uzak AI metinleri: TAMAMLANMADI

WordPress kaynak üreticisinin dağıtımı, Kommo AI350584 altındaki mevcut Program/Genel/Resmî Bilet metinlerini otomatik yenilemez. Geçmiş notlarda güncelleme UI'den yapılmış; desteklenen uzak text-source update API doğrulanmamış, önceki tahmini PATCH/PUT404. Bu oturumda Kommo/browser kontrol aracı veya yapılandırılmış Kommo kimliği yok. Tahmini endpoint, yeni/mükerrer kaynak veya müşteri mesajı kullanılmadı.
Aşağıdaki metin mevcut ilgili kaynaklara işlenecek hazır öneridir; Kommo'ya kaydedildiği veya AI önizleme testinin geçtiği iddia edilmez. Özellikle3Kasım2+4=1880 önerisi yeni uygun etkinliklerde1500 olarak yenilenmelidir.
Oluşan kısa program metni1995UTF-16 birimidir; eski admin ekranındaki1950 göstergesi aşılır. Bu metin Kommo'ya otomatik yazılmadı; gerçek editör limiti doğrulanmadan körlemesine yapıştırılmamalı.

## Hazır AI senaryosu

Aile Paketi 2 — öneri metni
Aile Paketi 2 yayındadır: 2 yetişkin + 4 çocuk, toplam 6 kişi. Mevcut 2+2 paketinden ayrı bir seçenektir.
Yalnız güncel etkinlik kaynağında family_2_4 seçeneği bulunan programlarda önerilir. İzmir merkez/Konak 1.500 TL; Çorlu, Çerkezköy, Kırklareli, Edirne, Tire ve Ödemiş 1.250 TL. Bu fiyatlar diğer şehirlere veya Milano/Biletinial programlarına genellenmez. Keşan ve Süleymanpaşa için bu seçenek doğrulanmamıştır.
Kalabalık aile sorusunda yetişkin ve ücretli çocuk sayıları belli değilse önce sayılarını sor. 2 yetişkin ve 4 çocuk için Aile Paketi 2'yi öner; geçerli tekil, 2+2, 2+4 ve ek bilet birleşimlerini karşılaştırarak en uygun toplamı söyle. Daha az kişiye veya farklı yetişkin/çocuk dağılımına otomatik uygunluk tanımlama. Yaşlar: 0–2 ücretsiz, 3–12 çocuk, 13+ yetişkin.
Örnek: “İki yetişkin, dört çocuğumuz var; İzmir için ne önerirsiniz?” → “Aileniz için 2 yetişkin ve 4 çocuğu kapsayan Aile Paketi 2 uygun. İzmir merkez gösterisinde toplam 1.500 TL. Seansınızı https://madagaskarsirki.com/bilet-al/ adresinden seçebilirsiniz.”
Örnek: “Çorlu'da iki yetişkin, dört çocuk kaç TL?” → “Aile Paketi 2 ile altı kişi için toplam 1.250 TL.”
Örnek: “Kalabalık bir aileyiz, aile paketi var mı?” → “Evet, 2 yetişkin + 2 çocuk paketinin yanında 2 yetişkin + 4 çocuk için Aile Paketi 2 de var. Hangi gösteriye katılacaksınız; kaç yetişkin ve kaç çocuksunuz?”
Eski “İzmir 2+4 = 2+2 paket + 2 çocuk = 1.880 TL” önerisi, Aile Paketi 2 açıkken güncel en uygun seçenek değildir: yeni toplam 1.500 TL; tekil 2.330 TL'ye göre 830 TL avantaj. Standart 245/490 fiyatlı doğrulanmış programlarda tekil 1.960 TL yerine paket 1.250 TL; avantaj 710 TL.

## Kabul ve geri alma

Uzak AI metinleri kaydedildikten sonra İzmir2+4=1500,Çorlu2+4=1250; mevcut1+2 tekil öneri,2+2 uygunluğu, yeni paket kapalı program ve Biletinial/Milano ayrımı önizlemeleri yapılmalı. Gerçek müşteri mesajı başlatılmamalı.
Geri alma yalnız #110'a dağıtım öncesi kaynak kodunu geri koymaktır; native2+4/2+2 satış modülleri kapatılmaz. Bu işlem sipariş, ödeme, iade, bilet, kapasite kaydı, Salesbot veya tetikleyici değiştirmedi.

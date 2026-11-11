# Milano Sirki canlı tasarım düzeltmeleri — 15 Ekim 2026

Ana sayfa CTA bağlantıları inline/padding nedeniyle hem telefonda hem masaüstünde çakışıyordu. Milano WordPress global styles #2 katmanına flex/gap, inline-flex, min-height ve responsive sütun kuralları eklendi. Mobilde tam genişlik ve alt alta, masaüstünde yan yana. Mevcut CSS korunarak ekleme yapıldı.

## Canlı uygulanan değişiklikler
- Ana sayfa butonları: 14px gap, minimum48px dokunma hedefi, metin sarma.
- Mobil kart/sütunlar: min-width0, tek sütun; görseller height:auto; başlık boyutları responsive.
- Menü: masaüstünde gereksiz hamburger kaldırıldı; mobil native aç/kapat korunur. 360px açma ve kapama doğrulandı.
- Bilet Al #118: mevcut formun yalnız görünümü; krem seans kartı, grid alanlar, büyük kırmızı ödeme butonu. Form action, hidden alanlar, fiyatlar, adetler ve satış kapısı değiştirilmedi.
- Politika sayfaları #33/34/35/115: h1 28–40px, h2 23–30px.
- Etkinlik sayfaları #125/126/127: 28–48px başlık, kelimeler korunur; sarı#F5C04A korunur.
- Takvim bağlantıları krem ve44px; SSS summary44px; footer hedefleri44px.
- Gutenberg ana sayfa takvim section6: iki paragrafı ayırma ve attribute/markup uyumu. Native Kurtarma ile kalan başlık has-text-color sınıfı onarıldı.
- Karamürsel/Darıca/Derince native h1 Kurtarma uyarıları onarıldı. Kaydedilen tüm metin ve img src değerleri öncekiyle aynı (3/3).

## Kontroller
15 yayımlanmış sayfa: Ana, Şehirler(Yalova burada), Gösterilerimiz, Hakkımızda, SSS, Kurumsal, İletişim, Bilet Al, KVKK, Gizlilik, Çerez, İade, Karamürsel, Darıca, Derince.
360/390/430/768/1024/1440px aynı-origin canlı iframe viewportları:90/90 yüklenmiş,90/90 yatay taşma yok. Dikey scrollbar15px ölçüm clientWidth'e dahil değildir. Fiziksel telefon veya ayrı anonim oturum testi değildir. Masaüstü canlı ekran görüntüleri ve seçilmiş mobil görsel kontrolü yapıldı;90 ayrı tam sayfa görsel karşılaştırması yapılmadı.
Ana CTA mobil yaklaşık52px yükseklik ve14px aralık;1024/1440 yan yana. Gösteri Takvimi tıklaması #gosteri-takvimi hedefine ulaştı. Menü ve footer iç bağlantı hedefleri canlıda açıldı. Instagram/WhatsApp/Maps href doğrulandı; dış platformların giriş ve hizmet kullanılabilirliği E2E doğrulanmadı. Sipariş/ödeme formu gönderilmedi.
Gutenberg: ana ve üç etkinlikte toplam4 başlık kurtarıldı; diğer sayfalarda uyarı görülmedi. Kaynak yedekleri bu klasörde.

## Sınırlar / kalan iş
Ana sayfanın ilk hatalı görüntüsü araçta incelendi fakat ayrı dosya olarak korunmadı. Önce/sonra CSS ve blok yedekleri mevcut; KVKK/Gizlilik için bu çalışmada alınmış önce/sonra görüntüler raporda.
Yalova'nın ayrı yayımlanmış etkinlik sayfası envanterde yok; /sehirler/ ve/bilet-al/ üzerinden denetlendi.
İade politikasında 'çevrimiçi satış henüz aktif değildir' ve diğer politika metinlerinde gelecekte satışa ilişkin eski ifadeler var. Tasarım kapsamı dışındaki hukuki/ticari içerik değişikliği yapılmadı.
Bu kayıt dosyaları canlıya uygulanmış WordPress görünüm değişikliklerinin kaydıdır; main merge/deploy değildir. Madagaskar merkezi altyapısında herhangi bir yazma veya işlem yapılmadı.

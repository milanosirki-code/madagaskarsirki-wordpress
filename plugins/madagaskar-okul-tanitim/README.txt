Madagaskar Okul Tanıtım Yönetimi v1.3.0
================================================

Bu paket madagaskarsirki.com WordPress yönetim paneli için hazırlanmıştır.

İlk kurulumda:
- 2387 okul/kurum başlangıç verisi yüklenir.
- İl/ilçe bazında filtreleme yapılır.
- Durum, personel, etkinlik, son ziyaret ve not tutulur.
- Her okul Google Maps'te açılabilir.
- Program ve gösteri salonları kaydedilebilir.
- WordPress kullanıcılarına Tanıtım Elemanı rolü verilebilir.
- Seçilen okullar personele rota grubu ve ziyaret sırası ile atanabilir.
- Tanıtım elemanı yalnız kendi görevlerini görür ve ziyaret sonucunu kaydeder.
- Her görev tek dokunuşla Google Maps navigasyonunda açılır.
- Seçilen okullardan Google Maps rota bağlantıları üretilebilir.
- Yeni MEBBİS .xls, temiz .xlsx veya .csv dosyaları içeri alınabilir.
- Farklı MEB/temizlenmiş liste sütun başlıkları otomatik eşleştirilir.
- Adresi olmayan kurumlar “Adres Eksik” olarak ayrıca işaretlenir.
- Google Geocoding API ile salon ve okul koordinatları bulunabilir.
- Okullar gösteri salonuna kuş uçuşu mesafesine göre yakından uzağa sıralanabilir.
- Köy/belde/küme evleri/köyiçi gibi açık kırsal adresler otomatik dışarıda bırakılır.
- Mükerrer kayıtlar dedupe hash ile engellenir.

Kurulum:
1. WordPress > Eklentiler > Yeni Eklenti Ekle > Eklenti Yükle.
2. Bu ZIP dosyasını seçin.
3. Şimdi Kur > Etkinleştir.
4. Sol menüden "Okul Tanıtım" bölümünü açın.

Not:
Google Maps rota ekranı v1'de seçilen okulları Google Maps bağlantılarına böler.
Trafik/sürüş süresine göre otomatik rota optimizasyonu ayrı Google Routes API entegrasyonu gerektirir.

V1.3 kullanım sırası:
1. WordPress kullanıcıları bölümünden personele "Tanıtım Elemanı" rolü verin.
2. Okul Tanıtım > Program ve Salonlar bölümünden gösteri programını kaydedin.
3. Görev Dağıtımı bölümünde programı, personeli ve okulları seçin.
4. Personel kendi hesabıyla giriş yaparak Görevlerim ekranını kullanır.
5. Harita Ayarları bölümüne Google Maps API anahtarını girin.
6. Program salonunu ve ardından okul adreslerini koordinatlandırın.
7. “Salondan Yakından Uzağa Sırala” düğmesiyle ziyaret sırasını oluşturun.

# MMC Okul Tanıtım & Saha İş Akışı — 2026-10-07

## Amaç

Okul tanıtım operasyonunu tek sıra halinde yönetmek:

1. MEBBİS verisini aktar.
2. Okul/adres listesini temizle ve fiziksel kampüsleri tek ziyaret noktasına indir.
3. Okul öğrenci sayılarını doğrulanabilir web kaynaklarından tamamla.
4. Öğrenci sayısına göre davetiye/bilet baskı planını çıkar.
5. Etkinlik öncesi saha planını oluştur.
6. Personel görev ataması ve rota oluştur.
7. Ziyaret, teslim edilen materyal ve fiilî erişim sonucunu kaydet.

## Sabit kurallar

- İmam Hatip Ortaokulları tanıtım/rota havuzuna alınmaz.
- Aynı açık adresteki okullar tek fiziksel ziyaret noktası/kampüs olarak gruplanır.
- Aynı kampüs içindeki anaokulu, ilkokul ve ortaokul ayrı okul birimleri olarak öğrenci toplamına katkı verir; rota durağı bir adettir.
- Öğrenci sayısı bulunamayan okul için tahmin yapılmaz.
- Ekranlarda hem **öğrenci sayısı bilinmeyen okul birimi sayısı** hem de bundan etkilenen kampüs sayısı görünür.
- Baskı önerisi otomatik olarak baskı işlemi başlatmaz; planlanan, basılan ve dağıtılan adet ayrı kayıtlardır.

## MMC menü sırası

1. **MEBBİS Veri Aktarımı**
2. **Okul & Kampüs Listesi**
3. **Öğrenci Sayıları**
4. **Davetiye / Bilet Baskı Planı**
5. **Saha Planı & Ziyaret Sonuçları**
6. **Görev Atama / Rota-Görev Planı**
7. **Rota Oluşturma**

Destek ekranları (ham okul listesi, adres eksikleri, kırsal çıkarılanlar, ayarlar) aynı merkezde korunur.

## Veri alanları

Okul ana kaynağı yeni alanları destekler:

- kurum türü / eğitim kademesi
- telefon
- web adresi
- kampüs anahtarı / kampüs adı
- öğrenci sayısı
- öğrenci sayı durumu
- öğrenci kaynağı türü ve URL
- öğrenci doğrulama tarihi
- öncelik
- veri yılı

MMC uyumluluk cache'i aynı alanları salt-okunur biçimde taşır.

## Kampüs tekilleştirme

Öncelik sırası:

1. Açıkça atanmış `campus_key`
2. İl + ilçe + yeterince belirgin normalize açık adres
3. Adres yetersizse kademesi temizlenmiş okul adı

Bu nedenle aynı fiziksel adresteki Sınav Koleji anaokulu/ilkokulu/ortaokulu gibi kayıtlar tek saha durağı olur.

## Baskı formülü

Program bazında politika:

- dağıtım yüzdesi
- yedek yüzdesi
- yukarı yuvarlama paketi
- kampüs minimum/maksimum
- öğrenci sayısı bilinmeyen kampüs için varsayılan adet

Varsayılan:

- dağıtım: %100
- yedek: %0
- yuvarlama: 10
- bilinmeyen kampüs: 0

Örnek: 742 öğrenci, %100 dağıtım, %0 yedek, 10'lu yuvarlama → 750 önerilen baskı.

## Eksik öğrenci verisi

Baskı ekranı aşağıdakileri ayrı gösterir:

- toplam okul birimi
- öğrenci sayısı bilinen okul birimi
- **öğrenci sayısı bilinmeyen okul birimi**
- etkilenen kampüs sayısı
- doğrulanmış öğrenci toplamı

Eksik öğrenci verisi varsa baskı ekranında uyarı çıkar. Sistem eksik sayıyı kendiliğinden tahmin etmez.

## Veri sahipliği

- Okul adı/adres/web/öğrenci ana kaynağı: **Madagaskar Okul Tanıtım**
- MMC: program bağlamı, kampüs görünümü, baskı snapshotı, görev, rota, ziyaret ve saha sonuçları
- WooCommerce/Tickera/PayTR akışına bu değişiklikler dokunmaz.

## Yeni MMC tabloları

- `wp_mmc_school_print_policies`
- `wp_mmc_school_print_plans`

Baskı planı program + kampüs bazında idempotent tutulur.

## Test / canlıya alma

Production'a doğrudan yazılmaz.

1. PHP syntax CI
2. MMC school workflow contract
3. staging/admin smoke test
4. MEBBİS örnek dosya import testi
5. aynı adres kampüs gruplanma testi
6. öğrenci sayısı bilinen/bilinmeyen sayaç testi
7. baskı formülü testi
8. program saha hedefi senkron testi
9. rota ve görev atama smoke testi
10. kontrollü production deploy ve post-deploy doğrulama

Rollback: ilgili plugin sürümlerini önceki production kaynağına döndür; yeni tabloları silmek gerekmez, pasif veri olarak kalabilir.

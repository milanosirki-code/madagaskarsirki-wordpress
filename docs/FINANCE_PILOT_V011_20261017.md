# Finans Yönetici Pilotu v0.1.1 — 17 Ekim 2026

## Amaç ve kaynaklar
Canlı `madagaskarsirki.com` üzerinde kullanıcı tarafından kurulup etkinleştirilen `mdg-finans-yonetici-pilot` v0.1.0 incelendi; staging ortamında pilot kurulu değil. Eklenti V5 finans, MMC köprü ve WooCommerce sipariş haritasını salt-okunur kullanır. Satış/PayTR/Tickera/finans kayıtları değiştirilmedi.

## 0.1.1 değişiklikleri
1. V5 yönetim menüsünün bilinmeyen alt sayfaları gizleme kuralı, yalnız pilot alt menüsü için CSS istisnasıyla aşılır.
2. Bilet iade anaparasının gider hesabına alınmaması kuralı V5 ile hizalanır: WooCommerce ve özel Kırıkkale (#15) Biletinial kaydı.
3. Hiçbir gelir veya gider kanıtı bulunmayan olaylar `Veri yok` olarak gösterilir. Sabit/ortak maliyet yüklenen olaylar toplama katılmaya devam eder.
4. Henüz tahsil edilmeyen manuel gelirleri gösteren bilgi sütunu eklenir; bu tutar kayıtlı gerçekleşmiş gelire eklenmez.
5. Sistem yalnız mevcut PHP veri kaynaklarını ve WordPress yetki denetimini kullanır; yeni kayıt, yeni veritabanı tablosu ve ödeme işlemi yoktur.

## Doğrulanan canlı kanıt
- Pilot v0.1.0 canlıda etkin, staging'de kurulu değil.
- Ekim 2026: 181 gider satırı, 69 gelir satırı, 6 sabit gider satırı.
- Aktif köprü: Madagaskar 13, Milano 4 ayrı etkinlik, mükerrer aktif MDG etkinlik kimliği bulunmadı.
- Sipariş eşleme tablosunda event_id+order_id+order_item_id için tekrar eden kayıt bulunmadı.
- Ekim doğrudan gider 704.526,55 TL, genel/araç gider kayıtları 853.063,18 TL (bu düz tutara iade anaparası dahil değildir çünkü iade kapsamı programdır). Ekim 6 sabit gider toplamı 42.000 TL.
- Bu ham tutarlar KDV/gelir ve mutabakat farklarından dolayı kesin mali rapor olarak kullanılmaz.

## Doğrulamalar
- PHP `php -l` geçti; Node `--check` geçti.
- İade istisnası için dört ayrı PHP reflection senaryosu geçti.
- ZIP arşiv bütünlüğü kontrol edildi.
- Kapsam ve tarih ayrımı: finans kayıt ayı ile programın gösterim ayı aynı olmayabilir. V5 ile karşılaştırılması gerekir.
- WordPress.com oturum koruması nedeniyle yetkili admin HTML/mobil render otomatik görsel olarak doğrulanamadı.

## Staging / canlıya geçiş adımları
1. Plugin ZIP'ini **önce staging** ortamına yükle, etkinleştir; eski V5/MMC eklentilerini değiştirme.
2. `wp-admin/admin.php?page=mdg-finans-yonetici-pilot` ekranını aç.
3. Eylül/Ekim ve Madagaskar/Milano/Markası teyitsiz filtrelerini kontrol et.
4. Aynı tarihlerde V5 özetindeki web gelirini, tahsil edilen diğer geliri, doğrudan gideri, dağıtılmış ortak payı ve sabit payı karşılaştır. Farkları yazarak raporla.
5. Kırıkkale iptal/iade senaryosunda iade anaparasının ikinci gider olmamasını kontrol et.
6. Finans ve müşteri siparişi yaratmadan, satış sayfaları/sepet/ödeme/QR raporlarının salt-okunur smoke kontrolünü gerçekleştir.
7. Kullanıcıdan canlıya geçiş onayı alındıktan sonra v0.1.1 ZIP'i mevcut eklentinin üzerine yükselt, aynı kontrolü canlıda tekrar et.

## Geri alma
Pilot v0.1.1 başarısızsa v0.1.0 ZIP'e dön. V5/MMC/WooCommerce veri ve kodlarına müdahale edilmedi. Finans kayıtları bu pilot tarafından oluşturulmadı ya da dönüştürülmedi.

# Milano / Madagaskar — ayrı marka, tek etkinlik tek satış sitesi

**Durum:** Yalnız geliştirme dalı / taslak PR #235. Canlıya deploy edilmedi. Milano sitesi Coming Soon; bilet satışları kapalı.

## Kesin iş modeli (12.10.2026)
- **Her fiziksel etkinlik bir markaya** atanır: `milano` veya `madagaskar`.
- Milano'ya atanan etkinlik yalnız `milanosirki.com`; Madagaskar'a atanan etkinlik yalnız `madagaskarsirki.com` üzerinden satılır.
- Dünya Organizasyon ortak yönetim, finans, CRM ve WooCommerce/Tickera/PayTR altyapısını kullanabilir; ancak etkinlikler iki sitede birden **satılamaz**.
- Ayrı marka sitelerindeki teklif, e-posta, bilet tasarımı ve müşteri iletişimi ait olduğu markaya uygun olmalıdır.

## Mevcut kod
- **Canlı Milano v0.1.0:** yalnız yöneticiye salt okunur HTML tabanlı önizleme. Güvenilir stok, satış ya da QR sağlamaz. Başlangıçta 5 etkinlik / 13 seans okunmuş olması yeni iş modeline uygun olduğu anlamına gelmez.
- **Bu daldaki Milano consumer v0.2.1:** JWT/checkout değil, sunucu tarafı secret ile salt okunur kaynak; `event_owner_brand='milano'` değilse göstermez; marka alanı yoksa fail-closed. **Canlıya kurulmadı**.
- **Bu daldaki merkez kaynak API v0.2.0:** sadece kanonik marka doğrulama adaptörünün `dunya_milano_canonical_event_owner` filtresinden `milano` döndürdüğü MDG eventlerini besler. Diğerlerini dışlar. **Adaptör henüz yazılıp MDG/MMC veri modelinde doğrulanmadı; bu yüzden gerçek veride boş besleme beklenir.**
- Kaynak API doğru kapasite ve WC variation uyumluluğunu kontrol etmeye çalışır; bu, checkout sırasında nihai stok yetkilendirmesi yerine geçmez.

## Doğrulanması gereken marka kaynağı
Mevcut notlara göre `MMC_Program_Service::create_program()` yeni programlara `brand=Madagaskar Sirki` yazar; ancak MDG event kaydı ile MMC program markasının 1:1 bağlandığı ve tek kanonik kaynak olduğu **henüz doğrulanmadı**. Canlı şema ve eşleştirme görülmeden bu bilgiyi MDG event owner olarak doğrudan okumak **yasaktır**. Eski/eksik/hatalı marka etkinlikleri manuel eşleştirilir; otomatik Milano kabul edilmez.

## Devam geliştirme kapıları
1. MMC program → MDG event → WC ürün/varyasyon → Tickera event bağlarını kanıtla; mevcut satışlı programları değiştirme.
2. Etkinlik oluşturma ekranında zorunlu marka seçimi; yeni etkinlikte otomatik varsayılan yerine bilinçli seçim, mevcut aktif Madagaskar etkinliklerine regresyon koruması.
3. Kanonik owner adaptörünü yaz; end-to-end ve yanlış marka testleriyle doğrula.
4. **Sadece katalog filtresi yetmez:** ürün URL, add-to-cart, WC REST/AJAX, checkout, kampanya, PayTR dönüş, QR ve iade akışları marka/site sahipliğini server-side doğrulasın.
5. Gizli server token sadece server-side yapılandırmaya konur, repo/Drive/JS/HTML'e yazılmaz. Dış Biletinial satışlarının marka politikası ayrıca kontrol edilir.
6. İki markanın arayüz, müşteri iletişimi ve raporlarına ayrıştırma uygula. İkinci bir gereksiz WooCommerce ürün ve QR yaratma.
7. CI negatif marka testleri; eşzamanlı kapasite, iade ve PayTR 3DS testi; ayrı canlı onay, rollback planı, sonra deploy.

**Kaynak:** `docs/MILANO_EXCLUSIVE_BRAND_ROUTING_20261012.md`. Bu belge eski aynı etkinliği iki sitede satma varsayımının yerini alır.

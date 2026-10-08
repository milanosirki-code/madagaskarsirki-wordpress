# Madagaskar Sirki — Bilet Şablonu / Protokol / Fiyat-KDV Canlı Değişiklik Kaydı

**Tarih:** 2026-10-08  
**Canlı site:** https://madagaskarsirki.com  
**Repository:** milanosirki-code/madagaskarsirki-wordpress  
**Durum:** CANLI / doğrulandı

## 1. Amaç

Normal online satış biletleri ile protokol/davetiye biletlerinin tasarımını kurumsal hale getirmek; QR okunabilirliğini korumak; metin taşmalarını düzeltmek; normal biletlerde gerçek ödenen fiyat + %10 KDV ayrıştırmasını göstermek; protokol/davetiye/ücretsiz özel biletlerde fiyat göstermemek.

## 2. Arka plan / filigran tasarımı

Önceki koyu/siyah zemin kaldırıldı.

Yeni tasarım:
- açık krem arka plan: `#f7f2e6`
- düşük opaklıklı Madagaskar Sirki logo filigranı
- logo kaynağı: WordPress medya ID 1500, "Madagaskar Logo"
- QR kod siyah-beyaz tutuldu
- QR için ayrı beyaz güvenli panel eklendi
- QR padding en az 6 olarak korundu
- salon konumu QR alanı beyaz panel içinde tutuldu

Amaç: görsel kurumsallık + yüksek QR kontrastı.

## 3. Aktif Tickera şablonları

Gelecekte satışta olan programlarda kullanılan aktif şablonlar güncellendi:

- #15
- #16
- #17
- #19
- #20
- #23
- #24
- #26
- #27

Her şablon değişiklik öncesi ayrı option yedeğiyle saklandı:
`mdg_ticket_template_<ID>_backup_20261008_watermark`
ve metin taşması öncesi:
`mdg_ticket_template_<ID>_backup_20261008_overlap`
ve fiyat alanı öncesi:
`mdg_ticket_template_<ID>_backup_20261008_price`

## 4. Yazıların üst üste binmesi düzeltmesi

Sorun:
- `venue_name` alanı çok kısa
- `event_terms` yalnız 14 px yüksekliğindeydi
- uzun adres + WhatsApp + koltuk + fatura açıklaması MİSAFİR alanına taşıyordu

Yeni yerleşim:
- event_datetime: x14 / y112 / w430 / h14 / 10.5 pt
- venue_name: x14 / y132 / w430 / h34 / 12 pt
- event_terms: x14 / y174 / w425 / h92 / 9.2 pt
- attendee_name: x14 / y300 / w380 / h18 / 11.5 pt

QR alanına bu düzeltmede dokunulmadı.

## 5. Fatura notu kaldırıldı

Bilet verisinden şu metin kaldırıldı:

"Fatura Bilgilendirmesi: Faturanızı etkinlik günü salon girişindeki görevlilerimizden teslim alabilirsiniz."

Kaldırma işlemi PDF verisi üretilirken filtre katmanında yapılıyor.

## 6. Normal biletlerde fiyat / KDV

Canlı snippet:
- **Snippet #161 — MDG Bilet Fiyat KDV Özeti 20261008**
- aktif
- code_error: yok

Fiyat kaynağı:
- ürün liste fiyatı değil
- WooCommerce siparişindeki ilgili line item'ın **gerçekte ödenen toplamı**

KDV kuralı:
- WooCommerce siparişlerinde vergi satırı şu an 0 olduğu için
- %10 KDV, KDV-dahil ödenen tutarın içinden matematiksel olarak ayrıştırılıyor
- formül: net = toplam / 1.10
- KDV = toplam - net

Örnek:
- toplam 500,00 TL
- KDV hariç 454,55 TL
- KDV %10: 45,45 TL

Normal bilet alanı:
- BİLET BEDELİ
- KDV Hariç
- KDV (%10)
- TOPLAM

Kampanyalı biletlerde siparişte gerçekten ödenen indirimli tutar kullanılır.

## 7. Ücretsiz çocuk / 0 TL normal bilet

Normal satış akışında sipariş kalemi 0 TL ise:
- ÜCRETSİZ BİLET
- Bilet Bedeli: 0,00 TL
- KDV (%10): 0,00 TL
- TOPLAM: 0,00 TL

Bu kural normal satış sistemindeki 0 TL biletler içindir.

## 8. Protokol / davetiye ayrımı

Son karar:
- PROTOKOL / DAVETİYE / ÜCRETSİZ ÖZEL BİLETLERDE fiyat ve KDV alanı **hiç gösterilmeyecek**
- protokol biletinin üst bilet türü başlığı:
  **PROTOKOL DAVETİYESİ**
- normal "Yetişkin 13 Yaş ve üzeri" başlığı protokol biletinde gösterilmiyor
- misafir adı korunuyor
- fiyat alanı veri katmanından tamamen unset ediliyor

Protokol tespiti:
`_mdg_protocol_v1` post meta.

Snippet #161 içinde protokol istisnası uygulanıyor.

## 9. Protokol PDF tasarımı

Canlı protokol PDF düzeni:
- **Snippet #128 — MDG Protokol PDF Düzeni**
- aktif
- code_error: yok

Önceki yedek:
- **#155 — BACKUP — MDG Protokol PDF before watermark 20261008**

Protokol PDF:
- ayrı render mantığını koruyor
- shared Tickera template'i kaydetmeden in-memory hazırlıyor
- QR verisini değiştirmiyor
- protokol için fiyat alanı eklenmiyor

## 10. Fiyat istisnası yedeği

Protokolde fiyat gösterme denemesinden geri dönüldü.

Yedek:
- **#164 — BACKUP — MDG Bilet Fiyat KDV before protocol exclusion 20261008**

Canlı son durum:
- normal bilet = fiyat/KDV var
- protokol = fiyat/KDV yok
- protokol başlığı = PROTOKOL DAVETİYESİ

## 11. Smoke test örnekleri

### Eskişehir ücretli normal bilet
Ticket instance: **#5071**
- sipariş: #5069
- item: #790
- toplam: 500,00 TL
- fiyat özeti: 454,55 + 45,45 = 500,00
- şablon: #20
- PDF render: OK
- QR: siyah-beyaz / güvenli alanlı

### Denizli ücretsiz çocuk
Ticket instance: **#5076**
- sipariş: #5072
- item: #792
- line total: 0
- "ÜCRETSİZ BİLET" sonucu doğrulandı
- eski Fatura Bilgilendirmesi metni yok

### Eskişehir protokol
Ticket instance: **#5078**
- sipariş: #5077
- paket: Nurettin Topçu İlköğretim Okulu
- protokol meta mevcut
- fiyat özeti alanı yok
- başlık: PROTOKOL DAVETİYESİ
- misafir: Musa Özdemir

## 12. 8 Ekim protokol üretim kaydı

Nurettin Topçu İlköğretim Okulu / Eskişehir / 11.10.2026 / 12:00 için 3 native Tickera protokol bileti üretildi:

- #5078 — Musa Özdemir
- #5079 — Esra Durmaz Delioğlu
- #5080 — Emre Delioğlu

Tickera serial codes:
- 0000002113
- 0000002114
- 0000002115

Sipariş:
- #5077
- 0 TL
- completed

Protokol paket durumu:
- ready

## 13. Operasyonel kural

Bundan sonra:

1. Normal online satış bileti:
   - filigranlı açık tasarım
   - QR beyaz güvenli panel
   - gerçek ödenen fiyat
   - %10 KDV ayrıştırması
   - toplam
   - fatura gişe notu yok

2. Kampanyalı bilet:
   - normal bilet mantığı
   - gerçek kampanyalı ödenen tutar esas

3. 0 TL normal bilet:
   - "ÜCRETSİZ BİLET" + 0,00 TL fiyat özeti

4. Protokol / davetiye:
   - fiyat/KDV yok
   - "PROTOKOL DAVETİYESİ" başlığı
   - ayrı protokol PDF akışı korunur

## 14. Geri dönüş

Geri dönüş gerekirse:
- template option yedekleri kullanılabilir
- Snippet #155 protokol PDF tasarımı için önceki sürümü içerir
- Snippet #164 protokol fiyat istisnası öncesi #161 sürümünü içerir

Bu kayıt 2026-10-08 canlı çalışmasının kalıcı teknik notudur.


## 15. İşlem tarihi / bilet no satırı — 2026-10-08 gece güncellemesi

Normal online satış biletlerinde MİSAFİR satırı ile fiyat/KDV özeti arasına yeni bir belge referans satırı eklendi:

- `İŞLEM TARİHİ: dd.mm.YYYY`
- `BİLET NO: <Tickera ticket_code>`

Kaynaklar:
- işlem tarihi = ilgili WooCommerce siparişinin `date_created` değeri
- bilet no = Tickera `ticket_code` post meta

Örnek doğrulama — ticket #5071:
- İŞLEM TARİHİ: 08.10.2026
- BİLET NO: 0000002108
- KDV Hariç: 454,55 TL
- KDV (%10): 45,45 TL
- TOPLAM: 500,00 TL

Şablon yerleşimi:
- attendee_name: y300
- mdg_document_line: y322 / h14 / 9.2 pt
- mdg_price_summary: y342 / h44 / 9.3 pt

Bu satır 9 aktif Tickera şablonunun tamamına eklendi:
#15, #16, #17, #19, #20, #23, #24, #26, #27

Yeni geri dönüş yedekleri:
- Snippet #166 — `BACKUP — MDG Bilet Fiyat KDV before işlem tarihi bilet no 20261008`
- template option: `mdg_ticket_template_<ID>_backup_20261008_docline`

Protokol / davetiye istisnası:
- `mdg_document_line` protokol biletinde üretilmez
- `mdg_price_summary` protokol biletinde üretilmez
- üst başlık `PROTOKOL DAVETİYESİ` olarak kalır

PDF smoke test:
- #5071 normal ücretli: OK
- #5076 normal 0 TL: OK
- #5078 protokol: document_line yok, price_summary yok

Terminoloji notu:
Bilet gerçek e-Fatura/e-Arşiv fatura numarası üretmediği için şablonda “Fatura Tarihi / Fatura No” yerine mali referans olarak “İşlem Tarihi / Bilet No” kullanılmıştır.

# Codex Başlangıç Denetimi — 1 Ekim 2026

Bu rapor, Codex/GitHub çalışma standardı kurulurken canlı Madagaskar ve USKD sistemlerinde yapılan salt-okunur doğrulamanın başlangıç fotoğrafıdır. Bu denetim sırasında sipariş, program, operasyon, Kommo veya USKD canlı içeriğine yazma yapılmadı.

## 1. GitHub / CI

- Çalışma branch'i: `codex/project-standards-20261001`
- Draft PR: #47
- Changed PHP Syntax workflow: başarılı
- PHP hedefi: 8.4
- PR mergeable: evet
- Production deploy: yapılmadı

## 2. Madagaskar sistem sağlığı

`madagaskar/system-health-checks` sonucu:

- Kritik: 0
- Uyarı: 2
- OK: 14

Uyarılar:
1. Aktif zincirde satış nesnesi kapsamı bazı programlarda tam doğrulanmamış görünebilir; program bazlı integrity kontrolü yapılmalıdır.
2. Canlı Kommo token kaynağı `MMC_KOMMO_TOKEN` olarak doğrulanmıştır. Legacy `MS_KOMMO_TOKEN` artık aktif token kaynağı değildir; kaldırma ayrı kontrollü işlem olmalıdır.

Doğrulanan temel kaynaklar:
- WooCommerce aktif
- Tickera algılandı
- PayTR algılandı
- 190 aktif salon
- 22.871 aktif okul
- 2025 nüfus verisi: 81/81 il, 973/973 ilçe
- Kommo API bağlantısı başarılı
- MMC temel veritabanı tabloları mevcut
- 68 aktif Code Snippets kaydı analiz edildi; sağlık kontrolü güçlü çakışma adayı bildirmedi

## 3. Pursaklar — Program #3

- Program: `PRG-2026-ANK-PURSAK-001`
- Tarih: 01.10.2026
- MMC Event: #3
- MDG Event: #16
- Salon: Abdurrahim Karakoç Kongre ve Kültür Merkezi
- Seanslar: 17:30, 19:30
- MDG bridge: bağlı, confidence 100
- MDG publish preview: ready
- Satış mapping coverage: complete
- MDG ↔ MMC ücretli satış mutabakatı: 15 sipariş / 55 bilet / 55 kişi / 20.750 TL; fark 0
- Kommo kaynak consistency: safe; family_2_2 = 2 yetişkin + 2 çocuk, capacity_units=4 doğrulandı

MMC satış defteri ayrıca toplam 24 farklı WooCommerce sipariş kimliği içeriyor:
- 15 processing / ödeme alınmış
- 4 pending
- 5 failed

Bu nedenle MMC `orders_count=24` ile MDG mutabakatındaki `15 ücretli sipariş` farklı kavramlardır.

Operasyon:
- plan: draft
- checklist summary: 42 toplam / 0 tamam
- pre-departure: 21 / 0
- araç, sanatçı, personel, ekipman kayıtları: 0

Saha:
- hedef okul: 76
- atanmış: 76
- ziyaret: 0

Meta planı:
- mevcut
- durum: draft
- kayıtlı harcama / purchase / revenue: 0

## 4. Kırıkkale — Program #2

- Program: `PRG-2026-KIR-MERKEZ-001`
- Tarih: 02.10.2026
- MMC Event: #2
- MDG Event: #15
- Salon: 17 Ağustos Spor Salonu
- Seanslar: 17:30, 19:00
- Program integrity: 17 OK / 0 warning / 0 critical
- MDG bridge: identity, confidence 100
- Tarih/salon/seans/kimlik eşleşmeleri: tam
- Satış mapping coverage: complete
- MDG ↔ MMC ücretli satış mutabakatı: 4 sipariş / 17 bilet / 17 kişi / 6.750 TL; fark 0

MMC satış defteri toplam 9 farklı WooCommerce sipariş kimliği içeriyor:
- 4 processing / ödeme alınmış
- 3 pending
- 1 cancelled
- 1 failed

Bu nedenle MMC `orders_count=9` ile MDG mutabakatındaki `4 ücretli sipariş` farklı kavramlardır.

Operasyon:
- plan: draft
- checklist summary: 42 toplam / 0 tamam
- pre-departure: 21 / 0
- araç, sanatçı, personel, ekipman kayıtları: 0

Saha:
- hedef okul: 101
- atanmış: 0
- ziyaret: 0

Meta planı:
- mevcut
- durum: draft
- kayıtlı harcama / purchase / revenue: 0

## 5. Satış raporu semantiği — dikkat

MMC Sales Service içindeki özet mantığında:

- `orders_count`: ledger'daki tüm farklı `external_order_id` değerlerini sayar; ödeme alınmamış pending/failed/cancelled siparişler de dahildir.
- `ticket_count`, `sold_capacity`, `net_revenue`: ödeme tarihi oluşan siparişler üzerinden hesaplanır.
- `gross_revenue`: ledger'a yazılmış line gross toplamıdır; ödenmemiş sipariş kalemlerini de içerebilir.
- `failed_orders`: failed + cancelled durumlarını ayrıca sayar.

Bu alanlar kullanıcı arayüzünde açık etiketlenmezse “satış siparişi” ile “checkout denemesi / ledger siparişi” karıştırılabilir. Kod değiştirilmeden önce bu semantik korunmalı ve isimlendirme/raporlama ayrı bir düzeltme olarak ele alınmalıdır.

## 6. USKD canlı entegrasyonu

USKD `Yaklaşan Etkinlikler`:
- Page ID: 392
- kaynak: Madagaskar Page ID 1311 / `sehirler`
- tarayıcı tarafında REST fetch + DOM parsing
- gösterilen alanlar: şehir, tarih, salon, seans
- fiyat gösterilmiyor

Canlı Page 392 içeriğinin GitHub yedeği:
`integrations/uskd/live-page-392.html`

Hazırlanan yeni server-side eklenti:
`integrations/uskd/uskd-madagaskar-events/`

Eklenti henüz USKD canlı siteye kurulmadı/aktive edilmedi.

## 7. Açık kontrollü adımlar

- Ayrı `milanosirki-code/uskdernegi-wordpress` repository'si oluşturulmalı; bağlı GitHub aracı yeni repo oluşturma aksiyonu sunmadığı için USKD kaynakları geçici olarak `integrations/uskd/` altında tutuluyor.
- USKD server-side event eklentisi canlıya alınmadan önce staging/draft smoke test yapılmalı.
- Satış özetinde ledger siparişi ile ücretli sipariş terminolojisi netleştirilmeli.
- Operasyon kayıtları gerçek operasyon bilgisi olmadan otomatik tamamlanmamalı.
- Legacy Kommo token temizliği ayrı, geri alınabilir güvenlik işi olarak yapılmalı.

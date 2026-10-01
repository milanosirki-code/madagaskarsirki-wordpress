# Code Snippets → Plugin Geçiş Planı — 1 Ekim 2026

## Amaç

Canlı `madagaskarsirki.com` üzerinde iş mantığının Code Snippets içinde dağılmasını azaltmak; kalıcı kodu GitHub + test + plugin + PR akışına taşımak.

## Başlangıç durumu

Denetim başlangıcında:
- toplam snippet kaydı: 100
- aktif snippet: 68

1 Ekim 2026 kontrollü temizlik sonrası:
- aktif snippet: 64
- sistem sağlık: 0 critical / 2 warning / 14 OK
- Pursaklar #3 integrity: 17 OK / 0 warning / 0 critical
- WooCommerce: erişilebilir
- Tickera bridge: algılandı
- PayTR: aktif
- Kommo: bağlı; token kaynağı `MMC_KOMMO_TOKEN`

## Kapatılan geçici/acil snippetler

Aşağıdaki kayıtlar Code Snippets eklentisinin kendi REST `deactivate` endpoint'i kullanılarak kapatıldı:

| ID | Ad | Neden |
|---:|---|---|
| 99 | ACIL - MMC Region Service Restore | Region Service dosyasını belirli hash'e geri yazabilen acil restore katmanı; ana MMC bootstrap artık Region Service'i doğrudan require ediyor. |
| 100 | ACIL - MMC Admin Region Guard Installer | Admin dosyasına guard yazan installer; guard marker zaten mevcut ve MMC bootstrap yükleme sırası doğru. |
| 98 | GEÇİCİ — MMC Region Guard Patch | Query-parametre ile tek seferlik dosya patch aracı; kalıcı marker mevcut. |
| 97 | GEÇİCİ — MMC Staging Fix Deploy Once | Query-parametre ile birden fazla plugin dosyasını patch eden deployment aracı; production'da sürekli aktif tutulmamalı. |

Bu snippetler silinmedi; geri dönüş için Code Snippets içinde pasif halde tutuldu.

## 1. Öncelik — AI işlem katmanı

Canlıda aktif:
- #72 Program + Salon + Etkinlik + Satış
- #74 Fatura Takip
- #75 Okul Tanıtım
- #77 V5 Finans
- #78 Raporlar ve Müşteri/Bilet
- #79 V4 İade Güvenliği
- #80 Sistem Sağlığı
- #81 MMC Genel Bakış
- #82 MMC Görevler
- #83 Bölge ve Nüfus
- #84 MMC Saha
- #85 MMC Operasyon
- #86 Pazarlama / Meta
- #87 Kommo Güvenli Köprü
- #88 MMC-MDG Köprüsü
- #89 MMC Satış Defteri
- #94 V4 Satış / Erteleme / Aktarım

Bunların büyük bölümü PR #46 altında GitHub kaynaklarına alınmış durumda. Hedef:
1. PR #46 modül bazlı smoke testleri tamamla.
2. Kalıcı servisleri MMC plugin koduna taşı.
3. Ability registration'ı plugin içinde tut.
4. Her modül canlı plugin koduna geçtiğinde ilgili snippet'i tek tek pasifleştir.
5. Her pasifleştirmeden sonra system-health + ilgili modül smoke test çalıştır.

Hepsi bir defada kapatılmamalıdır.

## 2. Öncelik — Kommo / dinamik kaynak

- #70 Madagaskar Kommo Active Events Unified Source
- #66 Madagaskar Kommo Konum Cevapları
- #67 Madagaskar Kommo Konum Menü Kaydı Fix
- #12 MS Kommo Otomatik Bilet Linki

#70 için GitHub kaynağı:
`docs/code-snippets/mdg-kommo-active-events-unified-source.php`

Hedef, dinamik program kaynağını MMC/Kommo servis katmanına taşımak ve public/read-only kaynak contract'ını test altına almaktır.

## 3. Öncelik — Satış / checkout / bilet

- #58 Madagaskar PayTR Bekleme Kilidi V1
- #57 Madagaskar Bilet PDF Adres Fix V1
- #56 Madagaskar Bilet Al Cache Temizleme V1
- #59 MS WhatsApp ve Biletlerim Erişim Düzeltmesi V1
- #51 MS Bilet Al Sayfası V4
- #8 WooCommerce Telefon Zorunlu
- #5 Madagaskar Bilet Tipi Kısaltma

Bu grup yüksek risklidir. Taşıma sırası:
1. mevcut hook ve priority envanteri,
2. checkout regression testi,
3. plugin implementasyonu,
4. snippet ile plugin'in aynı hook'u iki kez bağlamadığını doğrulama,
5. önce plugin test, sonra ilgili snippet deactivate.

## 4. Öncelik — Sayfa/SEO/görünüm

Örnek aktif kayıtlar:
- #14 eski sayfa yönlendirmeleri
- #15 ana sayfa / turne SEO
- #16 şehir URL standardizasyonu
- #17 sosyal paylaşım görseli
- #18 iletişim resmi adres
- #19 hukuki sayfalar
- #20 güvenli sayfa önbelleği
- #21 global alt bilgi
- #22 sitemap
- #23 performans
- #24 SSS dokunma alanı
- #27/#26/#25 Ankara event schema
- #30 dinamik şehirler ve biletler
- #34 şehirler şablonu
- #35 ana sayfa
- #36 gösteriler CSS
- #39 gösteri detayları
- #40 galeri
- #41 hakkımızda
- #42 kurumsal
- #44 SSS
- #46 iletişim
- #48 blog
- #52 blog tekil
- #53 sosyal paylaşım
- #55 hukuki iletişim
- #61 etkinlik tarihi düzenleme
- #64 SEO / Mamak 301
- #60/#62 iptal duyuruları

Bunlar `madagaskar-site-customizations` gibi ayrı, küçük bir site eklentisine veya uygun olanlar tema/blocks katmanına taşınmalıdır. Etkinliğe/tarihe özel tek seferlik kayıtlar kalıcı plugin'e körlemesine alınmamalı; önce hâlâ gerekli olup olmadıkları belirlenmelidir.

## 5. Operasyonel yardımcılar

- #65 Madagaskar Rota Tek Link Paylaşımı
- #71 Madagaskar Finans API

Bunlar ilgili domain plugin/modülünün içine taşınmalıdır:
- rota → okul/saha/rota servisi
- finans API → MMC finance/abilities

## Güvenlik ilkesi

Canlı snippet'in yerine plugin koyarken:
- önce GitHub kodu,
- syntax/test,
- staging/live kontrollü activation,
- ardından snippet deactivate,
- smoke test,
- rollback için eski snippet pasif halde kısa süre saklama
sırası uygulanır.

Snippet doğrudan silinmez; başarılı geçiş ve gözlem sonrası arşivlenir.

## Bilinen raporlama semantiği

MMC sales summary'de `orders_count`, ledger'a girmiş tüm farklı WooCommerce sipariş kimliklerini sayar. Pending/failed/cancelled siparişler de buna dahil olabilir. `net_revenue` ve `ticket_count` ise ödeme alınmış satışı temsil eder.

Bu nedenle UI/API etiketleri ileride şu şekilde ayrılmalıdır:
- ledger siparişleri / checkout kayıtları
- ücretli siparişler
- başarısız/iptal/pending siparişler
- net ciro

Mevcut hesaplama değiştirilmeden önce geriye dönük uyumluluk test edilmelidir.

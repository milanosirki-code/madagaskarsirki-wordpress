# Madagaskar Legacy Redirects

Bu plugin, kaldırılmış eski gösteri URL'lerini güncel kanonik sayfalara kalıcı 301 ile yönlendirir.

| Eski URL | Hedef |
| --- | --- |
| `/akrobasi/` | `/gosteriler/akrobasi/` |
| `/hula-hoop/` | `/gosteriler/hula-hoop/` |
| `/palyaco/` | `/gosteriler/palyaco/` |
| `/tum-gosteriler/` | `/gosteriler/` |

Kapsam yalnız tam path eşleşmesidir. Admin, AJAX ve cron isteklerine müdahale etmez.

Rollback: plugin'i pasifleştir.

## 26 Ekim 2026 — Search Console eski adresler (v1.0.3)

Search Console 404 grubundaki 24 URL canlı HTTP ile kontrol edildi: 20 gerçek 404, dört `share=twitter&nb=1` URL'si X paylaşım ekranına yönleniyor. Bu yama, güncel içerik karşılığı doğrulanan 15 eski yolu tam eşleşmeli 301 listesine ekler.

| Eski yol | Doğrudan hedef |
| --- | --- |
| `/ana-sayfa/` | `/` |
| `/gosteriler__trashed/sahne-arkasi/` | `/sahne-arkasi/` |
| `/sehirler__trashed-2/gaziantep/` | `/sehirler/gaziantep/` |
| `/galeri/basin-gorselleri/` | `/galeri/` |
| `/gosteriler__trashed/akrobasi/` | `/gosteriler/akrobasi/` |
| `/galeri__trashed/basin-gorselleri/` | `/galeri/` |
| `/galeri/fotograflar/` | `/galeri/` |
| `/gosteriler/jonglor/` | `/jonglor/` |
| `/sehirler-2/` | `/sehirler/` |
| `/sehirler-2/mersin/` | `/sehirler/mersin/` |
| `/festival-organizasyonlari/` | `/kurumsal/festival-organizasyonlari/` |
| `/sehirler-2/ankara/` | `/sehirler/ankara/` |
| `/sehirler-2/konya/` | `/sehirler/konya/` |
| `/galeri-2/` | `/galeri/` |
| `/gosteriler-2/` | `/gosteriler/` |

Basın ve fotoğraf üst sayfaları zaten Galeri'ye yönlendiği için yeni kurallar doğrudan son hedefe gider; ikinci bir yönlendirme eklenmez. API adresi, tema wildcard yolu, iki örnek blog ve karşılığı kanıtlanmayan uzun eski blog adresi bu yamaya dahil değildir. Paylaşım parametreleri de değiştirilmez. Sepet/ödeme/sipariş/QR adreslerine yeni kural yoktur.

Canlı hedef kontrolleri: `docs/evidence/search-console-redirect-targets-20261026.json`. Tüm seçilen nihai hedefler HTTP 200 ve sıfır yönlendirme döndürdü. Kaynak v1.0.2 canlı WPVibe readback ile repo main'deki dosyayla eşleşti.

Kontroller: mevcut PHP sözdizimi ve redirect contract CI işleri çalıştırılmalıdır. Dağıtım sonrası 15 kaynakta ilk yanıt 301, Location doğru hedef, nihai yanıt 200 ve yönlendirme sayısı 1 olmalı. `/bilet-al/` ve kapsam dışı bir 404 ayrıca sınanmalı.

Geri alma: yalnız bu eklentinin tam v1.0.2 dosyasını geri yükle; diğer çalışan eklentileri kapatma. Bu branch'in GitHub'da olması canlıya dağıtım kanıtı değildir. Canlı dağıtım ve son testler ayrıca kaydedilecektir.

Aynı incelemede Search Console: Bursa/Eskişehir yönlendirme hatası doğrulaması 26.10.2026 tarihinde başlatıldı; İzmir blogu noindex'i canlıda kalkmış, indeksleme isteği kabul edilmişti. Sepet/ödeme/hesap noindex ayarları korunur.

### Canlı dağıtım sonucu

26.10.2026: WordPress'in mevcut Eklenti Düzenleyicisi ile yalnız `madagaskar-legacy-redirects/madagaskar-legacy-redirects.php` güncellendi. WPVibe bağımsız dosya readback'i test edilen v1.0.3 kaynakla (son satır sonu normalizasyonu dışında) birebir eşleşti. Kod editöründe ilk metin aktarımı tam değişim yapmadığı için kaydetme otomatik incelemede engellendi; canlı değişiklik olmadan aktarım düzeltildi, panodan kopyalanan 2.388 karakter kaynakla birebir doğrulandı ve ardından kaydetme başarılı oldu.

İlk HTTP denemelerinde eski 404 önbelleği görüldü. URL bazlı WPVibe cache purge desteklenen cache motoru bulmadığı için hiçbir şeyi temizlemedi; başarılı purge iddiası yoktur. Yenilenen parametresiz canlı GET testleri 15/15 yeni kuralda tam bir 301 ve nihai 200 doğruladı. Bilet-al 200, mevcut shop/akrobasi 301 ve kapsam dışı tema wildcard 404 korundu. 19/19 kabul kontrolü geçti; kanıt `docs/evidence/search-console-redirects-postdeploy-20261026.json`.

Kod commit'i `0f0c4a46439bfebed3f8234df6159338d69442ce` için 13/13 GitHub Actions işi başarılı. PHP syntax, Legacy redirect contract ve Issue 182 katalog kontratı dahil. Eski katalog testi beklediği sürüm numarası nedeniyle ilk denemede hata verdi; beklenti 1.0.3'e güncellendi, güvenlik kontrolü kaldırılmadı.

PR: https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/296 — draft, main'e merge edilmedi. Canlı kaynak PR branch'indeki plugin koduyla eşleşir. Otomatik merge yapılmadı. Bu işlem ödeme/sipariş/iade/ürün/seans/QR verisi yazmadı; gerçek satın alma testi yapılmadı.

Search Console'daki 404 sayısının düştüğü henüz doğrulanmadı: eski rapor yeni taramayı bekliyor. Kalan beş 404 (API adresi, tema wildcard, iki örnek blog ve karşılığı kanıtlanmayan uzun eski blog) için toplu yönlendirme veya tüm 404 grubunda düzeltme doğrulaması başlatılmadı. Bu 15 URL düzeltmesinin dışında ayrı inceleme gerektirir.

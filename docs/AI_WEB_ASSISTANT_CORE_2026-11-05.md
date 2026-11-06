# A1 — Web asistanı katalog ve hesaplama çekirdeği

Tarih: 5 Kasım 2026. Kaynak: main `83c04d8857ed9d312c872533ae653e6a1fc07393`.
Kapsam: yeni, izole `madagaskar-web-assistant-core` 0.1.0 paketi. Önceki inceleme PR #304, uygulama PR #305.

## Sonuç

Yönetici yetkisiyle çağrılabilen iki servis geliştirildi: `Service::catalogue()` güncel, doğrulanmış seans tekliflerini ve engelleme nedenlerini getirir; `Service::quote(session_id, party, source_revision)` native veriyi yeniden okuyup en ucuz geçerli bilet birleşimini hesaplar. `Party::from_ages()` 0–2 / 3–12 / 13+ sınırlarını doğrular. Sonuçlar rezervasyon veya satın alma değildir.

Yeni plugin hiçbir public endpoint, müşteri arayüzü, cron, aktivasyon migrasyonu veya checkout hook'u kaydetmez. Yalnız sınıfları yükler; kaynak okuma ancak servis çağrısıyla başlar. Canlı WordPress'e yüklenmedi, main birleştirilmedi. LLM/sohbet bağlantısı bu aşamanın kapsamında değildir; yeni anahtar veya ücretli servis kurulmadı.

## Katalog kuralları

- Tek ve iki yönde tutarlı aktif MMC↔MDG köprüsü; tam Madagaskar marka sahipliği ve seçili MDG salon kimliği zorunludur.
- MMC program/etkinlik ve MDG etkinlik/seans durumları birlikte denetlenir. Keşan A0 durum çelişkisi otomatik öneriyi durdurur; kaynak statüsü düzeltilmez.
- Her seansın kendi biletleri okunur. Native WooCommerce varyasyon ebeveyni, yayın durumu, satın alınabilirlik, stok ve fiyat eşitliği gerekir. Kapasite için MDG kaynağı kullanılır; native çocuk/yetişkin ve ortak ana ürün adet sınırları korunur.
- UTC zamanları Europe/Istanbul bağlamında MMC seans tarihi/saatine eşlenir. Seans başlangıcı, satış penceresi, bozuk tarihler ve tam doluluk kontrol edilir.
- Yalnız mevcut Madagaskar HTTPS etkinlik URL'si döndürülür; dış alan adı, query/token, kullanıcı bilgisi veya rastgele bağlantı kabul edilmez.
- Sanal aile paketinin mevcut plugin etkinliği, etkinlik enablement ve fiyat önceliği korunur. İzmir'deki pasif MMC 1.100 TL satırı 1.300 TL etkinlik fiyatını ezmez. Aktif çok birimli MDG türüyle çakışmada aile önerisi kapanır.

## Hesaplama

Para kuruş cinsinden tamsayıdır; model aritmetiği kullanılmaz. Tekil, paket, ek bilet ve çoklu paket birleşimleri karşılaştırılır. 3 Kasım paket kullanım politikası korunur: 1+3 ve 2+1 dahil uygun kişi grupları hesaplanır; üçüncü yetişkin çocuk hakkına sayılmaz. Her paket native 2 yetişkin + 2 çocuk bileşeni ve 4 kapasite tüketimi olarak kalır. Kullanılmayan haklar ve 0–2 yaş kucak çocukları fiili katılımcı sayısı ile kapasite sayısını birbirinden ayırır.

Seçim sonrası kaynak revision değişirse eski seçim reddedilir. Teklif en çok 60 saniye geçerlidir; seans veya satış daha önce kapanıyorsa süre kısalır. Gerçek checkout yeniden doğrulama/rezervasyon sorumluluğunu korur.

Okul davetiyesi, kampanya birleştirme veya toplu satış hesaplanmaz; ilgili ek alanlar sessizce yok sayılmaz, açıkça reddedilir ve destek akışına bırakılır. Müşteri/sipariş/ödeme verisi okunmaz veya yazılmaz.

## Doğrulama

GitHub Actions `Web Assistant Core` PHP 7.4 ve 8.4 üzerinde lint ve davranış testlerini çalıştırır. Testler paket örnekleri, tam kuruş hesabı, yaş 2/3/12/13, bağımsız özyinelemeli optimum oracle, düşük kapasite/ana ürün stoku, geçersiz girdi, marka/köprü/durum/saat/fiyat/URL hataları, kaynak revision ve native-adapter servis akışlarını kapsar. Fixture veritabanı yazıları ve sepet başlatmayı yasaklar. Çalıştırma sonucu ve ilgili commit/run kimliği PR açıklaması ile Drive günlüğünde tutulur.

Yerelde PHP bulunmadı; Docker socket erişimi reddedildi. Bu yüzden yerel PHP çalıştırma başarı iddiası yok; PHP sonuçları gerçek GitHub Actions job/log okumalarıyla doğrulanır. Testler sentetiktir; canlı WordPress, Kommo, guest checkout veya QR kabul testi sayılmaz.

## Staging ve geri dönüş

1. Yüklü MMC/MDG/Woo/aile sürümlerini ve veri eşlemelerini doğrula; yalnız yeni plugin'i staging'e ekle.
2. Yönetici bağlamında katalog ve seçilen session/revision/quote sonucunu incele. Keşan'ın engellenmesini, Milano/geçmiş seansların önerilmemesini, İzmir fallback fiyatını ve native JS aile satırını doğrula.
3. Yeni müşteri arayüzü geliştirmeden önce bu kaydı güncelle. Public controller ayrı permission, rate-limit, kullanıcı mesajı ve erişilebilirlik değerlendirmesi gerektirir. Genel idari ability veya admin token public tarafa taşınmaz.
4. Canlı pilot, ilgili staging/manual kapılar tamamlandıktan sonra ayrı kontrollü dağıtım adımıdır.

Rollback: yalnız yeni plugin'i devre dışı bırak/kaldır. Yeni ayar, veritabanı kaydı, rezervasyon veya zamanlanmış iş yoktur; çalışan satış modülleri ve snippetler kapatılmaz.

## Kayıtlar

- GitHub: https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/305
- Tasarım ve A0: https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/304
- Drive raporu: https://docs.google.com/document/d/1ji2wr6hxwdIr875azZDYHullsEwZjyu735dhR4v3ozQ/edit?usp=drivesdk
- Kaynak rehberi: https://docs.google.com/document/d/1kZN16Djtw-dyX4zw1ZoPNE0QvpP3zjxj6figFnoumhk/edit?usp=drivesdk

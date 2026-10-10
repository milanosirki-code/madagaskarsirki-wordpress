# Finans merkezi — 10 Ekim 2026

İş: #220. Mevcut Gelir, Gider ve Kârlılık Merkezi geliştirilir; ayrı bir finans uygulaması kurulmaz.

## Davranış

- MMC Finans menüsü, mevcut V5 merkezini açar. Yalnız MMC finans yetkisi olan ancak WooCommerce yönetim yetkisi olmayan kullanıcının mevcut erişimi korunur.
- Program seçimi sekmelerde, rapor ayı formunda, yeni gelir/gider formunda ve kayıt sonrası yönlendirmede korunur. Gelir/gider listeleri program ve kayıt ayına göre süzülür; Genel Bakış işletmenin tamamını gösterir. Program özeti bütün tarihleri kapsar ve diğer sekmelerde kısaltılır.
- Manuel gelir, gider ve sabit gider gerekçeyle düzeltilebilir. Önceki ve sonraki değer, kullanıcı ve zaman ayrı geçmiş tablosunda tutulur. Eski silme endpointleri iz bırakmadan silmeyi reddeder. Yanlış maliyet gerekçeli 0 TL düzeltmesiyle kapatılabilir; kayıt korunur.
- Maliyet ile ödeme hareketi ayrıdır. Takip başlatılırken belgelerle teyit edilmiş geçmiş toplam girilir; eski yöntem etiketi otomatik ödeme kanıtı değildir. Geçmiş toplam gerekçeyle düzeltilebilir. Yeni hareketin hesap, tarih, tutar, referans ve gerekçesi bulunur. Kısmi ödeme desteklenir; kalan aşılmaz. Aynı işlem anahtarı tekrar gönderilirse ikinci hareket oluşmaz. Hatalı hareket ters kayıtla kaldırılır; geçmiş korunur.
- Kasa/banka adları yerel muhasebe hesaplarıdır. Ekran yalnız kaydedilmiş hareketlerin netini gösterir; açılış bakiyesi veya ekstre olmadığı için gerçek banka bakiyesi iddia edilmez. Geçmiş teyit toplamı hesap hareketlerine tekrar eklenmez. Para transferi, banka entegrasyonu, gerçek tahsilat veya geri ödeme yapılmaz.
- WooCommerce, Meta ve iade kaynakları kendi sistemlerinden yönetilir; manuel düzeltme ekranından değiştirilmez.
- Canlı PR #208 ortak gider dağıtımı aynen korunur. Ayrı sekmede toplam, dağıtılan ve dağıtılmamış gider görünür. Tutar değişirse eski payların geçersiz sayılması korunur. “closed” otomatik iptal sayılmaz; MDG durumu ve mevcut seyirci dâhil seçimi görünür.
- MMC fatura/teminat/kapanış sayfasına doğrulanmış aktif köprüyle ulaşılır. Eşleşme yoksa başka program seçilmez. V5 ve MMC defterleri toplanmaz ve kayıtlar kopyalanmaz.
- MMC sayfası ve `summary()` yalnız okur. Senkron ve kapanış kaydı oluşturma açık yazma işlemlerinde kalır. `incurred` gider durumu yeni kayıtta ödeme tarihi veya gelir tahsilatı yaratmaz. Otomatik MMC tutarı kaynak dışında değiştirilmez; teminat ödeme durumu güncellemesi korunur.

## Kaynaklar ve kurulum

Canlı V5 kaynağı PR #208 başıyla birebir aynıydı (`2368ccbeb0082cd47acefdbc14479e51f3e7447b`). SHA-256: `1a8b03fe8349c000f4ce2292581d9b1545fa66cd04f7ea8325dd3661ad56e47f`. Bu çalışma mevcut canlı paylaştırmayı main'e taşır. Geçici kurulum snippet'i kullanılmaz.

V5 dosyasına aynı yükleme zincirinde `MDG_V5_Finance_Records` eklenir. İlk yüklemede `mdg_v5_settlements`, `mdg_v5_payments`, `mdg_v5_finance_history` tabloları InnoDB olarak kurulur. Tarihsel kayıtlar taşınmaz veya otomatik ödeme hareketine dönüştürülmez. Hesaplar `mdg_v5_finance_accounts_v1` seçeneğinde tutulur. İşlemler mevcut `manage_woocommerce` yetkisi ve nonce gerektirir. Kaynak başına MySQL kilidi ile ödeme ve düzeltme seri çalışır; hareket ve geçmiş aynı transaction içindedir. Düzeltme ayrıca kaynak tablonun InnoDB olmasını ve eski değer hash'ini kontrol eder.

## Doğrulama

Yerel PHP 8.3: dört dosyada syntax kontrolü; web snapshot, web refund, allocation testleri ve 30 ödeme/düzeltme/bağlam/salt-okuma kontrolü geçti. CI aynı testleri PHP 7.4 ile çalıştırır.

Canlı smoke planı: Finans menüsü; program → gider → gelir → ortak gider → hesaplar sekmeleri; form program seçimi; gerçek kaydı değiştirmeden düzeltme formu; aktif köprü ve MMC dönüş bağlantısı; sayfa görüntülemeden önce/sonra finans kayıt sayıları; yeni tabloların InnoDB ve boş olması; sistem sağlık kontrolleri; herkese açık etkinlik/sepet/checkout okunabilirliği. Finans veya sipariş denemesi üretimde oluşturulmaz.

## Geri alma

V5 ve üç MMC dosyasını deploy öncesi kaynaklarına geri döndürün. Yeni tabloları ve hesap seçeneğini silmeyin; kayıtlar inceleme için korunur. Eski sürüm yeni hareketlerden haberdar olmayacağı için ödeme takibini durdurup tekrar açmadan mutabakat yapın. #208 ortak gider baseline'ını koruyun. Checkout ve satış kodu değiştirilmez.

## Kalan kapsam

Gerçek banka/PayTR mutabakatı ve doğrulanmış açılış bakiyeleri bu sürümde yoktur. MMC ile V5 farklı kayıt sahipleridir; tek giriş menüsüyle bağlanır, otomatik muhasebe birleşmesi yapılmaz. Program sonuçları eksik kayıt ve vergi farklılıkları nedeniyle geçici sonuç olarak sunulur.

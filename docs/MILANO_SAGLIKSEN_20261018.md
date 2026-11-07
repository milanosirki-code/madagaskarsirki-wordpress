# Milano SAGLIKSEN — 18 Ekim 2026

## Durum

Kod ve izole testler hazır; kampanya SATIŞA AÇIK DEĞİLDİR. Üretim ödeme motoruna kod dağıtılmadı. Sayfa 298 taslakta tutulur. Gerçek ödeme/callback/QR, eşzamanlı kapasite ve cihaz üzerindeki görsel testler geçmeden satış açılmamalıdır.

## Canlı inceleme

- Merkezi site: madagaskarsirki.com; Milano: milanosirki.com.
- Kampanya kod doğrulama ve sepet motoru **Code Snippets 124**, `MDG_Corporate_Campaigns_20261005`; kaynak `docs/code-snippets/mdg-corporate-campaigns.php`. Canlı okuma ile main kaynak arasında yalnız sondaki boş satır farkı bulundu.
- Kodlar `mdg_corporate_campaign_codes_v1` WordPress seçeneğinde (wp_options) saklanır; ayrı kampanya tablosu yoktur. Rapor aktif snippet125 ve `_mdg_campaign_*` WooCommerce satır metadatasını kullanır.
- `mdg_campaign_code`, `mdg_campaign_nonce`, `mdg_campaign_action` form alanları; sunucuda normalizasyon, nonce ve güncel katalog doğrulaması vardır. Seçim HMAC ile imzalanır. Sepet miktar ve varyasyonları yeniden doğrulanır; fiyat istemciden alınmaz.
- Eski kurumsal sistem yetişkin başına iki uygun çocuk hakkı verir. Milano sabit paketi bununla karıştırılmamalıdır; ek yetişkin yeni ücretsiz çocuk hakkı doğurmaz.
- Aynı kurumun eski Denizli kampanyası bulundu; bu kayıt değiştirilmedi veya kopyalanmadı. Tam `sagliksen` anahtarı başlangıçta yoktu. Eski kampanya kodları bu arşive alınmadı.
- Canlı handoff0.2.4; Milano ön site handoff0.2.1; shared-events0.1.0; merkezi MDG Bilet3.6.3, MMC1.3.49, WooCommerce11.2.1, Tickera3.6.0.8, Bridge1.7.8, PayTR3.1.2.
- Aile fiyat geçiş snippet180 incelendi; `mdg_campaign_manifest` içeren satırları atlar. Bu, tüm ücret/eklenti etkileşimlerinin uçtan uca doğrulandığı anlamına gelmez.

## Canlı eşleştirmeler

Saatler Türkiye saati; DB `start_at` UTC saklanır. Kaynak: merkezi SQL köprü + herkese açık handoff catalog, 18.10.2026. Tam kimlikler `integrations/milano/sagliksen-20261018/mapping-live.json`.

| Şehir / tarih | MMC program/etkinlik | MDG etkinlik | Seans / yerel saat | Woo ürün | Tickera etkinlik |
|---|---|---|---|---|---|
| Karamürsel 21 Ekim |16/16|28|181 17:30; 182 19:30|6531;6534|6529|
| Yalova 22 Ekim |14/14|24|154 18:00;155 19:30|6318;6321|6316|
| Darıca 23 Ekim |17/17|27|179 17:30;180 19:30|6539;6542|6537|
| Derince 24 Ekim |18/18|26|176 12:00;177 14:00;178 16:00|6547;6550;6553|6545|

Tüm 18 aktif çocuk/yetişkin türü capacity_units=1. Canlı çocuk245 TL, yetişkin490 TL; mevcut family_2_2=1100 TL değiştirilmedi. Anlık kalan kapasite494/492/500/500/497/500/500/500/500 idi; bu anlık okuma satış garantisi değildir.

## Hazırlanan entegrasyon

`milano-sagliksen` mevcut merkezi kampanya sınıfının sign, plan, checkout_manifest, cart_state, prices, item_meta ve kupon/checkout doğrulamalarını kullanır. Yeni sipariş, POS, kapasite veya QR üreticisi içermez.

- Sabit bir pakette 1 mevcut ADULT varyasyonu475 TL ve 2 mevcut CHILD varyasyonu0 TL. Ek ADULT490 TL ve ek CHILD245 TL ayrı rollerdir; fiyatlar güncel merkezi türden gelir. İki paket2 yetişkin +4 çocuk950 TL.
- Paket adedi1 veya2; diğer seçimler ve fiyat/ürün/yönlendirme alanları reddedilir. Sunucu seans, marka, tarih, satış penceresi, bilet bağlantıları ve kontenjanı handoff snapshot üzerinden yeniden doğrular.
- Handoff0.2.5, yalnız geçerli HMAC ve güncel paket planına uyan kampanya satırına Milano giriş izni verir; mevcut normal satış bağlamı korunur. Sepette tüm satır adedi/rolü, tür ve kapasite metadatası tekrar kontrol edilir.
- `mdg_live_sale`, mevcut type_id ve capacity_units=1 ile mevcut merkezi hold/ödeme/bilet hattına gider. Üç/altı miktarlı plan gerçek QR üretimi kanıtı değildir.
- Kampanya mixed cart ve global/order coupons ile birleştirilemez. Paket dışı yetişkin ücretsiz çocuk hakkını artırmaz. Ek yetişkin mevcut raporda yetişkin toplamına dahil edilir.
- Yeni form shortcode `[milano_sagliksen]`, gerçek merkezi katalogdan şehir/seans okur; POST ile merkezde doğrulanan özete, ardından merkezi nonce ile mevcut kampanya checkoutuna gider. Form JavaScript tutarı yalnız önizlemedir.
- Kod yokluğu, eksik bağımlılık veya kapalı kampanya güvenli kapalı sonuç üretir. Registry önerisi active=false, brand=milano, cities=[] ile sevk edilir. cities=[] eski Madagaskar formunun bu kaydı kullanmasını engeller.
- Ön sitede yalnız yeni adaptör yüklenir; mevcut0.2.1 handoff bu görevde0.2.5 ile körlemesine değiştirilmemelidir. Merkezi0.2.4 kaynak ayrı incelenmiştir.

## Test sonuçları

69 yeni izole PHP assertion geçti; gerçek mevcut kampanya `plan/cart_state/prices` fonksiyonları yürütüldü. WordPress DB ve snapshot katmanı test doubles kullanır; gerçek sunucu entegrasyonu testi değildir.

| Senaryo | Sonuç | Kanıt sınırı |
|---|---|---|
|1 yetişkin+2 çocuk|475 TL,3 miktar|İzole gerçek plan/prices yolu|
|2 yetişkin+4 çocuk|950 TL,6 miktar|İzole gerçek plan/prices yolu|
|Tek paket+3.çocuk|720 TL|245 TL ayrıca|
|Tek paket+ek yetişkin|965 TL|490 TL ayrıca; çocuk hakkı artmaz|
|Yanlış/diğer kod, yabancı şehir/seans|Reddedildi|İzole doğrulama|
|Kapalı/geçmiş seans|Merkezi hata iletildi|Mock snapshot; gerçek kapanış testi bekliyor|
|Yetersiz kapasite|Reddedildi|Snapshot miktar testi; yarış testi bekliyor|
|Fiyat/URL/varyasyon manipülasyonu|Reddedildi|İzole doğrulama|
|HMAC/miktar/rol/tür/kapasite/seans değiştirme|Checkout bloke|İzole mevcut cart_state|
|Kupon, mixed cart, sonradan kampanya kapatma|Checkout bloke|İzole doğrulama|
|Mevcut kampanya regresyonları|104 katalog/fiyat+13 rapor+12 fiyat menüsü+14 yönetim kontrolü geçti|PHP harness|
|Mevcut yaş alanı JS|Geçti|jsdom, gerçek cihaz değil|
|Milano formu:4şehir/9seans, paket/ek tutar, sabit POST hedefi|Geçti|PHP render+jsdom; görsel cihaz testi değil|
|PHP syntax|Değişen5 kaynak dosyada geçti|PHP8.3.6|
|Başarılı/başarısız ödeme, callback|YAPILMADI|Gerçek para ve canlı sipariş oluşturulmadı|
|Üç/altı benzersiz geçerli QR ve başarısız ödemede geçersizlik|YAPILMADI|Plan miktarıyla karıştırılmamalı|
|Hizmet bedeli ve son ödeme toplamı|UÇTAN UCA DOĞRULANMADI|Ayar değiştirilmedi|
|Mobil tarayıcı/gerçek cihaz ve kritik runtime logları|TAMAMLANMADI|Yeni kod canlıya dağıtılmadı|

## Test ortamındaki engel

PayTR kaynak alanı `test`; canlı `no`, staging `yes`. Her iki ayar da değiştirilmedi. Staging handoff0.2.2 ve Code Snippets etkin değil. Staging seans150/151 Yalova24;154/155 başka etkinlik26;156–158 etkinlik27; hepsi draft. Canlıdaki154/155 Yalova24 ile eşleşmez. Canlı ID'leri staging'e körlemesine yazmak yanlış gösteriye bağlar. Test ortamının yetkili fixture eşleştirmeleriyle hazırlanması ve güncel kod bağımlılıklarının kurulması gerekir; eski ortamda sahte başarılı ödeme/QR sonucu raporlanmadı.

## Kontrollü dağıtım ve geri alma

1. Önce ayrı prova ortamını güncel marka/köprü/seans/ürün/şablon eşleştirmeleriyle hazırla. Üretim kimliklerini mevcut staging kimliklerine tahminen eşleme. Bilet/e-posta/Kommo dış gönderimlerini izole et; PayTR test=yes doğrula.
2. Mevcut124 ve125 snippetlerinin ve merkezi handoff dosyasının en güncel içerik/hash yedeğini al; eşzamanlı değişiklik varsa bu PR'ı yeniden birleştir. Vendor dosyası değiştirme.
3. Prova için patched124,125 ve handoff0.2.5 + yeni adaptörü yerel eklenti yollarıyla yükle. Kampanya kaydını yalnız test kapsamı doğrulanınca ekle; prod önerisi otomatik açılmaz.
4. Gerçek merkezi sepet→checkout→test ödeme→callback→Tickera bileti/PDF zincirini test et. Tam3/6 benzersiz giriş QR'ı, ücret, kapasite düşümü, başarısız ödeme ve tekrarlı callback testlerini doğrula. Fiziksel kapı okutması ayrıca kayıt altına alınmalı.
5. Üretimde yalnız `sagliksen` anahtarını native option API ile insert et, active=false bırak; mevcut kaydın yokluğunu yeniden doğrula ve tüm kayıt seçeneğini topluca değiştirme. İlgili plugin ability yoktur (203 ability incelendi).
6. Testler ve gerekli açık kullanıcı yayın onayı sonrasında merkezi kaynakları kontrollü dağıt; ön sitede adaptörü yükle. Sayfa298'de hazırlık içeriğini `[milano_sagliksen]` ile yalnız adapter başarılı katalog döndürdükten sonra değiştir; sahte form koyma. Kayıt active=true ve sayfa publish ancak tüm yayın koşulları geçince yapılır.
7. Geri alma: önce kampanya kaydını active=false yap ve298'i taslak tut; bekleyen kampanya sepetleri normal fiyatına döner ve checkout bloke olur. Mevcut kampanya ödemeleri/biletleri uzlaştırılmadan fiyatlandırma sınıfını kaldırma. Sonra adaptörü kapatıp yalnız bu görevin kaynak değişikliklerini geri yükle; diğer kampanyalara/ürünlere/PayTR/QR metadatasına dokunma. Tarihçe ve satış kayıtlarını silme.

## Arşiv

Branch `feat/milano-sagliksen-20261018`; draft PR bağlantısı ve gerçek kapalı kayıt/sayfa readback sonucu son durum notuna eklenir. Kodun GitHub'da olması üretime dağıtıldığı anlamına gelmez.

## Gerçek uygulama ve son okuma

- Draft PR: https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/284 . Main birleştirilmedi; merkezi snippet/eklenti kodu veya ön site satış kodu dağıtılmadı.
- Native WP option patch insert ile yalnız yeni `sagliksen` anahtarı oluşturuldu. Geri okumada active=false, brand=milano, schema=milano_bundle_v1, bundle_price=475, event_ids=[28,24,27,26], cities=[] birebir doğrulandı. Diğer kampanya kayıtları yazılmadı.
- Var olan Milano sayfa298 içeriği match-once korumasıyla güncellendi; ID/slug korundu, yeni sayfa oluşturulmadı. `page-298-prepared.html` ile birebir geri okundu; post_status=draft. Görsel bir satın alma formu eklenmedi. Gerçek form adaptör kodunda hazırdır; henüz siteye kurulmadığından taslakta yanıltıcı satın alma kontrolü gösterilmez.
- Taslak düzenleme: https://milanosirki.com/wp-admin/post.php?post=298&action=edit . Yayın sonrası hedef slug /kampanya/; henüz yayımlanmadı.
- Normal merkezi Milano katalog tekrar okundu: aynı9seans ve245/490/1100TL. Bu katalog kontrolü ödeme veya kapasite yarış testinin yerine geçmez.
- İlk CI güvenlik taraması bir eski kampanya kodunun test örneğini engelledi. Test örneği genel demo değeriyle değiştirildi; güvenlik kuralı gevşetilmedi. Başlangıç commit'i branch geçmişinde tutulmadan temiz kaynakla yenilendi. Yerel 11 güvenlik testi ve tam güncel kaynak taraması başarılı.
- Üretim sipariş/ödeme/ürün/kapasite/QR verisi değiştirilmedi; tahsilat veya mesaj gönderilmedi. Geri alma: kayıt active=false olarak kalır; sayfa298 eski içerik yedeği `page-298-before.html` ile yalnız bu görevin taslak metni geri alınabilir. Çalışan satış kodu değişmediğinden şu aşamada kod rollback'i gerekmez.

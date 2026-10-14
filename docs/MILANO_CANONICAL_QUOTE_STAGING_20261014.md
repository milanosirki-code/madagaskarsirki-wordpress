# Milano merkezi fiyat + kapasite ön kontrolü (14 Ekim 2026)

Bu eklenti ödeme/sipariş/bilet/rezervasyon oluşturmaz. Yalnız merkezi MDG verilerini okur.

- `GET /wp-json/dunya-milano/v2/yalova-preview`: sadece staging sitesinde, sadece `manage_woocommerce` yetkilisine açık. İki Yalova prova seansına 1 aile 2+2 teklifini fiyatlar; satış yetkisi vermez.
- `POST /wp-json/dunya-milano/v2/quote`: Milano sunucusundan merkezî sunucuya özel gizli anahtar ile sunucudan-sunucuya kullanılmak üzere hazırlanmıştır. `DUNYA_MILANO_QUOTE_TOKEN` gizli ortam ayarı yoksa tamamen kapalıdır. Tarayıcıya ve GitHub/Drive'a anahtar konulmaz.
- Her istek önce MMC→MDG tek aktif kanonik köprü ve `brand=Milano Sirki` sahipliğini doğrular; Madagaskar'a düşmez. Merkezi MDG/Woo seans durumu, 2 gerçek varyasyonun parent ve fiyatı, kapasite, satış penceresi ve MMC family_2_2 fiyatı yeniden doğrulanır.
- Yalova testi: #150 ve #151, 2 yetişkin +2 çocuk, 1.100 TL, 4 kapasite birimi. 500 kişi/seans defterinden sadece anlık bilgi döner, stok ayırmaz.
- Bu bir kapasite garantisi değildir. Kesin rezervasyon ancak MDG'nin atomik kapasite kilidi ve tek merkezi sipariş akışında yapılmalıdır.
- Onay ve testlerden önce **yalnız staging'e kurulabilir**, canlı satış başlatılmaz. Geri alma: bağımsız eklentiyi devre dışı bırakmak.
- Gerçek PayTR ikinci alan adı izni, güvenli Milano checkout UI/geri dönüş, 4 bağımsız QR, PDF testleri, callback çift işleme ve iade testleri bekliyor. GitHub #249 ve #250.

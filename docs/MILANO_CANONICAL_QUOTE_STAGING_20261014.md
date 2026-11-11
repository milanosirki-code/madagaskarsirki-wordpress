# Milano merkezi fiyat + kapasite ön kontrolü (14 Ekim 2026)

Bu eklenti ödeme/sipariş/bilet/rezervasyon oluşturmaz. Yalnız merkezi MDG verilerini okur.

- `GET /wp-json/dunya-milano/v2/yalova-preview`: sadece staging sitesinde, sadece `manage_woocommerce` yetkilisine açık. İki Yalova prova seansına 1 aile 2+2 teklifini fiyatlar; satış yetkisi vermez.
- `POST /wp-json/dunya-milano/v2/quote`: Milano sunucusundan merkezî sunucuya özel gizli anahtar ile sunucudan-sunucuya kullanılmak üzere hazırlanmıştır. `DUNYA_MILANO_QUOTE_TOKEN` gizli ortam ayarı yoksa tamamen kapalıdır. Tarayıcıya ve GitHub/Drive'a anahtar konulmaz.
- Her istek önce MMC→MDG tek aktif kanonik köprü ve `brand=Milano Sirki` sahipliğini doğrular; Madagaskar'a düşmez. Merkezi MDG/Woo seans durumu, 2 gerçek varyasyonun parent ve fiyatı, kapasite, satış penceresi ve MMC family_2_2 fiyatı yeniden doğrulanır.
- Yalova testi: #150 ve #151, 2 yetişkin +2 çocuk, 1.100 TL, 4 kapasite birimi. 500 kişi/seans defterinden sadece anlık bilgi döner, stok ayırmaz.
- Bu bir kapasite garantisi değildir. Kesin rezervasyon ancak MDG'nin atomik kapasite kilidi ve tek merkezi sipariş akışında yapılmalıdır.
- Onay ve testlerden önce **yalnız staging'e kurulabilir**, canlı satış başlatılmaz. Geri alma: bağımsız eklentiyi devre dışı bırakmak.
- Gerçek PayTR ikinci alan adı izni, güvenli Milano checkout UI/geri dönüş, 4 bağımsız QR, PDF testleri, callback çift işleme ve iade testleri bekliyor. GitHub #249 ve #250.

## 14 Ekim 2026, v0.1.2 — Tickera/Designer fail-closed guard

PR255 artık aşağıdaki koşulları da teklif öncesinde zorunlu kılar (özellikle markalar arası veya eski üretim nesneleri yanlış bağlandığında):
- MDG seansının Tickera etkinliği eksiksiz bağlı, gerçek `tc_events` türünde ve kanonik Milano başlığında olmalıdır.
- Tickera etkinliğinin `_mdg_event_id` ve `_mdg_production_key` değerleri Woo ürünündeki etkinlik/üretim anahtarıyla eşleşmelidir; boş üretim anahtarı kabul edilmez.
- Woo ürününün `tc_designer_template_id` kaydı bulunan **Milano** isimli aktif Designer şablonuna işaret etmeli, iki ürün varyasyonu da aynı Designer ve üretim anahtarını kullanmalıdır.
- MDG kapak ve salon konum QR medya nesneleri WordPress'te gerçek görsel olmalıdır; ürün `_tc_is_ticket=yes` olmalıdır.
- Halka açık teklif için ortak Tickera etkinliği ve Woo ana ürününün yayında olması, Woo `_allow_ticket_checkout=yes` olması zorunludur. Taslak önizlemede bu kontrollerin yayın kısmı uygulanmaz; ancak gerçek brand/üretim/Designer eşleşmesi yine zorunludur.
- Bu korumalar **checkout hazırlığını** doğrular; sepet/ödeme oluşturmaz, kapasite ayırmaz, PayTR veya dört ayrı Ticket instance yerine geçmez.

### Staging veri delili
- MDG #24 → Tickera #6056, `tc_events/draft`, anahtar `MDG-E24-618c401e2640`.
- Seans #150 → ürün #6058, varyasyon #6059/#6060; seans #151 → #6061, #6062/#6063.
- Altı Woo nesnesinin tamamında `tc_designer_template_id=29`, ortak üretim anahtarı; Designer #29 aktif ve Milano'ya özel. Ürünler taslak, checkout `no`. Bunlar bilinçli olarak satışa uygun değildir.
- Kod GitHub Actions 11/11 geçti; ancak **staging kurulu quote 0.1.0**, PR255 güncel 0.1.2 henüz kurulu değil. Önceki 0.1.1 yükleme denemesi güvenlik denetimi tarafından engellendi. Staging runtime sonucu olarak sunulmamalıdır.

### Ayrı negatif kabul senaryoları
1. Madagaskar MDG #7 / seans #60 ⇒ `brand` reddi.
2. Milano MDG #24 + ilgisiz seans #60 ⇒ `session` reddi.
3. Tek istekte 11 aile paketi ⇒ `qty` reddi.
4. Çocuk=0, yetişkin=0, aile=0 ⇒ `qty` reddi.
5. Üründe yanlış Designer ID veya Tickera üretim anahtarı ⇒ `milano_ticket_integrity` reddi.
6. `_allow_ticket_checkout=no` ya da taslak durum ⇒ herkese açık teklif reddi.
7. İmzalı sunucu erişim anahtarı tanımsız ⇒ `not_configured` (503), erişim yok.
8. Kalan kapasite seçilen kişi sayısından az ⇒ `capacity` reddi.
9. Yalova staging admin ön izlemede 18:00 ve 19:30, aile 1.100 TL, 4 birim, toplam kapasite 500.
10. Hiçbir adımda gerçek sipariş, hold, PayTR, bilet/QR veya müşteri bildirimi oluşturulmamalı.

**Onay kapısı:** Tüm negatif senaryoların gerçek staging çalıştırması ve dört benzersiz bilet QR/pdf testi olmadan PR merge veya canlı satış yapılmaz. `milanosirki.com` ön yüzünden tek merkezî sipariş/PayTR ödeme yönlendirmesi de henüz yazılmadı.

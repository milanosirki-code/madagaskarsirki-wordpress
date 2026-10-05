# Ortak kurumsal kampanya kataloğu canlı kaydı

5 Ekim 2026. PR #133. Kullanıcı şifresiz, her il ve haftanın satış programını kodla açan otomatik ortak sayfa istedi.

Yeni snippet 124 aktif, code_error null. Eski Denizli pilot snippet 123 pasif, code_error null. Yalnız kampanya sayfa 4520 başlığı Kurumsal Kampanyalar yapıldı ve shortcode mdg_corporate_campaigns ile değiştirildi. URL /kurumsal-davetiye-pilot/ korunur; password boş. Mevcut normal sayfalar, ürünler, siparişler, PayTR veya checkout hook'ları değiştirilmedi. Yeni admin menüsü WooCommerce → Kurumsal Kampanyalar; kurum kodları yeni registry option'ında yönetilir. Gerçek kurum kodu henüz eklenmedi.

PHP lint ve 37 regression kontrolü geçti. Canlı HTTP testleri:

- Kodsuz GET 200: parola yok; etkinlik kartı yok; yalnız kod formu.
- test-denizli ve TEST-DENİZLİ POST 200: yalnız etkinlik 12; seans 99/100, 17:30/19:30, yetişkin 500 TL.
- TEST-İZMİR POST 200: yalnız etkinlik 10; seans 88/89/90, 12/14/16, yetişkin 600 TL.
- test-manisa POST 200: yalnız etkinlik 23; seans 147/148/149, 12/14/16, yetişkin 500 TL. ADULT/CHILD mapping çalışıyor.
- Hatalı kodda kartlar açılmıyor ve geçersiz kod mesajı var.
- Denizli 99, 1 yetişkin+2 çocuk hesaplama 500 TL; farklı il seans 88 reddediliyor; 1 yetişkin+3 çocuk reddediliyor.
- Normal /bilet-al/ ve /sepet/ 200: kampanya formu sızmıyor, fatal notice yok. /odeme/ boş sepet yönlenmesi ayrı smoke kontrolüdür; gerçek ödeme testi değildir.

Yeni etkinliğin aynı il kodunda otomatik görünmesi, kapalı/geçmiş/dolu seans ve kapatılmış/süresi dolmuş kurum kodu regresyonlarda test edildi; canlı etkinlikler test için değiştirilmedi. Kurum yönetimi admin formu yetkili tarayıcı üzerinden uçtan uca henüz test edilmedi.

Platform GET Cache-Control public max-age=300/s-maxage=600 döndürüyor; no-cache talebinin host tarafından bütünüyle uygulandığı varsayılmaz. Ana kod/hesap akışı POST ile her istekte ortak kaynağı okur. GET kodlu bağlantılar host cache süresince gecikebilir.

Snippet activation/deactivation endpoint'leri 500 döndürdü; yazmalar tekrar edilmedi. Hedef read-back ile 124 aktif ve 123 pasif, her ikisinde code_error null doğrulandı; frontend/render/POST testleri sağlıklı. 500 origin nedeni kanıtlanmadı.

Bu hâlâ etkileşim/hesap denemesidir. Gerçek kupon, ödeme ve QR bilet üretimi bu sürümde yoktur. Rollback: 124'ü pasifleştir, 123'ü etkinleştir, 4520 shortcode'unu eski hale getir. Registry kayıtlarını silme.

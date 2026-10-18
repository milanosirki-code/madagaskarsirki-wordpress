# Milano bilet seçimi — adet kontrolleri ve özet, 18 Ekim 2026

Durum: test ortamında hazır; canlıya henüz uygulanmadı. Kullanıcı, ilk üç tasarım maddesinin yayınından sonra “Devam” dedi. Kapsam yol haritası4–5: bilet adet kontrolleri ve ödeme öncesi seçim özeti.

## Değişiklik
Mevcut milano_merkezi_bilet shortcode'u ve sunucudan üretilen formlar korunur. Core/html geliştirme bloğu bilet sayfasına eklenir. JavaScript yalnız arayüzü zenginleştirir: okunaklı saat başlığı, tarih/şehir, artı/eksi, adet/satır tutarları, toplam kişi ve bilet toplamı. Aile2+2 dört kişidir. Hizmet bedeli ve kesin toplamın ortak ödeme ekranında gösterildiği yazılıdır. Fiyatlar mevcut form etiketlerinden kuruş cinsinden okunur; backend'e price/amount gönderilmez. GET action, input adları, event/session kimlikleri ve mevcut ödeme/kapasite/QR sunucu doğrulamaları korunur. Ek ağ isteği, eval veya depolama yoktur.

Upstream başlık/fiyat biçimi tanınmazsa ilgili form değiştirilmez. JavaScript çalışmazsa orijinal form kalır. Sıfır/geçersiz/negatif/kesirli/aşırı adet ve aile dahil20yetişkin/20çocuk sınırı kontrol edilir. Saf arayüz özeti ödeme tutarının otoritesi değildir; merkezi sistem yeniden doğrular.

## Kaynak ve yedek
Canlı bilet sayfa118. Önceki tam içerik WordPress draft293. Yeni kaynak draft294. Staging6129 aynı Assembler temasında, gerçek sayfanın render edilmiş örnek formlarıyla test edilir; milano_bilet_odeme bayrağı kaldırılmış ve action=# yapılmıştır, sipariş/ödeme yolu tetiklenmez. Telefon iframe sayfası6131.

## Kontroller
node --check geçti. JSDOM testleri9seansın GET action/method/hiddenID/payload alanlarının korunmasını; yetişkin/çocuk/aile toplamlarını; aile4kişi; artı/eksi sınırları; aggregate limit; geçersiz adet; sıfır submit; tekrar init ve upstream fallback senaryolarını doğruladı.
2yetişkin+1çocuk+1aile=7kişi/2325TL,1aile=4kişi/1100TL. @wordpress/blocks core/html validasyonu isValid=true. WordPress draft readback ve sunulan JavaScript kaynakla eşleşti.
WordPress shortcode işleyicisinin inline script içindeki bazı && işleçlerini entity'ye dönüştürmesi staging'de fark edildi. Script eşdeğer koşullarla bu dönüşüme maruz kalmayacak biçimde yazıldı; sunulan JS byte karşılaştırması ve syntax kontrolü geçti.
Masaüstü ve375pxiframe(tarayıcıscrollbarıyla360pxclient) kontrollerinde taşma yok, artı/eksi ve özet çalışır. Gerçek cihaz ve gerçek tahsilat/PDF/QR teslimi test edilmedi.

## Yayın ve geri alma
Önce güncel sayfa118 içerik karşılaştırması; geliştirme bloğunu mevcut kaynağın sonuna ekle; readback ve canlı örnek seçimleri kontrol et. Test formları/fixture asla canlı kaynak olarak kullanılmaz. Geri alma yalnız eklenen core/html bloğunu kaldırmaktır; gerekirse draft293 ile önceki kaynağı karşılaştır. Sipariş/ürün/kapasite/ödeme/QR verisine dokunulmaz.

## Tekrar test
Bu dizinde npm install --ignore-scripts ve npm test. Node syntax kontrolü: node --check selection.js. enhancement.blocks.html, selection.js ve selection.css'nin dağıtım karşılığıdır; UI-only mantığı backend fiyat yetkilendirmesi olarak kullanma.

Önizleme: https://staging-f321-milanosirki-lgajf.wpcomstaging.com/milano-bilet-secimi-onizleme-20261018/?design=3#yalova
Telefon: https://staging-f321-milanosirki-lgajf.wpcomstaging.com/milano-bilet-telefon-testi-20261018/?design=3
Madde6(seans derin bağlantıları) ve7(merkezi ödeme marka görünümü) henüz uygulanmadı.

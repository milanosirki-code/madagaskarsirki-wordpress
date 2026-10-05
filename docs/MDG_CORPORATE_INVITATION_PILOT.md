# Kurumsal çocuk davetiyesi Denizli pilotu

Kullanıcı normal sayfaları etkilemeyen bir pilot istedi ve ilk kurum olarak test kurumunu seçti. İlk aşama şifreli, menüye eklenmeyen, noindex bir etkileşim prototipidir. Seans, kod ve yetişkin/çocuk adetleriyle hesaplama yapılır; gerçek kampanya, ödeme veya QR bilet açılmaz.

## Doğrulanmış canlı eşleşmeler 5 Ekim 2026

Etkinlik 12: 8 Ekim 2026, Denizli Büyükşehir Belediyesi Kongre ve Kültür Merkezi, Özay Gönlüm Salonu.

| Seans | MDG seans | Woo ana ürün | Çocuk varyasyonu | Yetişkin varyasyonu |
| --- | --- | --- | --- | --- |
| 17:30 | 99 | 2425 | 2426 | 2427 |
| 19:30 | 100 | 2428 | 2429 | 2430 |

Canlı REST okumasında yetişkin 500 TL, çocuk 250 TL. Prototip yetişkin fiyatını her çizimde WooCommerce üzerinden okur; kayıtlı sabit fiyat kullanmaz. Varyasyonun ana ürün, etkinlik ve seans eşleşmesi bozuksa hesaplama durur. Bu eşleşmeler yalnız bu tarih için geçerlidir.

## Sınırlar

- Test kodu `TEST-DENIZLI`; gerçek WooCommerce kuponu değildir.
- 1 veya 2 yetişkin; yetişkin başına en fazla 2 ücretsiz çocuk; 3–12 yaş dahil.
- 0–2 yaş mevcut kurala göre ücretsiz; forma dahil edilmez. 13+ yetişkin.
- Form yalnız hesaplama yapar. Veri saklamaz, kapasite ayırmaz, mesaj göndermez.
- Sepet, checkout, PayTR, sipariş, QR veya scanner hook'u yoktur.
- Eklenen hook'lar yalnız özel shortcode ve o shortcode bulunan sayfanın robots filtresidir.
- CSS özel kapsayıcı altında; tema ve genel CSS değiştirilmez.
- Form nonce ve sunucu tarafı doğrulama kullanır; şifreli sayfa korumasını dolaylı shortcode renderinde de denetler.

## Uygulama

1. Ayrı branch, syntax/policy/render testleri, açıklamalı draft PR.
2. Code Snippets üzerinde PHP açılış etiketi çıkarılmış aynı dosyayı yeni snippet olarak pasif oluştur; mevcut snippetlere dokunma.
3. Yeni şifreli sayfa: `kurumsal-davetiye-pilot`, içerik `[mdg_corporate_invitation_pilot]` shortcode bloğu. Menüye ekleme.
4. Yalnız bu test shortcode snippetini etkinleştir. Bu işlem gerçek kampanyayı açmaz.
5. Şifresiz erişimde form/nonce görünmemesini; şifreyle form ve 1+2/2+4 hesaplarını; hatalı kod/1+3 engelini kontrol et.
6. Normal bilet sayfası, sepet ve checkout erişimini smoke test yap.

## Gerçek kampanyaya geçiş kabul kriterleri

Bu prototip ücretsiz gerçek bilet üretiminin tamamlandığı anlamına gelmez. Sonraki ayrı patch öncesinde kurum adı, doğrulama şekli, seans kotası ve son kullanma tarihi belirlenir. Ortak kod üyeliği kanıtlamaz. Tek kullanımlık kurum kodları seçilebilir.

Gerçek akış mevcut yetişkin ve çocuk varyasyonlarını aynı sipariş/seansta kullanmalı; indirimi yalnız tanımlı kampanya çocuk satırlarına uygulamalıdır. Yetişkin silinirse, seans değişirse, adetler artırılırsa veya başka indirim eklenirse kural sunucuda tekrar doğrulanmalıdır. Normal çocuk bilet fiyatı değiştirilmez. Tek bir ücretli yetişkinin sınırsız ücretsiz çocuk siparişinde yeniden kullanılmasına izin verilmez. Kota eşzamanlı isteklerde atomik korunmalıdır.

Ortak kapasite için mevcut `MDG_Live_Sales` ve `MDG_Capacity` akışının canlı karşılığı doğrulanmalı; ücretsiz çocuklar da kişi olarak sayılmalıdır. Yalnız başarılı yetişkin ödemesi sonrası Tickera QR üretimi, başarısız ödeme/iade halinde giriş geçersizliği, doğru PDF seansı ve tek kullanımlık check-in gerçek entegrasyon testlerinden geçmelidir. Bu prototip bu entegrasyonları test etmez.

İlk gerçek pilotta çocuk ve yetişkin QR'leri aynı sipariş/seans üzerinden görevli tarafından birlikte doğrulanabilir. Otomatik bağlı yetişkin check-in engeli, Checkinera/Tickera canlı hook sözleşmesi incelenmeden varmış gibi sunulmamalıdır.

## Testler ve geri dönüş

`php -l docs/code-snippets/mdg-corporate-invitation-pilot.php`

`php tests/corporate-invitation/pilot.php`

Policy testleri geçersiz kod/seans, yetişkinsiz giriş hakkı, çocuk sınırı, negatif/ondalık/dizi girdileri, fiyat eksikliği, eşleşme sapması, şifre koruması, nonce ve render çıktısını kapsar. Bu testler gerçek ödeme veya QR smoke testinin yerine geçmez.

Rollback: yalnız yeni snippet'i pasifleştir; yalnız pilot sayfayı taslağa al. Gerçek bilet, sipariş ve kapasite kaydı olmadığı için domain veri geri dönüşü gerekmez. Mevcut satış modüllerini kapatma.

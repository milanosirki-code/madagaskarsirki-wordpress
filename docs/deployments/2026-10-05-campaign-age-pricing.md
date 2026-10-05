# Kurumsal kampanya yaş bazlı hesaplama canlı kaydı

5 Ekim 2026. PR #134 (PR #133 branch'i üzerine). Yalnız mevcut kampanya snippet 124 kaynak kodu güncellendi. PUT ve read-back: active=true, code_error=null. Sayfa 4520 URL/şifre/başlık korunur. Ürün fiyatları, normal sayfalar, PayTR, siparişler ve gerçek bilet kayıtları değiştirilmedi.

Kurallar: 0–2 yaş ücretsiz/hak tüketmez; 3–12 dahil her ücretli yetişkin bileti için iki ücretsiz çocuk, fazlası normal çocuk fiyatı; 13+ yetişkin fiyatı ve yetişkin bileti sayısı. Formda aynı kişi hem yetişkin sayısına hem çocuk yaş listesine yazılmaz. Güncel seans Woo fiyatları kullanılır. Yaş/kişi sayısı sunucuda doğrulanır; JS yalnız anlık önizlemedir. Form 1–10 yetişkin, 0–20 yaş girdisi destekler.

Doğrulama:

- PHP 8.3 lint; 57 regression kontrolü geçti.
- Embedded JS node syntax kontrolü geçti.
- jsdom DOM testi: çocuk sayısına göre alan oluşturma, değerleri koruma, yetişkin artırınca üçüncü çocuğun ücretli→ücretsiz dönüşümü, fiyat/seans değişimi, 0/2/12/13 sınırları, eksik yaş ve sıfır çocuk geçti. Bu gerçek tarayıcı görsel testi değildir.
- Canlı HTML'de yaş inputları, inline UI scripti ve yetişkin değişim handler'ı teslim ediliyor.
- Canlı read-only POST: Denizli 1 yetişkin+3 çocuk (5,7,12) 750 TL; 2+3 1000 TL; 2+5 1250 TL.
- Canlı 0/2/3/12 yaşları, 1 yetişkin: 500 TL; 0–2 hak tüketmiyor.
- Canlı 13/3/5/12 yaşları, 1 yetişkin: 2 yetişkin bileti+3 ücretsiz çocuk, 1000 TL.
- İzmir 1 yetişkin+3 uygun çocuk: yetişkin600+çocuk300 = 900 TL.
- Yaş sayısı seçilen çocuk sayısıyla uyuşmazsa hesap reddediliyor.
- Normal Bilet Al HTML'i açılıyor; kampanya yaş alanı sızmıyor.

Canlı smoke scripti tests/corporate-invitation/live-age-smoke.py yalnız deneme hesaplamalarına POST gönderir; gerçek sepet/sipariş/ödeme/bilet yaratmaz. İlk ağ denemesi proxy tünel zaman aşımı verdi; yeniden çalıştırılan test tüm senaryoları tamamladı.

Gerçek ödeme ve QR bilet üretimi hâlâ kapalıdır. Rollback: yalnız snippet 124 kodunu PR #133 sürümüne döndür; kurum kodlarını/ürünleri/siparişleri silme. Mevcut host GET cache sınırlamaları PR #133 kaydındaki gibidir; POST ana hesaplama kaynağı yeniden okur.

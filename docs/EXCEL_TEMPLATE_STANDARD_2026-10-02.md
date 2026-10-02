# Madagaskar — Yeni Etkinlik Excel Şablonu Standardı — 2026-10-02

## Karar

Bu değişiklik yalnız bundan sonra hazırlanacak/yayınlanacak etkinlikler için geçerlidir. Geçmişte yayımlanmış veya satışta olan etkinliklerin mevcut SSS ve kuralları geriye dönük olarak değiştirilmez.

## Sabit standartlar

- Aile Paketi: 2 yetişkin + 2 çocuk.
- Bilet yalnız üzerinde yazılı etkinlik tarihi ve seans için geçerlidir.
- Organizatör kaynaklı iptal, erteleme veya tarih/seans değişikliği olmadığı sürece iptal/iade/değişiklik yapılmaz; zorunlu mevzuat hakları saklıdır.
- Kullanılmayan biletler geçersizdir; kullanılmayan bilet için bilet bedeli iadesi veya seans değişikliği yapılmaz.
- Organizatör kaynaklı program değişikliğinde süreç, organizatör duyurusu ve biletin satın alındığı satış kanalının koşullarına göre yürütülür.
- Üçüncü taraf satış kanalındaki hizmet bedeli iadesi ilgili kanal koşullarına tabidir.

## Excel SSS standardı

1. Gösteri ne zaman ve nerede?
2. Bilet fiyatları nedir?
3. Aile paketi nedir?
4. Oturma düzeni nasıl?
5. Çocuklar tek başına katılabilir mi?
6. Biletlerde iptal, iade veya değişiklik yapılabilir mi?
7. Biletimi gününde veya seans saatinde kullanmazsam ne olur?
8. Etkinlik iptal edilir, ertelenir veya program değişirse ne olur?

## Kod durumu

- Kanonik repository değişikliği: PR #96, main'e squash merge edildi.
- Live MMC sürümü: 1.3.47.
- Canlıda geçici/operasyonel köprü: Code Snippets ID 115.
- Snippet yalnız `admin_post_mmc_download_publish_content_template` aksiyonunu priority 1 ile karşılar ve şablonu üretip `exit` eder.
- Mevcut import mantığı değişmez; yeni Excel yapısı mevcut `YAYIN_ICERIGI` ve `SSS` import akışıyla uyumludur.

## Rollback

Snippet 115 devre dışı bırakılırsa canlı sistem tekrar MMC eklentisinin yerleşik Excel şablon üreticisini kullanır. Veri tabanında geçmiş etkinlik kaydı değiştirilmediği için rollback veri migrasyonu gerektirmez.

# Kommo CRM satış takibi — 12 Ekim 2026

## Canlı yapılandırma
Kullanıcının CRM satış takibi kurulumunu sürdürme talebi kapsamında native Kommo yapılandırması kaydedildi. WordPress/PHP, sipariş ve ödeme kodu değiştirilmedi.

- Ana satış hattı 14267487, takip edilecek aşaması 110281307: görev tetikleyicisi 38150350. Aşamaya geçişte mevcut sorumluya Takip görevi, 1 gün süre. Görev açıklaması ödeme/sipariş doğrulaması, şehir-etkinlik-seans kontrolü ve sonuç kaydını istiyor.
- Mevcut 37757972 tetikleyicisi satın alma bekleniyor aşamasından bir gün sonra takip aşamasına geçiriyor. Yeni görev süresi bu geçişten itibaren başlar.
- Kurumsal Talepler hattı 14357351, Teklif yapıldı aşaması 110895651: görev tetikleyicisi 38150364. Aşamaya geçişte mevcut sorumluya Takip görevi, 3 gün süre. Kurum/yetkili, teklif tarihi, şehir/etkinlik, katılımcı sayısı ve tutar kontrol edilip görüşme sonucu kaydedilmeli.
- Her iki tetikleyici için mevcut aşama kayıtlarına toplu uygulama kapalı. Gerçek aşama geçişinden görev üretimi henüz gözlenmedi; kaydedilmiş yapılandırma doğrulandı.

## Şehir görünümleri
Tüm satış hatlarında aktif aşamaları ve Şehir alanı 2448378'in tam metin eşleşmesini kullanan kaydedilmiş görünümler:
| Görünüm | Gözlenen aktif CRM kaydı |
|---|---:|
| CRM — Manisa / aktif kayıtlar | 6 |
| CRM — İzmir / aktif kayıtlar | 0 |
| CRM — Aydın / aktif kayıtlar | 16 |
| CRM — Uşak / aktif kayıtlar | 23 |
| CRM — Ankara / aktif kayıtlar | 180 |

Şehir alanı boş, ilçe veya farklı yazımla kayıtlı müşteriler dışarıda kalır. İzmir 0 sonucu o şehirde müşteri bulunmadığını göstermez. Bunlar tekil kişi veya iletişim izinli kampanya kitlesi değildir. Ana hatla sınırlı ek Manisa görünümü de ayrı adla kayıtlıdır.

## Toplu CRM ekranı
Canlı adres: https://milanosirki.kommo.com/dashboard?sel=all&period=month&pipeline_id=14267487

Native sayı widget'ları kaydedilip yeniden yüklenen ekranda doğrulandı:
- CRM — Ankara / aktif kayıt: 180.
- CRM — Satın alma takibi: ana satış hattındaki 110281307 aşaması, 1 kayıt.
- CRM — Kurumsal açık teklifler: kurumsal hattaki 110895651 aşaması, 0 kayıt.

Mevcut dashboard mesajlar, kaynaklar, görevler, aktif/kazanılan/kayıp CRM kayıtlarını da gösteriyor. Tutarlar CRM fırsat değeridir; WooCommerce/PayTR tahsilatı değildir. Sayı widget'ları mevcut durum toplamını gösterir; ekrandaki “bu ay” ifadesi bunların yalnız aylık kayıtlar olduğunu kanıtlamaz. Kommo resmi dashboard belgeleri entity widget'ların tarih filtresinden bağımsız olduğunu belirtir:
https://support.kommo.com/docs/manage-your-dashboard
https://support.kommo.com/docs/create-custom-widgets-on-your-dashboard

## Açık işler ve doğrulama sınırları
- AI otomatik şehir ve satış aşaması kaydı eklenmedi. Mevcut AI eylemleri incelendi, taslak iptal edildi; WooCommerce etkinlik şehri alanını serbest sohbetten değiştirmek sipariş eşleşmesini bozabilir.
- Şehir eksik/farklı yazım temizliği ve ayrı ilgi şehri yakalama tasarımı sonraki iştir.
- Eski kayıtlar için toplu görev açılmadı; yeni geçişlerden görev üretimi izlenmeli.
- CRM kazanıldı aşaması gerçek tahsilat raporu yerine kullanılamaz.
- Müşteriye kampanya mesajı, ödeme/iade işlemi veya toplu kayıt değişikliği yapılmadı.

## Geri alma
Native otomasyon ekranından yalnız 38150350 ve 38150364 tetikleyicilerini devre dışı bırakın. Oluşturulmuş görevler ayrıca incelenmelidir. Şehir görünümleri ve yeni widget'lar native arayüzden kaldırılabilir; mevcut diğer otomasyonlar korunmalıdır.

## Kanıt
Canlı dashboard ekran görüntüsü kommo-crm-sales-20261012.jpg olarak mevcut proje Drive arşivine kaydedilecektir. Müşteri adları, telefonları, tokenlar ve sipariş kimlikleri bu kayda eklenmedi.

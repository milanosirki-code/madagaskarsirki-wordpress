# Bilet erişimi ve WhatsApp yeniden gönderim — 9 Kasım 2026

## Canlıda tamamlanan işler

- 2–9 Kasım dönemindeki 18 ilgili Kommo sohbeti ayrıntılı incelendi. Bu sayı tam hafta toplamı değildir; anahtar kelime ile bulunan örneklemdir.
- İlk bilet erişim talebinde doğrudan insan aktarımı yerine doğrulanmış kişisel link veya `https://madagaskarsirki.com/biletlerim/#ms-bilet-sorgu` öneriliyor.
- Sipariş numarası bulunamıyorsa veya form denendiği halde sonuç vermiyorsa mevcut bilgilerle aktarım korunuyor.
- Rol, kılavuzlar, Temel bilgiler, Madagaskar satış kaynağı ve Milano kaynağındaki ilgili çelişkiler düzeltildi. Aile paketi/fiyat/yaş/engelli kuralları değiştirilmedi.
- WordPress sayfa 1535: mevcut `[ms_bilet_sorgu]` ayrı, dengeli bir shortcode bloğunda üste alındı. Üyelik/e-posta gerekmeyen sipariş no + telefon yöntemi anlatıldı. Eski yalnızca kişisel linkle çalışma uyarısı düzeltildi.
- WordPress navigation 1357: mevcut menüye Biletimi Bul bağlantısı eklendi.
- Üretimde mevcut ödenmiş, dört biletlik bir siparişle form eşleşmesi ve kişisel bilet sayfası doğrulandı. Toplu PDF indirildi; PDF 4 sayfa, A5 ve geçerli PDF 1.7. İlk sayfada etkinlik, tarih/saat, çocuk rengi ve giriş QR alanı görsel olarak kontrol edildi. Kişisel dosya/QR/telefon/link arşivlenmedi.
- Kommo bağlantı tanısı başarılı. Bilet botu 110115, onaylı `madagaskar_bilet_linki_v2` WhatsApp şablonunu kullanıyor. Botun başarısız gönderim dalı mevcut destek görevini ekliyor. Botta değişiklik yapılmadı.

## Yeni geliştirme

`docs/code-snippets/mdg-ticket-recovery-20261109.php` henüz etkin olmayan kod teslimidir.

1. Tickera'nın indirme linki filtresinde yalnızca Download/İndir etiketi `QR Biletimi Aç / İndir` olur. İmzalı URL, nonce ve mevcut özel etiketler korunur.
2. Biletlerim sayfasına sipariş numarası olmadan kayıtlı cep telefonu ile WhatsApp yeniden gönderim formu eklenir.
3. Son 90 günün en yeni en fazla 3 ödenmiş siparişi aranır. Her sonuçta WooCommerce fatura telefonu yeniden normalize edilerek tam karşılaştırılır.
4. Kuyruk çalışırken sipariş yeniden kontrol edilir; iptal/iade/başarısız/bekleyen/ücretsiz sipariş gönderilmez.
5. Kommo lead adı, pipeline ve tam bir adet ana kişi doğrulanır. Ana kişinin PHONE alanı sipariş telefonu ile eşleşmeden hiçbir CRM yazımı veya mesaj işlemi yapılmaz.
6. Mevcut yerel bilet URL üreticisi ve Bilet Linki yazıcısı kullanılır; yalnız bu siparişin HTTPS/alan adı/order_id/token sözleşmesine uygun URL kabul edilir.
7. Mevcut onaylı bot 110115 başlatılır. HTTP 202 yalnızca kuyruğa kabul demektir; teslim edildi iddiası veya teslim bayrağı değişikliği yapılmaz.
8. IP ve telefon oran sınırı ile atomik sipariş bazlı 15 dakika gönderim kilidi kullanılır. Kullanıcıya sipariş eşleşmesi/sayısı/kişisel URL gösterilmez; bütün numaralar aynı genel yanıtı alır.

## Bağımlılıklar ve güvenlik

- WooCommerce, çalışan Action Scheduler veya WP-Cron.
- Mevcut `ms_biletlerim_url`, `ms_kommo_token_value`, `ms_kommo_write_bilet_url` fonksiyonları.
- Mevcut `MS_KOMMO_BASE_URL` ve sipariş pipeline sabiti; hesap alan adı sabit doğrulanır. Anahtar okunup arşive veya çıktıya yazılmaz.
- Bot 110115 ve mevcut WhatsApp kanal/şablon ayarları korunur. Yeni SMS sağlayıcısı/kimlik bilgisi yoktur.
- Kuyrukta yalnız sipariş ID tutulur; telefon/token/QR kuyruk argümanlarına veya uygulama loguna yazılmaz.
- Yeni checkout, ödeme tamamlama, stok, ürün, iade veya tahsilat hook'u yoktur.
- Bot başarısızlığında mevcut destek görevi doğabilir; yinelenen çağrılar sipariş kilidiyle sınırlanır.

## Testler

`php tests/ticket-recovery-test.php` gerçek ağ/mesaj göndermeyen bağımsız sözleşme testidir. Telefon formatları, imzalı linkin korunması, farklı müşteri/pipeline/sipariş reddi, iptal/iade kapısı, kuyruk hatası, eşleşme yeniden doğrulaması ve tekrar gönderim kilidi kapsanır.

CI: `.github/workflows/ticket-recovery.yml`; ayrıca mevcut Changed PHP Syntax workflow'u çalışır.

## Kontrollü etkinleştirme ve smoke test

1. CI sonuçları ve bu dosya gözden geçirilir. Kod, WPCode PHP snippet olarak aktarılırsa açılış `<?php` etiketi kaldırılır. WPCode aracı yeni PHP snippet'i kapalı kaydeder; kullanıcı etkinleştirir.
2. İlk canlı test işletmeye ait bir test siparişi ve işletmenin kontrolündeki telefonla yapılmalıdır. Bu çalışmada gerçek müşteriye deneme mesajı gönderilmemiştir.
3. E-posta önizlemesinde yeni etiket ve değişmeyen link doğrulanır.
4. Kayıtlı telefonla form gönderilir; bot kuyruğu ve WhatsApp teslimi ayrı ayrı doğrulanır.
5. Yanlış numara, sipariş/CRM telefon uyuşmazlığı, iade ve 15 dakika içindeki tekrar denenir; kişisel veri veya ikinci mesaj oluşmamalıdır.
6. Kommo'nun sipariş numarası yok yanıtı ancak canlı yeniden gönderim doğrulanınca bu yeni forma yönlendirilecek şekilde değiştirilir. Şimdiki çalışan canlı aktarım korunur.

## Geri dönüş

Yeni snippet kapatılır. Yeni form ve etiket filtresi kalkar. Gönderim işçisi artık kayıtlı olmadığı için bekleyen işler mesaj göndermez. Hiçbir sipariş/ödeme/iade kaydı silinmez. Atomik gönderim kilitleri kendi zamanlı temizliğiyle kaldırılır; veri veya tokenlar topluca silinmez. Önceki sipariş no + telefon formu ve çalışan Kommo destek akışı kullanılmaya devam eder.

## API kaynağı

Kommo resmi API: `POST /api/v4/bots/{id}/run`, `entity_id`, `entity_type=leads`, başarılı kuyruk yanıtı 202.
https://developers.kommo.com/reference/launch-a-salesbot

# Basın Bileti Talepleri — 2026-11-23

## Amaç
Yerel basın kuruluşlarının, yalnızca e-postayla gönderilen gizli bağlantıdan basın bileti talep etmesi; yönetici onayından sonra mevcut **Protokol / Ücretsiz Biletler** altyapısıyla (Code Snippets #127, `MDG_Protocol_Tickets`) QR bilet üretilip kuruluşun e-postasına gönderilmesi.

## Bileşen
- Eklenti: `wp-content/plugins/madagaskar-basin-bileti/madagaskar-basin-bileti.php` (v1.0.1)
- Admin: Madagaskar (mmc-dashboard) → **Basın Bileti Talepleri** (`manage_woocommerce`)
- Gizli form: `/?mdg_basin=talep&anahtar=<48 hex>` — anahtar `mdg_press_access_key_v1` seçeneğinde; admin ekranından yenilenebilir (eski bağlantı iptal olur). `anahtar` parametresi Safe Cache (#20) tarafından zaten önbellek dışı; sayfa `no-store`, `noindex`.

## Kurallar
- Kuruluş başına seans başına **en fazla 2 kişi** (muhabir, kameraman, foto muhabiri, editör). Sayım: aynı normalize kuruluş adı **veya** aynı e-posta; reddedilenler sayılmaz. Talep ve onayda tekrar kontrol edilir.
- Yalnız `MDG_Protocol_Tickets::catalogue()` içindeki satıştaki ve başlamamış seanslar listelenir; kapasite kontrolü yapılır.
- Onay → `kind=protocol`, `mode=named`, numarasız; bilet üzerindeki isim: `Ad Soyad (Basın · Görev · Kuruluş)`. Biletler protokol paketleri listesinde de görünür. WooCommerce müşteri e-postaları protokol filtresiyle kapalıdır; bilet e-postasını bu eklenti `wp_mail` ile gönderir (kişisel bağlantılar + toplu PDF).
- Spam: nonce, honeypot, IP başına saatte 6 gönderim.
- Bildirim: yeni talep `madagaskarsirkiturkiye@gmail.com` (filtre `mdg_press_notify_to`), başvurana alındı e-postası; red e-postası isteğe bağlı gerekçeyle.

## Hook'lar
`plugins_loaded`(20) → `admin_menu`(100), `admin_post_mdg_press_approve|reject|rotate`, `template_redirect`(1).

## Bağımlılıklar
Code Snippets #127 (`MDG_Protocol_Tickets`: catalogue, phone, save, issue_batch, package_url, person_url, PAGE), MDG/Woo/Bridge/Tickera (protokol modülünün kendi bağımlılıkları).

## Testler
`tests/press-tickets/regression.php` (20 kontrol), `tests/press-tickets/flow.php` (14 kontrol, WP stub'larıyla form→talep→limit→onay→e-posta), mevcut `tests/protocol-tickets/*` ve cache testi. CI: `.github/workflows/press-tickets.yml`.

## Canlı smoke-test planı
1. Eklentiyi etkinleştir; admin menüsü ve gizli bağlantı görünür.
2. Bağlantıyı anahtarsız/yanlış anahtarla aç → 404.
3. Doğru bağlantıyla formu aç → yalnız satıştaki seanslar listelenir.
4. Test kuruluşu adıyla 1 kişilik talep (yönetici e-postasına) → admin bildirimi ve alındı e-postası.
5. Aynı kuruluşla 2 kişilik ikinci talep → sınır hatası.
6. Talebi onayla → protokol paketi `Hazır`, e-postada kişisel bilet bağlantısı ve PDF açılıyor; QR kod mevcut.
7. Test biletini Protokol ekranından/standart iptal yoluyla geçersiz kıl (kapasite iade).
Gerçek ödeme/checkout akışına dokunulmaz.

## Geri alma
Eklentiyi devre dışı bırak. Üretilmiş protokol biletleri geçerli kalır (gerekirse mevcut iptal yolu). Talep kayıtları `mdg_press_req_*` seçeneklerinde durur; silme yapılmaz.

## 1.0.1 (23.11.2026)
Canlı testte aynı talep iki kez kaydedildi (çift gönderim/yenileme). Düzeltme: aynı seans + e-posta + kişi listesiyle açık talep varsa yeni kayıt açılmaz; başarılı gönderimden sonra 303 yönlendirme (yenilemede tekrar POST yok); gönder düğmesi tıklanınca kilitlenir.

# Denizli PDF önbellek düzeltmesi — 2026-10-06
Kullanıcı Denizli davetiyesinin hâlâ eski göründüğünü bildirdi. Gerçek public PDF indirme yanıtı incelendi.

Doğrulanmış neden: snippet20 MS Güvenli Sayfa Önbelleği, protokol query anahtarlarını tanımadığı için template_redirect -999'da output buffer açıp davetiyenin nocache başlığını public max-age300 / s-maxage600 olarak değiştiriyordu. Header X-MS-Safe-Cache enabled-v3 görüldü. İlk public PDF içeriği yeni yerleşimdeydi; hangi eski dosyanın kullanıcının cihazında açıldığı ayrıca doğrulanmadı.

Çözüm:
- Snippet20 private query exclusions: mdg_davetiye, mdg_protokol, anahtar, bilet, kisi. Diğer mevcut dinamik/ödeme korumaları korunur. Callback namespace ms_v3p ile güncellendi ve koşullu tanım kullanılır.
- Denizli paket ve PDF bağlantılarına duzen=20261006-v2 eklendi.
- Paket sayfası Güncel PDF düzeni v2 etiketini gösterir.
- İndirilen ad Denizli-100-A9-Guncel-v2.pdf gibi ayrı ad taşır.
-128 yönetici teşhisi gerçek public HTTP PDF yanıtını da okur.
- WordPress object cache flush yapıldı; CLI'nin CDN purge teyidi yok.

Son external HTTP doğrulama:
200 application/pdf; Cache-Control: no-cache, must-revalidate, max-age=0, no-store, private.
X-MS-Safe-Cache başlığı yok. Content-Disposition güncel v2 dosya adını içeriyor.
Gerçek indirilmiş PDF rasteri gözle kontrol edildi: salon/adres/koltuk alanları ayrı, fatura notu yok. İki QR yazılımla çözüldü; giriş payload hash native resolved hash ile eşleşti.
Paket public HTML sürüm etiketi ve sürümlü linkleri gösteriyor.
Cache test: güvenli bilgi sayfası cacheable;7 private key vePOST excluded.
Yeni sipariş/bilet/mesaj yok. Paylaşılan özel bearer linkler bu kayda eklenmedi.

Kaynaklar: docs/code-snippets/mdg-safe-cache-protocol-exclusions.php (snippet20), mdg-denizli-numbered-invitations.php (126), mdg-protocol-pdf.php (128).
PR: https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/162
Rollback:126/128 önceki sürümleri kullanılabilir;20 protokol private exclusions kaldırılması önerilmez, çünkü cache bug geri gelir. Normal bilgi sayfası cache davranışı korunur.

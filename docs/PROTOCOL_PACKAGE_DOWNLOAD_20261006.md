# Protokol paketi toplu indirme / paylaşım — 2026-10-06
İstek: Denizli20 davetiyeyi yönetim ekranından indirmek/paylaşmak.

Eklendi:
-127 genel yönetim ekranındaki Denizli kartı ve hazır paket ekranlarında tek PDF indir, bağlantıyı kopyala, WhatsApp ile paylaş ve seçilebilir bağlantı alanı.
-126 mevcut Denizli public paket sayfasında aynı seçenekler.
-127 genel public paket sayfasında aynı seçenekler. Kişisel linkte yalnız o kişinin mevcut yetkili biletleri indirilir.
-128 native Designer generate_multi ile bilet başına bir sayfa. Şablon bellekte hazırlanır, ortak DB şablonu kaydedilmez. En fazla400 bilet.
-Toplu route mevcut token doğrulama, completed sipariş ve invalidation filtrelerinden SONRA çalışır. Public alan toplu=pdf; cache excluded private ticket query keys zaten geçerli.

Canlı doğrulama:
-126/127/128 aktif ve code_error null.
-Yönetim HTML'sinde20 bileti tek PDF indir/kopyala/WhatsApp butonları var.
-jsdom actual admin HTML clipboard success ve permission-denied fallback kontrolleri başarılı.
-PHP syntax, mevcut53 protokol +10 layout kontrolleri ve bulk composition/limit testi başarılı.
-Gerçek public toplu download200 application/pdf, attachment Denizli-Protokol-20-Bilet-Guncel-v3.pdf,471754 bayt. Cache-Control private/no-store.
-20 PDF sayfası:10 adet17.30,10 adet19.30; A2–A11 her seansta bir kez. Fatura notu yok.
-20 sayfa raster'e çevrildi; ilk/son sayfa gözle kontrol edildi. Her sayfadaki iki QR çözüldü;20 unique giriş QR hash kümesi mevcut20 native ticket_code hash kümesiyle bire bir eşleşti.
-Yeni sipariş/bilet üretilmedi. WhatsApp mesajı gönderilmedi. Fiziksel scanner testi yok.

Kullanım: menüyü yenileyip Denizli kartının20 bileti tek PDF indir butonuna basın. Bağlantıyı kopyalayıp iletin veya WhatsApp butonunda alıcıyı seçip gönderin. Bu link tüm pakete erişim verir; kişisel paylaşım için kişinin kendi scoped linkini kullanın.
Rollback:126/127/128 önceki branch sürümlerine dönülür; mevcut biletler/tekil QR'lar korunur.
Kaynaklar docs/code-snippets/mdg-protocol-pdf.php, mdg-denizli-numbered-invitations.php, mdg-protocol-ticket-menu.php.
PR: https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/163
Bu kayda bearer link, gerçek QR içeriği veya telefonlar eklenmedi.

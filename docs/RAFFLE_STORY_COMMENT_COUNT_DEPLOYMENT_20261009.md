# Çekiliş Story yorum sayısı — canlıya alma 2026-10-09
Kullanıcı talebi: toplam yorum sayısı ve "yorum arasından yapılan çekiliş sonucunda 2 kazanan belirlenmiştir" metni.
PR: https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/213
Branch: codex/raffle-story-comment-count-20261009
Commit: 2279ad0b448c4257322970bf6a38f202cee8c502
Canlı Code Snippets #173 aktif, code_error null; native REST source readback doğrulandı.
Sadece admin_post_mck2_story_result priority1 çıktı köprüsü; manage_options + mevcut kampanya nonce şartı; SELECT-only toplam yorum ve winner sayısı. Tek tam metin eşleşmesinde dönüşüm; uyuşmazlıkta orijinal çıktı. Rollback #173 pasife almak.
Gerçek plugin Story callback ile authenticated geçici renderer:
- Mamak kampanya15: 98 / yorum arasından / yapılan çekiliş sonucunda 2 kazanan belirlenmiştir.
- Denizli kampanya13: 130 / yorum arasından / yapılan çekiliş sonucunda 2 kazanan belirlenmiştir.
Her ikisinde SVG1080x1920 ve PNG export JavaScript mevcut; eski etiket yok. Tarayıcıdan PNG download testi yapılmadı.
Geçici test snippet174 pasif ve code_error null doğrulandı; admin-only diagnostic route artık aktif değil.
Önce/sonra domain kontrol toplamları aynı:
campaigns9 fingerprint12149464461; draws18 fingerprint42837802077; audit9 fingerprint20923945166.
Çekiliş veya doğrulama yeniden çalıştırılmadı, kazananlar değiştirilmedi.
Yerel php executable yok; yerel lint çalışmadı. Native plugin activation + gerçek PHP Story render iki kampanyada başarılı.
#212 yayınlama/doğrulama riskleri ayrı kapsamda kaldı; bu kullanıcı isteği yalnız Story metnidir.

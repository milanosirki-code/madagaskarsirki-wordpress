# Tickera salt okunur bilet mutabakatı — güvenli veri sözleşmesi

Tarih: 12 Ekim 2026. Durum: staging doğrulaması bekliyor. Bu belge canlı kayıtları okumaz veya değiştirmez.

## Doğrulanan mevcut kod davranışı

MDG V4'ün bilet denetimi, `tc_tickets_instances` kayıtlarını ve WooCommerce siparişi ile ilişki kuran `post_parent` alanını kullanıyor. Etkinlik ve bilet türü ayrımında `event_id` ile `ticket_type_id` metaları bulunuyor. Bu, salt okunur karşılaştırma için başlangıçtır; Tickera Bridge'in her sürümde bütün kayıtları aynı şekilde ilişkilendirdiği henüz kanıtlanmadı.

GitHub testleri (commit d43177b38393554c988fba12ce4329b0238882f0): Changed PHP Syntax, Madagaskar Plugins CI, Milano API sözleşmesi tamamı başarılı.

## Güvenli denetim girişleri

Her test siparişi için yalnız şu alanlar karşılaştırılacak:
- WooCommerce order ID ve satır başına beklenen bilet sayısı.
- Tickera bilet instance ID, order ilişkisi ve bilet durumu.
- Farklı bilet instance sayısı; fazla, eksik veya mükerrer kayıt göstergesi.
- Marka ve etkinlik eşleştirme sonucunun geçerli/geçersiz olması.

Gerçek bilet QR içeriği, giriş kodu, ödeme token'ı, müşteri adresi/telefonu veya PayTR anahtarları asla loglanmamalı ve GitHub fixture içine konmamalı.

## Kabul senaryoları

1. Tek biletlik sipariş → 1 benzersiz instance.
2. İki biletlik sipariş → tam 2 benzersiz instance.
3. Tekrarlanan ödeme bildirimi → bilet sayısı değişmez.
4. Farklı marka / eşleşmeyen etkinlik → bilet üretimine izin verilmez.
5. Kısmi/tam iade ve başarısız ödeme → yeniden bilet üretilmez.
6. Farklı seanslardan oluşan bir siparişte hem toplam hem satır/seans bazlı sayı kontrol edilir.
7. Aynı sipariş için eşzamanlı iki ödeme callback denemesi staging üzerinde ayrıca test edilir.

## Açık engeller

- Tickera Bridge'in canlı üretim hook kodu yetkili kaynaktan henüz alınmadı.
- Giriş QR koduna veya kişisel ödeme bilgilerine erişen yeni bağlayıcı GitHub'a eklenmedi.
- `post_parent` ilişkisinin tüm Tickera instance senaryolarında yeterli olduğu doğrulanmalı.
- Başarılı bilet mutabakatı sağlayan veri okuması için staging ve sınırlandırılmış yetki gerekli.
- Milano sitesi hâlâ yayına kapalı, canlı Madagaskar satış akışında değişiklik yok.

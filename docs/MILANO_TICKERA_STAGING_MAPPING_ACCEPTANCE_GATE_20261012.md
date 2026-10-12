# Milano — Tickera gerçek veri eşleştirmesi için dağıtım öncesi kabul kapısı

12 Ekim 2026. **Çalışma alanı: test/staging; üretim ortamında sorgu veya yazma yapılmaz.**

## Kanıtlananlar
- GitHub PR #235 commit d9b4d4ce3f06ff99d3e06c6e3115b4e5f735c3ff için Milano sözleşme iş akışı (38302184032), PHP (38302183871) ve Madagaskar Plugins CI (38302183818) başarılı.
- `tickera-full-audit.php` yalnız sentetik satır/sayı/kimlik/event/type bilgisiyle çalışır, Tickera üretim kaynaklarına bağlı değildir.
- MDG V4, Tickera instance üzerinde `post_parent`, `event_id`, `ticket_type_id` ve kod hash alanlarını inceleyebilir. **Bu, tekil WooCommerce order_item_id ile bağlantıyı kanıtlamaz.**

## Fail-closed gerçek veri kapısı
- Yetkili staging ortamında ve test siparişinde WooCommerce order_item_id → Tickera ticket instance ilişkisinin vendor tarafından sunulan güvenilir meta/hook ile doğrulandığı **yazılı kanıt olmadan** line_id bazlı sonuçları doğru kabul etmeyin.
- Sadece `post_parent` üzerinden WC order ile eşleşme, **siparişin hangi satırına ait olduğunun kanıtı değildir**.
- Line ilişkisinin bilinmediği kayıtların durumu `unverified_mapping` olmalı; otomatik doğrulama, bilet yeniden üretme veya bilet iptal etme yapılmamalı.
- Her satırın kanonik MDG session ve ticket type ID'si ayrıca doğrulanmalı. Aynı WC siparişinde birden çok seans bulunabiliyorsa toplam adet eşitliği tek başına yeterli değildir.
- PayTR tekrar callback durumunda kapasite ve bilet instance sayısı ile email tetikleme sayısı karşılaştırılmalı.
- Çıktıda ödeme anahtarı, müşteri iletişim bilgisi ve açık QR/bilet kodu olmamalı. SHA-256 özetleri de ihtiyaç dışında kaydedilmemeli.
- Salt-okunur staging gözlemi başarıyla sonuçlanmadan otomatik bilet koruma/yeniden oluşturma hook'u eklenmemeli.

## Durdurma koşulları
- Vendor eklenti sürümü veya bilet instance meta şeması değişmişse,
- Satır eşleştirmesi belirsiz/çokluysa,
- Siparişin markası çözümlenemiyorsa,
- Ödeme callback imza ve tekrar işleme davranışı doğrulanmamışsa,
sonuç **bloklayıcı** kabul edilmeli.

## Açık karar
Gerçek Tickera Bridge v1.7.8 satır-instance eşleştirmesi ve PayTR 3.1.2 callback davranışı güvenli staging denetimiyle kanıtlanmadan Milano satışına geçilmez. Mevcut canlı Madagaskar site ve QR'larında değişiklik yapılmadı.

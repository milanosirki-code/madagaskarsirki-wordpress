# Çekiliş sonuç yayını — 10 Ekim 2026

Issue #212; eklenti sürümü 3.4.0.

## Yönetici akışı
1. Asil adayların Instagram takip/beğeni şartlarını manuel kontrol edin.
2. Uygun asil adayları Doğrulandı olarak kaydedin; uygun olmayan aday için mevcut güvenli yedek değiştirme akışını kullanın.
3. Tüm asil pozisyonlar doğrulanınca Sonuç Yayını bölümünde Çekiliş sonuçlarını yayınla düğmesine basın.
4. Yayından kaldır düğmesi yayını geri alır. Asil aday veya doğrulama durumu değişince eski yayın parmak izi geçersiz olur; tekrar yayın gerekir.

Mevcut ve eski kampanyalar otomatik yayımlanmaz. Kamuya açık arşiv, doğrulama sayfası, sitemap ve Story yalnız açıkça yayımlanmış, güncel ve doğrulanmış asil sonuçları gösterir. Yayımlanmamış adaylara ücretsiz giriş hakkı gösterilmez.

## Teknik doğrulama
- POST, manage_options, kampanya nonce ve aday/snapshot eşleşmesi gereklidir.
- Yayın kampanya ve asil satır kilitleri altında InnoDB transaction ile kaydedilir. Canlı campaigns/draws/options tablolarının InnoDB olduğu kontrol edildi.
- Eski açık doğrulama formu yeni adayı doğrulayamaz; koşullu UPDATE aday kimliğini de denetler.
- Yayın kaydı kullanıcı ve zaman bilgisi içerir; şema değişikliği yoktur.
- Kamuya açık sonuç sayfaları DONOTCACHEPAGE/no-cache kullanır.
- Story istatistiği toplam yorum sayısını kullanır; mevcut #173 köprü snippet'i yeni metinde etkisiz kalır.
- 28 sentetik davranış testi ve PHP sözdizimi kontrolü geçti. Fixture testleri gerçek Instagram şartlarını doğrulamaz ve canlı adayları değiştirmez.

## Dağıtım ve geri dönüş
GitHub inceleme/test sonrası yalnız ana eklenti dosyası güncellenir. Reporting include değişmez. Geri dönüş aynı dosyanın önceki sürümünü geri koymaktır; önceki sürümün otomatik kamuya açılma davranışını geri getirebileceği için gerektiğinde kamuya açık sonuçları önce kapatın.

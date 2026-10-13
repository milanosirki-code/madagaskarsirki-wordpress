# Milano Yalova — MMC program/marka geçişi ve geri alma planı

**13 Ekim 2026 | Durum: inceleme paketi oluşturuldu, canlıya HENÜZ uygulanmadı.**

### Başlangıç canlı kontrolü

- **Madagaskar**: WordPress 7.1.3 / PHP 8.4.26; Madagaskar Management Center `1.3.49` aktif; Madagaskar AI Abilities `0.7.0` aktif.
- Canlı `MMC_Program_Service::create_program()` **brand=Madagaskar Sirki** değerini kod içine sabitliyor. Milano'ya ait program açamaz.
- Canlı `madagaskar/program-create` Abilities API yalnız `province_name` ister, `brand` alanı yoktur. **`program-venue-event-sales` AI modülü etkin olduğu `mdg_ai_abilities_modules` seçeneğinde doğrulandı**.
- `wp_mmc_programs`: **13 Madagaskar Sirki, 0 Milano Sirki**. Yalova 22.10.2026 gerçek MMC / MDG seansı henüz yok. Ayrıca `wp_mmc_programs.brand` SQL varsayılanı eski sürümden **Madagaskar Sirki**; servis açık markayla insert etmedikçe DB varsayılanını güvenlik kaynağı kabul etme.
- Bağlantılar canlı ama **staging sitesi bağlı değil**: 3 WPVibe sitesi Milano, Madagaskar, USKD; WordPress.com'da bu iş için bağlı staging bulunamadı.
- Mevcut satışlar, Madagaskar WooCommerce/PayTR/Tickera, 12 etkinlik MDG köprü eşlemesi ve mevcut 13 programın markası **mutlaka korunmalı**.

### Hazırlanan GitHub taslağı

PR [#235](https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/235), özellik dalı `feature/milano-exclusive-brand-routing-20261012`.

Yalnız üç işletme kod dosyasını etkileyen **review-only** dosya paketi:

1. `wp-content/plugins/madagaskar-management-center/includes/class-mmc-program-service.php` — zorunlu kanonik `brand`, sadece `Madagaskar Sirki` veya `Milano Sirki`, program + log.
2. `wp-content/plugins/madagaskar-management-center/includes/class-mmc-admin.php` — `Yeni Program Aç` formunda boş varsayılanlı zorunlu marka seçici ve liste marka kolonu.
3. `wp-content/plugins/madagaskar-ai-abilities/modules/program-venue-event-sales.php` — `madagaskar/program-create` Abilities API `brand` enum ve zorunlu alan; callback bilgiyi MMC servisine gönderir.

GitHub Action [Milano MMC Brand Selection - Staging Package](https://github.com/milanosirki-code/madagaskarsirki-wordpress/actions/workflows/milano-brand-staging-package.yml) başlatıldı ve ilk çalışma başarılı; review paketi artifact olarak üretildi. **Bu ZIP bir WordPress eklentisi değildir, asla WP eklenti yükleyicisinde kullanılmamalıdır.** Sadece üç kaynak dosyası, Yalova fixture, SHA256 ve oluşturma çağrısı envanteri içerir. Eklentinin geri kalanını üzerine yazmaz. Paket otomatik dağıtım yapmaz.

### Canlıya geçiş öncesi zorunlu adımlar

1. **Staging kur ve doğrula.** Aynı sürüm, ayrı veritabanı, Woo satış/PayTR callback dışarı kapalı; staging'den müşteriye gerçek bilet/mail/WhatsApp gitmemeli.
2. **Tam geri dönüş yedeği:** canlı iki eklentinin mevcut ZIP'i (veya dosya envanteri + hash), `wp_mmc_programs`, `wp_mmc_logs`, `wp_mmc_tasks`, `wp_mmc_events`, `wp_mmc_program_venues` ilgili tablolarının yedeği. Checkout etkinlik/ürün/sipariş tablolarına dokunma.
3. **Kaynak uyumluluğu:** staging'deki üç dosyanın canlı sürümle farklarını satır bazında incele; kod kontrolünün 80 dosyalık ana PR'ından farklı diğer değişiklikleri kazara taşıma. Geniş PR'ın tamamını canlıya yükleme.
4. **API istemcileri:** program oluşturan tüm yönetici ve Abilities çağrı noktalarında artık `brand` zorunlu olacak. Eski otomasyonlar bu yeni alanı göndermiyorsa güvenli biçimde hata alır, yanlış markalı kayıt oluşturamaz. Kurumsal/Okul/Kommo iş akışlarında regresyonu kontrol et.
5. **Bakım penceresi:** üç dosyayı **eşgüdümlü** yükselt; PHP lint ve WP admin/AI endpoint smoke test yap. Hata halinde eşgüdümlü geri al.
6. **Üretimde doğrulama:** `madagaskar/program-create` abilities şemasında `brand` alanı ve required/enum görünmeli; yönetici formunda iki marka seçilebilmeli, hiçbirisi önseçili olmamalı. Mevcut 13 program `Madagaskar Sirki` kalmalı.
7. **İLK kez oluşturma (sale OFF):** yalnız `brand=Milano Sirki, province_name=Yalova, district_name=Merkez, plan_year=2026, planned_date=2026-10-22` ile Abilities API üzerinden `preparation` programı yarat. Yalova için önceden aynı marka/gün kaydı oluşmuşsa ikinci kez yaratma. Gelen ID, code, marka ve üç hazırlık görevini oku. **Henüz MDG seansı/stock/order/Tickera/QR/PayTR oluşturma.**
8. **Önce ayrı salon teyidi:** MDG venue `#181` Yalova / Merkez / `90. Yıl Spor Salonu` adres ve harita alanları canlıda boş. Kullanıcının verdiği adres ve maps kısa linkiyle id eşleştirmesini teyit et; master salon verisini diğer programlara yansıtmadan önce etki incelemesi yap.
9. **Daha sonraki ayrı satış hazırlığı:** iki seans 18.00 + 19.30, her biri 500 kişi, serbest oturma; yetişkin 490 TL, çocuk 245 TL, aile 2+2 1100 TL, dört ayrı giriş/QR. Çocuk 0–2 ücretsiz fiziksel kişi kapasitesini kontrol et. Tekil Milano marka envanter kapısı, Woo/PayTR ikinci domain 3DS/callback, Tickera PDF/QR ve iade/eşzamanlı son koltuk testi olmadan satış açma.
10. **Rollback:** canlı iki eklentinin kaynak dosyalarını ve gerektiği kadar ortam veritabanını onaylı yedekten geri al; çift marka veri kaydı veya ödeme oluşturma; satış kapalı ve bakım kontrolü. Eğer hazırlık programı açılmışsa geçmiş kayıtları ve görevleri incelemeden rastgele SQL DELETE yapma.

### Aşama teslim ve sınır

GitHub otomatik testleri yalnız izole kod sözleşmeleridir. **Gerçek staging WordPress E2E, saha 500 kişi giriş güvenliği, canlı 2. domain PayTR ödemesi ve yeni Milano programı henüz denenmedi.** Sadece başarılı GitHub testine dayanarak canlı müşteri ödemesi açılmaz. Review paketi, prod dağıtımı değil, kontrollü dağıtımın planıdır.

- Program hazırlık fişi: [Yalova 22.10.2026](MILANO_YALOVA_FIRST_EVENT_20261022.md)
- Aşama kontrolü: [Milano marka/ayrı satış](MILANO_STAGE2_PRE_SALE_ANNOUNCEMENTS_20261013.md)

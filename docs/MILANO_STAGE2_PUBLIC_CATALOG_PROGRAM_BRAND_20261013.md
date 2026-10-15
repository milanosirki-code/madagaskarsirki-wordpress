# Milano Sirki — Aşama 2: Program markası, API uyumu ve güvenli seans kataloğu

**Tarih:** 13 Ekim 2026. **Çalışma dalı:** `feature/milano-exclusive-brand-routing-20261012`, PR #235. **Durum:** Taslak kod/test; canlı ödeme/bilet satışı açılmadı.

## Gerçek canlı sistem kontrolleri

- MMC tablosunda **13 Madagaskar Sirki programı, 0 Milano Sirki programı** var. Uşak, Didim, Aydın, Manisa ve İzmir dahil kayıtlar Madagaskar markalı. Hiçbirinin sahibi otomatik değiştirilmedi.
- Madagaskar canlı `MMC_Program_Service::create_program()` `brand` alanını açıkça **Madagaskar Sirki** olarak sabitliyor. Kullanıcı arayüzü ve `madagaskar/program-create` ability'si Milano marka seçimini henüz sağlamıyor. Marka seçmeden canlı program oluşturmak risklidir.
- Canlı Madagaskar producer `/wp-json/dunya-milano/v1/sessions`: gizli sunucu tokenı eksik -> `503 dmsf_not_configured`. Bu beklenen kapalı varsayılandır.
- Milano sitesi `milanosirki.com` tanıtım amaçlı public; Milano WooCommerce, Tickera ve PayTR kurulu değil. Canlı Milano ortak etkinlik eklentisi v0.1.0 olup salt okunur, kartları yalnız yönetici görür. Canlı Madagaskar ödeme/kontenjan/QR sistemlerine yazılmadı.

## Taslakta uygulanmış güvenli geliştirmeler

1. **MMC servis katmanı**: PR'da `MMC_Program_Service::brand_options()` tam iki marka döner. `create_program()` açık `brand` ister ve yalnız tanınan marka ile kaydı oluşturur; yok veya belirsiz marka reddedilir. MMC admin formu marka seçimini zorunlu kılar. Bu kod **henüz canlıda değil**.
2. **MMC program oluşturma API'si**: `wp-content/plugins/madagaskar-ai-abilities/modules/program-venue-event-sales.php` içinde `mdg_ai_program_create()` kullanıcıdan gelen `brand` değerini servis katmanına iletir. `madagaskar/program-create` ability'si `brand` enum (`Madagaskar Sirki`, `Milano Sirki`) ve `required: ['province_name','brand']` ile güncellendi. Bu, servis katmanı zorunlu marka kuralıyla API uyumunu sağlar.
3. **Consumer v0.2.3 (GitHub-only)**: Milano salt okunur şehir ve seans kartları, yönetici için önizleme ve boş/sold-out durumları desteklenir. Public sunum ancak `DUNYA_MILANO_PUBLIC_CATALOG_ENABLED === true` sunucu sabitiyle **açıkça** yetkilendirilirse etkin olur; varsayılan false. Public içerikte tam stok/kalan sayı, yönetici mesajı, ödeme bağlantısı, fiyat ve QR yoktur.
4. **Kaynak marka güvencesi**: Sunucu tokenlı beslemede yalnız `event_owner_brand=milano` kayıtları kabul edilir. Başka marka/boş/bozuk brand ve beklenmeyen şema fail-closed; public katalog bilgileri güvenilir kaynaktan türetilir.
5. **Yeni testler**: `public_enabled`, `public_empty`, `public_wrong_brand`, `public_missing_brand`; `mmc-program-brand-contract.php` içinde AI ability callback ve şema doğrulama assert'leri; ilgili GitHub Action senaryo listesi genişletildi. Başarılı sentetik testler gerçek WordPress/PayTR/kapasite yarışına denk değildir.

## Blokerler ve işletme kararı

- İlk Milano programının **il, ilçe, salon, tarih, seans saatleri ve kapasite** bilgisi kesinleşmiş değildir. Bunlar olmadan gerçek program/MDG seansı oluşturulmayacak; herhangi bir Madagaskar etkinliği Milano'ya çevrilmeyecek.
- Canlıda MMC servis/admin/AI ability üçlüsü aynı sürüm seti ve regresyon testleriyle birlikte dağıtılmadan program-create çağrılmamalı. Eski API müşterilerinin artık zorunlu marka göndermesi gerekecek; uyumluluk/sürüm geçişi ayrıca kontrol edilmeli.
- İlk yalnız Milano markalı program oluşturulduktan sonra onaylı salon+seans → MDG taslak köprü → tokenlı API canary, Madagaskar doğrudan URL/Store API/Woo engelleri ve kullanıcı görünümü test edilmeli.
- MDG feed'in mevcut taslağı yalnız `ONSALE` olaylarını verir; satış açılmadan önce etkinlik takvimini public göstermek için ön satış/durum stratejisi ayrı doğrulanmalıdır. Bu aşamada satış açarak katalog test etmeyin.
- İkinci alan adı için PayTR merchant, ödeme iFrame/3DS, merkezi callback, QR/PDF, aile paketi, çoklu istek, iade ve çerez onay testleri geçmeden satış canlıya alınmayacak.

## GitHub / Google Drive

- PR #235: https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/235
- Ana çalışma günlüğü: https://docs.google.com/document/d/1GSV-_niF39NTkB45--sqodofxDVH25S7XeKxefzUa4Q/edit
- Canlı WordPress veya WooCommerce program/sipariş/kapasite/QR kayıtlarında **hiçbir yazma yapılmadı**.

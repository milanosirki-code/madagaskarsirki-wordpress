# T9 — Yönetici onayı bekleyen işler — 7 Ekim 2026

Bu belge Issue #141 / T9 hazırlık kaydıdır. **Production değişikliği yapmaz.**
Kaynak gerçekliği için güncel GitHub issue/PR durumu ve repodaki son doğrulanmış canlı kayıtlar esas alınmıştır.
Canlı sürüm bilgisi eski bir checkpoint'ten geliyorsa özellikle "son belgelenen" olarak belirtilir; deploy öncesi yeniden doğrulanmalıdır.

## 1. Issue #124 — Program 9 okul-program bridge

**CURRENT**
- Program 9: PRG-2026-ANK-YENIMA-001 / Yenimahalle.
- Canonical zincir: MDG event 7 → bridge 8 → MMC program 9.
- `wp_mad_okul_programlar` row 7: `source_event_id=7`, `mmc_program_id=NULL`.
- Issue #124 audit'i bunun tek alanlık aday ilişki olduğunu doğruladı.
- Okul ataması, ziyaret, personel, hedef ve saha verisi yazılmadı.

**RISK**
- `ensure_mmc_bridge` gibi geniş bir helper yalnız bu alanı değil türetilmiş alanları ve bağlı okul kayıtlarını da değiştirebilir.
- Yanlış toplu write okul/saha kapsamını bozabilir.

**CHANGE**
- Yalnız yönetici onayından sonra tek satır / tek alan:
  `row7.mmc_program_id: NULL → 9`.
- Başka hiçbir alan, okul, ziyaret, personel, satış veya Kommo kaydı değiştirilmeyecek.

**TEST**
1. Before snapshot ve ilgili satır/ilişki hashleri.
2. Tek alan update.
3. Row 7 read-back.
4. MDG7 → bridge8 → MMC9 zincirini yeniden doğrula.
5. School/target/visit/profile kayıtlarının BEFORE=AFTER olduğunu doğrula.
6. PHP/DB hata kontrolü.

**ROLLBACK**
- Aynı satırda `mmc_program_id: 9 → NULL`.
- Snapshot ve read-back ile doğrula.

**OWNER APPROVAL REQUIRED?** EVET — tek alan write için açık onay gerekir.

---

## 2. Issue #82 — Kommo AI source envanteri / retrieval

**CURRENT**
- Issue #82 açık.
- Canonical Unified Source V2 sahibi aktif snippet #110; eski #70 pasif.
- Hidden URL HTTP 404→200 düzeltmesi daha önce canlıya alındı ve main'e merge edildi.
- Son doğrulanmış yerel source referansları: URL 1334640, program 1334998, location 1334988.
- **Güncel Kommo remote source toplamı, active-agent attachment listesi, source-limit durumu, reindex/index freshness ve gerçek AI retrieval hâlâ doğrulanmış değil.**
- Yeni per-program source üretimi durduruldu.

**RISK**
- Körlemesine source silmek aktif agent retrieval'ını bozabilir.
- Eski source ID'lerinin varlığını geçmiş kayıttan varsaymak yanlış silmeye yol açabilir.
- Source silme/recreate aynı ID'yi geri getirmeyebilir.

**CHANGE**
1. Önce yalnız read-only Kommo source inventory ve active-agent attachment haritası.
2. Canonical unified set ile stale/duplicate adayları eşleştir.
3. Program/location text source içeriklerinin güncel canonical output ile eşleşmesini doğrula.
4. Retrieval smoke tamamlanmadan source silme.
5. Yalnız yönetici onayladığı, varlığı ve kullanılmadığı güncel envanterle kanıtlanmış source'larda detach/disable/delete uygula.

**TEST**
- Uşak, Didim, Manisa ve güncel Ankara programları için gerçek AI preview/context retrieval.
- İptal/geçmiş programların yanlış aktif cevap üretmediğini kontrol et.
- Source count/attachments/index durumu before/after.
- Gerçek müşteri/WhatsApp mesajı yok.

**ROLLBACK**
- Tercih edilen güvenli değişiklik detach/disable ise yeniden attach/enable.
- Delete ancak exact content/metadata yedeği ve yeniden oluşturma planı varsa; yeniden oluşturma source ID'sini korumayabilir.

**OWNER APPROVAL REQUIRED?**
- Read-only inventory/retrieval: HAYIR.
- Remote source detach/disable/delete/update/reindex: EVET.

---

## 3. Aile Paketi 1.1.3 + AI Abilities 0.7.0

**CURRENT**
- GitHub main:
  - Madagaskar Aile Paketi 2+2 = **1.1.3**
  - Madagaskar AI Abilities = **0.7.0**
- Son belgelenen canlı checkpoint (4 Ekim):
  - Aile Paketi = 1.1.2
  - AI Abilities = 0.6.1
- Deploy öncesi canlı sürümler yeniden okunmalıdır.
- Aile 1.1.3, MMC canonical `family_2_2` fiyatını read-only lookup ile kullanır ve capacity_units=4 modelini korur.
- AI 0.7.0 legacy MDG→MMC migration modüllerini desteklenen modül haritasına ekler; varsayılan enabled module listesi boş olduğundan otomatik migration başlatmaz.

**RISK**
- Aile Paketi checkout, fiyat ve kapasite zincirine dokunur.
- AI write-migration modülü yanlışlıkla etkinleştirilirse domain write yapabilir.
- İki eklentiyi aynı anda güncellemek rollback ve hata izolasyonunu zorlaştırır.

**CHANGE**
- Onay verilirse iki eklenti **ayrı ayrı**, önce fresh live version/hash snapshot ile deploy edilir.
- AI 0.7.0 deployunda yeni write migration modülü otomatik enable edilmeyecek.
- Aynı bakım adımında iki sürüm birden değiştirilmez.

**TEST**
Aile 1.1.3:
1. Standart ve etkinlik-özel family_2_2 fiyat read-back.
2. 1 paket = 2 yetişkin + 2 çocuk = 4 kapasite birimi.
3. Sepet → checkout → PayTR formu smoke; gerçek ödeme yok.
4. Tickera component/QR üretim zincirine yalnız mevcut güvenli test yöntemleriyle bak.

AI 0.7.0:
1. Plugin health ve module inventory.
2. Enabled modules before/after aynı kalmalı.
3. Read-only ability smoke.
4. Legacy migrate write modülü çağrılmamalı.
5. PHP warning/fatal ve system-health kontrolü.

**ROLLBACK**
- Exact pre-deploy plugin package/hash'e dön.
- Her eklentiyi ayrı rollback et.
- Herhangi bir migration/domain write başlatılmadan rollback kapısı korunur.

**OWNER APPROVAL REQUIRED?** EVET — Aile 1.1.3 ve AI 0.7.0 deploy onayları ayrı ayrı verilmelidir.

---

## 4. Tickera Bridge 1.7.8 + Phone Number Validation 1.10.1

**CURRENT**
- Son belgelenen canlı kayıt (2 Ekim):
  - Tickera Bridge for WooCommerce: 1.7.7; 1.7.8 güncellemesi bekliyordu.
  - Phone Number Validation: 1.10.0; 1.10.1 güncellemesi bekliyordu.
- 2 Ekim yönetici kararı: **bu iki güncelleme yapılmayacak**.
- Bu karar değiştirilmediği sürece deploy yok.
- Güncel canlı/available sürümler bakım öncesi yeniden doğrulanmalıdır.

**RISK**
- Tickera Bridge bilet/sipariş/checkout zincirinde.
- Phone Number Validation checkout telefon alanını bloke edebilir.
- Üçüncü taraf güncellemelerinde schema/hook davranışı repo içi kaynaklardan garanti edilemez.

**CHANGE**
- Yalnız yönetici önceki "yapılmayacak" kararını açıkça değiştirirse.
- Güncellemeler tek tek yapılır; aynı adımda toplu update yok.
- Önce exact current plugin version/package/rollback noktası.

**TEST**
1. Site/system health before.
2. Tickera Bridge güncelle → sepet → checkout → PayTR formu (gerçek ödeme yok).
3. Mevcut bilet/QR görünürlüğünde regresyon yok.
4. Phone Number Validation güncelle → geçerli Türkiye telefonu kabul; belirgin geçersiz format reddediliyor.
5. Tekrar checkout → PayTR formu.
6. PHP/WooCommerce yeni hata kaydı yok.

**ROLLBACK**
- Her plugin için önceki exact sürüm/paket.
- Tek plugin rollback; tüm site backup yalnız son çare çünkü yeni siparişleri de geri alabilir.

**OWNER APPROVAL REQUIRED?** EVET — önceki "güncellenmeyecek" kararının açıkça değiştirilmesi gerekir.

---

## Onay özeti

T9 kapsamında production write yapılmadı.

Yönetici kararları:
1. Issue #124 tek alan bridge write: ONAY / BEKLET.
2. Issue #82 remote Kommo source mutation/cleanup: read-only inventory sonrası ONAY / BEKLET.
3. Aile Paketi 1.1.3 ve AI Abilities 0.7.0: her biri için ayrı DEPLOY ONAYI / BEKLET.
4. Tickera Bridge + Phone Number Validation: 2 Ekim "güncellenmeyecek" kararını DEĞİŞTİR / KORU.

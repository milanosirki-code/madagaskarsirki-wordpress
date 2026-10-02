# Madagaskar Sirki — Master Teknik Durum ve Devir Notu

**Güncelleme:** 1 Ekim 2026, 21:41 TSİ  
**Öncelik:** `madagaskarsirki.com`  
**Repository:** `milanosirki-code/madagaskarsirki-wordpress`  
**Drive teknik rehberi:** `Madagaskar + USKD Codex Çalışma ve Yedek Rehberi`  
**Drive Doc ID:** `1Q6-mOKbbkYZgWVcxFYK3yy9zLFjqEZEwKTJ14Z1PiZI`

Bu belge, 1 Ekim 2026 sonu itibarıyla canlı sistem, GitHub ve Google Drive arasındaki kanonik devir özetidir. Ayrıntılı kayıtlar ilgili runbook, production snapshot ve issue dosyalarında tutulur.

## 1. Canlı Madagaskar durumu

### AI Abilities
- Canlı plugin: **Madagaskar AI Abilities v0.6.1**
- GitHub `main`: **v0.7.0**
- v0.7.0 içindeki `legacy-mdg-mmc-migrate` modülü staging/kaynak olarak main’dedir; kalıcı plugin modülü olarak canlıya alınmamıştır.
- Read-only `legacy-mdg-mmc-preview` canlıda v0.6.1 ile doğrulanmıştır.
- Son tam sistem sağlığı, Sincan canary öncesi: **0 kritik / 0 uyarı / 16 OK**.
- Sincan canary transaction içi bridge ve satış mutabakatı kontrollerinin tamamını geçti.

Kanonik production snapshot:
`deploy/ai-abilities/production-state-2026-10-01.json`

### Code Snippets
- Son tam doğrulanan aktif snippet sayısı: **36**.
- AI migration kapsamında legacy snippet’ler #72, #74, #75, #77–#89 ve #94 pasiftir.
- **Snippet #101** şu anda özel dikkat gerektirir:
  - son adı: `GEÇİCİ — Legacy MDG → MMC Migration Runner`
  - Sincan canary öncesi aktif edildi;
  - canary başarılı olduktan hemen sonra WPVibe günlük kotası dolduğu için kapanış çağrısı çalışmadı;
  - **son doğrulanmış durum active=true**.
- WPVibe yeniden açıldığında ilk canlı işlem #101 durumunu okumak ve aktifse pasifleştirmektir.

## 2. Sincan canary migration — TAMAMLANDI

Legacy MDG Event **#11** başarıyla MMC kontrol zincirine taşındı.

- Yer: Ankara / Sincan
- Tarih: 3 Ekim 2026
- MMC Program ID: **8**
- Program kodu: `PRG-2026-ANK-SINCAN-001`
- MMC Event ID: **8**
- Program venue ID: **8**
- Program durumu: **sales_open**
- Seans: **3**
- Aktif legacy bilet kodları: `adult`, `child`
- Mapping coverage: **6/6**
- Bridge: `linked=true`, `stale=false`
- Identity: **6/6**
- Legacy WooCommerce order bulundu: **36**
- Sync edilen order: **36**

MMC ledger özeti:
- orders_count: **36**
- ticket_count: **89**
- sold_capacity: **89**
- gross_revenue: **43.500 TL**
- refunded_amount: **0 TL**
- net_revenue: **36.000 TL**
- failed_orders metric: **8**

Ücretli MDG↔MMC mutabakatı:
- paid orders: **28 / 28**
- items: **45 / 45**
- tickets: **89 / 89**
- capacity units: **89 / 89**
- missing_in_mmc: **[]**
- extra_in_mmc: **[]**
- MDG revenue ex-tax: **36.000 TL**
- MMC revenue: **36.000 TL**
- revenue_diff: **0 TL**

Transaction **COMMIT** olmuştur; rollback tetiklenmemiştir.

Ayrıntılı rapor:
`docs/SINCAN_MMC_CANARY_2026-10-01.md`

## 3. Kalan legacy MDG → MMC migration sırası

Read-only v0.6.1 preview’de altı adayın tamamı `create_program_then_bridge` ve `safe_for_later_write=true` çıktı; Sincan tamamlandı.

Kalan sıra:
1. MDG #7 — Ankara / Yenimahalle — 4 Ekim 2026
2. MDG #12 — Denizli / Pamukkale — 8 Ekim 2026
3. MDG #9 — Ankara / Mamak — 10 Ekim 2026
4. MDG #13 — Eskişehir / Odunpazarı — 11 Ekim 2026
5. MDG #10 — İzmir / Konak — 8 Kasım 2026

Önce #101 kapatılmalı, health ve Sincan Program #8 read-only doğrulanmalı; ancak sonra eventler **tek tek** transaction/rollback ile migrate edilmelidir.

Takip: **Issue #81**.

## 4. Kırıkkale satış doğrulaması

Program #2 / `PRG-2026-KIR-MERKEZ-001`:
- MDG Event #15
- 2 Ekim 2026
- 17 Ağustos Spor Salonu
- 17:30 / 19:00
- Bridge linked=true / stale=false / confidence=100
- Seans match 2/2
- Ana Woo ürünleri ve 6 child/adult/family varyasyonu publish ve purchasable
- Son production audit MMC summary:
  - orders_count: **13**
  - ticket_count: **34**
  - sold_capacity: **34**
  - gross_revenue: **20.000 TL**
  - net_revenue: **13.250 TL**
  - refunded: **0**
  - failed_orders metric: **2**
  - reconciliation revenue difference: **0**

#3467 müşteri 3D Secure’u tamamlamadı; #3304 ödenmediği için timeout ile iptal oldu. Bunlar checkout fatal değildir. #3735 PayTR ile 2.200 TL başarılı ödeme olarak doğrulandı.

Ayrıntı:
`docs/PRODUCTION_AUDIT_2026-10-01_1515.md`

## 5. Ana sayfa / şehirler / checkout

- Madagaskar ana sayfa Page #59 yalnız `[ms_anasayfa_v2]` çağırır.
- Shortcode aktif Snippet #35 `MS Anasayfa v3` içindedir.
- Ana sayfa ve `/sehirler/` aynı `ms_city_v2_live_cities()` kaynağını kullanır.
- Ana sayfada doğrulanan ilk üç kart: Pursaklar → Kırıkkale → Sincan.
- Eski Bartın / Çubuk kartları ana sayfada yoktur.
- WooCommerce / Tickera / PayTR satış yolu canlıdır.
- Boş sepetle ödeme sayfasının sepete dönmesi normal WooCommerce davranışıdır.

## 6. Kommo / WhatsApp

Bağlantı katmanı:
- configured=true
- connected=true
- http_ok=true
- token_source=`MMC_KOMMO_TOKEN`
- uses_legacy_token=false

Program kaynak problemi:
- Pursaklar #3: source limit error
- Kırıkkale #2: refresh_needed
- Didim #6: source limit error
- Manisa #7: source limit error
- Uşak #4 ve Efeler #5: synced

Kaynaklar körlemesine silinmeyecek. Önce Kommo AI source envanteri çıkarılacak; canonical unified dynamic source mimarisine göre eski/duplicate kaynaklar ayrıştırılacak.

Takip: **Issue #82**.

## 7. MMC operasyon verisi

Kırıkkale Program #2 için satış sistemi sağlıklı olmasına rağmen operasyon verisi eksiktir:
- checklist 0/42
- pre-departure 0/21
- araç 0
- sanatçı 0
- personel 0
- ekipman 0
- 101 hedef okul
- 0 atama
- 0 ziyaret
- Meta plan draft / spend 0

Bu teknik satış hatası değil, operasyon veri tamamlama açığıdır. Uydurma veri yazılmamalıdır.

## 8. Aktif transition / hotfix plugin denetimi

Canlıda birlikte aktif görülenler:
- Madagaskar Bilet Yönetimi 3.6.3-ticket-invalidation-dry-run
- Madagaskar Bilet Yönetimi V4.0 4.0.14-transition
- İlçe Bazlı SKU Hotfix 1.0.0
- İlçe Bazlı SKU Hotfix V2 2.0.0

Bilet Yönetimi V4, 26 Ağustos’ta kurulmuş; eski 3.6.3 kısa süre kapatılıp yeniden açılmıştır. Bu, birlikte çalışmanın bilinçli transition olabileceğini gösterir.

SKU v2, production-plan dosyasına district_code / ürün slug / SKU / Tickera slug patch’i uygulayıp backup/hash kaydı bırakmıştır. V1 ve V2 kaynakları exact karşılaştırılmadan plugin kapatılmayacaktır.

Takip: **Issue #77**.

## 9. USKD — ikinci öncelik

Madagaskar önceliği nedeniyle USKD geliştirmesi beklemededir.

Tamamlananlar:
- Page 392 server-side `[uskd_madagaskar_events]`
- Etkinlik Takvimi dinamik
- fiyat ve checkout yok
- Ana sayfa Page 378 dinamik kaynağa bağlandı
- eski Bartın / Çubuk statik kartları kaldırıldı
- GitHub deployment kayıtları PR #69 ve #70 ile main’e alındı
- USKD package CI düzeltmeleri PR #73–#76 ile işlendi

Canlı plugin son doğrulanan sürüm: **v0.1.0**.  
GitHub kaynak/paket: **v0.2.0**.  
v0.2.0 canlı yükseltmesi, Madagaskar önceliği nedeniyle bekletildi.

## 10. 1 Ekim 2026 GitHub önemli PR’ları

- #67 — AI migration final records
- #68 — USKD shortcode-ready Page 392
- #69 — USKD live deployment record
- #70 — USKD homepage dynamic events
- #71 — Legacy redirects
- #73 — USKD v0.2.0 shortcode limit
- #74 — USKD CI validation fix
- #75 — USKD workflow cleanup
- #76 — USKD v0.2.0 pending-live record
- #78 — Production sales audit
- #79 — Legacy MDG→MMC read-only preview
- #80 — Preview schema fix / v0.6.1
- #83 — Controlled legacy write migration / v0.7.0 staging
- #84 — Successful Sincan canary production record

## 11. Açık takip başlıkları

- Issue #77 — transition / duplicate hotfix plugin source audit
- Issue #81 — remaining live legacy MDG events → MMC
- Issue #82 — Kommo AI source limit / stale source cleanup
- Issue #57 — USKD dedicated repository split
- Issue #72 — live MMC vs GitHub source/version exact comparison

## 12. WPVibe kotası ve ilk devam adımı

WPVibe Free günlük fair-use limiti Sincan canary kapanış kontrolü sırasında doldu. Sistem tarafından bildirilen tahmini yeniden açılma zamanı yaklaşık **2 Ekim 2026 13:10 TSİ**.

WPVibe tekrar erişilebilir olduğunda sıra:
1. Snippet #101 state oku; aktifse kapat.
2. `madagaskar/system-health-checks` çalıştır.
3. Program #8 Sincan bridge/dashboard/sales read-only doğrula.
4. Kalan legacy migration’ları tek tek sürdür.
5. Kommo Issue #82 source envanterine geç.
6. Issue #77 canlı plugin source capture çalışmasını sürdür.

## 13. Kaynak hiyerarşisi

1. GitHub `main` — uygulama kodu ve kalıcı teknik gerçek kaynak
2. `deploy/ai-abilities/production-state-2026-10-01.json` — canlı production snapshot
3. Bu master-status dosyası — devir ve öncelik özeti
4. Google Drive teknik rehberi — operasyon/yedek kopya ve ekipler arası ortak not
5. WPVibe / WordPress read-only kontroller — canlı gerçeklik doğrulaması

Canlıda yapılan her kalıcı değişiklik GitHub’a geri işlenmeli; GitHub’da tamamlanan anlamlı üretim değişikliği Drive rehberine özetlenmelidir.


## 14. Google Drive senkron durumu

1 Ekim 2026 21:41 TSİ itibarıyla Google Drive teknik rehberi güncellenmiştir.

Drive belgesi:
- Ad: `Madagaskar + USKD Codex Çalışma ve Yedek Rehberi`
- Doc ID: `1Q6-mOKbbkYZgWVcxFYK3yy9zLFjqEZEwKTJ14Z1PiZI`
- Güncellenen konular:
  - canlı AI Abilities v0.6.1 / GitHub main v0.7.0 ayrımı,
  - Snippet #101 son doğrulanmış active=true uyarısı,
  - Kırıkkale 1 Ekim production audit rakamları,
  - Sincan Program #8 canary sonucu,
  - kalan legacy migration sırası,
  - Kommo source-limit Issue #82,
  - transition/hotfix Issue #77,
  - legacy migration Issue #81,
  - USKD v0.1 live / v0.2 source-ready durumu,
  - WPVibe kota engeli ve ilk devam adımları,
  - PR #78, #79, #80, #83, #84, #85 ve kanonik dosya yolları.
- Drive read-back revision: `ANLCKQlDOOe3iC9OlEPPSMdTtLvogc4JvL5-PPGKMlXQ-5ked6VuaVnUikinOtf9jWyhOrvmz_f_SnHxGhJTSSdhwjeAcLFNnQemc6mNjjY`
- Read-back doğrulaması: v0.6.1, Sincan program kodu, #101 uyarısı, Yenimahalle başlangıçlı kalan sıra, Issue #82 ve bu master dosya adı dokümanda bulundu.

Bu nedenle bu PR merge edildikten sonra GitHub ve Drive 1 Ekim 2026 kapanış durumu bakımından senkron kabul edilir.


## 15. 2 Ekim 2026 sabah devam notu

10:30 TSİ civarında WPVibe tekrar denendi; rolling 24-hour Free fair-use limiti hâlâ kapalıdır. Sistem daha fazla kullanımın yaklaşık 10:10 UTC / 13:10 TSİ civarında açılacağını bildirdi. Bu nedenle canlı write yapılmadı.

Kota beklerken public liste zinciri incelendi. `MS Şehirler Dinamik V2` kaynağı recovery dosyasından bulundu. `ms_city_v2_event_sessions()` yalnız legacy `end_at >= now_utc` filtresine güvendiği için stale/yanlış end_at değerlerinde geçmiş tarihli session'lar public listeye sızabilir.

Yeni takip:
- Issue #87 — geçmiş legacy event'leri public şehir/bilet listelerinden güvenli şekilde çıkar
- `docs/PUBLIC_EVENT_DATE_GUARD_2026-10-02.md`
- `docs/LEGACY_MIGRATION_EXECUTION_QUEUE_2026-10-02.md`

Canlıya ilk dönüş sırası değişmedi:
1. #101 state oku ve aktifse kapat
2. system health
3. Sincan Program #8 read-only kontrol
4. Issue #87 için exact live Snippet #30 + legacy session start/end teşhisi
5. Yenimahalle ile kalan migration sırasına devam


## 16. Snippet #30 kaynak recovery — 2 Ekim 2026

`MS Şehirler Dinamik V2` tarihsel kaynak kodu eski dosya arşivinden bulundu ve GitHub'a alındı.

Kaynaklar:
- `docs/recovered-sources/2026-10-02/snippet-30-ms-sehirler-dinamik-v2-recovered.php.txt`
- `docs/recovered-sources/2026-10-02/snippet-30-ms-sehirler-dinamik-v2.1-date-guard-candidate.php.txt`
- `docs/recovered-sources/2026-10-02/README.md`

Bu kaynak **2 Ekim canlı Snippet #30 exact capture olarak kabul edilmez**; tarihsel karşılaştırma tabanıdır.

V2.1 adayındaki tek davranış değişikliği: local session tarihi bugünden eskiyse public listeye eklememek. Sipariş, ürün, Tickera, Kommo veya event status write yoktur.

WPVibe açıldığında exact live #30 okunacak, recovered source ile diff alınacak ve yalnız uyumluysa Issue #87 patch'i uygulanacaktır.


## 17. 2 Ekim 2026 canlı site/snippet değişiklikleri — PR #86 ile doğrulandı

GitHub PR #86 (`Record Claude site changes and live snippet captures (2026-10-02)`) merge edilmiştir. Merge kaydı commit `f099a855...` zincirindedir.

Kanonik ayrıntılı kayıt:
`docs/CLAUDE_SITE_CHANGES_2026-10-02.md`

Canlı capture klasörü:
`docs/live-captures/2026-10-02/`

### Canlıya alınmış/bildirilen snippet değişiklikleri

1. **MS Global Alt Bilgi V3.3** (V3.2'nin yerini aldı, 2 Ekim 11:50)
   - Kurumsal, Blog ve SSS hızlı bağlantıları
   - yasal bağlantılar; iki gizlilik sayfası ayrı etiketlerle (site politikası ve Meta/sosyal medya)
   - çerez bildirimi, `/gizlilik-ve-cerez-politikasi/` bağlantısıyla
   - kaynak: `ms-global-alt-bilgi-v3.3.php.txt` (V3.2: `onceki/ms-global-alt-bilgi-v3.2.php.txt`)
   - alt bilgi canlı görünümde doğrulandı
   - çerez bildirimi ana sayfada ekranda göründü (ekran görüntüsü, 2 Ekim 11:55)

2. **Snippet #35 — MS Anasayfa V3.2**
   - gösteri günü kartında `BUGÜN` rozeti
   - dört yeni SSS
   - ana sayfada krem arka plan
   - kaynak: `snippet-35-ms-anasayfa-v3.2.php.txt`
   - ekran görüntüsü/ziyaretçi görünümünde doğrulandı

3. **MS Bilet Sorgulama V1**
   - `/biletlerim/` üzerinde sipariş numarası + telefon ile bilet bağlantısı sorgulama
   - salt-okunur
   - rate-limit transientleri içerir
   - kaynak: `ms-bilet-sorgulama-v1.php.txt`
   - formun görünmesi doğrulandı
   - gerçek misafir siparişiyle form gönderimi henüz doğrulanmadı
   - `REMOTE_ADDR` gerçek ziyaretçi IP'sini veriyor mu ayrıca doğrulanmalı

4. **MS Yarım Kalan Ödeme Kaydı V1 — DENEME MODU**
   - müşteri mesajı göndermez
   - Kommo'ya yazmaz
   - siparişi değiştirmez
   - Action Scheduler/WP-Cron ile 20 dakika sonra yalnız WooCommerce log'a `GÖNDERİLİRDİ/GÖNDERİLMEZDİ` kaydı üretir
   - log source: `madagaskar-odeme-hatirlatma`
   - kaynak: `ms-yarim-kalan-odeme-kaydi-v1-deneme.php.txt`
   - işletme sahibi tarafından eklendiği bildirildi; canlı log henüz doğrulanmadı

### İçerik / navigasyon değişiklikleri

- Üst menü artık 5 öğe:
  - Gösteriler
  - Şehirler
  - SSS
  - İletişim
  - Bilet Al
- `/sehirler/` altındaki 11 statik şehir sayfası tarih/fiyat içermeyen genel içerikle güncellendi.
- Header WhatsApp bağlantısı 1 Ekim çalışmasında güncellendi.
- SSS sayfası 26 soru olarak güncellendi.
- Blog içerik/SEO güncellemeleri PR #86 kaydında listelenmiştir.

### Yeni/açık kontroller

- K1: gerçek misafir siparişiyle Bilet Sorgulama testi
- K2: deneme siparişi + 20 dakika sonra `madagaskar-odeme-hatirlatma` log kontrolü
- K2 gönderim aşaması: mesaj metni ve Kommo WhatsApp gönderici adımı henüz yok
- K3: etkinlik sayfası sadeleştirme açık
- K4: Şehirler/Bilet Al kaynak işi — tarihsel Snippet #30 artık recovered; Issue #87 devam ediyor
- K7: Ankara statik sayfa SEO çıktısı hâlâ 26 Eylül referansı taşıyabilir
- K8: 26 Eylül geçmiş ürün sayfası hâlâ erişilebilir; satın alınabilirlik ayrıca incelenmeli
- K9: ana sayfa kırmızı zemin V3.2 ile krem düzeltildi; site geneli karar ayrı
- K10: header'daki çift `Bilet Al` telefon görünümü doğrulanınca ele alınacak
- sarı yasal şerit ile alt bilgi yasal satırı tekrar içeriyor

### Envanter uyarısı

Önceki `36 aktif snippet` sayısı PR #86 öncesi son tam WPVibe doğrulamasıdır. K1/K2 gibi yeni snippet'ler sonrasında aktif snippet toplamı **yeniden doğrulanmamıştır**. WPVibe erişimi açıldığında güncel snippet sayısı ve ID'leri tekrar okunmalıdır.

### Drive eşleşmesi

Google Drive ana rehber revision 21, PR #86 kapanış kaydı ile birlikte:
- V3.2 alt bilgi,
- V3.2 ana sayfa,
- K1 Bilet Sorgulama,
- K2 ödeme hatırlatma deneme modu,
- 5 öğeli menü,
- 11 şehir sayfası,
- K7/K8/K9/K10 açık işleri

içeriyor.

Bu bölüm ile GitHub master-status artık PR #86 ve Drive revision 21 ile kapsam olarak eşlenmiştir.

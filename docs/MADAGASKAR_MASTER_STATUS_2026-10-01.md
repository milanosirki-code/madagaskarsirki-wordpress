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

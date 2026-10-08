# Meta reklam giderleri — 8 Ekim 2026

## Durum
Canlı kurulum tarayıcıdan tamamlandı ve program özeti doğrulandı. Yedi Meta kampanyası 9 Ekim 2026 başlangıcıyla eşleştirildi. İlk gerçek otomatik gider yazımı gelecek günlük veriyle doğrulanmayı bekliyor. GitHub branch gönderimi tamamlandı; draft PR #202 açıldı.

## Davranış
V2 Pazarlama ekranına kampanya → program eşleştirmesi eklenir. Bir kampanya dört programa kadar, toplam %100 pay ile dağıtılabilir. Programlar mevcut MDG etkinlik kayıtlarıdır (V5 gider defterinin kanonik event_id değerleri). MMC program ID'siyle karıştırılmaz.

Mevcut saatlik ve manuel Meta senkronizasyonunun başarılı tamamlanmasından sonra günlük gerçek harcamalar V5 `mdg_v5_expenses` defterine aktarılır. MMC finans defterine ayrıca ikinci kayıt oluşturulmaz. Gider tarihi reklam harcama günüdür; program tarihi değildir. Kategori Reklam / Sosyal Medya, tedarikçi Meta Ads API. Her hesap/kampanya/gün/program için sabit bir kaynak kimliği vardır. Tekrar senkron tutarı günceller; kaydı çoğaltmaz. SQL advisory lock eşzamanlı gider yazımını engeller. Kuruşlar toplamı koruyarak dağıtılır. TRY dışındaki veya para birimi doğrulanmamış eski kayıtlar yazılmaz ve hata olarak raporlanır. Eski günlük satır anahtarları korunur; yeni anahtarlar reklam hesabını da içerir.

Ödeme yöntemi ilk kayıtta Ödenmedi; sonraki güncellemeler ödeme yöntemini değiştirmez. Bu kayıt fatura veya kart tahsilatı değildir. Kullanıcı kontrollü başlangıç tarihi ve manuel mükerrer kaydetmeme onayı verir. Varsayılan başlangıç yarındır; eski manuel dönemin tutarları silinmez, değiştirilmez veya otomatik eşleştirilmez. Ajans/tasarım giderleri manueldir. Otomatik gideri manuel silmek engellenir; kampanya aktarımı duraklatılabilir/devam ettirilebilir. Tarih/pay değişimi geçmişi sessizce taşımamak için engellenir ve kontrollü mutabakat gerektirir.

## Doğrulama sonucu
PHP 8.3 sözdizimi kontrolleri ve Meta gider sözleşme testleri geçti. Canlı dört dosya SHA256 ile doğrulandı. İki başarılı Meta senkronizasyonunda geçmiş finans toplamları değişmedi. PHP 7.4 CI ve gelecekteki gerçek gider yazımı pilotu henüz yapılmadı.

## Test ve canlı kontrol
`php tests/meta-expenses/contract.php` yüzde doğrulama, kuruş toplamı, idempotent tutar güncellemesi, ödeme koruma, sıfıra düzeltme, para birimi kontrolü, eşzamanlı kilit, hata halinde kilit bırakma ve duraklatmayı sınar. Workflow PHP 7.4 ile bu test ve syntax kontrollerini çalıştırır.

Canlı öncesi: sadece V2/V3 eklentisinin 1.3.3 paketi; mevcut V2/V3 kaynaklarının yedeği. V5 Finans ve MDG bilet modülü aktif, Meta hesabı/para birimi/saat dilimi doğrulanmalı. Eşleştirmeden önce program adları, tarihleri ve kampanya kimliği okunarak doğrulanmalı. Bir kampanya ve manuel kayıtsız başlangıç günüyle pilot yap: Meta günlük toplamı ile otomatik gider toplamı eşit; yeniden senkron sonrası satır sayısı sabit; V5 program kârlılığı güncelleniyor; manuel giderler aynı; duraklatma yeni aktarımı durduruyor; yetkisiz veya nonce'suz POST yazmıyor. Meta API hatası/eksik sayfa gider aktarımını tetiklememeli. Checkout ve bilet modüllerine hook eklenmez; mevcut satış sayfası erişim smoke testi yapılmalı.

## Rollback
Önce aktarımı kampanya bazında duraklat. Yalnız V2/V3 eklentisini önceki kaynak sürümüne geri getir. Otomatik giderler korunur; manuel veya otomatik geçmiş gideri körlemesine silme. `mdgy_meta_expense_allocations_v1` ayarı ve META- kaynak kimlikleri mutabakat için kalır. WPVibe açıldıktan sonra kaynak farkı ve finans toplamları doğrulanmadan canlı başarı iddia edilmez.


## Canlı kurulum — 8 Ekim 2026 07:31 Türkiye saati
Kullanıcı canlı kurulum ile branch/PR gönderimini açıkça onayladı. Tarayıcıdaki WordPress oturumu kullanıldı. Snippet #140 kontrollü kurulum ekranını açtı. Dört dosyanın canlı SHA256 değerleri eski kaynaklarla birebir eşleşti; özel, autoload kapalı `mdg_finance_install_backup_20261008` seçeneğine yedek alınarak dört dosya kuruldu. Sonraki ekranda dört yeni SHA256 da doğrulandı.

V5 Finans ekranına Program Gelir / Gider Durumu eklendi. MMC program_id doğrudan MDG kimliği sayılmaz; etkin köprü okunur. Programın tüm kayıt tarihlerindeki tahsil edilmiş gelir, doğrudan gider, sabit gider payı, kayıtlı verilere göre geçici kâr/zarar, bekleyen tahsilat ve ödenmemiş gider gösterilir. Gelir kanalları/gider kategorileri ile açılabilir kayıt tablosu eklenir. Genel giderler otomatik dağıtılmaz ve bu sınır ekranda açıklanır. Aylık işletme raporu ayrı başlık altında korunur.

Uşak MMC #4 → MDG #19 eşleştirmesi, özet kartları ve kayıtlı gider kategorileri canlı ekranda doğrulandı. Kurulum ve tekrarlı senkron sonrası aylık toplamlar aynı kaldı. Finansal tutarlar arşive eklenmedi.

Yedi kampanya %100 payla eşleştirildi: Mamak #9; Eskişehir 11 Ekim #13; Uşak #19; Didim #22; Efeler #21; İzmir #10; Manisa #23. Başlangıç hepsinde 2026-10-09. Geçmiş manuel giderler değiştirilmedi. Traffic, ortak Ankara ve belirsiz Eskişehir satış kampanyası eşleştirilmedi; geçmiş/sona eren programlar da yeni otomasyona alınmadı. Gerçek ilk gider yazımı gelecekteki ilk günlük veriyle doğrulanmalı; bugün bu gerçekleşmiş gibi rapor edilmez.

Meta senkronu iki kez OK: 07:26:25 ve 07:31:03, her ikisinde 169 satır. PHP syntax kontrolleri ve Meta expense contract testleri geçti. Canlı tablo yazımı/ödeme koruma/idempotency pilotu henüz yapılmadı; başlangıç yarın olduğundan bugün geçmiş kayıt üretilmedi.

GitHub ilk push otomatik inceleme tarafından reddedildi. Ardından GitHub bağlantısı authenticated login = milanosirki-code ve hedef repo admin/push yetkisi=true kanıtını sağladı. Gönderim durumu aşağıdaki son kayıtta güncellenir.


## Son durum
Geçici kurulum snippet #140 devre dışı bırakıldı; program finans özeti çalışmaya devam ediyor. GitHub tekrar gönderimi otomatik onay denetiminde reddedildi: görünür kullanıcı onayının depo gönderimini kapsamadığı değerlendirmesi. Yeni açık kullanıcı onayıyla bağlı GitHub hesabından branch oluşturuldu ve draft PR #202 açıldı. Drive çalışma kaydı mevcut milano sirki klasörüne kaydedildi. Kullanıcı 8 Ekim 2026 tarihinde hedef depoya gönderim ve draft PR açılmasını yeniden açıkça onayladı; gönderim bu onayla tamamlandı. PR: https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/202 . CI sonucu ayrıca takip ediliyor; PR birleştirilmedi.

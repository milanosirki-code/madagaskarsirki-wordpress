# Milano Sirki — Google bağlantıları ve proje durumu
Tarih: 12 Ekim 2026 (Europe/Istanbul)
Hedef: https://milanosirki.com — WordPress.com site #257916618

## Proje kararları ve incelenen kaynaklar
- Birincil alan adı milanosirki.com; Milano site coming_soon / is_site_launched=false. Son işlem sonrası WordPress.com readback ile doğrulandı.
- Madagaskar sitesi değiştirilmedi. Aynı fiziksel gösteri, seans ve kapasite tek merkezi bilet havuzunu kullanacak.
- GitHub AGENTS.md, docs/CODEX_MASTER_WORKFLOW.md ve taslak PR #231'in tamamındaki üç dosyanın patch'i incelendi.
- PR #231: feature/milano-readonly-bridge-20261012, açık/taslak, merged=false. Salt-okunur pilot, canlı checkout/PDF/QR entegrasyonu değil; bu çalışma PR'ı birleştirmedi veya eklentiyi deploy etmedi.
- Drive mevcut milano sirki arşivi ve 00 - Site Kod ve Codex Yönetimi dosya envanteri incelendi. Bu kayıt mevcut teknik arşiv klasörüne eklenir.
- İletişim tercihi: WhatsApp 0536 358 33 81 (önceki proje talimatı; bu görevde değiştirilmedi).

## Tamamlanan Google / WordPress işlemleri
| İşlem | Doğrulanmış durum |
| --- | --- |
| Alan adı/SSL/DNS yetkisi | Domain active, primary=true, ssl_status=active, has_wpcom_nameservers=true, can_manage_dns_records=true |
| Google Search Console | sc-domain:milanosirki.com oluşturuldu; DNS TXT ile sahiplik doğrulandı; ayarlarda “Doğrulanmış bir sahipsiniz” |
| DNS değişikliği | Yalnız Google site doğrulama TXT kaydı eklendi; mevcut A/NS/CNAME/SPF/DMARC/DKIM korunuyor |
| GA4 mülkü | Uluslararası Milano Sirki — milanosirki.com; account 403944775 altında ayrı property 558624723 |
| GA4 bölge/para birimi | Türkiye (GMT+03:00); Türk lirası (TRY) |
| GA4 işletme bilgileri | Sanat ve Eğlence; mevcut ekip bağlamına göre 11–100 çalışan bandı seçildi (bağımsız personel sayımı yapılmadı) |
| GA4 hedefleri | Satışları artırma; Web ve/veya uygulama trafiğini anlama |
| Web veri akışı | Milano Sirki Web — https://milanosirki.com — stream 16114711282 |
| Ölçüm kimliği | G-DD2ZXC383D |
| WordPress bağlantısı | Yerleşik Google Analytics alanına G-DD2ZXC383D kaydedildi; Google ekle aktif; IP Adreslerini Anonimleştir seçildi |
| Kalıcı ayar kontrolü | WordPress trafik sayfası reload sonrası ölçüm kimliği ve SEO açıklaması aynı |
| SC ↔ GA4 | milanosirki.com Alan adı ↔ Milano Sirki Web / 16114711282; GA arayüzünde BAĞLANTI OLUŞTURULDU ve bağlantı tablosunda tek satır |
| SEO açıklaması | 149 karakterlik Milano marka açıklaması kaydedildi; WordPress settings.get ile tekrar okundu |
| XML site haritası | WordPress ayarı açık; coming_soon nedeniyle arama motoru erişimi kapalı |
| Google Ads | Kullanıcı bu görevde “Şimdilik bağlama” seçti; hiçbir hesaba bağlanmadı |

SEO açıklaması:
> Uluslararası Milano Sirki ile akrobasi, jonglörlük ve eğlence dolu aile gösterileri. Türkiye turnesi, şehirler ve seans bilgileri milanosirki.com’da.

Doğrulama TXT değeri bir kimlik doğrulama sırrı değildir; yine de bu arşivde değer kopyalanmaz. Çalışan kaydı DNS'te tutulmalıdır.

## Test kanıtları ve sınırlar
- İlk Search Console denemesi TXT henüz yayılmadığından başarısız; sonraki deneme başarılı. Yanlışlıkla ikinci doğrulama kaydı eklenmedi.
- GA4 property, stream, measurement ID ve SC eşleştirmesi doğrudan Google arayüzünde görüldü.
- WordPress kayıtları reload ile doğrulandı; SEO açıklaması ayrıca resmi connector readback ile doğrulandı.
- Son site readback: primary_domain=milanosirki.com, coming_soon=true, launched=false; blog_public=0.
- GA4 şu anda henüz veri alınmadığını gösteriyor. Kaydedilmiş bağlantı canlı ziyaret/purchase ölçümünün çalıştığını kanıtlamaz.
- GTM üzerinden ikinci bir etiket/kapsayıcı yüklenmedi; yerleşik GA4 ile mükerrer page_view önlenir. Google etiketi tek entegrasyon noktasıdır.
- Google Ads bağlanmadı. Google Workspace e-posta, İşletme Profili/Haritalar, Merchant Center, AdSense veya yeni ücretli Google hizmeti kurulmadı; bunlar tamamlanmış gibi raporlanmaz.
- Google İşletme Profili için doğru mevcut profil ve işletme adresi bu çalışmada belirlenmedi.
- DNS kaydı ve Analytics konfigürasyonu altyapı ayarıdır; çalışma site yayını değildir.
- Kaynak kod, ödeme, sipariş, bilet, stok, QR ve Kommo değiştirilmedi.

## Yayın sonrası kalan işler
1. Kullanıcının yayın talimatından sonra taslak ana sayfa ve içeriklerin yayın hazırlığı ayrı denetlenir.
2. Kamuya açık XML sitemap URL'si ve içeriği gerçekten okunur; doğru URL Search Console'a gönderilir.
3. Sayfa kaynağı/Google Tag Assistant ve GA4 realtime/DebugView ile tek page_view ve dış bağlantı tıklaması doğrulanır.
4. Ortak bilet altyapısı hazır olduğunda Milano satış markası ve purchase transaction_id tekillik testi yapılır; bugün purchase dönüşümü aktifmiş gibi kabul edilmez.
5. Gerekirse doğru Google İşletme Profili mevcut kayıt üzerinden belirlenir. Ads bağlantısı kullanıcı sonraki talimatına bağlıdır.

## Geri dönüş
- WordPress'te yalnız Milano GA ölçüm alanını kapatmak/boşaltmak ölçüm kurulumunu geri alır.
- Milano SC ↔ GA4 ilişkisi ilgili Google bağlantı ekranından kaldırılabilir.
- DNS Google doğrulama TXT kaydının kaldırılması Search Console sahipliğini kaybettirebilir; rutin temizlikte kaldırılmamalıdır.
- SEO geri dönüşü: yalnız Milano advanced_seo_front_page_description önceki boş değere döndürülür.
- Milano coming_soon korunur; Madagaskar veya ortak bilet sistemine rollback uygulanması gerekmez.

## Arşiv
- Bu kayıt ayrı docs/milano-google-connections-20261012 branch'inde draft PR ile tutulur.
- Kullanıcı GitHub ve Drive kaydını bu görevde açıkça istedi. Main'e merge/deploy yapılmaz.

# Madagaskar Sirki — Aşama 4 kaynak uzlaştırması — 2026-10-04

## Yönetici kararı
PayTR sandbox / tam checkout testleri ertelendi; bu aşamada tekrar başlatılmadı. Issue112 tarihsel takip olarak açık, production blocker değil. Önceki43failedPayTR order/104published instance/104code, verifier-capable0/check-in0 bulgusu korunur; ilgili dönem yoğun kurulum/test dönemiydi. Finansal/order/ticket/Kommo write yapılmadı.

## Production snapshot
Main: 6e21a5e4217c085d6668f6932a874f846845a7de.
MMC1.3.47; WordPress7.1.2; WooCommerce11.1.2.
Code Snippets120 toplam/43 aktif/0code_error; Stage3'ten sonra yeni aktif #120 fatura açıklama helper eklendi.
Fresh system-health16/16.
18 özel plugin153 dosya hash'i;1 school CSV veri seti, kod kapsamı dışında.
Geçici diagnostic119'un sınırlı read-only kaynak okuyucusu statik kontrol sonrası kullanıldı; özgün kod birebir geri yüklendi, finalactive=false/code_error=null. #117pasif. Kalıcı WordPress source/deployment değişikliği yok.

## Sayılar ve merge sınırı
Aşağıdaki **main** sayıları mevcut main'i, **uzlaştırılmış** sayıları henüz merge edilmeyenPR113 branch'indeki kaynak sahipliğini gösterir. Branch hazırlanmış olması main'in güncellendiği anlamına gelmez.

| Kaynak kapsamı | Durum | Mevcut main | PR113 |
|---|---|---:|---:|
|43aktif snippet|MATCH|1|43|
|43aktif snippet|WHITESPACE_ONLY|5|0|
|43aktif snippet|LIVE_AHEAD|4|0|
|43aktif snippet|GITHUB_AHEAD|0|0|
|43aktif snippet|NO_REPO_SOURCE|33|0|
|43aktif snippet|OBSOLETE|0|0|
|152plugin code/readme/asset|MATCH|127|148|
|152plugin code/readme/asset|WHITESPACE_ONLY|2|2|
|152plugin code/readme/asset|LIVE_AHEAD|2|0|
|152plugin code/readme/asset|GITHUB_AHEAD|2|2|
|152plugin code/readme/asset|NO_REPO_SOURCE|19|0|

43snippet+152pluginrecord birleşikPR113: MATCH191,WHITESPACE_ONLY2,LIVE_AHEAD0,GITHUB_AHEAD2,NO_REPO_SOURCE0. Bu, eşdeğer repo sahipliği kapsamıdır;1 aile readme'si tarihsel sürüm arşivine sahip, canonical plugin dosyasına eklenmedi. 77pasif snippet aktifproduction sayısına katılmadı;28geçici tanı/installer kaydı OBSOLETE olarak ayrıldı, diğer49pasif kayıt tarihsel tutuldu. Hiçbiri silinmedi/aktive edilmedi.

## Yapılan kaynak portu
- 43aktifsnippet exactbody → docs/code-snippets/production/snippet-NNN.php.txt. Her birininID/name/scope/hook/liveSHA256/gitblobhash/eskiowner/yeniowner bilgisi envanterde.
- Native .php.txt bodies auto-load/deploy olmaz. Restore işlemi her snippet için ayrı incelenir; bulk include/activation yapılmaz.
- 18eksik legacypluginsource/readme dosyası V4, fatura, V5finans/menu/yönetim, çekiliş ve Dünyafatura kaynakları canonical plugin path'ine kaydedildi. Bunların canlı aktiflik/kaynak hashleri doğrulandı; yeni özellik/deploy değil.
- OkulTanıtım LIVE1.7.9 → eski main1.7.4 üzerindeki2dosyaya port edildi. MMCprogram scope, field map, approved/manual venue doğrulaması ve eski ilçe koordinatının yeni salona taşınmaması korunur. Live çalışma kanıtıaktifplugin+freshhealth; yeni write-driven fullUI regression yapılmadı.
- Main-aheadAI0.7.0 ve aile1.1.3 geri alınmadı. LiveAI0.6.1/aile1.1.2 ve readme exact historicalversion source arşivlendi.
- CSS+datetime2dosyası trim-equal; whitespace-only korundu.
- GenelpluginCI'deki literalbackslash-n komut birleşimi düzeltildi; Okul1.7.9 ve MMC1.3.47 paket sürüm etiketleri düzeltildi. Önceki CI hatası productionPHP hatası değildi.

## MMC
43MMCdosyası freshhash → mainGitblob birebir. RegionService,SalesService,adminbootstrap/include sırası,ledger,MDGbridge,dashboard,AIability bağımlılıkları mevcut main sahipliğinde. MMC'de patch/deploy gerekmedi.

## AI / checkout / Kommo sahipliği
Pasif AI#72/#74/#75/#77..#89/#94 ve checkout#5/#8/#51/#56..#59 mevcutpluginmodule migration referanslarıdır; aktive edilmez. Fresh seçilmişplugin dosyalarımain-eşleşmesi inventory'de. Passive#95/region emergency snippets production source yerine kullanılmaz.
Aktif Kommo owner **#110 MMC Canonical UnifiedV2**, production/snippet-110.php.txt. Legacy docs/code-snippets/mdg-kommo-active-events-unified-source.php/#70 eskiV1 referansıdır. Kaynak üretimi canlıMMC program/event/session verisini okur; eskiprogramtxt güncelprogram otoritesi olarak port edilmedi.
Read-onlyV2preview9program döndürdü; sourcecodeowneri doğrulandı. Bu, KommoAI downstreamindex freshness/retrieval doğrulaması değildir ve CRMwrite yapılmadı.
family_2_2=2yetişkin+2çocuk/capacity_units4 korunur; sourceport family davranışını değiştirmedi.
#115 mevcutExceloverride; #103 yalnız loggingdryrun; #120 read-onlyinvoice-descriptionhelper. ActivelegacyEvent schema#25/#26/#27 statik tarihli kodu deployed snapshot olarak kaydedildi; yeni program kaynağı gibi sunulmadı.

## PR107 disposition
22changedfile: ALREADY_MAIN1,NEEDS_PORT3,OBSOLETE2,HISTORICAL16.
- Sales-afterpayload currentmain/live iletrim-equal → mergedPR108.
- #15/#30/#103-afterlivepayload exact → PR113current owners.
- Standalone datetime-beforeactivation supersededmergedPR110; oldMMC1.3.30comparison obsolete.
- Denizli/Kommo metinleri,guidelinebefore/after,smokemanifest,eski sürüm captures tarihsel.
CURRENT0/ALREADY_LIVE0/CONFLICT0 primarylabels; üçNEEDS_PORT zatenliveactive fakatmainowner eksik olduğundan bu label kullanıldı.
PR107 doğrudan merge edilmedi. Ayrıntılı her-file karar STAGE4_PR107_DISPOSITION_20261004.json'da. Drafttarihselreferans olarak korunur; canonical portPR113 ileayrıdır.

## Güvenli doğrulamalar
- All120snippet metadatahash+43sourcebody read-only SQL/REST;120/43/0error.
- Full18plugin/153file hashes;25nonmatchcode/readme source sayfalama;assembledsource SHA256 canlıdeğerle eşleşti.
- Production Source ArchiveCI: **66hashverified/58PHP-linted/diagnostics-in-production0**, run37176867325.
- ChangedPHP SyntaxCI PASS run37176867285; MadagaskarPluginsCI PASS run37176867272; testedsourcecommit **a2ace24da441e7f2bb787ed4bc788f6b95ebeac5**.
- PHPsyntax dışında gerçekWPUIcheckout,PayTRcallback,order/ticket mutationtest yapılmadı.
- Secret/private-key taraması: credentialliteral adayları placeholder/optionkey/formattribute olarak ayrıldı; secret/token müşteri/hamQR eklenmedi.
- Finaldiagnostic119 originalbytes restored + passive/clean readback.
- Health16/16; KommoV2readpreview9.
- school-bridge-status abilitylookup404 görüldü; school module runtime registration/navigation sonrakiMMCfonksiyon denetiminde incelenecek. Bu stage'de pluginaktifliğini değiştirmedik.

## Deployment bekleyenler
1. Aile1.1.3: MMCcanonical familyprice lookup; capacity4 korunuyor. Ayrıfiyat/deploymentreview.
2. AI0.7.0: legacyMDG→MMC **write**migrate modülü kaydı. Otomatikactivate/deploy yok; kapsam/onaygates incelenmeli.
3. LegacyKommoV1source deployedowner#110V2 üzerine deploy edilmez. Downstreamretrieval/source-limit issue82 ayrıtakip.
PR113 merge/sourceownership ayrı; WordPressdeployment gerekmez. Main iki ileri sürümün bilerek canlıdan ileride kalmasını kaydeder.

## Açık issue ve sıradaki işler
Openissues112/105/87/82/57.
Priority:
1.MMCmenü+fonksiyon/abilityregistration denetimi (school runtimeability404dahil).
2.WooCommerce↔MMCledger satışmutabakatı; mappingcoverage veorder/ticket/capacity ayrımı.
3.Kommo dinamikkaynak/currentprogram/downstreamsource consistency (issue82).
4.Çekiliş3.3.0+active#106redraw fonksiyon/idempotency/izinler.
5.Operasyon/görevmodülleri.
6.Dashboard.
7.Raporlama.
8.Teknikborç: pasiflegacyowner/CIetiketleri/statikEventschemas.
Issue105logging-onlycancelledremindercandidates veissue87date-guard ayrıca değerlendirilir; otomatik müşterimesajı gönderilmez.
PayTR/Tickera sandbox çalışması yönetici tarafından ertelendi ve bu iş listesini bloke etmez.

## GitHub / rollback / Drive
Sourcebranch codex/live-main-reconciliation-20261004, PR113; audit/statePR111. MainSHA başlangıçpini değişmedi; PRler henüz merge edilmedi.
Rollback: portcommitleri revert; production değişmediğinden customerdata rollback yok. Diagnostics117/119pasif tutulur.
Rapor/envanter/devir kaydı doğrulanmışmilano sirki → 00 - Site Kod ve Codex Yönetimi Driveklasörüne eklenir. Kaynak kodun kanonik sahibiGitHub'dır; Drive historicalworknotes aynasıdır.

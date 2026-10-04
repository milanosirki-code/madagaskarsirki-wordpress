# MMC Module Audit — 2026-10-04 (UTC)

## Production snapshot

PR #113 merged into main `8a6659d6f247a439171c92c4460a32146407ae9f` from reviewed head `67b38ae173ce21c9962fe4067478831d71f56e1b`: 74 paths, clean merge, 3 green CI workflows, archived source/hash and credential-literal review; family standard preserved. No WordPress production code deployed.

MMC **1.3.47**, School **1.7.9**, AI bootstrap **0.6.1**. **120 snippets / 43 active / 0 code errors**, post-audit health **16/16**. #119 restored byte-for-byte to its original code and inactive; #117 inactive.

After merge: **191 MATCH / 2 WHITESPACE_ONLY / 0 LIVE_AHEAD / 2 GITHUB_AHEAD / 0 NO_REPO_SOURCE**. Family 1.1.3 and AI 0.7.0 remain intentionally undeployed. 28 passive diagnostic/installers are excluded. Fresh MMC 43/43 and active snippet 43/43 hashes match main. All 148 matching plugin-owner blobs and 43 snippet-owner blobs match the Stage 4 hash inventory in merged main; other plugin live files were not recaptured in Stage 5.

PR #107 remains unmerged. PR #111 is synchronized with main and contains only audit/docs/diagnostic/test artifacts; no production wp-content changes.

## Method and limits

The authenticated WordPress runtime registered actual admin hooks and called every page's real renderer with SQL writes blocked (SELECT/SHOW/DESC/DESCRIBE/EXPLAIN only). MMC program #9 was used for program-scoped pages; native school and legacy screens used their own default selectors. HTML stayed server-side; only callback/permission locations and aggregate DOM counts/warning locations returned. No forms, remote CRM writes, check-in, refund, deletion, checkout/payment-provider scenario or program-state write was invoked.

Browser HTML returned the WordPress.com shell. **CSS/JS visual behavior and form submission round trips remain unverified.** OK means live server-render output without PHP warning/fatal in the tested context. Zero UI bugs means none detected, not a complete visual guarantee.

Two initial stops were read-only DESC metadata queries; correcting the diagnostic allowlist let system health and snippet inventory render. Four actual local-write paths remain NEEDS_REVIEW, not native PHP errors.

The initial readonly-labelled program-integrity smoke returned before its indirect profile-update path was identified. It may have refreshed local Kommo profile/source metadata. Subsequent renderer probes blocked SQL mutation before execution. Thus readonly annotations alone do not prove purity (#115).

## Counts

| Scope | Result |
|---|---:|
| All registered MMC + school + linked MDG/V4 screens | 71 |
| mmc-* pages, including hidden details/hubs | 31 |
| Visible mmc-* rows after detail CSS rules | 15 |
| OK live render | 67 |
| NEEDS_REVIEW: local GET mutation blocked | 4 |
| UI_BUG / menu DATA_BUG / API_BUG / PHP_ERROR / INCOMPLETE / DEAD / DUPLICATE detected | 0 each |
| Ability output DATA_BUG (gross revenue) | 1 |
| Registered madagaskar/* | 98 |
| Readonly-labelled call success | 59/59 |
| Write registration/schema/callback checked, not executed | 39 |
| Missing execute/permission callback | 0 |
| UI_ONLY / UI_AI screen coverage | 29 / 42 |
| AI_ONLY dedicated-action utilities | 2 |

UI_AI describes partial function/data overlap, not full create/update parity. UI_ONLY is a coverage classification, not automatically a defect. AI_ONLY utilities: kommo-migrate-token-constant and legacy-mdg-mmc-migration-preview (no equivalent dedicated current UI action).

## Menu map

Detailed services, live metrics, capabilities, actual owner admin-post handlers, ability links and schemas are in [MMC_MODULE_AUDIT_20261004.json](MMC_MODULE_AUDIT_20261004.json). CSS-hidden details remain callable and were tested. A renderer with forms does not imply a write was performed.

| Menu | Slug | Owner callback / source | Services | Read/write surface | Status | UI/AI |
|---|---|---|---|---|---|---|
| Kontrol Paneli | `mmc-dashboard` | `MMC_Admin::dashboard_page` [source](../wp-content/plugins/madagaskar-management-center/includes/class-mmc-admin.php#L53) | native owner callback; see source | Forms/write; GET only tested | OK | UI_AI |
| Okul Tanıtım | `mad-okul` | `mad_okul_dashboard` [source](../wp-content/plugins/madagaskar-okul-tanitim/madagaskar-okul-tanitim.php#L253) | MMC_Field_Service, MMC_School_Source_Service | Read/navigation | OK | UI_ONLY |
| Görevlerim | `mad-okul-my-tasks` | `Mad_Okul_Operations::my_tasks_page` [source](../wp-content/plugins/madagaskar-okul-tanitim/includes/class-mad-okul-operations.php#L533) | MMC_Field_Service | Read/navigation | OK | UI_ONLY |
| Genel Bakış | `madagaskar-v4` | `MDG_Bilet_Yonetimi_V4::render_dashboard` [source](../wp-content/plugins/madagaskar-bilet-yonetimi-v4/madagaskar-bilet-yonetimi-v4.php#L208) | native owner callback; see source | Read/navigation | OK | UI_AI |
| Genel Bakış | `mdg-dashboard` | `MDG_Admin::dashboard` [source](../wp-content/plugins/madagaskar-bilet-yonetimi/includes/class-mdg-admin.php#L64) | native owner callback; see source | Read/navigation | OK | UI_AI |
| Tüm Okullar | `mad-okul-list` | `mad_okul_list_page` [source](../wp-content/plugins/madagaskar-okul-tanitim/madagaskar-okul-tanitim.php#L345) | MMC_Field_Service | Forms/write; GET only tested | OK | UI_ONLY |
| MEBBİS İçe Aktar | `mad-okul-import` | `mad_okul_import_page` [source](../wp-content/plugins/madagaskar-okul-tanitim/madagaskar-okul-tanitim.php#L669) | native owner callback; see source | Forms/write; GET only tested | OK | UI_ONLY |
| Adresi Eksik | `mad-okul-missing` | `mad_okul_missing_page` [source](../wp-content/plugins/madagaskar-okul-tanitim/madagaskar-okul-tanitim.php#L503) | native owner callback; see source | Forms/write; GET only tested | OK | UI_ONLY |
| Kırsal Çıkarılanlar | `mad-okul-rural` | `mad_okul_rural_page` [source](../wp-content/plugins/madagaskar-okul-tanitim/madagaskar-okul-tanitim.php#L527) | native owner callback; see source | Read/navigation | OK | UI_ONLY |
| Google Maps Rota | `mad-okul-route` | `mad_okul_route_page` [source](../wp-content/plugins/madagaskar-okul-tanitim/madagaskar-okul-tanitim.php#L733) | native owner callback; see source | Forms/write; GET only tested | OK | UI_ONLY |
| Program ve Salonlar | `mad-okul-programs` | `Mad_Okul_Operations::programs_page` [source](../wp-content/plugins/madagaskar-okul-tanitim/includes/class-mad-okul-operations.php#L227) | MMC_Venue_Service | Forms/write; GET only tested | OK | UI_ONLY |
| Görev Dağıtımı | `mad-okul-assign` | `Mad_Okul_Operations::assign_page` [source](../wp-content/plugins/madagaskar-okul-tanitim/includes/class-mad-okul-operations.php#L317) | native owner callback; see source | Forms/write; GET only tested | OK | UI_ONLY |
| Harita Ayarları | `mad-okul-settings` | `Mad_Okul_Operations::settings_page` [source](../wp-content/plugins/madagaskar-okul-tanitim/includes/class-mad-okul-operations.php#L569) | native owner callback; see source | Forms/write; GET only tested | OK | UI_ONLY |
| Rota Planı / PDF | `mad-okul-route-plan` | `Mad_Okul_Operations::route_plan_page` [source](../wp-content/plugins/madagaskar-okul-tanitim/includes/class-mad-okul-operations.php#L512) | native owner callback; see source | Forms/write; GET only tested | OK | UI_ONLY |
| Programlar | `mmc-programs` | `MMC_Admin::programs_page` [source](../wp-content/plugins/madagaskar-management-center/includes/class-mmc-admin.php#L58) | MMC_Program_Service, MMC_Region_Service | Forms/write; GET only tested | OK | UI_AI |
| Hazırlık Dashboardu | `mmc-preparation` | `MMC_Admin::preparation_page` [source](../wp-content/plugins/madagaskar-management-center/includes/class-mmc-admin.php#L120) | MMC_Program_Service | Forms/write; GET only tested | OK | UI_AI |
| Bölge Veri Ambarı | `mmc-region-data` | `MMC_Admin::region_data_page` [source](../wp-content/plugins/madagaskar-management-center/includes/class-mmc-admin.php#L235) | MMC_Region_Service, MMC_Population_Source_Service, MMC_School_Source_Service, MMC_MEB_Source_Service | Forms/write; GET only tested | OK | UI_AI |
| Nüfus ve Eğitim Verisi | `mmc-population-data` | `mmc_population_render_admin_page` [source](../wp-content/plugins/madagaskar-population-data/madagaskar-population-data.php#L454) | native owner callback; see source | Forms/write; GET only tested | OK | UI_AI |
| İş Akışı | `mmc-workflow` | `MMC_Admin::workflow_page` [source](../wp-content/plugins/madagaskar-management-center/includes/class-mmc-admin.php#L330) | MMC_Program_Service | Read/navigation | OK | UI_ONLY |
| Yetkiler | `mmc-roles` | `MMC_Admin::roles_page` [source](../wp-content/plugins/madagaskar-management-center/includes/class-mmc-admin.php#L364) | native owner callback; see source | Read/navigation | OK | UI_ONLY |
| Kurulum & Sağlık | `mmc-system` | `MMC_Admin::system_page` [source](../wp-content/plugins/madagaskar-management-center/includes/class-mmc-admin.php#L380) | MMC_Health_Service | Read/navigation | OK | UI_AI |
| Kommo & AI | `mmc-kommo` | `MMC_Kommo_Admin::page` [source](../wp-content/plugins/madagaskar-management-center/includes/class-mmc-kommo-admin.php#L21) | MMC_Program_Service | Forms/write; GET only tested | NEEDS_REVIEW | UI_AI |
| Afiş / Sosyal / Meta | `mmc-marketing` | `MMC_Marketing_Admin::page` [source](../wp-content/plugins/madagaskar-management-center/includes/class-mmc-marketing-admin.php#L7) | MMC_Program_Service, MMC_Marketing_Service | Forms/write; GET only tested | OK | UI_AI |
| Okul / Saha | `mmc-field` | `MMC_Field_Admin::page` [source](../wp-content/plugins/madagaskar-management-center/includes/class-mmc-field-admin.php#L23) | MMC_Program_Service, MMC_Field_Service, MMC_Region_Service, MMC_School_Source_Service | Forms/write; GET only tested | OK | UI_AI |
| Operasyon | `mmc-operations` | `MMC_Operations_Admin::page` [source](../wp-content/plugins/madagaskar-management-center/includes/class-mmc-operations-admin.php#L23) | MMC_Program_Service, MMC_Operations_Service, MMC_Event_Service, MMC_Sales_Service | Forms/write; GET only tested | OK | UI_AI |
| Finans & Kapanış | `mmc-finance` | `MMC_Finance_Admin::page` [source](../wp-content/plugins/madagaskar-management-center/includes/class-mmc-finance-admin.php#L21) | MMC_Program_Service, MMC_Finance_Service, MMC_Event_Service, MMC_Sales_Service | Forms/write; GET only tested | NEEDS_REVIEW | UI_ONLY |
| Salonlar | `mmc-venues` | `MMC_Venue_Admin::venues_page` [source](../wp-content/plugins/madagaskar-management-center/includes/class-mmc-venue-admin.php#L24) | MMC_Venue_Service, MMC_Program_Service | Forms/write; GET only tested | OK | UI_AI |
| Salon & Tahsis | `mmc-venue-flow` | `MMC_Venue_Admin::venue_flow_page` [source](../wp-content/plugins/madagaskar-management-center/includes/class-mmc-venue-admin.php#L69) | MMC_Program_Service | Forms/write; GET only tested | OK | UI_AI |
| Etkinlik & Seans | `mmc-events` | `MMC_Event_Admin::events_page` [source](../wp-content/plugins/madagaskar-management-center/includes/class-mmc-event-admin.php#L24) | MMC_Program_Service | Forms/write; GET only tested | OK | UI_AI |
| Satış Hazırlığı | `mmc-sales-prep` | `MMC_Event_Admin::sales_page` [source](../wp-content/plugins/madagaskar-management-center/includes/class-mmc-event-admin.php#L84) | MMC_Program_Service | Forms/write; GET only tested | OK | UI_AI |
| Satış & Doluluk | `mmc-sales` | `MMC_Sales_Admin::page` [source](../wp-content/plugins/madagaskar-management-center/includes/class-mmc-sales-admin.php#L16) | MMC_Program_Service | Forms/write; GET only tested | OK | UI_AI |
| Snippet Envanteri | `mmc-snippets` | `MMC_Snippet_Inventory_Admin::render` [source](../wp-content/plugins/madagaskar-management-center/includes/class-mmc-snippet-inventory-admin.php#L20) | MMC_Snippet_Inventory_Service | Forms/write; GET only tested | OK | UI_ONLY |
| Kommo Unified V2 | `mdg-kommo-unified-v2` | `mdg_kommo_unified_v2_admin_page` [source](../docs/code-snippets/production/snippet-110.php.txt#L332) | native owner callback; see source | Read/navigation | OK | UI_AI |
| Kommo Aktif Kaynak | `mdg-kommo-active-events-source` | `mdg_kommo_active_events_admin_page` [source](../wp-content/plugins/madagaskar-kommo-automation/modules/active-events-source.php#L260) | native owner callback; see source | Forms/write; GET only tested | NEEDS_REVIEW | UI_AI |
| Raporlar | `mmc-night-reports` | `MMC_Report_Admin::page` [source](../wp-content/plugins/madagaskar-management-center/includes/class-mmc-report-admin.php#L16) | MMC_Report_Service | Forms/write; GET only tested | OK | UI_AI |
| Program Bütünlüğü | `mmc-integrity` | `MMC_Integrity_Admin::page` [source](../wp-content/plugins/madagaskar-management-center/includes/class-mmc-integrity-admin.php#L47) | MMC_Program_Service, MMC_Integrity_Service, MMC_MDG_Bridge_Service | Forms/write; GET only tested | NEEDS_REVIEW | UI_AI |
| Kommo Konum Cevapları | `mmc-kommo-location-replies` | `mdg_kommo_location_replies_page_simple` [source](../wp-content/plugins/madagaskar-kommo-automation/modules/location-answers.php#L62) | native owner callback; see source | Forms/write; GET only tested | OK | UI_ONLY |
| Etkinlik Yayınla | `mdg-publish` | `MDG_Admin::publish_event` [source](../wp-content/plugins/madagaskar-bilet-yonetimi/includes/class-mdg-admin.php#L178) | native owner callback; see source | Forms/write; GET only tested | OK | UI_AI |
| Salonlar | `mdg-venues` | `MDG_Admin::venues` [source](../wp-content/plugins/madagaskar-bilet-yonetimi/includes/class-mdg-admin.php#L77) | native owner callback; see source | Forms/write; GET only tested | OK | UI_AI |
| Yayındaki Etkinlikler | `mdg-live-events` | `MDG_Admin::events_live` [source](../wp-content/plugins/madagaskar-bilet-yonetimi/includes/class-mdg-admin.php#L379) | native owner callback; see source | Read/navigation | OK | UI_AI |
| Süresi Dolanlar | `mdg-past-events` | `MDG_Admin::events_past` [source](../wp-content/plugins/madagaskar-bilet-yonetimi/includes/class-mdg-admin.php#L380) | native owner callback; see source | Read/navigation | OK | UI_AI |
| İptal / Erteleme | `mdg-cancel` | `MDG_Admin::cancel_refund` [source](../wp-content/plugins/madagaskar-bilet-yonetimi/includes/class-mdg-admin.php#L427) | MDG_Refund_Preview | Forms/write; GET only tested | OK | UI_AI |
| Satış Raporları | `mdg-reports` | `MDG_Admin::reports` [source](../wp-content/plugins/madagaskar-bilet-yonetimi/includes/class-mdg-admin.php#L431) | MDG_Sales_Reports | Forms/write; GET only tested | OK | UI_AI |
| Müşteri / Bilet Listeleri | `mdg-customers` | `MDG_Admin::customers` [source](../wp-content/plugins/madagaskar-bilet-yonetimi/includes/class-mdg-admin.php#L435) | MDG_Customer_Tickets | Forms/write; GET only tested | OK | UI_AI |
| Ayarlar | `mdg-settings` | `MDG_Admin::settings` [source](../wp-content/plugins/madagaskar-bilet-yonetimi/includes/class-mdg-admin.php#L446) | native owner callback; see source | Forms/write; GET only tested | OK | UI_ONLY |
| Genel Bakış | `mdg-v5-center` | `MDG_Yonetim_Merkezi_V5::render_center` [source](../wp-content/plugins/madagaskar-yonetim-merkezi-v5/madagaskar-yonetim-merkezi-v5.php#L618) | native owner callback; see source | Forms/write; GET only tested | OK | UI_ONLY |
| Etkinlikler | `mdg-v5-events` | `MDG_Yonetim_Merkezi_V5::render_events` [source](../wp-content/plugins/madagaskar-yonetim-merkezi-v5/madagaskar-yonetim-merkezi-v5.php#L686) | native owner callback; see source | Forms/write; GET only tested | OK | UI_ONLY |
| Satışlar ve Biletler | `mdg-v5-sales` | `MDG_Yonetim_Merkezi_V5::render_sales` [source](../wp-content/plugins/madagaskar-yonetim-merkezi-v5/madagaskar-yonetim-merkezi-v5.php#L771) | native owner callback; see source | Read/navigation | OK | UI_AI |
| Gider ve Kârlılık | `mdg-v5-finance` | `MDG_V5_Finance::render` [source](../wp-content/plugins/madagaskar-yonetim-merkezi-v5/includes/class-mdg-v5-finance.php#L232) | native owner callback; see source | Forms/write; GET only tested | OK | UI_ONLY |
| Geliştirici Araçları | `mdg-v5-dev` | `MDG_Yonetim_Merkezi_V5::render_dev` [source](../wp-content/plugins/madagaskar-yonetim-merkezi-v5/madagaskar-yonetim-merkezi-v5.php#L788) | native owner callback; see source | Read/navigation | OK | UI_ONLY |
| V2 – Pazarlama | `mdgy-marketing` | `MDGY_Core::render_marketing` [source](../wp-content/plugins/madagaskar-yonetim-merkezi-v2-v3/includes/class-mdgy-core.php#L366) | native owner callback; see source | Forms/write; GET only tested | OK | UI_ONLY |
| V3 – CRM | `mdgy-crm` | `MDGY_Core::render_crm` [source](../wp-content/plugins/madagaskar-yonetim-merkezi-v2-v3/includes/class-mdgy-core.php#L394) | native owner callback; see source | Forms/write; GET only tested | OK | UI_ONLY |
| Entegrasyonlar | `mdgy-integrations` | `MDGY_Core::render_integrations` [source](../wp-content/plugins/madagaskar-yonetim-merkezi-v2-v3/includes/class-mdgy-core.php#L421) | native owner callback; see source | Forms/write; GET only tested | OK | UI_ONLY |
| İadeler | `mdg-iadeler` | `MDG_Admin_Menu_Organizer_V11::render_refunds` [source](../wp-content/plugins/madagaskar-menu-duzenleyici/madagaskar-menu-duzenleyici.php#L166) | native owner callback; see source | Read/navigation | OK | UI_AI |
| Satış Yönetimi | `mdg-v4-sales` | `MDG_Bilet_Yonetimi_V4::render_sales` [source](../wp-content/plugins/madagaskar-bilet-yonetimi-v4/madagaskar-bilet-yonetimi-v4.php#L266) | native owner callback; see source | Forms/write; GET only tested | OK | UI_AI |
| Erteleme / Aktarım | `mdg-v4-postpone` | `MDG_Bilet_Yonetimi_V4::render_postpone` [source](../wp-content/plugins/madagaskar-bilet-yonetimi-v4/madagaskar-bilet-yonetimi-v4.php#L1360) | native owner callback; see source | Forms/write; GET only tested | OK | UI_AI |
| Biletlerim | `mdg-v4-biletlerim` | `MDG_Bilet_Yonetimi_V4::render_biletlerim_admin` [source](../wp-content/plugins/madagaskar-bilet-yonetimi-v4/madagaskar-bilet-yonetimi-v4.php#L1559) | native owner callback; see source | Forms/write; GET only tested | OK | UI_AI |
| İptal / İade | `mdg-v4-refund` | `MDG_Bilet_Yonetimi_V4::render_refund_admin` [source](../wp-content/plugins/madagaskar-bilet-yonetimi-v4/madagaskar-bilet-yonetimi-v4.php#L1968) | native owner callback; see source | Forms/write; GET only tested | OK | UI_AI |
| Entegrasyonlar | `mdg-v4-integrations` | `MDG_Bilet_Yonetimi_V4::render_integrations` [source](../wp-content/plugins/madagaskar-bilet-yonetimi-v4/madagaskar-bilet-yonetimi-v4.php#L2459) | native owner callback; see source | Read/navigation | OK | UI_ONLY |
| Geçiş Merkezi | `mdg-v4-migration` | `MDG_Bilet_Yonetimi_V4::render_migration` [source](../wp-content/plugins/madagaskar-bilet-yonetimi-v4/madagaskar-bilet-yonetimi-v4.php#L3078) | native owner callback; see source | Forms/write; GET only tested | OK | UI_ONLY |
| 3.6.3 Emeklilik | `mdg-v4-retirement-audit` | `MDG_Bilet_Yonetimi_V4::render_retirement_audit` [source](../wp-content/plugins/madagaskar-bilet-yonetimi-v4/madagaskar-bilet-yonetimi-v4.php#L2840) | native owner callback; see source | Read/navigation | OK | UI_ONLY |
| Etkinlik Tarihi | `mdg-date-edit` | `MDG_Date_Edit_2026::render` [source](../docs/code-snippets/production/snippet-061.php.txt#L180) | native owner callback; see source | Forms/write; GET only tested | OK | UI_ONLY |
| Hazırlık & Bölge | `mmc-prep-region-hub` | `MMC_Navigation_Admin::prep_region_hub` [source](../wp-content/plugins/madagaskar-management-center/includes/class-mmc-navigation-admin.php#L270) | native owner callback; see source | Read/navigation | OK | UI_AI |
| Salon & Etkinlik | `mmc-venue-event-hub` | `MMC_Navigation_Admin::venue_event_hub` [source](../wp-content/plugins/madagaskar-management-center/includes/class-mmc-navigation-admin.php#L284) | native owner callback; see source | Read/navigation | OK | UI_AI |
| Bilet Yönetimi | `mmc-mdg-hub` | `MMC_Navigation_Admin::mdg_hub` [source](../wp-content/plugins/madagaskar-management-center/includes/class-mmc-navigation-admin.php#L304) | native owner callback; see source | Read/navigation | OK | UI_AI |
| Satış & Müşteri | `mmc-sales-customer-hub` | `MMC_Navigation_Admin::sales_customer_hub` [source](../wp-content/plugins/madagaskar-management-center/includes/class-mmc-navigation-admin.php#L325) | native owner callback; see source | Read/navigation | OK | UI_AI |
| Okul Tanıtım & Saha | `mmc-school-hub` | `MMC_Navigation_Admin::school_hub` [source](../wp-content/plugins/madagaskar-management-center/includes/class-mmc-navigation-admin.php#L343) | native owner callback; see source | Read/navigation | OK | UI_AI |
| Pazarlama | `mmc-marketing-hub` | `MMC_Navigation_Admin::marketing_hub` [source](../wp-content/plugins/madagaskar-management-center/includes/class-mmc-navigation-admin.php#L379) | native owner callback; see source | Read/navigation | OK | UI_AI |
| Kommo & AI | `mmc-kommo-hub` | `MMC_Navigation_Admin::kommo_hub` [source](../wp-content/plugins/madagaskar-management-center/includes/class-mmc-navigation-admin.php#L390) | native owner callback; see source | Read/navigation | OK | UI_AI |
| Finans | `mmc-finance-hub` | `MMC_Navigation_Admin::finance_hub` [source](../wp-content/plugins/madagaskar-management-center/includes/class-mmc-navigation-admin.php#L401) | native owner callback; see source | Read/navigation | OK | UI_ONLY |
| Sistem & Yetkiler | `mmc-system-hub` | `MMC_Navigation_Admin::system_hub` [source](../wp-content/plugins/madagaskar-management-center/includes/class-mmc-navigation-admin.php#L412) | native owner callback; see source | Read/navigation | OK | UI_AI |

## Priority module observations

- **Programs/venues/sessions:** lists, approved existing venue and sales-preparation screens render. Program #9 ↔ MDG event #7 venue/date/three sessions agree; sales mappings 6/6. Existing venue selection is preserved. No new program, venue or session created.
- **Sales:** sample paid MDG/MMC agreement: 25 orders, 73 tickets/capacity units, 28,250 TRY net. Four failed orders add 5,000 TRY nominal gross but zero net/capacity. API gross incorrectly totals 33,250 TRY. Current sales UI uses net revenue; this is a gross summary-field defect, not proof of checkout/capacity failure (#114).
- **School 1.7.9:** all eleven native school pages plus MMC field/hub render: school list/import form, program/venue selector, assignment, routes, tasks and settings. Program #9 has no target districts/schools and an unlinked school-program candidate #7. Verify date/city/venue and approved target districts before repair; no mapping guessed or written.
- **Region/population:** live warehouse/population views and abilities work; no Region_Service class/load-order error observed.
- **Operations:** existing plan, resources, checklist and schedule reads/render succeed. ensure_plan may seed missing data in other contexts; this existing-plan sample did not require a write.
- **Kommo:** connection/pipeline diagnostics, dynamic MMC V2/snippet #110 preview and source consistency succeed. Program #9 AI refresh_needed remains; source preview does not establish downstream retrieval freshness. Tokenized URLs/raw secrets are not archived.
- **Marketing/tasks/reports:** pack/meta/task/report reads succeed. No pack creation, task update, Meta action or report email sent.
- **Dashboard:** renderer/aggregations work. Unavailable active-customer lead metric shows unavailable; synced program leads are distinct. Sales/failed/refund counters use different time/status filters; no invented zero for unknown lead metric.
- **Feature overlap:** legacy MDG/V4/V5/MDGY screens remain callable. Similar navigation is not proof they are safely removable duplicates. No module was declared DEAD or disabled.

## GET side effects

| Screen | Verified local mutation path | Result |
|---|---|---|
| mmc-kommo | ensure_profile → profile UPDATE | Blocked |
| mmc-integrity | checks → status_bridge_preview → ensure_profile → profile UPDATE | Blocked; readonly ability shares path (#115) |
| mmc-finance | sync_external_sources → refresh_closure_snapshot → snapshot UPDATE | Blocked; no financial transaction |
| mdg-kommo-active-events-source | collector → update_option | Blocked option cache update |

These four full renders are not certified under pure read-only operation. Do not allow writes merely to obtain a green audit.

## Services, endpoints and background work

The JSON inventories 20 MMC service classes, public methods and registered background hooks. Program, Venue, Event, Region/Population/MEB/School sources, Sales, Kommo, Field, Operations, Finance, Marketing, Dashboard, Health, Integrity, MDG bridge/draft sync, Snippet inventory and Report remain in their existing architecture.

Core MMC primarily uses capability/nonce-protected `admin-post.php?action=...` and WordPress Abilities API; no dedicated MMC REST controller was found. Per-owner admin-post action lists and menu↔ability names are in JSON.

- Sales: woocommerce_payment_complete (priority 20), woocommerce_order_status_changed (20), woocommerce_order_refunded (20), unchanged and not triggered by this audit.
- Kommo: init/rewrite/template_redirect source delivery, cron queue and program-log hooks. No raw source token URL recorded.
- Field/Operations/Marketing/Finance: program lifecycle hooks; Report: existing scheduled runner. No manual cron execution.
- Related legacy REST routes verified as metadata only: `/mdgy/v1/kommo/webhook` and `/madagaskar-finans/v1/record`; write routes not invoked.
- Native abilities metadata/schemas: `/wp-abilities/v1/abilities`. Execution used the supported run_ability connector, not raw /run calls.

## Ability inventory

Full input/output schemas, permissions/capability source references, callbacks and returned shape/smoke results are in JSON. All 98 execute and permission callbacks are registered/callable. Permission callbacks were source-reviewed; this authenticated administrator's read calls were authorized. Negative-role permission tests were not done. Write callbacks were not run against real records.

| Ability | Module | Read/write | Permission | Live check |
|---|---|---|---|---|
| `madagaskar/kommo-unified-source-v2-preview` | snippet-110.php.txt | READ | manage_options | CALL_OK |
| `madagaskar/system-health-checks` | system-health-integrity.php | READ | manage_options | CALL_OK |
| `madagaskar/program-integrity-checks` | system-health-integrity.php | READ | manage_options | CALL_OK; finding |
| `madagaskar/program-selected-venue-check` | system-health-integrity.php | READ | manage_options | CALL_OK |
| `madagaskar/program-repair-school-bridge` | system-health-integrity.php | WRITE | manage_options | Schema/callback checked; not executed |
| `madagaskar/mmc-sales-health` | mmc-sales-ledger.php | READ | manage_woocommerce | CALL_OK |
| `madagaskar/mmc-sales-mappings` | mmc-sales-ledger.php | READ | manage_woocommerce | CALL_OK |
| `madagaskar/mmc-sales-mapping-save` | mmc-sales-ledger.php | WRITE | manage_woocommerce | Schema/callback checked; not executed |
| `madagaskar/mmc-sales-import-legacy` | mmc-sales-ledger.php | WRITE | manage_woocommerce | Schema/callback checked; not executed |
| `madagaskar/mmc-sales-sync-order` | mmc-sales-ledger.php | WRITE | manage_woocommerce | Schema/callback checked; not executed |
| `madagaskar/mmc-sales-sync-event` | mmc-sales-ledger.php | WRITE | manage_woocommerce | Schema/callback checked; not executed |
| `madagaskar/mmc-sales-summary` | mmc-sales-ledger.php | READ | manage_woocommerce | CALL_OK; finding |
| `madagaskar/mmc-sales-recent-orders` | mmc-sales-ledger.php | READ | manage_woocommerce | CALL_OK |
| `madagaskar/mmc-sales-refresh-health` | mmc-sales-ledger.php | WRITE | manage_woocommerce | Schema/callback checked; not executed |
| `madagaskar/dashboard-overview` | mmc-dashboard.php | READ | manage_options | CALL_OK |
| `madagaskar/dashboard-programs` | mmc-dashboard.php | READ | manage_options | CALL_OK |
| `madagaskar/dashboard-critical-alerts` | mmc-dashboard.php | READ | manage_options | CALL_OK |
| `madagaskar/tasks-list` | mmc-tasks.php | READ | manage_options | CALL_OK |
| `madagaskar/task-get` | mmc-tasks.php | READ | manage_options | CALL_OK |
| `madagaskar/tasks-summary` | mmc-tasks.php | READ | manage_options | CALL_OK |
| `madagaskar/task-create` | mmc-tasks.php | WRITE | manage_options | Schema/callback checked; not executed |
| `madagaskar/task-update` | mmc-tasks.php | WRITE | manage_options | Schema/callback checked; not executed |
| `madagaskar/task-set-status` | mmc-tasks.php | WRITE | manage_options | Schema/callback checked; not executed |
| `madagaskar/refund-preflight` | v4-refund-safety.php | READ | manage_woocommerce | CALL_OK |
| `madagaskar/refund-cases-list` | v4-refund-safety.php | READ | manage_woocommerce | CALL_OK |
| `madagaskar/refund-case-get` | v4-refund-safety.php | READ | manage_woocommerce | CALL_OK |
| `madagaskar/order-refunds-history` | v4-refund-safety.php | READ | manage_woocommerce | CALL_OK |
| `madagaskar/v4-sales-product-status` | v4-operations-safety.php | READ | manage_woocommerce | CALL_OK |
| `madagaskar/v4-postponement-mappings` | v4-operations-safety.php | READ | manage_woocommerce | CALL_OK |
| `madagaskar/v4-postponement-map-get` | v4-operations-safety.php | READ | manage_woocommerce | CALL_OK |
| `madagaskar/v4-postponement-preflight` | v4-operations-safety.php | READ | manage_woocommerce | CALL_OK |
| `madagaskar/v4-transfer-scan` | v4-operations-safety.php | READ | manage_woocommerce | CALL_OK |
| `madagaskar/v4-transfer-last` | v4-operations-safety.php | READ | manage_woocommerce | CALL_OK |
| `madagaskar/customer-tickets-query` | reporting-customer.php | READ | manage_woocommerce | CALL_OK |
| `madagaskar/customer-ticket-lookups` | reporting-customer.php | READ | manage_woocommerce | CALL_OK |
| `madagaskar/sales-system-audit` | reporting-customer.php | READ | manage_woocommerce | CALL_OK |
| `madagaskar/daily-report-snapshot` | reporting-customer.php | READ | manage_woocommerce | CALL_OK |
| `madagaskar/report-runs` | reporting-customer.php | READ | manage_woocommerce | CALL_OK |
| `madagaskar/report-run-get` | reporting-customer.php | READ | manage_woocommerce | CALL_OK |
| `madagaskar/report-send-now` | reporting-customer.php | WRITE | manage_woocommerce | Schema/callback checked; not executed |
| `madagaskar/region-sources` | mmc-region-population.php | READ | manage_options | CALL_OK |
| `madagaskar/region-districts` | mmc-region-population.php | READ | manage_options | CALL_OK |
| `madagaskar/region-program-summary` | mmc-region-population.php | READ | manage_options | CALL_OK |
| `madagaskar/region-set-target-districts` | mmc-region-population.php | WRITE | manage_options | Schema/callback checked; not executed |
| `madagaskar/region-ensure-primary-district` | mmc-region-population.php | WRITE | manage_options | Schema/callback checked; not executed |
| `madagaskar/region-add-metric` | mmc-region-population.php | WRITE | manage_options | Schema/callback checked; not executed |
| `madagaskar/population-lookup` | mmc-region-population.php | READ | manage_options | CALL_OK |
| `madagaskar/population-target-summary` | mmc-region-population.php | READ | manage_options | CALL_OK |
| `madagaskar/region-recent-imports` | mmc-region-population.php | READ | manage_options | CALL_OK |
| `madagaskar/field-sync-target-schools` | mmc-field.php | WRITE | manage_options | Schema/callback checked; not executed |
| `madagaskar/field-targets` | mmc-field.php | READ | manage_options | CALL_OK |
| `madagaskar/field-summary` | mmc-field.php | READ | manage_options | CALL_OK |
| `madagaskar/field-assign-targets` | mmc-field.php | WRITE | manage_options | Schema/callback checked; not executed |
| `madagaskar/field-set-scope` | mmc-field.php | WRITE | manage_options | Schema/callback checked; not executed |
| `madagaskar/field-routes` | mmc-field.php | READ | manage_options | CALL_OK |
| `madagaskar/field-route-add` | mmc-field.php | WRITE | manage_options | Schema/callback checked; not executed |
| `madagaskar/field-recent-visits` | mmc-field.php | READ | manage_options | CALL_OK |
| `madagaskar/field-share-links` | mmc-field.php | READ | manage_options | CALL_OK |
| `madagaskar/field-share-link-create` | mmc-field.php | WRITE | manage_options | Schema/callback checked; not executed |
| `madagaskar/field-share-link-revoke` | mmc-field.php | WRITE | manage_options | Schema/callback checked; not executed |
| `madagaskar/operations-plan-get` | mmc-operations.php | READ | mmc_manage_operations | CALL_OK |
| `madagaskar/operations-plan-ensure` | mmc-operations.php | WRITE | mmc_manage_operations | Schema/callback checked; not executed |
| `madagaskar/operations-plan-update` | mmc-operations.php | WRITE | mmc_manage_operations | Schema/callback checked; not executed |
| `madagaskar/operations-summary` | mmc-operations.php | READ | mmc_manage_operations | CALL_OK |
| `madagaskar/operations-resources-list` | mmc-operations.php | READ | mmc_manage_operations | CALL_OK |
| `madagaskar/operations-resource-add` | mmc-operations.php | WRITE | mmc_manage_operations | Schema/callback checked; not executed |
| `madagaskar/operations-program-resources` | mmc-operations.php | READ | mmc_manage_operations | CALL_OK |
| `madagaskar/operations-resource-assign` | mmc-operations.php | WRITE | mmc_manage_operations | Schema/callback checked; not executed |
| `madagaskar/operations-assignment-update` | mmc-operations.php | WRITE | mmc_manage_operations | Schema/callback checked; not executed |
| `madagaskar/operations-checklist-list` | mmc-operations.php | READ | mmc_manage_operations | CALL_OK |
| `madagaskar/operations-checklist-update` | mmc-operations.php | WRITE | mmc_manage_operations | Schema/callback checked; not executed |
| `madagaskar/operations-schedule-list` | mmc-operations.php | READ | mmc_manage_operations | CALL_OK |
| `madagaskar/operations-schedule-sync` | mmc-operations.php | WRITE | mmc_manage_operations | Schema/callback checked; not executed |
| `madagaskar/operations-schedule-add` | mmc-operations.php | WRITE | mmc_manage_operations | Schema/callback checked; not executed |
| `madagaskar/operations-schedule-status` | mmc-operations.php | WRITE | mmc_manage_operations | Schema/callback checked; not executed |
| `madagaskar/marketing-pack-get` | mmc-marketing.php | READ | manage_woocommerce | CALL_OK |
| `madagaskar/marketing-pack-ensure` | mmc-marketing.php | WRITE | manage_woocommerce | Schema/callback checked; not executed |
| `madagaskar/marketing-item-get` | mmc-marketing.php | READ | manage_woocommerce | CALL_OK |
| `madagaskar/marketing-item-update` | mmc-marketing.php | WRITE | manage_woocommerce | Schema/callback checked; not executed |
| `madagaskar/meta-plan-get` | mmc-marketing.php | READ | manage_woocommerce | CALL_OK |
| `madagaskar/meta-plan-update` | mmc-marketing.php | WRITE | manage_woocommerce | Schema/callback checked; not executed |
| `madagaskar/mdg-bridge-status` | mmc-mdg-bridge.php | READ | manage_woocommerce | CALL_OK |
| `madagaskar/mdg-bridge-candidates` | mmc-mdg-bridge.php | READ | manage_woocommerce | CALL_OK |
| `madagaskar/mdg-publish-preview` | mmc-mdg-bridge.php | READ | manage_woocommerce | CALL_OK |
| `madagaskar/mdg-bridge-auto-link` | mmc-mdg-bridge.php | WRITE | manage_woocommerce | Schema/callback checked; not executed |
| `madagaskar/mdg-bridge-manual-link` | mmc-mdg-bridge.php | WRITE | manage_woocommerce | Schema/callback checked; not executed |
| `madagaskar/mdg-create-draft-from-program` | mmc-mdg-bridge.php | WRITE | manage_woocommerce | Schema/callback checked; not executed |
| `madagaskar/kommo-configuration` | mmc-kommo.php | READ | manage_options | CALL_OK |
| `madagaskar/kommo-connection-diagnostics` | mmc-kommo.php | READ | manage_options | CALL_OK |
| `madagaskar/kommo-pipeline-diagnostics` | mmc-kommo.php | READ | manage_options | CALL_OK |
| `madagaskar/kommo-program-status` | mmc-kommo.php | READ | manage_options | CALL_OK |
| `madagaskar/kommo-source-consistency-check` | mmc-kommo.php | READ | manage_options | CALL_OK |
| `madagaskar/kommo-stage-preview` | mmc-kommo.php | WRITE | manage_options | Schema/callback checked; not executed |
| `madagaskar/kommo-sync-program-lead` | mmc-kommo.php | WRITE | manage_options | Schema/callback checked; not executed |
| `madagaskar/kommo-create-text-source` | mmc-kommo.php | WRITE | manage_options | Schema/callback checked; not executed |
| `madagaskar/kommo-sync-ai-source` | mmc-kommo.php | WRITE | manage_options | Schema/callback checked; not executed |
| `madagaskar/kommo-migrate-token-constant` | mmc-kommo.php | WRITE | manage_options | Schema/callback checked; not executed |
| `madagaskar/legacy-mdg-mmc-migration-preview` | legacy-mdg-mmc-preview.php | READ | manage_woocommerce | CALL_OK |

Enabled migration modules: system-health-integrity, mmc-sales-ledger, mmc-dashboard, mmc-tasks, v4-refund-safety, v4-operations-safety, reporting-customer, mmc-region-population, mmc-field, mmc-operations, mmc-marketing, mmc-mdg-bridge, mmc-kommo, legacy-mdg-mmc-preview.

Supported but disabled: invoice-tracking, school-promotion, v5-finance, program-venue-event-sales. Thus absent school-bridge-status is explained by the school module gate, not a missing PHP class. No automatic activation/migration or 0.7.0 deployment.

## Priorities

| Priority | Verified finding | Next action |
|---|---|---|
| P0 | None found in this scoped audit | No new payment/fatal blocker asserted |
| P1 | [#114](https://github.com/milanosirki-code/madagaskarsirki-wordpress/issues/114): failed nominal amounts in collected gross summary | Separate reporting patch; paid/failed/refund regression; no ledger rewrite |
| P2 | Program #9 school/field setup/bridge absent | Verify intended targets and school/date/venue mapping before any write |
| P3 | [#115](https://github.com/milanosirki-code/madagaskarsirki-wordpress/issues/115): readonly integrity has local-write path | Separate read-only service-path patch/tests |
| P3 | #82: source refresh_needed / downstream retrieval not proven | Read-only consistency/retrieval check; later approved sync |
| P4 | Four GET local-write paths, partial AI coverage and missing authenticated visual test | Owner-level review; no broad redesign |

Open issues: #115, #114, #112 (deferred/nonblocking), #105, #87, #82, #57. Highest next work is **#114 sales gross semantics**. Then verified school/field setup and #115 read-only purity. Do not start all modules/features at once.

## Tests, deployment and rollback

PR #113 CI succeeded: Plugins 37177289680, Production Source Archive 37177289672, Changed PHP Syntax 37177289673. Review covered 66 SHA-256 archive files / 58 PHP source files/snippets. Final diagnostic PHP Syntax run 37180627884 succeeded before its last live edit. Local JSON completeness/count/callback/secret-pattern validation covers this report. Automatic historical Tickera contract workflow was inherited from PR #111; no new PayTR sandbox/full checkout/staging process started.

Family `family_2_2` = 2 adults + 2 children, capacity_units=4 is preserved by unchanged production source/definitions. No paid-order/capacity mutation regression is claimed.

Reverting main merge 8a6659d reverses repository reconciliation only; no automatic live deploy happened. #119 is already original/passive and #117 passive. Audit code stays under docs/diagnostics, not production source. Production payment/sales behavior patches need separate PRs. Family 1.1.3 and AI 0.7.0 remain separate future deployment items.

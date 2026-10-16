# Milano Google integrations — 16 October 2026

## Completed on the live site
Existing GA4 measurement ID G-DD2ZXC383D retained. Jetpack's duplicate/unconditional integration remains is_active=false. Native Complianz Wizard configured compile_statistics=google-analytics, configuration_by_complianz=yes, ua_code=G-DD2ZXC383D. No Google Ads/GTM container added. No new GA property created.
Native CMP UI initially failed after policy change: TypeError reading querySelector, cmplz_check_cookie_policy_id, old7.5.5 script. Existing official plugin updated via WordPress update7.5.5→7.5.6, active; WordPress object cache flushed. No direct plugin source patch or SQL used.
Browser retest: before consent and after Reject, both GA loader/config blocks type=text/plain category=statistics, no active src. Accept changes to one external loader https://www.googletagmanager.com/gtag/js?id=G-DD2ZXC383D plus one inline config. Revoke via Manage consent→Reject reloads and restores blocked text/plain blocks. Old console error belongs to pre-update timestamp; panel works after update. This verifies DOM tag gating, NOT full network/cookie inventory, GA4 Realtime ingestion, or legal compliance.
Cookie Policy page35 updated with two scoped replacements reflecting consent-aware activation instead of temporarily-off wording; raw readback verified. Existing business/contact/policy rights preserved.
PageSpeed Insights mobile via WPVibe: Performance78, SEO100, LCP5.3s, CLS0, TBT0ms, FCP1.1s. No CrUX field data; total payload4902KiB. No performance score guarantee.
Robots200, Googlebot permitted; Google-Extended block applies to AI crawling and was preserved. sitemap.xml200 references sitemap-1.xml and image-sitemap-1.xml. Current sitemap-1.xml has15URLs and lacks new blog posts. Jetpack documented12-hour regeneration, native scheduled jp_sitemap_cron_hook next2026-10-16 15:31:48UTC.

## Account access / pending actions
Google Search Console opened through the Cloud Browser. User selected Milano Sirki milanosirki@gmail.com and submitted password through browserAuth secure flow; credential values were never read. Google requires device approval on Redmi Note12Pro / GalaxyA73. Phone approval still pending. No ownership/property/account verification claimed. Prior log records SC ownership and SC–GA4 association, but must be rechecked after login.
Native immediate sitemap regeneration requested through WPVibe cron event run jp_sitemap_cron_hook. Operation op_2787c200f47b4404 is paused pending explicit WPVibe approval. No cron callback executed by this request. User-facing approval link supplied.
Google Rich Results Test opened for Karamürsel public URL. Google returned “Bir sorun oluştu / Giriş yapıp tekrar deneyin”; no successful rich-result validation claimed.
After Google approval: verify existing Search Console property, sitemaps/indexing/URL inspection for four event URLs and new articles; verify GA4 property/stream URL/timezone/currency, existing SC association, Realtime/DebugView; evaluate Business Profile existing verified office/service business rather than creating temporary venue locations. GTM is optional and should not duplicate the existing single tag. Do not create Google Ads campaigns or billing as part of this setup.
Madagaskar tracking, payments, orders, products and capacities were not changed. Cross-domain attribution/purchase events remain unconfigured and unverified; preserve brand separation and avoid passing attendee PII into analytics.

## Sources
https://complianz.io/help/wordpress/configuration/statistics-implementation/
https://support.google.com/analytics/answer/10737381
https://support.google.com/analytics/answer/10071811
https://support.google.com/tagmanager/answer/7582054
https://jetpack.com/support/sitemaps/
https://developers.google.com/search/docs/appearance/structured-data/event
https://support.google.com/business/answer/13763036

Backups: settings-before.json, cookie-page-before.json. Final native settings and page output in settings-after.json/cookie-page-after.json. Final tests in consent-tests.json. No credentials or authentication URLs recorded.

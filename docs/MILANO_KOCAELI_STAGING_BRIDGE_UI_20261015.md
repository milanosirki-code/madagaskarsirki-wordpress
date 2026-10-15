# Milano Kocaeli — staging UI continuation, 15 October 2026

## Result
PARTIAL_STAGING_BRIDGE_CONFIGURED — PUBLIC SALES NOT OPENED.
Only the named staging site was changed. No production records, Yalova records, payment signatures/callbacks, stock quantities, or existing orders were intentionally changed.

## Sources inspected
- Draft PR #235, head feature/milano-exclusive-brand-routing-20261012.
- Draft PR #265, head fix/milano-ability-brand-input-20261015; mergeable=false, not deployed.
- integrations/milano/plans/kocaeli-20261021-24-preparation.json at PR235 branch (not main).
- integrations/milano/tests/kocaeli-staging-gate-contract.php and .github/workflows/milano-kocaeli-staging-gates.yml.
- Google Drive master work log.
The manifest contract checks static plan contents only, not live checkout/QR.

## Actual staging changes through signed-in Cloud Browser
Site: staging-f321-milanosirki-lgajf.wpcomstaging.com.

Existing Tickera drafts edited through native vendor editor, saved as drafts, reloaded:
| Tickera | Event | Start local | End local |
| --- | --- | --- | --- |
| 6102 | Karamürsel | 2026-10-21 17:30 | 2026-10-21 20:30 |
| 6104 | Darıca | 2026-10-23 17:30 | 2026-10-23 20:30 |
| 6106 | Derince | 2026-10-24 12:00 | 2026-10-24 17:00 |

Correct user-approved full venue addresses and event rules (session-specific validity, 500 per session, 245/490/1100, 2+2 four admissions, staging closed) were saved. Tickera event time range spans all daily sessions; individual ticket session identity remains to be wired and tested.

Existing WooCommerce variable parent products were saved using native Bridge controls (Product is a Ticket checked, correct Event selected):
| Parent | Tickera | Session |
| --- | --- | --- |
|6081|6102|Karamürsel 17:30|
|6084|6102|Karamürsel 19:30|
|6087|6104|Darıca 17:30|
|6090|6104|Darıca 19:30|
|6093|6106|Derince 12:00|
|6096|6106|Derince 14:00|
|6099|6106|Derince 16:00|

All seven were revisited individually: title, Event value, draft status and hidden catalog visibility confirmed 7/7. No new product/variation/event created. Vendor UI writes are not proof of complete MDG mapping or variation ticket metadata.
Product 6099 ticket-template selection remains None; no unverified Yalova venue template was reused. Four-QR generation is NOT passed.

## Observed blockers
1. WPVibe account rolling limit 556/500, zero calls remaining; no reset spent and no bypass attempted. Cloud Browser was used as user requested.
2. Milano Bilet Hazırlığı UI confirms MDG events 25/26/27 remain draft, seven sessions 500 each, held=0/sold=0 and MDG Woo/Tickera fields display Taslak yok. Direct MDG service mapping has not been completed.
3. MDG Karamürsel production dry-run blocks generation for missing hero, venue Maps URL and generated venue QR. It offers generation, not adoption of these manually prepared existing skeletons. Creation was not invoked to avoid duplicates.
4. Staging Woo payments UI shows provider installation/offline payment options, no configured PayTR provider. Earlier health log also reported PayTR detected=false/enabled=false. No authorized PayTR test flow was available; no bank-card transaction attempted.
5. PDF template, MDG production key/event/session/type mapping, family checkout producing four distinct valid QR, ledger idempotency/concurrency/refund and brand routing tests remain unexecuted.

## Launch gate
No new live Kocaeli IDs, no production deployment, no public-sale enable action. All payment/QR/brand launch gates remain false. Do not mark sales completed based on parent Bridge configuration.
Next: authenticated plugin service/ability adoption of EXISTING IDs (not recreation), per-event Milano ticket template/venue assets, configured authorized PayTR sandbox, full functional tests, then live duplicate checks and controlled rollout.

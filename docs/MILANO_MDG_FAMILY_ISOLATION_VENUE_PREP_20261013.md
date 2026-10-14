# Milano MDG family isolation and Yalova venue preparation — 2026-10-13

Staging only; production unchanged.

- Source review of PR239 found a necessary companion protection: new Milano MDG drafts must not enter Madagaskar global mdg_family_package_22_v1 settings. Ported the reviewed two-service guards into PR240 without wholesale merging integration branches.
- MDG sync now rejects missing/unknown explicit program brand before MDG writes. Only verified Madagaskar drafts synchronize the existing family option. Milano and ownerless programs cannot fall through to the old compatibility draft path.
- New regression executes the actual sync method with isolated WordPress/MDG dependencies: checks canonical titles, two real adult/child ticket types, no four-person third ticket type, 18:00/19:30 Istanbul->15:00/16:30 UTC conversion, 500 capacity each, no Milano family-option mutation, existing Madagaskar settings retained. Five fallback negative cases also pass. This is a synthetic dependency test, not real payment/QR E2E.
- Commit fd3c58fe54ead5f62e1e3e7b2cca332dc7cd2807: 20/20 GitHub workflows success. Artifact11838568084; both package SHA256 checks pass. MMC1.3.50 updated on staging; AI package unchanged from prior installed brand-copy revision. Browser confirms update complete and Yalova sessions/prices intact.
- Staging program14: venue181 (90. Yıl Spor Salonu) added as program venue14, allocation draft / alternative, requested date22.10.2026 and sessions18:00,19:30. User map stored in notes. No rent/deposit amount was supplied; UI defaults0 are not evidence of payment or free rent. No petition generated/sent.
- The selected venue is prepared in the confirmation form, but the phone/direct confirmation checkbox remains unchecked. Must obtain the factual salon/date confirmation before marking allocation confirmed.
- Source audit of PR235 guard drafts: classic cart, Store API cart/checkout, processed-order and MDG direct URL hooks exist behind disabled server constant. They were not installed/activated here. Dedicated order-pay and campaign/legacy routes still need end-to-end validation; source review alone does not prove complete brand exclusivity.
- Sale remains closed. Venue attestation, live source comparison/backup, brand mapping+route guards, PayTR second domain, actual payment/capacity/four-QR validation and controlled deployment remain.
- Rollback for this staging code step: prior artifact11838296785. Venue candidate is a preparation record and should not be mistaken for approved allocation. PR240 remains draft.

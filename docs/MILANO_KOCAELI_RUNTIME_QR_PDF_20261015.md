# Milano Kocaeli runtime PDF and check-in test — 15 October 2026

Public Kocaeli sales remain CLOSED. Existing central Madagaskar checkout is the production architecture, as with Yalova. This test did not collect payment.

## Actual staging fixture
WooCommerce order6116, administrative REST creation, total1100 TRY: product6081 / child6082 quantity2 total490, adult6083 quantity2 total980, explicitly labeled fixture fee−370. Customer email/phone empty. Status changed administratively pending→completed. This is NOT genuine checkout, PayTR payment, automatic family discount acceptance, or capacity-reservation proof. An automatic new-order admin notification was observed; no customer email was supplied.

Tickera issued four distinct instances6117–6120 for event6102, correct session Karamürsel21October17:30–18:30 Europe/Istanbul, two child and two adult. Four actual vendor PDFs downloaded and decoded offline with zxing-cpp. All four admission QR values are distinct and match their stored ticket codes. A missing download was retried successfully. These are real runtime-generated PDFs, not rendered mockups.

## Discovered and corrected defect
Initial repeated scan of instance6117 was accepted twice. Its tc_checkins metadata contained two Pass records. Root cause: staging parent6081 _available_checkins_per_ticket was empty (unlimited). Native Woo product editor saved1. All seven staging parents6081/6084/6087/6090/6093/6096/6099 now verified1. The six other values were saved through native Woo REST batch update.
After correction, the already-used first QR was rejected. The other three QR codes were each accepted on first scan and rejected on repeat through the real Tickera Barcode Reader. Its selected native API key was the Karamürsel event key6103; Select2's visible label remained stale, while underlying native select and stored check-in API key confirmed correct scope.
Live seven Kocaeli parents6531/6534/6539/6542/6547/6550/6553 already had1, verified read-only; no live check-in configuration was altered. No Yalova product was changed.

## PDF layout
Actual first PDF showed multiline headline overlap and salon/terms collision. Native Ticket Designer corrected only Kocaeli clones31/32/33, both environments: headline16pt, venue12pt, terms9pt. Staging terms y232; corrected Karamürsel actual PDF visually inspected with no overlapping heading/venue/terms. Live clones have additional purchase date: terms y225, purchase date y352/font9 to separate that field. Live before-state backup committed before changing clones. No base template, Yalova29, or Madagaskar template was edited. Live real-order PDFs and other six session PDFs remain untested.

## Idempotency and cleanup
Repeating administrative completed status left exactly four ticket instances; this is only status replay, NOT duplicate signed PayTR callback proof.
Order6116 then cancelled through native Woo REST. Four instances6117–6120 verified trash; native cancellation had already trashed them before an attempted recoverable cleanup reported zero additional posts trashed. No permanent deletion. Stage session152 remains capacity500, held0, sold0: expected for REST fixture bypassing normal checkout reserve hook, NOT successful four-unit ledger consumption. Test order retained cancelled for audit.

## Remaining critical launch gates
Authorized isolated PayTR staging callback route remains unverified. Gateway stays disabled, test mode true; no bank-card charge and no production merchant callback/signature change. Local test checkbox cannot establish callback isolation. See MILANO_KOCAELI_PAYTR_CALLBACK_ISOLATION_20261015.md.
Actual adult/child/family checkout payment, four-unit commit exactly once, signed duplicate callback, failed payment, refund, concurrent capacity, all-seven-session QR attribution, mixed-session rejection, and complete brand/mobile checkout return remain unverified.
Live seven sessions176–182 are draft, each capacity500, held0,sold0. Live public handoff catalog still contains only Yalova event24 sessions154/155; Kocaeli is absent. No publish or sale gate was bypassed.

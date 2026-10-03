# Kırıkkale cancellation and refund operation — 2026-10-02

> **Final status:** The later verification in this report supersedes the initial pending snapshot below: 11/11 WooCommerce refunds completed for **TRY 13,750**, remaining refundable amount is **0**, refund IDs are **3958–3968**, and **46/46 tickets were invalidated**. Card/bank posting was not independently verified. The public cancellation notice was subsequently updated with the bank-posting wording plus Home and WhatsApp recovery links.

## Live result

- Scope: MMC Program **2**, `PRG-2026-KIR-MERKEZ-001`; MMC Event **2** bridged to MDG Event **15**; Kırıkkale / Merkez; **2026-10-02**, **17 Ağustos Spor Salonu**, sessions **17:30 / 19:00**.
- Identity and bridge verified in live integrity, sales and V4 screens before writes.
- MMC Program 2 is now **cancelled**, read back after calling the existing `MMC_Program_Service::set_status` service through a nonce-protected, identity-guarded operational action.
- Existing **V4 sales-close** controls applied to parents **2848 / 2852**. Post-operation WooCommerce REST readback: both parents and all variations **2849, 2850, 2851, 2853, 2854, 2855** have **purchasable=false**.
- Exact public event URL displays the cancellation notice and no purchase form: https://madagaskarsirki.com/etkinlik/madagaskar-sirki-kirikkale-02-ekim-2026/
- No test order/payment was created; checkout was not submitted. Verification used live purchasability flags, V4 locks and rendered event page.
- Legacy MDG event management label was still “Satışta” before the final MMC cancellation; that separate legacy label was not directly rewritten. Sales closure is enforced by V4, MMC cancellation and the exact-URL notice.

## Counts and amounts

| Metric | Result |
|---|---:|
| Distinct Kırıkkale orders | 21 |
| Paid orders | 11 |
| Included in refund requests | 11 |
| Excluded unpaid orders | 10 |
| V4 preflight passed before case creation | 11 |
| Created refund cases | 11 |
| Cases awaiting distinct second approver | 11 |
| Gateway refunds completed | 0 |
| Refundable actual paid amount | TRY 13,750.00 |
| Refund request amount initiated | TRY 13,750.00 |
| Gateway refund amount initiated | TRY 0.00 |
| Completed refund amount | TRY 0.00 |
| Tickets covered by pending cases | 46 |
| Prior refunds in these 21 orders | 0 |

MMC sales ledger showed TRY 17,750 from line-level amounts before family-package discounts. Refund requests use actual WooCommerce order totals, **TRY 13,750**, not that ledger amount.

## Paid orders and V4 cases

All rows: WooCommerce status **processing**, paid date present, gateway **paytr_payment_gateway** (`Paytr_Payment_Gateway`), prior refund **0**, remaining refundable amount equal to order total. Final readback still shows no WooCommerce refunds.

| Order | Order total / refundable TRY | Tickets | V4 case | State |
|---|---:|---:|---|---|
| 3915 | 1100.00 | 4 | 3947 | pending_approval |
| 3888 | 1100.00 | 4 | 3948 | pending_approval |
| 3879 | 1100.00 | 4 | 3949 | pending_approval |
| 3735 | 2200.00 | 8 | 3950 | pending_approval |
| 3696 | 1100.00 | 4 | 3951 | pending_approval |
| 3650 | 1100.00 | 4 | 3952 | pending_approval |
| 3647 | 500.00 | 1 | 3953 | pending_approval |
| 3585 | 1100.00 | 4 | 3954 | pending_approval |
| 3527 | 500.00 | 1 | 3955 | pending_approval |
| 3489 | 1000.00 | 2 | 3956 | pending_approval |
| 3180 | 2950.00 | 10 | 3957 | pending_approval |

Each V4 dry-run passed: status_ok, paid, no_prior_refund, amount_positive, full_amount_matches, gateway_found, gateway_supports_refunds, tickets_exist, tickets_all_active, line_items_exist, no_live_case; ready=true. Requests were created through the existing V4 UI and read back individually. The readonly `madagaskar/refund-cases-list` ability independently returned all 11 cases as pending_approval, approver unset, wc_refund_id=0.

Exact reason:
> Kırıkkale 02.10.2026 programı iptal edildi. Kullanıcı talimatıyla tam bilet bedeli iadesi.

The requesting administrator cannot approve their own cases. A **different authorized administrator** must use V4’s existing approval flow. Do not create replacement cases, invoke raw wc_create_refund, call the gateway directly, impersonate an approver or bypass snapshot/preflight safeguards.

## Exclusions

- **Failed, no paid date:** 3934, 3924, 3907, 3836, 3820, 3467.
- **Cancelled, no paid date:** 3313, 3304, 3234, 3225.
- All ten checked live in WooCommerce; refunds empty. No refund request created for them.
- 3467: known incomplete PayTR 3D flow; 3304: known unpaid timeout/cancellation.
- No ambiguous paid order or failed eligible preflight was found.

## Ticket/refund consistency

No gateway refund has been executed. Consequently all 11 orders remain processing, WooCommerce refunds are empty, and the 46 tickets remain in their existing active/publish state pending V4 approval. No independent invalidation or ticket deletion was performed. After a distinct approver completes V4 execution, verify gateway outcome, WooCommerce refund ID/order status and V4 ticket invalidation together. A pending case is not a completed refund.

## Operational snippet and validation

- Live Code Snippets **104**, source: `docs/code-snippets/kirikkale-cancellation-2026-10-02.php`.
- Purpose: missing MMC cancellation UI bridge plus cancellation notice for the one exact Kırıkkale URL.
- Hooks: `admin_menu`, `admin_post_mdg_kirikkale_cancel_20261002`, `template_redirect` at -100.
- Dependencies: MMC_Program_Service, WooCommerce product reads, WordPress manage_options capability, nonce verification. Program code/city/district/date and exact parent/variation membership and existing purchasability locks must pass before the status service call.
- No direct SQL, refunds, order writes, ticket writes, product writes, generalized execution endpoint or changes to other programs.
- Activation endpoint returned HTTP 500, but the write had committed. It was **not retried**. Subsequent readback: active=true, code_error=null, code_error_trace=null; admin operation and public notice loaded successfully. Source matched the repository.
- Local PHP binary was unavailable. Source was statically reviewed; plugin reported no code error; runtime smoke succeeded. Parent/variation REST flags were rechecked after cancellation.
- Rollback: deactivate snippet 104 to remove its admin action and public notice. This does **not** reopen sales, revert the program status or affect pending V4 cases. Reopening requires a new explicit organizer decision.
- No changes to Pursaklar, Sincan, Yenimahalle, Denizli, Mamak, Eskişehir, İzmir or any other program; no products/orders/tickets deleted.
- No customer names, phone numbers, emails, addresses or other customer PII recorded.

## Source control

Operational source and report are recorded on `ops/kirikkale-cancellation-2026-10-02`, draft PR **#92**. This is a production operation record; the draft PR is not claimed as merged.

## Follow-up: refunds completed and cancellation status regression fixed

The user completed the second-admin V4 flow. Live readonly checks around 14:08–14:10 TSİ verified **11/11 success**, **11/11 WooCommerce refunded**, full refunds totaling **TRY 13,750**, remaining refund amount **0**, one refund per order, and **46/46 tickets invalidated** with the respective V4 case reason. WooCommerce refund IDs: **3958–3968**. These results supersede the earlier pending-approval snapshot above; card-bank posting was not independently verified.

At the organizer's 15:11 TSİ follow-up, parents 2848/2852 remained purchasable=false, but MMC Program 2 had reverted to sales_open. MMC log **424**, timestamp **13:53:39**, records cancelled → sales_open with note “WooCommerce, Tickera ve PayTR gerçek satış entegrasyonu doğrulandı.” This made snippet 104's formerly status-dependent cancellation notice disappear.

Correction:
- Updated snippet 104 and its repository source: exact-URL cancellation notice no longer depends on the mutable MMC integration status or frontend MMC class availability.
- Added a tightly scoped mmc_program_logged guard for Program 2 / exact code / Kırıkkale / Merkez / date. A sales_open transition is returned to cancelled using MMC_Program_Service::set_status. Other programs/transitions are untouched. The guard's own cancelled transition exits immediately, avoiding recursion.
- Used the existing nonce-protected operation to restore Program 2 to cancelled; admin readback verified.
- Public event page verified with cancellation heading and no purchase form. All six variations rechecked purchasable=false; parents were already false.
- Live snippet update returned active=true and code_error=null. No live reopening was induced for testing. No order/refund/ticket writes were made in this correction.
- This guard enforces the current explicit cancellation decision. Any future authorized reopening must also revise/remove snippet 104's cancellation guard and notice; deactivating it alone does not reopen V4 product locks.

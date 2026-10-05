# Issue 116 — session-start sales cutoff

Rebuilt on main `51f899faebe9ae92778770a659a34c1f43b14043`, after PR130 merged and Issue129 completed. Live raffle snippet106 matches main; no real replacement POST was run because no disqualified slot existed.

The five live ticket-plugin files exactly matched this main before changes. Reviewed PR117 changes are transferred rather than merging that old branch. Previously event selectors, custom cart and reservation accepted an onsale session irrespective of its start; public lists and active snippet30 used end time.

`MDG_Sessions::sales_closed_by_time()` closes a valid UTC start at `start_at <= now`. Invalid start falls back to a readable past end; completely unreadable dates remain open. Event rendering, custom AJAX cart and pre-payment reservation share this decision. Native WooCommerce add-to-cart, classic cart validation and Store API validation now resolve the canonical product/session mapping and apply the same rule; client session metadata cannot bypass it. Unmapped products are unaffected. Existing order hold/payment completion behavior is preserved.

The plugin city/ticket queries and snippet30's two public-list queries use `start_at > now`. The current snippet30 candidate is `docs/code-snippets/ms-session-start-list-cutoff.php`; the historical production archive is preserved.

Validation: 83 isolated cutoff assertions (fixed clock boundary, ended/running/future/malformed, mixed and ended event render, draft preview, native product URLs, forged metadata, classic/Store API cart and pre-payment), existing ticket datetime regressions and plugin/snippet PHP syntax passed. All six CI workflows passed for production commit `75cbc888a1cd0e01a7f7c96a9a8ef11ff8b29725` in PR131.

Controlled deployment completed for the five plugin files and active snippet30, with exact candidate read-back. All seven captured MDG/ledger/Kommo table hashes stayed unchanged. Guarded installed-code smoke rejected expired Yenimahalle session60 via variation, direct parent product, classic cart, Store API and pre-payment reservation. The reservation used an unsaved WC_Order (ID0); no real order/payment was created and SQL-write attempts were zero. Expired page has no session picker/cart and shows sales ended; future Mamak sessions75–78 remain enabled and listed. The start-time boundary closes exactly now. This is a guarded server-render test, not a browser session.

Gross42,000 TRY / nominal47,000 / net42,000 / capacity107 are unchanged. Live canonical MDG family code `AILE_2_2` maps to MMC `family_2_2` and retains capacity4. Global snippets:125 total/45 active/code_error0. Temporary117 original code restored/passive and independently verified. Targeted smoke PHP warnings/fatals absent; global host logs were not audited.

Health is15 OK/1 warning, critical0: Code Snippets review candidates125/124/61, outside this change. The health inventory reports cached47 active while the final snippet API reports45; no unrelated snippet was disabled or health warning suppressed. New main advanced independently to `51126fdae33fde209542960b67c33c9b5d93a526`; PR131 is the dedicated production source branch. Full evidence: `docs/ISSUE_116_DEPLOYMENT_EVIDENCE.json`.

Rollback: restore the exact five pre-deploy plugin files, invalidate their opcache, restore snippet30's original body and independently reactivate/read back. Temporary diagnostic snippet117 must retain its original code and passive state after deployment/testing. This patch does not modify sales ledger, family packages, Kommo or field/program data.

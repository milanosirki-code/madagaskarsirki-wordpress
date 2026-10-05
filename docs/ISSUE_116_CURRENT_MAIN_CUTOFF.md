# Issue 116 — session-start sales cutoff

Rebuilt on main `51f899faebe9ae92778770a659a34c1f43b14043`, after PR130 merged and Issue129 completed. Live raffle snippet106 matches main; no real replacement POST was run because no disqualified slot existed.

The five live ticket-plugin files exactly matched this main before changes. Reviewed PR117 changes are transferred rather than merging that old branch. Previously event selectors, custom cart and reservation accepted an onsale session irrespective of its start; public lists and active snippet30 used end time.

`MDG_Sessions::sales_closed_by_time()` closes a valid UTC start at `start_at <= now`. Invalid start falls back to a readable past end; completely unreadable dates remain open. Event rendering, custom AJAX cart and pre-payment reservation share this decision. Native WooCommerce add-to-cart, classic cart validation and Store API validation now resolve the canonical product/session mapping and apply the same rule; client session metadata cannot bypass it. Unmapped products are unaffected. Existing order hold/payment completion behavior is preserved.

The plugin city/ticket queries and snippet30's two public-list queries use `start_at > now`. The current snippet30 candidate is `docs/code-snippets/ms-session-start-list-cutoff.php`; the historical production archive is preserved.

Validation: 83 isolated cutoff assertions (fixed clock boundary, ended/running/future/malformed, mixed and ended event render, draft preview, native product URLs, forged metadata, classic/Store API cart and pre-payment), existing ticket datetime regressions and plugin/snippet PHP syntax passed. No real WooCommerce order or payment was created. Production deployment and live evidence are pending CI.

Rollback: restore the exact five pre-deploy plugin files, invalidate their opcache, restore snippet30's original body and independently reactivate/read back. Temporary diagnostic snippet117 must retain its original code and passive state after deployment/testing. This patch does not modify sales ledger, family packages, Kommo or field/program data.

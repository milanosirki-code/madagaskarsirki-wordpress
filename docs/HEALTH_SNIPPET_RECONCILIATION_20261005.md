# PR131 closure and Code Snippets health reconciliation

PR131 merged to main `7c95b0cab1fa8b682aa1d8eb0c7a5f288e3c3a43`. The five deployed plugin hashes match the merged sources. Snippet30 matches the canonical source body after stripping the PHP opening tag and trailing line endings (one source-file terminal newline is omitted by its stored snippet body). No purchase/order/payment test was run; earlier guarded fixture evidence and gross42,000/nominal47,000/net42,000/capacity107/family4 were reread unchanged.

The health identifiers125/124/61 are Code Snippets IDs, not GitHub references. All three are active, code_error null and legitimate production code; classification KEEP_ACTIVE:

-125: MDG Kampanya Satışları — Salt Okunur Rapor; admin report/GET endpoint using Woo order CRUD. Matches `docs/code-snippets/mdg-campaign-sales-report.php` on branch `codex/campaign-sales-program-navigation` after standard PHP-tag/line-ending normalization. SHA256 `42dd53b5cfd5dfc25213b90a6e931849f92ab4331f4d63fb8f40dd98c023c129`.
-124: MDG Ortak Kurumsal Kampanya Dinamik İl Kataloğu 20261005; public corporate campaign catalogue and signed checkout/cart pricing. Matches `docs/code-snippets/mdg-corporate-campaigns.php` on branch `codex/campaign-event-poster` after normalization. SHA256 `007409446a255f21152f161367787fba5577d45888ae7b230398025684ca5a51`. Earlier navigation branch differs; no unrelated campaign branch is merged here.
-61: MS Etkinlik Tarihi Düzenleme V1; permanent capability/nonce-protected date-edit admin tool, v1.1.0. Current source differs from historical `docs/code-snippets/production/snippet-061.php.txt`; an exact current read-only capture is included in this branch. No date-edit action was executed.

Root cause1: `MMC_Snippet_Inventory_Service::analyze_row()` uses `!empty($row->active)`. Code Snippets trash uses active=-1, so trashed diagnostic records121/122 were counted as active. Live DB/API125total/45active, old inventory47active. This is not stale cache: the inventory queries the table on each call. Source fix accepts only integer1; no snippet is toggled, deleted or rewritten.

Root cause2: the global-function regex includes class methods. Separate `render()` and `plan()` methods in DateTool/Campaign/Report falsely trigger global duplicate warnings. Scope-aware PHP tokens exclude class/trait/interface/enum methods while retaining actual named global declarations, including nested globals. Actual duplicate globals and shortcode conflicts must continue warning.

Health fix/test/deployment currently in progress. Existing body/active state of temporary117 is restored after every diagnostic call. Code/identity/hash evidence is safe to publish; customer phone values and tokens are excluded. User preference is recorded in AGENTS.md: update GitHub and existing Drive archive for every task.

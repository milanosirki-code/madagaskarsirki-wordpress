# Denizli campaign 475 TL — 2026-10-05

Snippet124 updated, active=true/code_error=null. Applies to institution campaign codes only, canonical Denizli event12 / MMC program10. Adult effective rate min(current catalog price,475). Normal Woo adult variation2427 remains price/regular_price500. Child prices, free quota, TEST codes, other events/cities and existing orders unchanged.

PHP lint,87 pricing/security regressions and jsdom passed. Actual sagliksendenizli card HTML shows deleted500 +475 and data-adult-price475. Isolated bms POST quote confirmed475; admin-post cart handoff reached /odeme/ with PayTR and total_price47500. Store API persisted cart: 1 adult line47500 plus2 free child line0. No order/payment submitted. Product GET after deploy still500. Regressions also cover2 adults4 children950 and third eligible child725,13+ fare, other Denizli event/Izmir isolation and lower normal-price cap.

GitHub PR143 stacked on139. Rollback snippet124 to PR137 source. Previous public message drafts mentioned500 and should be revised to475 before reuse. No payment credentials, product prices or ticket generation hooks changed.

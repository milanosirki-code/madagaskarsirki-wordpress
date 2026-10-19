# WooCommerce WhatsApp AI coverage — 2026-10-13

Observed live evidence: order5986 lead28558402 received a delivered ticket message on12Oct19:50, then incoming refund/cancellation inquiry on13Oct06:57 with no subsequent AI response in loaded feed. Main sales greeting conversation28577362 received AI response13Oct07:36. Therefore greeting keywords were not universally rejected.

Native Woo pipeline14297695 AI activation trigger38155410 was incoming selected channels, agent350584, selected only Live Chat (Canlı sohbet/Woocommerce pipeline). WhatsApp source82576 (Madagaskar Sirki —03129113710) was not selected.

Completed: added WhatsApp source82576 to EXISTING trigger38155410, retained Woo live-chat channel. Bitti then globalKaydet; waited for saved button state. Reloaded page, reopened trigger/channel menu: WhatsApp and Woo live-chat checked. No second trigger created. No manual customer messages sent, no orders or refunds changed. Existing ticket/reminder/survey/payment/support/refund bots retained. Existing agent human-handoff rules for refund/support retained.

Important native UI detail: clicking checkbox input via check changed visual state without persisted selection in this UI. Selecting exact channel LABEL text and clicking the information paragraph outside the submenu updated apply description to selected channels. Only the final persisted reload proof counts.

Expected: NEW incoming WhatsApp messages on the trigger's existing Woo stage scope activate AI. Earlier unanswered messages were not replayed; fresh customer response/delivery and bot interaction still need live observation. The native trigger's data-status attribute was empty; do not infer all stage IDs from this. Need monitor survey answer interactions and human handoff.

Rollback: reopen38155410, remove only WhatsApp source82576, retain Woo livechat, Bitti+Kaydet. No data deletion.

Proof: kommo-woo-whatsapp-enabled-20261013.jpg

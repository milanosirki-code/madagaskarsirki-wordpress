# Kommo approval activation check — 2026-10-12

Live read-back: payment reminder V2 is approved; support-result V2 and refund-completed V2 remain pending review. Other V1 templates approved but retain unmapped placeholders.

Updated existing payment bot 118588 from template 86853 to approved payment V2 template 86871. ID obtained from the UI-generated Salesbot source after selecting madagaskar_odeme_hatirlatma_v2. Reopened saved bot and verified template_id 86871 persisted. Preserved ALLOW_SEND gate, paid/status/pipeline exclusions, order payment URL requirement and repeat-send lock. No trigger enabled and no customer message sent.

Remaining activation gate: docs/PAYMENT_REMINDER_CANARY_CONTRACT_186.md requires explicit owner approval for one selected order. Current request authorizes activation generally but identifies no test order. Need selected unpaid eligible order for controlled delivery validation before automatic rollout. Support/refund V2 cannot replace approved V1 until Meta approval.

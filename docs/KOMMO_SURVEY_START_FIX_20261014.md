# Native survey start repair — 14 October 2026

Bot118553 was blocked by {{lead.pipeline_id}}, which the earlier live no-send diagnostic showed resolving empty. The live exported source now uses the documented regex condition /^Order#[0-9]+$/ on {{lead.name}}. This is an order-name guard, not a pipeline identifier. Pipeline restriction remains in native trigger38144604: WooCommerce pipeline14297695, processing110416827 or won142,36hours after datetime field2460854. Do not launch this bot on arbitrary lead pipelines; order name alone does not establish pipeline ownership.

Template80796/source82576, paid tag, sent/answered/result locks, stage checks, error tasks and48h reply timeout are unchanged. Reopening the editor produced a semantically identical source; the only source difference is the one condition. All14 offline gate regressions passed against the read-back JSON. Offline checks do not emulate Kommo execution or WhatsApp delivery.

Legacy trigger38133528 remains retained for rollback; its existing Memnuniyet Anketi Gönderildi mi filter was narrowed from boş to boş değil and saved/read back. The bot itself rejects sent checkbox values1/true/Y, so the legacy trigger has no intended new-send path. No trigger was permanently deleted. The existing main datetime trigger was inspected and retained; Apply to all leads already in stage was unchecked. No retroactive bulk start was requested.

Deployment: native source and legacy trigger condition saved/read back in Kommo. This draft PR archives that controlled live hotfix; it is not a main merge. No customer message or survey acceptance test was sent during deployment. User payment-test locks remain preserved. Five previously active survey sessions were0 by the current list read; counts do not establish their outcomes or source version.

Rollback: restore the exact previous source condition {term1:{{lead.pipeline_id}},term2:14297695,operation:=} if reverting the source (this restores the old blocking bug). Restore legacy checkbox filter to boş only after reviewing recipients and schedule. Main trigger and customer sent/answered history must remain intact.

References: https://developers.kommo.com/docs/salesbot-dp (documented regex condition and lead-name placeholder); project audit issue242 and survey issue218. Real survey delivery and scheduled execution remain unverified.

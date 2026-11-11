# Support and refund template mappings

Created Kommo lead short-text fields:
- Destek Talep Numarası 2460838
- Destek Konusu 2460840
- Destek Sonucu 2460842
- İade Sipariş Numarası 2460844
- İade Tutarı 2460846
- İade Tamamlanma Tarihi 2460848

Saved madagaskar_destek_sonuclandi_v2 and madagaskar_iade_tamamlandi_v2 as draft Utility Turkish templates, using the field picker for all three respective placeholders and fictional sample values. Existing approved V1 templates retained.

Auto-review rejected submitting support V2 for Meta review: it requires explicit permission for the new external template submission. No workaround attempted. Refund V2 saved as draft without submitting. Both draft rows verified in template list.

Saved Madagaskar – İade Tamamlandı V1 bot using approved template 86869, source 82576, main contact only. Requires mdg_iade_tamamlandi_dogrulandi and nonempty refund number/amount/date, excludes repeat-start tag. Records channel acceptance, creates owner task on error, never auto-retries. No automatic trigger enabled because V1 placeholders are unmapped. Customer delivery not tested and no refund executed.

Next: explicit approval to submit both V2 templates; wait Meta approval, switch bots to approved mapped V2, validate data source and activate a controlled trigger. Do not infer confirmed refund from cancellation or manual completed order status.

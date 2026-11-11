# Support result notification bot

Saved Madagaskar – Destek Sonuç Bildirimi V1 in Kommo. Uses approved madagaskar_destek_sonuclandi_v1 (template 86871), WhatsApp source 82576, main contact only. No automatic trigger. Requires mdg_destek_sonuc_gonderim_onayli and excludes mdg_destek_sonuc_baslatildi. Sets one-send lock before submission, records channel acceptance separately from delivery, and creates owner follow-up task on error without retry.

BLOCKER: approved V1 has literal placeholders {{1}}, {{2}}, {{3}} without mapped Kommo fields. Number, subject and resolution must be mapped and validated before the send-approval tag is used. No customer message was sent. UI verifies saved row with zero launches and no trigger. Bot creation is complete as a gated draft; live delivery is not ready.

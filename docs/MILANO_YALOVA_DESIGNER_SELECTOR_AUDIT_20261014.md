# Milano Yalova — Designer selector divergence — 2026-10-14

**Problem established from the connected CENTRAL STAGING database only.** For MDG event #24 / one canonical Milano program #14, both product parents #6058 (18:00) and #6061 (19:30) plus 4 variation posts #6059, #6060, #6062, #6063 show `tc_designer_template_id=29` but legacy `_ticket_template=d_28`. Both Designer templates 28 and 29 exist and are active. Template #28 is Milano staging base, template #29 is venue QR clone. Venue entry QR IDs are distinct static elements in both designs. `mdg_ticket_template_qr_bindings_v1[24]` authoritatively identifies base=28, clone=29; its rollback selections are all (28,d_28). No Woo sale or hold for MDG sessions 150/151; 500 available each and drafts closed.

**Failure mode:** `MDG_Ticket_Session_Datetime::get_designer_template_id()` and standalone `mdg_tdfix_get_designer_template_id()` preferentially accept `_ticket_template=d_28` ahead of `tc_designer_template_id=29`. Tickera's helper might set Designer meta only. Purchased PDFs may therefore fall back to base #28, bypassing cloned venue-specific QR binding. It remains a potential problem until a real ticket PDF is rendered and verified.

**Fix in draft PR:** `MDG_Ticket_Template_QR_Binder::save_template_selection` synchronizes BOTH selector keys as part of explicit QR binding only. Unit regression emulates Tickera helper writing only Designer key, asserts both selectors become (29,d_29), and checks rollback and invalid zero-id guard. This PR is DRAFT; does not modify live or stage code by itself.

**Staging repair:** first deploy reviewed patch to CENTRAL STAGING only, then invoke the plugin's own event QR rebind action for MDG24, after verifying exact base clone / hash, and re-query both selector keys for ALL six object IDs. NEVER regenerate event or variation objects; do not send tickets/messages; keep event and all products in DRAFT. Only after real 19:30 PDF and four unique ticket QR tests may the PDF gate pass.

**Attempted WP-CLI post meta update:** protected/internal meta refused by the native tool; no database updates occurred. Do not force raw writes; use the owning plugin's approved binding action after reviewed patch.

**Release protection:** the shared binder also serves Madagaskar. Regression tests and comparison of current live Madagaskar bindings are required prior to any central production rollout; do not bulk rewrite existing purchases. PR must remain draft until QA.

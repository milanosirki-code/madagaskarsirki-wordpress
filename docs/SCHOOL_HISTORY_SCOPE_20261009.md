# School history and export scope — 2026-10-09

Live native browser verification:
- Aydın MMC5: 48 annual 2026 records / 16,407 students, no duplicate school-year names. Plan149 campuses /167 units, suggested and planned16,590, printed/distributed0.
- Manisa MMC7: student screen53 known /22,055; print164 campuses /189 units, suggested and planned22,310, printed/distributed0.
- Filtered Manisa annual history shows24 /9,381 because records page uses only primary district Şehzadeler. Student screen correctly includes Yunusemre via MMC_Region_Service targets. Unfiltered history includes all53 known names (some names occur elsewhere, so unfiltered name-only sum is not a reliable reconciliation).

Fix: use one normalized, parameterized geographic scope helper for annual history and student export; use configured targets with primary-district fallback. Invalid context/geography fails closed. No import replay or school/count/stock writes.

Validation: PHP syntax passed and regression passed for multi-district scope, duplicate/blank normalization, single district fallback, global view and invalid/missing context.

Live deployment status: NOT applied. Native plugin editor file matches GitHub main blob53804a19b42ea13954cc148e25bbbc5bb39cabe7. Original backed up locally as /workspace/scratch/school-records-live-backup-20261009.php. CodeMirror fill did not update backing textarea; clipboard/native keyboard delivery blocked by browser credential protection (retained_data_restricted). No file update was submitted. Next controlled deployment must use supported plugin/file update or user-assisted browser editing, then verify Manisa history53 /22,055 and Aydın48 /16,407, and both districts in student export.

Draft PR: https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/211

## Live deployment — 10 October 2026

Controlled deployment completed through the native WordPress plugin editor. Live before blob matched main 53804a19b42ea13954cc148e25bbbc5bb39cabe7; full backup retained. Clipboard/editor source verified before save; reload readback exactly matches PR source blob 434abb76395a9ebadb3a3f7499424385a4c5e9b9 (39,428 characters). PHP 8.3 syntax and program-scope regressions passed again.

Current live data has additional imports after the earlier report: Manisa annual history has 89 unique schools, 2026, total 37,367. Native Excel export has Şehzadeler 60 school rows /24 known /9,381 students and Yunusemre 136 rows /65 known /27,986; combined196 rows /89 known /37,367. Aydın remains48 history records /16,407; Excel Efeler170 rows /48 known /16,407. Excel headers preserved. No school/count/import/stock/customer/order mutation. Rollback: restore exact pre-deployment records file. Earlier53/22,055 was the prior snapshot, not today's acceptance total.

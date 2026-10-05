# Corporate campaign birthdate pilot deployment — 2026-10-05

- Live page: https://madagaskarsirki.com/kurumsal-davetiye-pilot/
- Only Code Snippets snippet 124 was updated. Read-back: active true, code_error null.
- Child date of birth replaces manual age entry. Completed age is computed against the selected performance date in both the preview and server calculation.
- Invalid, missing and future birthdates are rejected. Turning 13 on the performance date uses the adult ticket price.
- Existing adult/free-child quota and current event prices remain in effect.
- PHP lint and 67 regression checks passed. jsdom field, birthday, session-change and pricing checks passed.
- Live HTML confirmed date fields and age computation script. Seven live read-only POST smoke checks passed, including Denizli totals 750, 1000 and 1250 TL and İzmir 900 TL.
- Birthdates are not persisted by the pilot. This remains a calculation pilot: no payment, order, reservation or QR ticket is created.
- Normal pages, products, prices, checkout and payment configuration were not changed.
- Rollback: restore snippet 124 to the source version on PR #134.
- Source and validation changes: draft PR #135, stacked on PR #134.

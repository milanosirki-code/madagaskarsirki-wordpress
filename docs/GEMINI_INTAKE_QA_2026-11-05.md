# Intake usability and edge-case checks — 2026-11-05

This continues draft PR #308 on its existing isolated feature branch. The live Google request and server validation logic remain unchanged. Addon 0.1.2 adds a scoped responsive textarea stylesheet, an associated data-transfer description, accessible busy/invalid states, explicit missing-field guidance, fixed messages for limits, and a 35-second client timeout with a retry path. The existing manual form remains usable while the request is pending or fails.

New interaction tests load actual PHP markup and both production JS source files together: the model proposal fills city/counts, leaves the session unselected and sends no quote until explicit session selection and form submission. Tests also cover timeout recovery without erasing manual edits, accessible descriptions, incomplete fields, rate errors, Turkish Unicode, stale responses and duplicate clicks. The CSS uses full available width, a 16px textarea font, vertical resizing and wrapped buttons below 650px. No screenshot-based or real mobile-browser claim is made; no connected browser tool is available in this session.

Synthetic real staging requests before this UI-only update:

| Input case | Observed proposal | Result |
| --- | --- | --- |
| Family with children, no city/counts | city/adults/children=null, infants=0, incomplete=true | Does not invent missing data |
| İzmir, two adult guardians, children aged 2/7/13 | adults=3, children=1, infants=1 | Correct age categories |
| İstanbul, two adults, one child (only İzmir offered in staging) | city=null, adults=2, children=1, infants=0, incomplete=true | Does not substitute an offered city |
| İzmir, two adults, one child | correct city and 2/1/0 | Complete proposal |

These are small synthetic observations, not a general model accuracy guarantee. The user must review proposed fields. No customer records or secret values were used. Diagnostic option/transient writes and the bounded API test requests are expected. Production remains untouched. PHP/DOM CI, staging install, package archive and final readback are pending for 0.1.2.

A subsequent rapid request returned an HTML HTTP429 page from the request path, not the plugin's JSON PLEASE_WAIT response. This does not identify which upstream limiter acted or prove the plugin cooldown ran. No further live generation checks were made. The UI now handles both JSON limits and an HTML429 page with a fixed wait message while keeping the manual form available.

Rollback: replace the intake addon with archived 0.1.1 or deactivate it; the standalone native form/quote plugin is unchanged. Production publication remains subject to authenticated mobile/desktop review and the existing sales/checkout gates. Do not open anonymous access simply to obtain a screenshot.

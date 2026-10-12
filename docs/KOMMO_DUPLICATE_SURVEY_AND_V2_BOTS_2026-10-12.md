# Kommo survey repeat hotfix and V2 notification readiness — 2026-10-12

## Live evidence
The same survey Salesbot sent its initial template, recorded a customer answer, sent a thank-you, and immediately recorded another survey-start note and sent the identical template. This was observed in the same lead and conversation. Sales Followup V2 contains no survey-template send. The survey had 11 active sessions when investigated. No customer identifiers or message contents are archived.

## Saved live changes
- Survey 118553: a second guard immediately before the send checks started/answered tags, the survey-sent flag and an empty result field. The sent flag is set before the external template. All answer, error, exclusion and timeout paths now point to a terminal action block with no next step; the previous finish block with array parameters was removed. This follows the terminal action pattern observed in the working native ticket bot. It does not close the customer conversation.
- Support 118590: template changed to approved mapped V2 ID 86942. Ticket number, topic and result must all be nonempty; explicit send-approved tag and one-send lock retained. Terminal action replaces the previous finish block.
- Refund completed 118592: template changed to approved mapped V2 ID 86944. Order number, refund amount, completion date and refund-verified tag remain mandatory. Terminal action replaces the previous finish block. No refund transaction was executed.
- Payment reminder 118588: existing mapped V2 ID 86871 retained. Terminal action replaces the previous finish block. ALLOW_SEND eligibility gate retained; sync does not automatically set it.

## Verification and limitations
Survey was reopened and its saved guard, pre-send flag and terminal block confirmed. Support was reopened and its saved source confirmed. Refund was reopened and template 86944 and terminal action confirmed. A static graph check passed for all four replacement sources: every goto target exists and has the matching block type; survey answer/error paths terminate; the lock precedes the only survey send block. These checks do not establish a new live WhatsApp reply round trip. Existing waiting sessions may require follow-up observation.

Support/refund V2 approval and field binding were verified in the live template list and visual message picker. The Pro-plan gate explicitly blocked the tag-added trigger. No paid upgrade was performed; the unsaved trigger was abandoned. Support and refund are prepared for controlled manual start after verified fields/tags, but are not automatic. Payment also remains gated and without automatic rollout.

Refund-started, event-cancellation and event-change V1 templates are approved but still contain unbound numbered variables. Their existing bots were not enabled to send incomplete or incorrect notices. A mapped version or supported parameter binding is still required.

Selected payment canary order 5869 now has Woo status failed and Kommo closed-lost; its eligibility field is EXCLUDED. No message or order-status modification was performed.

## Rollback
The companion JSON archive contains before and after source for survey, support, refund and payment. Restore only the affected bot source through its editor if needed. Restoring the old survey source can reintroduce the observed repeat. Existing customer results/tags were preserved. No checkout, payment-provider or order mutation occurred.

## Remaining work
1. Verify one real response after the saved survey change produces a result/thank-you without another survey template.
2. Decide on a supported automatic trigger/source for support and confirmed refunds; tag-added is Pro-only in the current account.
3. Map the three remaining approved V1 notification templates.
4. Revalidate a currently eligible owner-selected payment canary before any real send.

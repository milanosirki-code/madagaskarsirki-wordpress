# Protocol ticket WhatsApp sharing
Date: 2026-10-06
Source: docs/code-snippets/mdg-protocol-ticket-menu.php; Code Snippets 127.
Base: codex/protocol-ticket-menu. No checkout, paid product or Kommo token changes.

Phone input supports named manual lines `Ad Soyad | 05xxxxxxxxx`, optional Excel/CSV telefon column and editable per-person numbers after issuance. Package recipient is a separate field for an explicitly chosen full-package recipient. The XLSX template includes telefon as a text column. National Turkish mobile variants normalize to country-prefixed digits; explicit international +country numbers supported. Invalid nonempty numbers block saving/preview.

WhatsApp sharing uses an admin capability and nonce checked POST to save an opened state and redirect to a fixed wa.me host with a prepared message. The administrator presses Send inside WhatsApp. No automatic message or delivery receipt is claimed. “Gönderdim” stores an administrator declaration, not provider delivery. Changing a contact resets its previous sharing marker. Optional contacts don't become WooCommerce billing phone fields, avoiding unverified third-party automation.

Individual recipient links use a per-person HMAC key derived from the random batch secret. Public authorization verifies the exact index and filters native tickets to that person across selected sessions. Individual PDF links keep that scope; modifying the attendee index or ticket ID cannot access another person's PDF. The full-package bearer link is reserved for package recipients.

Existing Kommo code reviewed: madagaskar-kommo-automation/modules/automatic-ticket-link.php schedules/writes a Bilet Linki custom field on a matching Order# card. It does not send a WhatsApp message or return delivery status. No direct WhatsApp transport discovered in the inspected active code. Accordingly this change implements usable manual WhatsApp sharing; automatic Kommo/Salesbot delivery remains a separate integration task requiring verified send workflow.

Hooks added: admin_post_mdg_protocol_contacts, admin_post_mdg_protocol_whatsapp, admin_post_mdg_protocol_mark_sent. Existing public route extended with scoped kisi authorization.
Tests: PHP syntax and 53 regression checks; phone formats, missing/malformed numbers, Excel/manual parsing, prepared message, target bounds, personal/full-package scope, existing QR/date/idempotency checks.
Live smoke plan: snippet active/no error, admin phone input, generated Excel roundtrip, synthetic sharing render and URL construction; no actual sends or new orders during diagnostics.
Rollback: restore prior source for snippet127. Native tickets remain; personal URLs from this version then stop resolving. Existing full-package URLs and snippet126 remain available.

## Live verification
Snippet127 active=true and code_error=null after reload. Live diagnostics: Excel supported/roundtrip/sharedStrings, legacy seat collision, three input modes, phone normalization, personal scope, synthetic sharing form, manual-delivery labels and WhatsApp URL all passed. Existing Kommo link bridge function is loaded. Admin HTML includes recipient phone field, manual name|phone instructions and Excel telefon column instructions. Catalogue includes8 active events and Denizli sessions99/100. No new orders, tickets, contacts, WhatsApp messages or provider calls were issued by these diagnostics. A real WhatsApp Send action and delivery to a handset were not exercised.
PR: https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/160

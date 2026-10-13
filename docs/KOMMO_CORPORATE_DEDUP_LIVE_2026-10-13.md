# Corporate CRM duplicate prevention — live 2026-10-13

Cause: corporate form snippet42 had only a 30-second IP transient written after lead creation/sync. Identical requests submitted later created separate leads.

Deployment: new separate Code Snippets ID187 active; original42 remains active and unmodified. Hash covers sanitized company, contact name, phone digits, city, date, event type, audience and note. Same exact request is reserved atomically by add_option before original handler; saved local lead deduplicated24h. Changed fields remain a new request. Consent and nonce unchanged. Hash/lease state stores no raw contact data. Failed local creation releases claim on shutdown; expired claims compare-deleted without removing another owner's lease. Cron clears expired state. An existing failed Kommo sync retains its local record for manual retry; duplicate submission does not create another lead.

Validation: CI commit d1d4f3bceaf0d8aa27fda3376d33c3fd4c84b33f, all13 workflows success including PHP syntax and nine isolated guard assertions. Post-deploy REST reread IDs187 and42 active; native admin shows187 active. Live /kurumsal/ renders form/consent/send button. No synthetic complete form submitted because original handler emails staff and creates real CRM records. Fresh real duplicate submission observation remains pending; preexisting duplicates are not backfilled, merged or deleted.

Rollback: deactivate snippet187; original form returns unchanged. Existing hash leases expire and scheduled cleanup is inert while disabled; reactivation clears expired keys when encountered.

Draft PR: https://github.com/milanosirki-code/madagaskarsirki-wordpress/pull/238
Screenshot: kommo-corporate-dedup-20261013.jpg

Remaining CRM work: diagnose exact unanswered WhatsApp conversation and Woo pipeline coverage; verify sales task stage-entry delivery; expand satisfaction reporting by city/session.

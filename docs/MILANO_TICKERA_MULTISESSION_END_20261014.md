# Last-session end guard — 2026-10-14

Bug: MDG draft producer uses the first session end for `event_end_date_time`, even when the plan has multiple sessions and exposes `tickera.last_end_local`. Yalova 22 October 2026 has 18:00–19:00 and 19:30–20:30; the shared Tickera event incorrectly ended at 19:00.

Fix: use validated `plan.tickera.last_end_local` for the shared Tickera event end, fail closed if missing. No change to event start, products, existing orders, payment, QR or capacity. For **existing staging draft Tickera #6056** update the stored end meta from 19:00 to 20:30 through scoped `wp post meta update` only after verifying its production key `MDG-E24-618c401e2640` and draft status. Do **not** regenerate all drafts.

IMPORTANT unresolved issue: standard Tickera Designer `event_datetime` may still show the shared event's first 18:00 start for the second-session 19:30 ticket. This change fixes shared end semantics only; before sale a per-ticket session time override must be verified in a real rendered Tickera PDF/QR, not inferred. Keep live publishing gated until corrected/verified.

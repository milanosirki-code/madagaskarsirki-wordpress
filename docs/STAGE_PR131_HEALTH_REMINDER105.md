# Madagaskar — PR131 / Health / Issue105 — 2026-10-05

Status checkpoint: work in progress, no reminder messages sent.

PR131 merged: `7c95b0cab1fa8b682aa1d8eb0c7a5f288e3c3a43`. Ticket plugin live/main hashes match; snippet30 normalized body matches. Sales42,000gross/47,000nominal/42,000net, capacity107 and family4 preserved.

Health before15OK/1warning. Actual snippets125total/45active; inventory47 is due to trashed121/122 with active=-1, not a cache. False warnings for production Code Snippets125/124/61 are class-method/global-function collisions. Minimum inventory patch and regression tests prepared; deployment pending CI.

Issue105 is next, DRY-RUN only. Required gates: cancelled, sales closed, canonical session start cutoff, later paid replacement for the same normalized phone AND event, already-reminded state, unpurchasable products, missing mappings fail closed. Real WhatsApp/Kommo send and automation enable remain forbidden. Real send count0.

This checkpoint is archived in the existing Drive project folder and will be updated with final CI/deployment/dry-run evidence. GitHub project state will be updated in the dedicated branches.

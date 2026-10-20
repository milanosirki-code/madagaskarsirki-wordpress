# Milano corporate campaign module

The Milano portal adapts the native campaign renderer and age calculation in a dedicated `Milano_Corporate_Engine`. It uses its own option (`milano_corporate_campaign_codes_v1`), admin action names and signed cart fields (`milano_corporate_*`). Existing Madagaskar campaign source and records are not changed.

The canonical Milano handoff catalogue supplies eligible events and sessions. The adapter accepts only a valid signature and a matching fresh event, session, product, variation, role, quantity and ticket type. Other ticket additions delegate to the original handoff guard. Mixed selections are preserved and rejected before replacement.

The native rules are retained: age is measured on the performance date; ages 0–2 are free without consuming the allowance; ages 3–12 receive up to two free places per paid adult; older participants use adult pricing. Prices and capacity are revalidated server-side.

The central shortcode is `[milano_corporate_campaigns]`. The front-page source is `integrations/milano/corporate-entry.html`. The central portal has its own Milano presentation. No test-only campaign codes are provisioned by the module.

Deployment: install the engine and adapter together as the plugin, or load the engine source before the adapter through the existing native Code Snippets module. Do not run both deployments at once. Create and verify the central portal before updating the front page. Configure production codes through the dedicated Milano admin screen. Roll back only the Milano module and front-page content; never replace the shared Madagaskar engine or database.

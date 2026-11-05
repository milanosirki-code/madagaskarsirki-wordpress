# Gemini connection verification — 2026-11-05

The existing Google provider was discovered before creating any new Google credential. Production has AI Provider for Google 1.2.1 active; staging has 1.2.0 active. Both have a nonempty `connectors_ai_google_api_key` option. Presence alone does not establish validity. No credential value was retrieved through the tools or saved in this repository.

The independent diagnostic plugin registers a POST endpoint only on the exact approved staging origin and requires manage_options. It rejects user prompts. It lists Google models, selects an explicit supported low-cost model, then sends one fixed synthetic prompt expecting `MDG_GEMINI_OK`. Only success, a model identifier or fixed safe error codes are returned. No provider response bodies, exceptions, headers or secrets are returned. Authentication stays in an HTTP header, redirects are disabled, and network time and response size are bounded. A lock and a 60-second cooldown limit repeated checks; these diagnostic option/transient writes are the only local writes. No checkout, cart, order, ticket or customer records are accessed.

Run `php tests/gemini-diagnostic.php` and PHP lint via the included PHP 7.4/8.4 GitHub Actions workflow before staging installation. Tests mock all HTTP calls. Real staging test result remains pending. This does not implement customer chat or prove the WordPress AI Client integration itself works; it verifies the existing credential directly against Google's API. Existing assistant PRs #305/#306 are unchanged.

Rollback: deactivate and delete this standalone diagnostic plugin. If a hard process termination leaves its lock behind, delete only `mdg_gemini_diagnostic_lock` after confirming no check is running. Production is not an installation target.

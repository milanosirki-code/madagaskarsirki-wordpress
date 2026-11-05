# Gemini connection verification — 2026-11-05

The existing Google provider was discovered before creating any new Google credential. Production has AI Provider for Google 1.2.1 active; staging has 1.2.0 active. Both have a nonempty `connectors_ai_google_api_key` option. Presence alone does not establish validity. No credential value was retrieved through the tools or saved in this repository.

The independent diagnostic plugin registers a POST endpoint only on the exact approved staging origin and requires manage_options. It rejects user prompts. It lists Google models, selects an explicit supported low-cost model, then sends one fixed synthetic prompt expecting `MDG_GEMINI_OK`. Only success, a model identifier or fixed safe error codes are returned. No provider response bodies, exceptions, headers or secrets are returned. Authentication stays in an HTTP header, redirects are disabled, and network time and response size are bounded. A lock and a 60-second cooldown limit repeated checks; these diagnostic option/transient writes are the only local writes. No checkout, cart, order, ticket or customer records are accessed.

Run `php tests/gemini-diagnostic.php` and PHP lint via the included PHP 7.4/8.4 GitHub Actions workflow before staging installation. Tests mock all HTTP calls. Real staging test result remains pending. This does not implement customer chat or prove the WordPress AI Client integration itself works; it verifies the existing credential directly against Google's API. Existing assistant PRs #305/#306 are unchanged.

Rollback: deactivate and delete this standalone diagnostic plugin. If a hard process termination leaves its lock behind, delete only `mdg_gemini_diagnostic_lock` after confirming no check is running. Production is not an installation target.

## Verified outcome

Staging 257930487 has diagnostic 0.1.2 active. Code commit e6c5af6e6e43243631ecee3387c6b4d9ccf58633. PHP 7.4/8.4 run 42080767731 passed; log shows 64 assertions per job. The first generation with the listed Gemini 2.5 Flash Lite returned upstream 404. A separate model-list diagnostic confirmed Gemini 3.1 Flash Lite was listed for generateContent. Selecting that observed stable model produced GEMINI_OK, success=true, fixed_prompt_only=true, customer_data_sent=false. This proves a direct request with the existing staging credential, not production generation or the WordPress AI Client call chain. Production namespace GET returned rest_no_route/404.

Installed ZIP 2701 bytes, SHA256 5d4891d9b36005eb899f43c62ed70b8cb90398b6ee681f3a883f0938b6a7cfec; Drive https://drive.google.com/file/d/1v4CavsHltRSgksF1FsH2XJwD7AnvN2G8/view (metadata and destination folder verified). No new Google or OpenAI key was created. No secret value was output, copied or archived. Gemini intake integration continues independently in draft PR #308.

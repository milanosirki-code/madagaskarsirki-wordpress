# Campaign child count fix — 2026-10-05

User screenshot showed 1 child selected but two required DOB inputs. Actual live HTML contained sm===m&#038;&#038;sd<d, and evaluating the delivered script reproduced SyntaxError. Source-only jsdom checks had missed WordPress content character conversion.

Snippet124 now emits the script via page-scoped wp_footer priority30. It never passes through the_content. change/input and pageshow keep input count in sync. A formnovalidate field update fallback is visible until successful JS initialization.

PHP lint, 79 PHP regressions and expanded jsdom checks passed. Post-deploy actual live HTML script evaluated successfully: 2→1 inputs, DOB2023-10-05, valid form, one free child and 500 TL. Live POST for 1 adult + 1 child returned 500 TL and exactly one required DOB input. No order or payment was created. Snippet PUT returned active=true, code_error=null.

Only snippet124 changed. Existing pricing/payment/QR logic preserved. Draft PR137 stacked on136. Rollback restore snippet124 from PR136; that reintroduces the known inline JS error.

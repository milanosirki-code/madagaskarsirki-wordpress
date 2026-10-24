# Live rollout — 2026-10-24

Native Code Snippets #208 created inactive, then activation POST returned HTTP500. Read-back showed active:true; no blind retry. Both homepage and /bilet-al/ returned HTTP200 with UI script and complete HTML. Browser then verified all three routes (homepage, directory, event) functioning. Native refinement PUT returned id208 active:true.

- Homepage: Sakarya, Çankırı, İzmir, 8 session cards. Homepage İzmir 16:00 -> session90, selected native picker 16:00, sticky 08 Kasım ·16:00.
- Ticket directory: 3 events, 8 exact session links. Sakarya 14:00 -> session160, selected native picker and sticky both 14:00.
- Existing picker change to16:00 correctly updates note/sticky. One adult ->490TL; reset after test. No cart submission or order.
- Latest helper text on dark directory computes rgb(244,241,232); large cards blue/navy. Chronological picker order preserved even for disabled started buttons.
- Node regression PASS. PHP CLI unavailable locally; live PHP render is verified; do not claim local php-lint ran.

Rollback: POST /code-snippets/v1/snippets/208/deactivate through owning API. Existing snippet35, original ticket renderer, PHP templates, sales policy, prices, native checkout JS and records are unchanged.

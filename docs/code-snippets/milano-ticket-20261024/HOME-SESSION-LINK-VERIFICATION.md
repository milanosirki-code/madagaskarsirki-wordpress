# Homepage session card rollout

2026-10-24: four match-once patches applied to pages 5 / 118. Homepage shows large individual 12:00,14:00,16:00 links. Exact links grounded in current ticket form session IDs. A started session is amber; completed at +60m is a non-link span aria-disabled=true. Stable state signatures avoid replacing focused links on each timer tick.

Live browser: clicked homepage Derince 14:00 -> /bilet-al/?seans=177#derince. The 14:00 form gained .ml-session-selected and label Seçtiğiniz seans · 14:00; its top was 24px (correctly scrolled into view). Clicking 16:00 quick nav moved selection and left exactly one selected form. No payment/order/cart mutation. No page JavaScript errors observed.

Tests: test-home-session-links.cjs PASS; mapping, states, +60m no link, all homepage/ticket script syntax, numeric parameter guard. Rollback: reverse home-session-link-patches.json in reverse order or WordPress revisions. Existing checkout forms and prices untouched.

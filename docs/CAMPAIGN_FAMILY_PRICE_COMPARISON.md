# Campaign family price comparison

Display-only change in active snippet 124. Denizli institution card compares one adult plus two children aged 3–12: normal individual tickets 1,000 TL, campaign 475 TL. Dynamic quote and server result compare actual people using current regular variation prices, exclude ages 0–2, price 13+ as adults, and include extra paid children. No product prices, checkout hooks, or order processing changes.

Dependencies and hooks remain the existing corporate shortcode and footer script. PHP syntax passes; 96 catalogue/quote/security assertions and DOM age-selection/comparison tests pass.

Live smoke: open demo-kurum-a and demo-kurum-b links, verify family headline and server quote 1,000 → 475; verify one child 750 → 475 and zero child 500 → 475 with the footer script. Confirm native adult variation prices remain 500. No payment submission required for this display change.

Rollback: restore snippet 124 source from base branch codex/campaign-event-poster through the native Code Snippets endpoint. Keep snippet active; do not alter registry, products, or orders.

> Güvenlik notu: Bu belgedeki demo-kurum/demo-okul değerleri gerçek kampanya kodlarının yerine kullanılan sentetik etiketlerdir. Canlı kodlar yalnız yetkili yönetim ekranında tutulur. Eski Git geçmişi hâlâ açığa çıkmış değerleri içerebilir; belge temizliği kodu iptal etmez.

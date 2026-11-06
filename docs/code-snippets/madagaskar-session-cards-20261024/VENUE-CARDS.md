# Salon kartları

Scope: homepage and /bilet-al/ event cards, immediately after session times. Read venue_name, venue_address and recorded venue_maps_url from the same existing public MDG event record. Map keeps the saved location link; directions uses Google Maps dir with exact saved venue name/address. No guessed coordinates, embeds, permission changes or location collection. Safe HTTPS Google Maps allowlist with text query fallback. Links open a new tab with noopener noreferrer. All text uses textContent; data uses existing JSON_HEX encoding.

Tests: node test-mdg-session-cards.cjs and node test-mdg-venue-links.cjs. Live smoke: 3 venue cards on each of homepage/directory, Sakarya saved short link exact, Çankırı and İzmir names/addresses correct, link destination and appearance checked. Rollback restore Code Snippets208 code from before-venue-cards.php (strip opening PHP tag); leave it active to retain existing seans cards. No payment/order/QR changes.

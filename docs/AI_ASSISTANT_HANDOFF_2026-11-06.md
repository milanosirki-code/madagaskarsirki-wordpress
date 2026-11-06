# Ticket-page handoff — 2026-11-06

Fresh staging catalogue returned İzmir MDG10, sessions88/89/90 on8November at12:00/14:00/16:00 Europe/Istanbul with canonical event URL `/etkinlik/madagaskar-sirki-izmir-08-kasim-2026/`. A SELECT confirmed MDG10 title, public_slug and onsale status match. The URL is a native MDG rewrite, not a WordPress post; no matching posts row is expected.

Installed native source `class-mdg-public-event.php` registers the event rewrite (line13), resolves by public_slug and requires onsale (55-61), builds live_url from that slug (36-38), and renders the ticket section with id=bilet-secimi (210). It obtains sessions from MDG_Sessions::by_event and labels local times. Native public-event.js resets quantities to zero and chooses the first session on load/pageshow (20,56-57). Thus linking to the right event does not transfer an assistant's chosen14:00/16:00 session or quantities. No native sales code was changed.

Core0.2.1 improves this bounded handoff: after strict event URL validation the UI adds only the fixed ticket-section fragment. The result explicitly names the chosen date/time and tells the user to select that session and the shown quantities again. It does not add query parameters, prefill the native page or create a cart/reservation/order. Existing server URL restrictions stay unchanged.

The existing quote lifetime was returned by the server but the UI previously left a rendered result visible indefinitely. The UI now validates a positive lifetime of at most60seconds, counts it conservatively from request start using performance.now, hides the result/removes href on timeout, and checks expiry again at link activation and tab visibility. This does not guarantee unchanged price/capacity after navigation; native checkout still validates.

Real HTML inspection of the private staging destination returned its login screen. That is evidence of the tool's unauthenticated fetch, not a failed user navigation or a broken ticket page. A concise optional user question about the red-button destination is pending. No authenticated browser click or native ticket DOM was verified in this session. The user's earlier phone screenshot verified only the recommendation card.

PHP/DOM CI, staging installation, package archive and readback are pending. Tests use the real PHP markup and scripts; new cases cover the anchor, explicit re-selection copy, expired link prevention and invalid quote lifetime. The Gemini integration fixture includes the unchanged server lifetime fields. No additional Gemini request, cart, checkout, payment, order or customer-message action was initiated. Production is unchanged.

Rollback: restore archived core0.2.0 ZIP on staging or deactivate core; Gemini intake depends on core. Keep existing native ticket and payment plugins active. Existing PR308 depends on305/306; it remains a draft, with no production publication implied.

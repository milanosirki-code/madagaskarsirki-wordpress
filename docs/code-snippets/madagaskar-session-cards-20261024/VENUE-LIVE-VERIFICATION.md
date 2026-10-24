# Venue card rollout — 2026-10-24

Native snippet208 PUT succeeded with active:true. Three Salon ve Ulaşım cards on homepage, three on /bilet-al/. In each DOM card, the session-time container's immediate next sibling is ms-venue-card and it contains two links. Canonical public record used for name/address/map.

Sakarya: Atatürk Spor Salonu, saved https://maps.app.goo.gl/bB8fukhJysrUSA656. Actual homepage link clicked; Google Maps opened Atatürk Kapalı Spor Salonu at exact saved address Papuççular, Adnan Menderes Cd. No:118, Adapazarı/Sakarya. Directions URL contains this name/address as destination. Çankırı: Hasan Fehmi Gökşen Spor Salonu; İzmir: Halkapınar Spor Salonu; correct saved URLs read back. New links target=_blank with noopener noreferrer.

Tests: test-mdg-session-cards.cjs PASS; test-mdg-venue-links.cjs PASS (saved URL, recorded route query, unsafe URL fallback, no venue fallback, no innerHTML). UI screenshot saved. No live checkout submission or sales-record changes. Rollback before-venue-cards.php restores snippet208's prior UI version.

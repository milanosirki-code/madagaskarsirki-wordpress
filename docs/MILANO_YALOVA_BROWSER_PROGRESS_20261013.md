# Milano Yalova browser staging progress — 2026-10-13

Staging only; production and real payments unchanged.

- Installed AI Abilities 0.7.1 artifact 11836416868 from commit dde9711a9c9ddfd76dc52380c8e1d28ef3085347. Both package SHA256 checks passed; 20/20 GitHub workflows succeeded.
- Browser schema/input smoke: 11/11 PASS. Legacy migration schema checks SKIP because mdg_ai_abilities_modules does not include legacy-mdg-mmc-migrate (preview is enabled). No write ability was executed by smoke.
- MMC program #14, PRG-2026-YAL-MERKEZ-001: created event EVT-20261022-YAL-MERK-14; manually corrected initial hard-coded Madagaskar title to Milano Sirki — Yalova Merkez.
- Two sessions saved and verified: 2026-10-22 18:00, 19:30, independent shared capacity 500 each. Free seating.
- Saved child 245 TL, adult 490 TL; family existing 1100 TL / capacity consumption 4 verified.
- Sales readiness still reports missing confirmed venue link. No unsupported phone/direct venue attestation was checked.
- Found broader hard-coded Madagaskar defaults in event-service.php:68, mdg-draft-sync-service.php:234, mdg-bridge-service.php:573, publish-content-excel.php:239 plus marketing/Kommo strings. Title correction alone does not prove exclusive Milano sales routing. Resolve these brand paths before production publishing.
- PayTR Milano domain approval, payment/QR end-to-end, and controlled production rollout remain incomplete. PR #240 remains staging review work.

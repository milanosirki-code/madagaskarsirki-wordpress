# Protocol / Complimentary Ticket Live Consolidation — 2026-10-06

## Purpose

The live protocol/free-ticket feature was developed through stacked PRs #154, #159, #160, #161, #162 and #163. Those PRs were based on one another rather than current main, so merging them independently would carry unrelated historical branch ancestry.

This consolidation ports only the final verified protocol feature set onto current main `aef750852b8e0d961367c17a2529d286817219fe`.

## Canonical live sources

- Code Snippets #126 — MDG Denizli Protokol A2-A11 — 8 Ekim 2026
  - repository source: `docs/code-snippets/mdg-denizli-numbered-invitations.php`
- Code Snippets #127 — MDG Protokol / Ücretsiz Biletler — Genel Menü 20261006
  - repository source: `docs/code-snippets/mdg-protocol-ticket-menu.php`
- Code Snippets #128 — MDG Protokol PDF Düzeni
  - repository source: `docs/code-snippets/mdg-protocol-pdf.php`

All three live snippets are active with `code_error=null`.

Code Snippets stores the executable body without the repository file's PHP wrapper. After stripping the leading `<?php` / trailing wrapper and normalizing line endings, each live source matches its final protocol branch file exactly.

## Live feature coverage

The current live system already supports:

- protocol or complimentary ticket type;
- named guest list by Excel/CSV or manual input;
- seat-number list;
- unnumbered quantity mode;
- institution/package title;
- event and session selection;
- recipient phone and per-person phones;
- secure package/person links;
- WhatsApp deep-link sharing;
- manual “sent” acknowledgement;
- one PDF containing all tickets in a ready package;
- copyable package link;
- Denizli A2–A11 existing 20-ticket batch;
- no-store/private PDF handling;
- existing native QR/ticket generation.

WhatsApp is a deep-link/manual send workflow. The system does not claim provider delivery confirmation.

## Safety

This consolidation does not deploy or mutate live WordPress. It archives already-live source on current main and adds tests/CI. No order, payment, ticket, capacity, customer phone, campaign, or QR record is changed.

## Tests

Protocol contract:
- PHP lint for all three canonical sources;
- regression.php;
- pdf-layout.php;
- cache.php;
- bulk-pdf.php.

The workflow is `.github/workflows/protocol-tickets.yml`.

## PR disposition

After this consolidation PR is green and merged:
- stacked PRs #154/#159/#160/#161/#162/#163 should be closed as superseded by the consolidation PR;
- their historical discussion remains useful evidence but they should not be merged separately.

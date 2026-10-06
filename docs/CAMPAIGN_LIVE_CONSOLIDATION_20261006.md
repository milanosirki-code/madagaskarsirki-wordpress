# Corporate Campaign Live Consolidation — 2026-10-06

## Purpose

The corporate/school campaign system evolved through stacked PRs #132–#139 and #143–#145. The live source continued to change after PR #145, specifically for the OKUL26 price-management workflow. Merging the historical stacked PRs independently would not reproduce current production.

This consolidation backports the exact current live campaign source and the verified live sales-report source onto current main.

## Canonical live sources

- Code Snippets #124 — MDG Ortak Kurumsal Kampanya Dinamik İl Kataloğu 20261005
  - canonical repository source: `docs/code-snippets/mdg-corporate-campaigns.php`
  - live active, `code_error=null`
  - source is captured exactly from live, with repository PHP wrapper added
- Code Snippets #125 — MDG Kampanya Satışları — Salt Okunur Rapor
  - canonical repository source: `docs/code-snippets/mdg-campaign-sales-report.php`
  - live active, `code_error=null`
  - live source matches PR #139 source after Code Snippets wrapper normalization

## Current live registry

Read-only option check on 2026-10-06:

- `bms`: Bmsdenizli / Denizli / active
- `sagliksendenizli`: Sağlıksen / Denizli / active
- `okul26`: Okul / Eskişehir / active
  - adult_campaign = 490 TL
  - child_campaign = 250 TL

No registry write was performed by this consolidation.

## Live pricing policy now canonical

The final live #124 source is ahead of PR #145 and includes:

- create and edit campaign flows;
- manager enters only discounted adult/child prices;
- normal adult/child price is read from the selected live program/session product data;
- no manual normal-price fields in the campaign admin;
- campaign effective price is capped with `min(live regular price, configured campaign price)`;
- free-child rules remain in the existing birthdate/age flow;
- OKUL26 operator workflow is supported;
- TEST codes remain isolated from real sale behavior;
- existing Denizli corporate behavior is preserved where explicit campaign pricing is absent.

## Sales report

#125 remains read-only and keeps program/campaign filters. It does not mutate orders, payments, mappings or campaign registry.

## Tests

The consolidation carries:
- `dynamic-catalogue.php`
- `age-ui.cjs`
- `sales-report.php`
- `live-price-menu.php`
- historical `live-age-smoke.py` as evidence helper

The dedicated CI contract lints both canonical sources, runs PHP regressions, and runs the jsdom UI contract.

## Safety

This GitHub consolidation performs no production write. It does not:
- create/edit orders;
- submit PayTR payment;
- create QR/tickets;
- change Woo regular product prices;
- alter campaign option registry;
- send WhatsApp/Kommo/email/SMS.

After a green merge, historical campaign PRs represented by this final live state should be closed as superseded and not merged independently.

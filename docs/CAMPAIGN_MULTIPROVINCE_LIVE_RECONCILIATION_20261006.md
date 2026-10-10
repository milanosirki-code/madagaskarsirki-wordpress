# Campaign Multi-Province Live Reconciliation — 2026-10-06

## Why this follow-up exists

PR #168 successfully consolidated the then-current live corporate/school campaign system onto main. After that merge, live Code Snippets #124 advanced again and became live-ahead of GitHub.

The new live behavior is intentional business functionality: one campaign code can be linked to multiple provinces with independent per-province campaign pricing and activation.

This reconciliation captures the exact current live #124 source without modifying production.

## Live source

Code Snippets #124:
- name: MDG Ortak Kurumsal Kampanya Dinamik İl Kataloğu 20261005
- active: true
- code_error: null
- canonical source: docs/code-snippets/mdg-corporate-campaigns.php
- live source uses schema_version=2 and a cities map

## Read-only live registry baseline

Captured with WP-CLI option get; no write performed.

### demo-kurum-a
Name: Örnek Kurum A
Active: true
schema_version: 2

Cities:
- Denizli — active — legacy city entry; live/program pricing fallback retained
- Ankara — active — adult_campaign 475 TL; child_campaign 250 TL
- Eskişehir — active — adult_campaign 475 TL; child_campaign 250 TL

### demo-kurum-b
- Denizli only
- active
- schema_version: 2

### demo-okul-c
- Eskişehir only
- active
- adult_campaign 490 TL
- child_campaign 250 TL
- schema_version: 2

The registry itself is production data and is not changed by this GitHub reconciliation.

## Public live verification

Authenticated rendered-page inspection of:
https://madagaskarsirki.com/kampanya/?kod=demo-kurum-a

The rendered main content shows:
- institution: Örnek Kurum A
- heading: Denizli / Ankara / Eskişehir gösterileri
- current campaign/event cards are rendered under the same demo-kurum-a code

This proves the live public flow resolves a single campaign code across multiple configured provinces.

## Schema v2 behavior

Legacy single-province rows are adapted at read time:
- old province/pricing fields become a cities[normalized_province] entry;
- schema_version becomes 2;
- legacy marker is retained;
- old top-level province/pricing are removed from the adapted representation.

Campaign resolution:
- validates campaign-level active/expiry;
- independently validates each city active/expiry;
- returns only active, unexpired configured cities.

Catalogue:
- includes only onsale events whose province exists in campaign cities;
- reads normal adult/child prices from the live program/session Woo products;
- applies per-city adult_campaign / child_campaign;
- caps effective campaign price at the live regular price;
- leaves free-child, birthdate age, Woo cart, PayTR and Tickera pipeline behavior intact.

## Admin workflow

A campaign code can now:
- update institution name/general active/general expiry;
- add a province;
- edit per-province adult/child campaign prices;
- toggle a province independently;
- keep other province pricing unchanged.

No manual normal-price fields are introduced.

## Tests

Existing corporate campaign regressions are preserved and extended.

dynamic-catalogue.php now verifies:
- one code resolves three provinces;
- catalogue includes only configured provinces;
- Ankara and Eskişehir can use different adult campaign prices;
- disabling one province does not disable the campaign;
- city expiry is independent;
- old single-province data adapts to schema v2.

multi-province-admin.php locks:
- schema v2/cities contract;
- legacy migration;
- city_add / city_edit / city_toggle operations;
- per-city pricing;
- live-normal-price cap.

Corporate campaign CI runs these in addition to the existing price, sales-report and jsdom UI contracts.

## Safety

This reconciliation:
- does not update mdg_corporate_campaign_codes_v1;
- does not create/edit orders;
- does not perform payment;
- does not create tickets/QR;
- does not send WhatsApp/Kommo/email/SMS;
- does not alter Woo regular prices.

Production write count for this reconciliation: 0.

> Güvenlik notu: Bu belgedeki demo-kurum/demo-okul değerleri gerçek kampanya kodlarının yerine kullanılan sentetik etiketlerdir. Canlı kodlar yalnız yetkili yönetim ekranında tutulur. Eski Git geçmişi hâlâ açığa çıkmış değerleri içerebilir; belge temizliği kodu iptal etmez.

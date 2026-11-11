# Milano brand-scoped admin workspace — 14 October 2026

## Goal
Separate **Milano** top-level WordPress administrator menu next to the current Madagaskar administration without altering any common MMC–MDG–WooCommerce–Tickera–PayTR write path.

## Code
`integrations/milano/admin-scope/milano-admin-workspace.php` (v0.1.0): a WordPress plugin for a **read-only first stage**. Installs menu `Milano` with Genel Bakış, Programlar, Bilet Hazırlığı.

## Security
- Capability `manage_woocommerce`; all displayed values escaped and URLs sanitized.
- Program query uses exact `mmc_programs.brand = 'Milano Sirki'`.
- Only a **single active** canonical MMC–MDG bridge is accepted and MDG event title must be Milano and not Madagaskar.
- Sessions drawn from the verified MDG event only; UTC converted via WordPress timezone for display.
- No mutations, endpoint handlers, legacy brand inference, order/QR cloning, or hooks on checkout/callback.
- Missing/ambiguous brand or linkage results in an explicit blocked display; no Madagaskar fallback.

## Limits
The menu does **not** yet edit programs, create tickets, publish sales, change template approval, send CRM messages or process PayTR. This is deliberately a scoped and auditable workspace shell. Future writes require dedicated brand-scoped forms, nonce/capability validation and real staging E2E, then separate PRs.

## Staging steps
1. Package this single plugin folder into a ZIP and install on **staging**, never on production before review.
2. Confirm that both `Madagaskar` and `Milano` administrator menus exist.
3. Visit `/wp-admin/admin.php?page=milano-brand-workspace`, `page=milano-programs`, and `page=milano-tickets`.
4. Confirm Yalova MMC #14 / MDG #24 and 18:00/19:30, 500 each; no unrelated program appears.
5. Confirm no order, product, template, payment or capacity record changes; WP_DEBUG log clean.
6. Rollback by deactivating only `milano-admin-workspace`; the separate existing Madagaskar menu remains untouched.

## Context
- PR246 stage-only code applied to isolated staging; unchanged live.
- Designer #28 created on staging, exact SHA256 `8ce4f316026e6ba707561b408e87d16e51ee71c91dd67d31b7695959741525cc`, not yet reviewed/approved for production plan.
- Venue #181 QR attachment #6047 and MDG #24 hero #6048 bound on staging.

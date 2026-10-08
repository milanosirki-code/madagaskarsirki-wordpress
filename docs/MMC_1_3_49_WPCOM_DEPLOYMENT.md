# MMC 1.3.49 WordPress.com deployment checkpoint

Status: **ARTIFACT ONLY — NOT YET DEPLOYED**

Purpose: deploy the refund-object scan fix from Issue #204 / PR #205.

## Release content

Target plugin directory:

`/wp-content/plugins/madagaskar-management-center`

Release version:

`1.3.49`

The artifact contains the complete canonical MMC plugin from:

`wp-content/plugins/madagaskar-management-center/`

It is not an overlay of only one or two files.

## Required pre-deploy checks

The workflow must pass:
- PHP lint for all MMC PHP files;
- exact 1.3.49 header/constant verification;
- collected gross regression;
- refund-object guard regression;
- artifact-vs-source diff.

## WordPress.com connection

Use a per-plugin GitHub Deployment connection:
- repository: `milanosirki-code/madagaskarsirki-wordpress`
- branch: `main`
- target: `/wp-content/plugins/madagaskar-management-center`
- automatic deployments: **OFF**
- workflow: `.github/workflows/wpcom-mmc-1349-deploy.yml`

Manual deployment only.

## Post-deploy verification

1. plugin inventory shows MMC 1.3.49 active;
2. system health remains 16 OK / 0 warning / 0 critical;
3. Program #7 Manisa integrity remains 0 critical and 6/6 mappings;
4. run one bounded Manisa event sales sync with lookback 30 days;
5. confirm no OrderRefund/get_payment_method exception;
6. confirm MDG and MMC remain reconciled at the same paid-order/ticket/person/net amount totals;
7. close Issue #204 only after all checks pass.

No refund, order, ticket or customer mutation is part of the deployment itself.

# Issue #57 — USKD standalone repository seed

Status: **SOURCE-ONLY / TARGET REPOSITORY NOT YET CREATED**

The GitHub connector still does not expose repository creation, so this stage prepares a deterministic import seed without deleting or moving the working sources from the Madagaskar repository.

## Target repository

`milanosirki-code/uskdernegi-wordpress`

## Export layout

The generated seed uses a standalone WordPress-oriented layout:

- `AGENTS.md`
- `README.md`
- `deployment-manifest.json`
- `repository-export-manifest.json`
- `backups/live-page-392.html`
- `backups/homepage-378-dynamic-events-ready.html`
- `backups/live-homepage-378-before-dynamic-events-2026-10-01.html`
- `backups/page-392-shortcode-ready.html`
- `wp-content/plugins/uskd-madagaskar-events/`
- `docs/USKD_MADAGASKAR_EVENTS_DEPLOYMENT.md`
- `tests/validate-uskd-madagaskar-events.py`
- `.github/workflows/build-uskd-madagaskar-events.yml`

The export rewrites only repository-relative paths in the manifest:
- plugin source path → `wp-content/plugins/uskd-madagaskar-events`
- rollback backup path → `backups/live-page-392.html`

The public data contract is unchanged:
- include: city, date, venue, sessions
- exclude: price, checkout link, WooCommerce order data

## CI artifact

Workflow:

`Build USKD standalone repository seed`

Artifact:
- `uskdernegi-wordpress-repo-seed.zip`
- SHA-256 checksum

The artifact also contains a per-file SHA-256 export manifest.

## Import sequence after the empty repository exists

1. Create an empty repository named `milanosirki-code/uskdernegi-wordpress`.
2. Import the generated seed as the initial source tree.
3. Run the included build workflow.
4. Confirm plugin PHP lint and deployment contract are green.
5. Compare the exported backup and plugin checksums against the seed manifest.
6. Only after the new repository is verified, remove the duplicated USKD sources from the Madagaskar repository in a separate PR.

This checkpoint does not change uskdernegi.org and does not delete any current source.

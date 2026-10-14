# Milano MDG production plan — 14 October 2026

Yalova staging MMC14/MDG24 has a Milano title, but its MDG plan still proposes Madagaskar Woo product names, Woo/Tickera slugs, support phone and Designer6. This focused change addresses issue245 without merging the general integration branches.

## Scope and behavior

The actual production-plan builder resolves ownership from exactly one active MMC–MDG bridge joined to its program. Missing tables/owner/brand, duplicate active rows, dangling program or database errors block readiness. There is no URL, title, first-row or legacy mapping fallback to grant a brand. Known Madagaskar programs retain their existing names, slugs, SKU scheme and phone; no existing object is renamed. New unlinked legacy drafts now require a canonical bridge before generation. This compatibility change must be reviewed during staging.

Milano product names and Woo/Tickera slugs use Milano; event terms use +90 536 358 33 81. An inconsistent Milano event title blocks readiness. Product capacities and 245/490 pricing follow the existing session/type records, without changing family normalization or payments.

Milano requires an explicit operator-reviewed Designer binding in `mdg_ticket_designer_brand_bases_v1['milano']`:

```php
array(
    'approved' => true,
    'template_id' => /* verified actual Milano Designer ID */,
    'template_sha256' => /* SHA256 of exact reviewed template_data bytes */,
    'candidate_id' => /* verified static venue QR element ID */,
)
```

No ID or approval is guessed or written by this patch. A named Milano template alone is insufficient: active status, Milano name, no Madagaskar name, exact approved content hash and the selected static venue QR element must match. Changed content requires renewed review. The admission QR element is excluded by the existing detector. Milano never reads shared Madagaskar QR bindings or falls back to a Madagaskar Designer. Madagaskar's shared-binding scan skips Milano bases to prevent reverse template contamination after future Milano QR binding.

The existing producer rebuilds the plan before any writes and refuses a nonready plan. No producer hook, nonce, capability, rollback, mapping write, existing order or payment callback is altered. The existing QR binder reads assigned product/variation Designer metadata; full binder and issuance integration still needs staging validation. This change does not secure unrelated manual binding workflows or prove brand catalogue/checkout isolation.

## Evidence and validation

Both installed live source files (production planner and draft producer) were read through WPVibe on 14 October. Their returned source matches current main after trimming transport trailing blank lines; the result is a source-content comparison, not a byte-exact hash assertion (WPVibe can redact secret patterns). No internal substantive difference was found in those two files. This does not establish equality of all MDG plugin sources. Live MDG plugin must not be overwritten wholesale.

`tests/mdg-production/brand-plan.php` executes the actual builder under isolated WordPress/database dependencies: Milano names/routes/phone, two local 18:00/19:30 sessions, 500 each, 245/490 prices, unknown/missing/duplicate/dangling owners, query failure/missing table, unapproved/changed/inactive/missing/wrong Designer, candidate/hash mismatch, title mismatch, missing hero, multiunit rejection, Madagaskar compatibility and reverse-binding protection. It verifies fixture sources remain unchanged and fails on unexpected write calls. These are fixture tests, not real payment or four-QR E2E.

Local PHP is unavailable. Validation runs in GitHub Actions `Milano MDG production brand` on PHP8.4, plus the repository's changed-PHP and plugin checks. Record actual CI results after completion; do not assume success from queued runs. Local `git diff --check` passed.

## Menu decision and next steps

The user's separate Milano administration-menu proposal is compatible with the shared backend. It should be a brand-scoped workspace over canonical program records, retaining the existing Madagaskar menu. A menu alone does not change producer defaults or create payment/template isolation. This PR changes only the underlying MDG plan; the Milano menu is not installed or implemented here.

Next: validate this focused patch on the existing isolated staging site; create/review the real Milano Designer and record its binding through a supported authenticated configuration path; complete MDG24 hero/address/maps/venue QR snapshot and factual allocation confirmation; only then create Woo/Tickera drafts. Validate catalogue/checkout guards, PayTR second-domain authorization, order/hold/callback and four real family QR tickets before controlled production rollout. Yalova sale remains closed.

## Rollback

Revert this one planner file to the captured prechange version. No migration or data write is performed by this patch. Do not delete approval options, drafts or mappings blindly if later stages have created them. Keep PR240 (MMC staging), PR239/241/243 (checkout/identity guards) separate.

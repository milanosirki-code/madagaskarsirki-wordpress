# Issue #166 — Kommo credential hygiene checkpoint

Status: **READ-ONLY AUDIT + SOURCE CI ONLY**

## Live findings — 2026-10-07

- Code Snippets #11 `MS Kommo Test` remains inactive and has `code_error=null`.
- Its token-like quoted literal is a non-secret placeholder; no credential value is recorded here.
- MMC Kommo configuration reports:
  - configured=true
  - token_source=MMC_KOMMO_TOKEN
  - uses_legacy_token=false
  - connected=true
  - http_ok=true
- Current MMC production path therefore does not depend on snippet #11.
- Bounded active-snippet review found no fixed Bearer credential literal in the inspected candidates.
- Active snippet #42 `MS Kurumsal Sayfası V5` still references the legacy constant `MS_KOMMO_TOKEN`, does not reference `MMC_KOMMO_TOKEN`, and contains no fixed Bearer literal. This is a migration dependency before fully removing the legacy constant.

## Repository findings

Current Kommo-related PHP sources use constants/dynamic secret sources rather than committed fixed Bearer strings. Several files intentionally retain an `MS_KOMMO_TOKEN` fallback for compatibility; no fixed secret assignment was detected by the bounded audit.

## CI guard

`tests/security/kommo-secret-hygiene.py` scans current plugin and code-snippet source roots for:

- fixed Bearer literals;
- long TOKEN/SECRET/API_KEY assignments;
- long secret-like `define(...)` literals.

The scanner prints only file/line/category, never a matched secret value. Known explicit placeholder words are ignored.

## Remaining external blocker

The legacy credential formerly exposed in snippet #11 must still be revoked/rotated in Kommo. Source removal does not revoke a credential.

Do not close Issue #166 until external revocation/rotation is confirmed.

# Issue #192 — Snippet #42 Kommo token migration plan

Status: **SOURCE-ONLY / NO LIVE WRITE**

Read-only capture of live Code Snippets #42 on 2026-10-07 confirms:

- the corporate-page Kommo section explicitly says it reuses `MS_KOMMO_TOKEN` and `MS_KOMMO_BASE_URL`;
- `mdg_corp_kommo_ready_v3()` currently requires `MS_KOMMO_TOKEN`;
- the request Authorization header currently uses `Bearer . MS_KOMMO_TOKEN`;
- no fixed Bearer credential literal is embedded in the active snippet;
- current central MMC Kommo configuration uses `MMC_KOMMO_TOKEN`, `uses_legacy_token=false`, while the non-secret base URL source remains `MS_KOMMO_BASE_URL`.

## Proposed minimal live diff

Change only token resolution for the corporate flow:

1. add `mdg_corp_kommo_token_v3()` that accepts **MMC_KOMMO_TOKEN only**;
2. make `mdg_corp_kommo_ready_v3()` require that helper instead of MS_KOMMO_TOKEN;
3. replace `Bearer . MS_KOMMO_TOKEN` with `Bearer . mdg_corp_kommo_token_v3()`;
4. leave `MS_KOMMO_BASE_URL` unchanged in this patch because it is not the exposed credential and remains the current configured base source;
5. preserve every corporate form, WooCommerce and Kommo field/pipeline behavior outside token resolution.

## Rollout safety

Before a live update:
- capture exact active #42 source hash/modified timestamp;
- confirm central Kommo diagnostics are connected/http_ok with MMC_KOMMO_TOKEN;
- apply only the token-resolution diff;
- read back #42 and verify active=true, code_error=null;
- test the corporate page in read-only/render mode;
- run Kommo connection diagnostics;
- do not create a test lead unless separately approved.

Only after this dependency is removed should legacy MS_KOMMO_TOKEN retirement/revocation proceed.

# Canonical Kommo source verification monitor

## Problem and supported boundary

Existing source refresh code used guessed AI PATCH/PUT routes that failed with404 in the previous audit. Current official Kommo AI public API method inventory documents adding sources and product synchronization, not text-source update. CRM integration-source PATCH endpoints are unrelated to AI knowledge sources and must not be substituted.

References: https://developers.kommo.com/reference/ai-api-methods and https://developers.kommo.com/reference/ai-features . The newer agent UI allows editing text, which was used successfully in the preceding live refresh. There is still no verified supported automatic text-update API. No speculative network mutations were retried.

## Implemented operational bridge

Snippet186 `Madagaskar Kommo Kanonik Kaynak Kontrolü V1`, admin-only. Source `docs/code-snippets/kommo-source-freshness.php`, draft PR230. Management Center submenu: Kommo Kaynak Kontrolü. Reuses existing MMC canonical functions, not legacy scraped page text.

Private management screen shows five current programs, copyable program/location text and the timestamp of last manual verification. A changed canonical text hash invalidates previous verification; Management Center warns on subsequent page loads. This is automatic local freshness comparison, not automatic reindexing and not continuous remote verification. Independent edits inside Kommo are outside its detection scope.

Confirmation requires administrator capability, WordPress nonce, both explicit source checkboxes and matching current hashes. If data changes after rendering the form, confirmation is rejected. Option `mdg_kommo_canonical_verified_v1` stores only hashes and verification timestamp (non-autoload). No customer records, runtime credentials or tokenized source URLs are displayed or stored.

## Validation

Latest implementation commit74c2affc86b2250fac6554a9b73ace03c0ed3833 passed Changed PHP Syntax run38260509237 and Kommo canonical source freshness run38260509273. Seven tests cover current/missing/incomplete snapshots, program-price changes, address changes and stale-form race. Live program text and location text were each read from Kommo UI and matched the monitor's canonical text exactly. Verification was saved at12Oct2026 17:05:32 Europe/Istanbul; screen showed `Son doğrulanan metinlerle eşleşiyor` and active program count5. The final version was applied to snippet186, reloaded successfully, and the obsolete pre-save warning was absent. Source read-back matches the repository version.

Rollback: deactivate snippet186. Current Kommo texts and existing automations are independent. Do not mark legacy source states current based on a different canonical hash.

## Current template gate

Live recheck after16:55 Istanbul: refund-started V2, cancellation V2 and change V2 remain `İncelemede`. Payment/support/refund-completed V2 remain `Onaylandı`. No pending template was activated and no customer messages were sent.

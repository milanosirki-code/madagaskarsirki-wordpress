# Issue #186 — Provider and message-template preview checkpoint

Status: **SOURCE-ONLY / NO DEPLOYMENT / SEND DISABLED**

## Live evidence

Read-only production inspection on 2026-10-07 found:

- Kommo CRM connection is configured and uses MMC_KOMMO_TOKEN without exposing the token value.
- The current Kommo integration has CRM lead/source operations and a ticket-link custom-field writer.
- No verified WhatsApp/chat message-send adapter was found in the committed/current integration path.
- wp_mmc_kommo_templates contains 52 seeded template rows for Programs 1–13.
- All current rows are status=expected; none is status=verified.
- Existing seeded keys are:
  - madagaskar_bilet_linki_v2
  - madagaskar_bilet_takip_v1
  - madagaskar_etkinlik_hatirlatma_v1
  - madagaskar_memnuniyet_v3
- There is no seeded/verified payment-reminder template yet.

Therefore Kommo/WhatsApp is only a **provider candidate**, not an approved send route.

## Draft template

Source-only draft key:

`madagaskar_odeme_hatirlatma_v1`

Status:

`draft_unverified`

Draft body:

Madagaskar Sirki  
Merhaba, bilet siparişinizin ödemesi tamamlanmamış görünüyor.  
Gösteri ve seansınız hâlâ satışa açıksa ödemenizi güvenli ödeme bağlantınız üzerinden tamamlayabilirsiniz.

Ödeme bağlantısı: {{PAYMENT_LINK}}

Ödeme yaptıysanız bu mesajı dikkate almayınız.  
Bilgi ve destek: WhatsApp +90 312 911 37 10

The template is not approved merely because it exists in source.

## Provider readiness rule

Preview marks provider_ready=true only when BOTH are externally verified:

1. payment reminder template verified/approved;
2. messaging adapter verified.

Even then:

`send_enabled=false`

until a separate owner approval explicitly enables a canary-send implementation.

No provider API call, message send, customer write, template-table write or WordPress deployment is part of this checkpoint.

# Kommo aggregate survey results — 2026-10-12

## Live native views

Created short-text lead field Anket Sonucu 2460856. Bot 118553 answer branches now store Anket: Çok memnun, Anket: Memnun, Anket: Takip gerekli, or Anket: Serbest yanıt, and add common tag anket_yanitlandi. Existing category tags, notes, complaint tasks and WhatsApp response logic preserved. Anket Sonucu and Anket Gösteri Tarih ve Saat added to the native lead list columns.

Saved native Kommo views:
- Anket – Tüm Sonuçlar (all five WooCommerce stages, common answered tag 245016, filter 206735411)
- Anket – Çok Memnun
- Anket – Memnun
- Anket – Takip Gerekli
- Anket – Serbest Yanıt

Additional operational views: initial Anket – Tüm Gönderimler (started-tag tracking, not delivery proof); Anket – Toplu Sonuçlar (active-stage answered view). Initial prefix text-filter trial was renamed Arşiv – Anket Metin Denemesi because Kommo text filters require complete value matching. Do not use it as the overall response count.

## Verification

Native saved all-results view reopened and contains one genuine response with Anket: Memnun. Category tag confirms the same response. Added common answered tag to that already-answered record and reloaded to verify persisted tag and tag-history note. No invented response or customer communication created. Other category tags had zero assignments at this check. Results update on response processing; started or sent counts must not be treated as answered counts.

All-results verified URL:
https://milanosirki.kommo.com/leads/list/pipeline/14297695/?filter_date_switch=created&filter%5Bpipe%5D%5B14297695%5D%5B0%5D=110416819&filter%5Bpipe%5D%5B14297695%5D%5B1%5D=110416823&filter%5Bpipe%5D%5B14297695%5D%5B2%5D=110416827&filter%5Bpipe%5D%5B14297695%5D%5B3%5D=142&filter%5Bpipe%5D%5B14297695%5D%5B4%5D=143&filter%5Btags_logic%5D=or&tag%5B0%5D=245016&useFilter=y&sel=206735411

## Rollback

Restore preceding survey bot JSON to remove result-field/common-tag writes; remove or rename newly created saved filters through Kommo. Preserve existing response tags/notes and genuine customer results. No payment or order status changed.

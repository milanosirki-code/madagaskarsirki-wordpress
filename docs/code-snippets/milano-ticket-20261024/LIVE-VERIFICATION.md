# Controlled live rollout — 2026-10-24

All six WordPress content/edit patches applied successfully (one match each) to page 118.

- Darıca active city tab computes display:none; past show archive labels completion.
- Derince 12:00 started state, amber heading rgb(255,240,213); 14:00 and 16:00 upcoming blue rgb(234,242,255).
- Quick navigation shows all three times with state labels.
- Map button background rgb(16,30,67), text white; route button inverse high contrast; canonical destinations unchanged.
- Quantity input 1 adult -> ₺490,00; plus button -> 2 adults, ₺980,00; reset to 0 after test. No form submitted, cart/payment/order not created.
- All six actually published scripts compile in Node VM; archive contains no encoded && operator.
- Local regression checks pass (date formats, invalid-date fallback, time boundaries, block balance, byte-identical original forms).

Mobile breakpoint styling included; a separate mobile browser viewport was not tested.

Rollback: reverse patches in reverse order or restore WordPress page revision. The draft PR keeps source and rollback material; no merge performed.

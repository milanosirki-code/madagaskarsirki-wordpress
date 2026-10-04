# Stage 2 production evidence — 2026-10-03

Read-only source snapshots and sanitized diagnostics for draft PR #111. These files are **not a deploy bundle**, not a current customer program and never a reason to overwrite working production code. Dynamic event/program data must be queried live.

Checkout product2278/session60 → isolated guest add-item201 → cart200 → checkout GET200 → remove-item200 → empty cart200. PayTR appears in available methods. order_id=0; no checkout POST, order, payment, ticket, QR or CRM message created. Do not mark the entire checkout/payment/ticket baseline healthy.

HTTP500 on #117 deactivation and #119 activation is core REST _fields filtering of a Code Snippets object. Exact TypeError/line/frames and successful no-fields workaround are in verification.json. Action response {} requires separate target GET readback. No core/vendor/MMC patch was deployed.

42 production snippets remain active. #117 and temporary #119 are passive, code_error null. #119 source is docs/code-snippets/stage2-runtime-audit.php (last syntax-tested commit cad39f9347f7da06c9087418d34464d19ae52581). Updating an already loaded active diagnostic triggered an eval redeclaration guard once; it was saved passive and activated in a fresh request. No production snippet was modified.

119/126 plugin files are byte-exact main; two further files are trim-exact with whitespace differences. Main is ahead for AI0.7.0 and family1.1.3; preserve main and live versions separately until tested deployment. Active MDG V4 transition source is archived because it has no same-path main source. Snippets15/30/103 exactly match PR107 after-captures. V2 MMC Kommo snippet110 is a separate current owner and is archived without replacing the legacy unified-source file.

All .php.txt files preserve runtime sources for review, not autoload. Family1.1.2 remains 2adult+2child, four units. No token, password, raw log, order key, QR code, cookie, customer data or live tokenized URL is included.

P1 finding tracked in #112: failed unpaid HPOS orders have existing published ticket instances; generation source lacks a paid-status gate, while download gating is separate. No historical instances were deleted. Staging/fixture verification and safe adapter design are required before changing ticket generation.

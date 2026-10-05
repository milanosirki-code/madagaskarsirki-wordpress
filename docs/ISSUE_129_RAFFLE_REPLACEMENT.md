# Sirk Çekilişi — manual replacement slots (Issue #129)

## Located live owners

- Active plugin `madagaskar-cekilis/madagaskar-cekilis.php`, version 3.3.0; class `Madagaskar_Cekilis_V2`; menu slug `madagaskar-cekilis`, callback `render_admin()`.
- Replacement owner: active snippet #106, original `MCKR_VERSION=1.0.0`; exact source already archived at `docs/code-snippets/production/snippet-106.php.txt` (historical snapshot stays immutable).
- Reviewed candidate `docs/code-snippets/madagaskar-raffle-replacement.php`, version 1.1.0; deploy its body without the PHP opening tag to #106 only. Base plugin/Meta code is unchanged.
- Admin forms are server-rendered. No draw JavaScript or REST route is involved. Existing POST actions: `mck2_sync_campaign`, `mck2_draw_campaign`, `mck2_verify_result`; replacement action `mckr_redraw`.

## Existing behavior and identity

`Admin form → admin-post → Madagaskar_Cekilis_V2::sync_campaign_action() → sync_campaign() → graph.instagram.com/v26.0 media/comments → evaluate_comments() → wp_mck_comments`.

The campaign's media ID selects the post; comments use a unique campaign/comment-ID key. Mention count and repeated-text filters determine eligibility. `one_user_one_entry` determines whether multiple valid comments add chances; `one_user_one_win` constrains original selection. Follow/like verification is manual because the API does not reliably expose per-participant proof. The initial draw freezes the pool; replacement uses only that campaign's stored valid comments and never refetches Meta.

`draw_campaign()` uses `random_bytes(32)` and HMAC ranking; replacement retains existing SHA-256 ranking derived from that cryptographically generated campaign seed. No legacy rand()/mt_rand() path is introduced.

Current results persist in `wp_mck_draws` by campaign/type/position; verification is pending/verified/disqualified. Campaign metadata and original seed/hash live in `wp_mck_campaigns`. `wp_mck_redraw_audit` retains old/new occupants, disqualified status/note, seed/score, admin ID and time. The available participant identity is username plus comment ID; immutable Instagram user ID is not stored by the existing model. Display names are not used.

## Root cause and isolated correction

Original active #106 executes `current_screen(GET) → unresolved_draws(5) → redraw_one()` automatically. Its manual link also uses GET. Historical audit occupants are not excluded, and valid-user exclusion depends on a campaign rule. Thus a page reload changes results and old occupants can re-enter through another comment.

Corrected: GET renders only read-only controls/history; explicit capability/nonce-protected POST supplies slot IDs and expected occupant comment IDs. The service locks the campaign first, validates every disqualified occupant, and replaces only those slots under one transaction. InnoDB is required and verified. Single-slot compatibility wrapper delegates to the atomic service.

Every current and historical selected username/comment ID is excluded regardless of the original one-user-win setting. Existing comment weighting/entry rules remain. As each replacement is persisted within the transaction, later slots cannot select it again. If any slot lacks an eligible candidate or any audit/result write fails, all replacements roll back; exact error: `Yeterli yeni uygun aday bulunamadı.`

One campaign gets an N-person button plus individual buttons when N>1. Valid/verified occupants are never touched. Old disqualified occupants remain visible in admin replacement history. Double-submit/stale occupants are rejected; no automatic GET draw remains. Original verification and initial-draw behavior are preserved; no separate audit of every original verification transition is added.

## Evidence and tests

- Before: 7 campaigns, 2590 comments, 14 result slots, 1 replacement audit row. Only counts and hashes are captured; no participant names/comments published.
- All four live raffle tables are InnoDB.
- PHP 8.4 syntax PASS; actual-service fixture test has 34 assertions: 3/1 and 5/2 replacements; old and verified usernames excluded even with multiple comments; history excluded on later replacements; insufficient pool and audit failure rollback; stale/double/incomplete snapshots; persisted reload; unauthorized/GET/bad nonce rejection; read-only render and visible manual POST/history.
- No live draw or verification action is used for testing. Synthetic fixture DB is isolated and performs no Meta calls.
- Deploy after branch CI, exact backup/hash and PR review; restore old #106 body on any regression. Read-back, actual admin rendering under a pre-SQL write guard, full raffle-table fingerprints, health and code_error must pass.

## Scope

PR #128 documentation merged; main at start `dd466cf475ee09ed2289037ceaa1d0560462fbe2`. Issue #82 parked OPEN. No Kommo source, sale, order, school field or real raffle result changes authorized for tests.

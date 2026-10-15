<?php
/**
 * Offline, read-only Milano Kocaeli staging manifest contract.
 * This validates the *plan*, not actual WordPress/WooCommerce/PayTR state.
 */
declare(strict_types=1);

$path = dirname(__DIR__) . '/plans/kocaeli-20261021-24-preparation.json';
$plan = json_decode((string) file_get_contents($path), true);
if (!is_array($plan)) {
    fwrite(STDERR, "FAIL: cannot read preparation manifest\n");
    exit(1);
}
function milano_kocaeli_validate(array $p): array {
    $errors = [];
    $require = static function ($condition, string $reason) use (&$errors) {
        if (!$condition) { $errors[] = $reason; }
    };
    $require(($p['brand'] ?? '') === 'Milano Sirki', 'brand must be Milano');
    $require(($p['prices_try']['child_3_12'] ?? null) === 245, 'child price');
    $require(($p['prices_try']['adult_13_plus'] ?? null) === 490, 'adult price');
    $require(($p['prices_try']['family_two_adults_two_children'] ?? null) === 1100, 'family price');
    $require(($p['family']['tickets'] ?? null) === 4, 'family admission quantity');
    $require(($p['family']['capacity_units'] ?? null) === 4, 'family capacity units');
    $e = $p['events'] ?? [];
    $require(is_array($e) && count($e) === 3, 'three separate owner events');
    $sessionIds = [];
    $venueIds = [];
    $eventIds = [];
    foreach ($e as $event) {
        $require(($event['capacity_per_session'] ?? null) === 500, '500 per session');
        $require(($event['venue_source'] ?? '') === 'new_poster_exact_canonical_mdg_venue', 'poster-exact venue');
        $venueIds[] = (int) ($event['assigned_venue_id'] ?? 0);
        $eventId = (int) ($event['staging_mdg_event_id'] ?? 0);
        $eventIds[] = $eventId;
        foreach (($event['staging_mdg_session_ids'] ?? []) as $id) { $sessionIds[] = (int) $id; }
    }
    sort($sessionIds);
    $require($sessionIds === [152,153,154,155,156,157,158], 'seven exact, unique session IDs');
    sort($venueIds);
    $require($venueIds === [196,197,198], 'three venue records');
    sort($eventIds);
    $require($eventIds === [25,26,27], 'three unique MDG drafts');
    $staging = $p['staging'] ?? [];
    $require(($staging['mdg_status'] ?? '') === 'draft', 'MDG draft status');
    $products = $staging['woo_draft_products'] ?? [];
    $require(is_array($products) && count($products) === 7, 'seven Woo variable parents');
    $wcIds = [];
    $wcSessions = [];
    foreach ($products as $product) {
        $wcSessions[] = (int) ($product['mdg_session_id'] ?? 0);
        foreach (['parent_product_id','child_variation_id','adult_variation_id'] as $field) {
            $wcIds[] = (int) ($product[$field] ?? 0);
        }
        $require(($product['child_mdg_ticket_type_id'] ?? 0) > 0, 'child type link');
        $require(($product['adult_mdg_ticket_type_id'] ?? 0) > 0, 'adult type link');
    }
    sort($wcSessions);
    $require($wcSessions === $sessionIds, 'Woo-to-MDG intended session set');
    $require(count(array_unique($wcIds)) === 21 && min($wcIds ?: [0]) > 0, '21 distinct WC product/variation IDs');
    $tickets = $staging['tickera_event_draft_posts'] ?? [];
    $require(is_array($tickets) && count($tickets) === 3, 'three Tickera draft posts');
    $tcEvents = array_map(static fn($v) => (int) ($v['mdg_event_id'] ?? 0), $tickets);
    sort($tcEvents);
    $require($tcEvents === $eventIds, 'Tickera draft event ownership plan');
    $tcIds = array_map(static fn($v) => (int) ($v['tickera_event_post_id'] ?? 0), $tickets);
    $require(count(array_unique($tcIds)) === 3 && min($tcIds ?: [0]) > 0, 'unique Tickera draft posts');
    $gates = $p['launch_gates'] ?? [];
    $required = ['central_mmc_brand_aware_create_proven','venue_identity_verified','capacity_user_approved',
        'three_owner_unique_mdg_links','woo_and_tickera_products_verified','per_session_four_ticket_qr_staging_pass',
        'paytr_checkout_callback_verified','brand_redirect_and_return_verified'];
    if (!empty($gates['public_sales_enabled'])) {
        foreach ($required as $key) { $require(($gates[$key] ?? false) === true, "live sale prematurely enabled: {$key}"); }
    }
    $require(($p['status'] ?? '') !== 'ONSALE' || !empty($gates['public_sales_enabled']), 'invalid on-sale status');
    return $errors;
}
$issues = milano_kocaeli_validate($plan);
if ($issues) { fwrite(STDERR, "FAIL: " . implode('; ', $issues) . "\n"); exit(1); }
$bad = $plan; $bad['launch_gates']['public_sales_enabled'] = true;
if (!milano_kocaeli_validate($bad)) { fwrite(STDERR, "FAIL: prematurely enabled sales allowed\n"); exit(1); }
$bad = $plan; $bad['staging']['woo_draft_products'][1]['child_variation_id'] =
    $bad['staging']['woo_draft_products'][0]['child_variation_id'];
if (!milano_kocaeli_validate($bad)) { fwrite(STDERR, "FAIL: duplicate WC variation ID allowed\n"); exit(1); }
$bad = $plan; $bad['family']['capacity_units'] = 1;
if (!milano_kocaeli_validate($bad)) { fwrite(STDERR, "FAIL: under-counted family capacity allowed\n"); exit(1); }
echo "PASS Milano Kocaeli staging manifest — 3 events, 7 sessions, 21 WC IDs, 3 Tickera drafts, fail-closed sales\n";

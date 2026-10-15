<?php
/**
 * Milano Yalova 2026-10-22 fixture contract: an owner-confirmed preparation
 * record, NOT a live ticket/order/inventory operation.
 */
error_reporting( E_ALL );
function ensure( $condition, $msg ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL YALOVA: {$msg}\n" );
        exit( 1 );
    }
}
$file = dirname( __DIR__ ) . '/fixtures/yalova-20261022.json';
$raw = file_get_contents( $file );
ensure( is_string( $raw ), 'fixture missing' );
$data = json_decode( $raw, true );
ensure( is_array( $data ) && JSON_ERROR_NONE === json_last_error(), 'invalid JSON' );
ensure( ( $data['schema_version'] ?? null ) === 1, 'unknown schema' );
ensure( ( $data['stage'] ?? '' ) === 'staging_input_only', 'fixture accidentally deployed' );
ensure( ( $data['brand'] ?? '' ) === 'Milano Sirki', 'not Milano-owned' );
ensure( ( $data['site_origin'] ?? '' ) === 'https://milanosirki.com', 'wrong sales origin' );
ensure( ( $data['city'] ?? '' ) === 'Yalova', 'wrong city' );
ensure( ( $data['district'] ?? '' ) === 'Merkez', 'wrong district' );
ensure( ( $data['venue_name'] ?? '' ) === '90. Yıl Kapalı Spor Salonu', 'wrong venue' );
ensure( ( $data['venue_master_candidate_id'] ?? 0 ) === 181, 'venue master candidate changed' );
ensure( ( $data['venue_address'] ?? '' ) === 'Bahçelievler Mahallesi, Turhan Koçal Caddesi No:13, Yalova', 'incorrect address' );
ensure( ( $data['maps_url'] ?? '' ) === 'https://maps.app.goo.gl/RnN2pZFTMoMD2W237?g_st=ac', 'owner-provided map link changed' );
ensure( ( $data['show_date'] ?? '' ) === '2026-10-22', 'wrong event date' );
ensure( ( $data['timezone'] ?? '' ) === 'Europe/Istanbul', 'wrong event timezone' );
ensure( ( $data['currency'] ?? '' ) === 'TRY', 'prices must be in lira' );
ensure( ( $data['sales_enabled'] ?? null ) === false, 'sales enabled before test approval' );
ensure( ( $data['checkout_enabled'] ?? null ) === false, 'checkout enabled before test approval' );
ensure( ( $data['requires_live_brand_aware_mmc_upgrade'] ?? null ) === true, 'brand migration gate missing' );
ensure( ( $data['requires_paytr_domain_approval'] ?? null ) === true, 'PayTR domain gate missing' );
ensure( ( $data['requires_canonical_event_id'] ?? null ) === true, 'canonical ID gate missing' );
ensure( ( $data['seating_mode'] ?? '' ) === 'free', 'must be unnumbered free seating' );
ensure( ( $data['seat_numbers_required'] ?? null ) === false, 'ticket cannot require seat number' );
ensure( ( $data['venue_doors_open_before_minutes'] ?? null ) === 30, 'doors should open around 30 minutes before start' );
ensure( ( $data['show_duration_minutes'] ?? null ) === 60, 'Madagaskar-standard show duration is around 60 minutes' );
$policy = $data['ticket_rules'] ?? null;
ensure( is_array( $policy ), 'operational rules missing' );
ensure( ( $policy['infant']['age_from'] ?? null ) === 0
    && ( $policy['infant']['age_through'] ?? null ) === 2
    && ( $policy['infant']['price_try'] ?? null ) === 0
    && ( $policy['infant']['accompanied_by_adult'] ?? null ) === true,
    '0-2 free infant policy mismatch' );
ensure( ( $policy['infant']['physical_headcount_control'] ?? '' ) === 'requires_staging_validation',
    'physical occupancy and free infants must be validated before launch' );
ensure( ( $policy['child']['age_from'] ?? null ) === 3
    && ( $policy['child']['age_through'] ?? null ) === 12
    && ( $policy['child']['ticket_key'] ?? '' ) === 'child'
    && ( $policy['child']['accompanied_by_adult'] ?? null ) === true,
    '3-12 child policy mismatch' );
ensure( ( $policy['adult']['age_from'] ?? null ) === 13
    && ( $policy['adult']['ticket_key'] ?? '' ) === 'adult',
    '13+ adult policy mismatch' );
ensure( ( $policy['checkin']['method'] ?? '' ) === 'QR'
    && ( $policy['checkin']['phone_ticket_display_allowed'] ?? null ) === true
    && ( $policy['checkin']['valid_only_for_selected_session'] ?? null ) === true
    && ( $policy['checkin']['qr_generation'] ?? '' ) === 'only_after_verified_payment',
    'QR must be created after verified payment for selected session only' );
ensure( ( $policy['seats']['mode'] ?? '' ) === 'free'
    && ( $policy['seats']['reserved_seats'] ?? null ) === false
    && ( $policy['seats']['seat_number_on_ticket'] ?? null ) === false,
    'free seating/unnumbered ticket mismatch' );
$family_entry = $data['family_2_2_fulfillment'] ?? null;
ensure( is_array( $family_entry )
    && ( $family_entry['sale_quantity'] ?? null ) === 1
    && ( $family_entry['physical_admissions'] ?? null ) === 4
    && ( $family_entry['qr_tickets'] ?? null ) === 4
    && ( $family_entry['status'] ?? '' ) === 'staging_contract_only'
    && ( $family_entry['must_not_copy_madagaskar_event_settings'] ?? null ) === true,
    'family fulfillment must be four individual QR tickets and brand isolated' );
ensure( ( $family_entry['component_tickets'] ?? null ) === array(
    array( 'ticket_key' => 'adult', 'quantity' => 2 ),
    array( 'ticket_key' => 'child', 'quantity' => 2 ),
), '2 adult and 2 child real ticket lines required' );

$sessions = $data['sessions'] ?? null;
ensure( is_array( $sessions ) && count( $sessions ) === 2, 'exactly two Yalova sessions required' );
$times = array();
$capacity_total = 0;
foreach ( $sessions as $session ) {
    ensure( is_array( $session ), 'invalid session data' );
    ensure( is_string( $session['local_start'] ?? null ), 'missing local start' );
    $local = $session['local_start'];
    ensure( preg_match( '/^2026-10-22T(18:00|19:30):00$/', $local ) === 1, 'wrong local session time' );
    ensure( ! isset( $times[ $local ] ), 'duplicate session clock time' );
    $times[ $local ] = true;
    ensure( ( $session['capacity_admissions'] ?? null ) === 500, 'must reserve 500 human admissions per session' );
    $capacity_total += $session['capacity_admissions'];
}
ensure( count( $times ) === 2 && $capacity_total === 1000, 'two sessions are independent 500-capacity pools' );

$p = $data['ticket_prices'] ?? null;
ensure( is_array( $p ) && count( $p ) === 3, 'must have precisely three user-approved prices' );
ensure( $p['adult'] === 490 && $p['child'] === 245 && $p['family_2_2'] === 1100, 'Yalova ticket prices changed' );
foreach ( $p as $value ) {
    ensure( is_int( $value ) && $value > 0, 'price must be a positive integer TRY amount' );
}
$family = $data['family_2_2_admissions'] ?? null;
ensure( is_array( $family ), 'family composition missing' );
ensure( $family['adult'] === 2 && $family['child'] === 2 && $family['total'] === 4, 'family package must grant four distinct admissions' );
ensure( $family['adult'] + $family['child'] === $family['total'], 'family count mismatch' );

// Package is one sale line but four tickets/QR check-in rights and four
// capacity admissions. An all-family session admits at most 125 packages.
ensure( intdiv( 500, $family['total'] ) === 125, 'wrong family package capacity' );
ensure( 125 * $family['total'] === 500, '125 packages should fill one session' );
ensure( 126 * $family['total'] > 500, '126 packages must be rejected by atomic capacity hold' );
ensure( 500 - ( 100 * $family['total'] + 100 ) === 0, 'mix of 100 family + 100 single admissions must exhaust capacity' );

$forbidden = array( 'paytr_key', 'merchant_key', 'secret', 'shared_feed_token',
    'wc_order_id', 'canonical_mdg_event_id', 'tickera_ticket_id', 'live_checkout_url' );
foreach ( $forbidden as $key ) {
    ensure( ! array_key_exists( $key, $data ), 'fixture must not contain real secret/order/checkout IDs: ' . $key );
}
echo "PASS Yalova owner-confirmed event configuration: 500+500 seats, 490/245/1100 TRY, family four QR rights, sales closed\n";

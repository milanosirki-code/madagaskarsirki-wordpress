<?php
// Standalone fixtures: no WordPress bootstrap, database, network or credentials.
require __DIR__ . '/../../wp-content/plugins/madagaskar-web-assistant-core/includes/class-core.php';
require __DIR__ . '/../../wp-content/plugins/madagaskar-web-assistant-core/includes/class-catalogue.php';
require __DIR__ . '/../../wp-content/plugins/madagaskar-web-assistant-core/includes/class-wordpress-source.php';
require __DIR__ . '/../../wp-content/plugins/madagaskar-web-assistant-core/includes/class-service.php';

use Madagaskar\WebAssistant\Catalogue;
use Madagaskar\WebAssistant\Failure;
use Madagaskar\WebAssistant\Quote;
use Madagaskar\WebAssistant\Party;
use Madagaskar\WebAssistant\Service;
use Madagaskar\WebAssistant\Value;
use Madagaskar\WebAssistant\WordPressSource;

set_error_handler( static function( $severity, $message, $file, $line ) {
    throw new ErrorException( $message, 0, $severity, $file, $line );
} );

$assertions = 0;
function check( $condition, $label ) {
    global $assertions;
    $assertions++;
    if ( ! $condition ) { throw new RuntimeException( 'FAILED: ' . $label ); }
}
function fails( callable $call, $reason ) {
    try { $call(); } catch ( Failure $e ) { check( $e->getMessage() === $reason, 'expected ' . $reason . ', got ' . $e->getMessage() ); return; }
    throw new RuntimeException( 'Expected failure: ' . $reason );
}
function bundle() {
    $bridge = array( 'program_id' => 26, 'mmc_event_id' => 26, 'mdg_event_id' => 36 );
    return array(
        'program' => array( 'id' => 26, 'brand' => Catalogue::BRAND, 'status' => 'sales_open',
            'planned_date' => '2026-11-12', 'city' => 'Tekirdağ', 'district' => 'Çerkezköy' ),
        'mmc_event' => array( 'id' => 26, 'program_id' => 26, 'status' => 'sales_open', 'event_date' => '2026-11-12' ),
        'mdg_event' => array( 'id' => 36, 'status' => 'onsale', 'title' => 'Fixture', 'sale_start' => null, 'sale_end' => null ),
        'program_bridges' => array( $bridge ), 'event_bridges' => array( $bridge ),
        'venue_matches' => true, 'venue' => array( 'name' => 'Fixture salon', 'address' => 'Fixture adres' ),
        'ticket_url' => 'https://madagaskarsirki.com/etkinlik/fixture-2026/',
        'mmc_session_times' => array( '2026-11-12 18:00:00' ),
        'family_conflict' => false, 'family' => array( 'available' => true, 'price_minor' => 110000, 'source' => 'fixture' ),
        'sessions' => array( array( 'id' => 223, 'event_id' => 36, 'status' => 'onsale', 'start_at' => '2026-11-12 15:00:00',
            'sale_start' => null, 'sale_end' => null, 'native_closed' => false, 'available_units' => 500,
            'product_ready' => true, 'parent_max_quantity' => 40, 'tickera_event_id' => 9121, 'tickets' => array(
                array( 'code' => 'ADULT', 'active' => true, 'price' => '490.00', 'capacity_units' => 1,
                    'variation_id' => 9125, 'variation_ready' => true, 'wc_price' => '490', 'max_quantity' => 20 ),
                array( 'code' => 'CHILD', 'active' => true, 'price' => '245.00', 'capacity_units' => 1,
                    'variation_id' => 9124, 'variation_ready' => true, 'wc_price' => '245.00', 'max_quantity' => 20 ),
            ) ) ),
    );
}
$now = Value::timestamp( '2026-11-05 06:00:00' );
function offer( $a = '490', $c = '245', $f = 110000 ) {
    global $now;
    $b = bundle();
    $b['sessions'][0]['tickets'][0]['price'] = $a; $b['sessions'][0]['tickets'][0]['wc_price'] = $a;
    $b['sessions'][0]['tickets'][1]['price'] = $c; $b['sessions'][0]['tickets'][1]['wc_price'] = $c;
    $b['family']['price_minor'] = $f;
    return Catalogue::offers( $b, $now )['offers'][0];
}
function quote( $a, $c, $o = null, $infants = 0 ) {
    global $now;
    return Quote::calculate( $o ?? offer(), array( 'adults' => $a, 'children' => $c, 'infants' => $infants ), $now );
}

check( Value::minor( '585.05' ) === 58505 && Value::minor( '0.5' ) === 50, 'decimal cents' );
foreach ( array( '1e3', '10,50', '-1', 'NaN', '1.001', '', '01.00', 12.5 ) as $bad ) { fails( static function() use ( $bad ) { Value::minor( $bad ); }, 'INVALID_MONEY' ); }
fails( static function() { Value::timestamp( '2026-02-30 12:00:00' ); }, 'INVALID_DATE' );
check( Party::from_ages( array( 30, 2, 3, 12, 13 ) ) === array( 'adults' => 2, 'children' => 2, 'infants' => 1 ), 'age boundaries 2/3/12/13' );
fails( static function() { Party::from_ages( array( 5, 12 ) ); }, 'INVALID_INTEGER' );
fails( static function() { Party::from_ages( array( 30, '5' ) ); }, 'INVALID_INTEGER' );
check( offer()['starts_at'] === '2026-11-12T18:00:00+03:00', 'UTC to Istanbul' );
foreach ( array( array( 1, 2, 98000 ), array( 1, 3, 110000 ), array( 2, 1, 110000 ), array( 2, 2, 110000 ), array( 3, 0, 147000 ) ) as $case ) {
    check( quote( $case[0], $case[1] )['recommended']['total_minor'] === $case[2], 'standard family example' );
}
$izmir = offer( '585', '290', 130000 );
check( quote( 2, 3, $izmir )['recommended']['total_minor'] === 159000, 'Izmir 2+3' );
check( quote( 2, 4, $izmir )['recommended']['total_minor'] === 188000, 'Izmir 2+4' );
check( quote( 2, 4, $izmir )['recommended']['savings_minor'] === 45000, 'Izmir exact savings' );
check( quote( 2, 2, $izmir, 2 )['attendance_count'] === 6, 'infants counted as attendance' );
check( quote( 2, 2, $izmir, 2 )['recommended']['capacity_units'] === 4, 'lap infants do not consume paid capacity' );
check( quote( 2, 1 )['recommended']['capacity_units'] === 4, 'partially used package still reserves four' );
$small = offer(); $small['available_units'] = 3;
check( quote( 2, 1, $small )['recommended']['total_minor'] === 122500, 'capacity can make singles preferable' );
$small['available_units'] = 2;
fails( static function() use ( $small ) { quote( 2, 1, $small ); }, 'NO_FEASIBLE_COMBINATION' );
$parent_limited = offer(); $parent_limited['parent_max_quantity'] = 3;
check( quote( 2, 1, $parent_limited )['recommended']['family_quantity'] === 0, 'shared parent stock bounds package expansion' );
$off = offer(); $off['family']['available'] = false;
check( quote( 2, 2, $off )['recommended']['total_minor'] === 147000, 'disabled family' );
$limited = offer(); $limited['tickets']['child']['max_quantity'] = 1;
fails( static function() use ( $limited ) { quote( 2, 2, $limited ); }, 'NO_FEASIBLE_COMBINATION' );
check( quote( 20, 20 )['recommended']['family_quantity'] === 10, 'native twenty-per-component limit' );
$tie = offer( '490', '245', 147000 );
check( quote( 2, 2, $tie )['recommended']['family_quantity'] === 0, 'equal cost prefers no package' );
$cents = offer( '490.03', '245.07', 110011 );
check( quote( 2, 3, $cents )['recommended']['total_minor'] === 134518, 'integer cents throughout' );
foreach ( array( -1, '2', 2.1, true, null, 21 ) as $bad ) { fails( static function() use ( $bad, $now ) { Quote::calculate( offer(), array( 'adults' => $bad, 'children' => 0 ), $now ); }, 'INVALID_INTEGER' ); }
fails( static function() { quote( 0, 2 ); }, 'INVALID_INTEGER' );
fails( static function() use ( $now ) { Quote::calculate( offer(), array( 'adults' => 2, 'children' => 2, 'invitation' => true ), $now ); }, 'UNSUPPORTED_PARTY_FIELD' );
fails( static function() use ( $now ) { Quote::calculate( offer(), array( 'adults' => 2, 'children' => 2 ), $now + 60 ); }, 'STALE_OFFER' );

// Independent recursive oracle: allocate actual people to individual tickets
// or each allowed package composition, then calculate issuance/stock/capacity.
function oracle( $adults, $children, array $o ) {
    $best = PHP_INT_MAX; $seen = array();
    $walk = function( $a, $c, $ia, $ic, $f, $cost ) use ( &$walk, &$best, &$seen, $o ) {
        if ( $ia > $o['tickets']['adult']['max_quantity'] || $ic > $o['tickets']['child']['max_quantity'] || $ia + $ic > min( $o['available_units'], $o['parent_max_quantity'] ) || $f > 10 ) { return; }
        if ( ! $a && ! $c ) { $best = min( $best, $cost ); return; }
        $key = implode( ':', array( $a, $c, $ia, $ic, $f ) );
        if ( isset( $seen[$key] ) && $seen[$key] <= $cost ) { return; }
        $seen[$key] = $cost;
        if ( $a ) { $walk( $a - 1, $c, $ia + 1, $ic, $f, $cost + $o['tickets']['adult']['price_minor'] ); }
        if ( $c ) { $walk( $a, $c - 1, $ia, $ic + 1, $f, $cost + $o['tickets']['child']['price_minor'] ); }
        if ( $o['family']['available'] ) {
            foreach ( array( 1, 2 ) as $pa ) {
                for ( $pc = 0; $pc <= 4 - $pa; $pc++ ) {
                    if ( $pa <= $a && $pc <= $c ) { $walk( $a - $pa, $c - $pc, $ia + 2, $ic + 2, $f + 1, $cost + $o['family']['price_minor'] ); }
                }
            }
        }
    };
    $walk( $adults, $children, 0, 0, 0, 0 );
    return $best;
}
foreach ( array( offer(), $izmir, $cents ) as $o ) {
    for ( $a = 1; $a <= 4; $a++ ) {
        for ( $c = 0; $c <= 6; $c++ ) {
            foreach ( array( 3, 7, 20 ) as $cap ) {
                $o['available_units'] = $cap;
                $expected = oracle( $a, $c, $o );
                if ( $expected === PHP_INT_MAX ) { fails( static function() use ( $a, $c, $o ) { quote( $a, $c, $o ); }, 'NO_FEASIBLE_COMBINATION' ); }
                else { check( quote( $a, $c, $o )['recommended']['total_minor'] === $expected, 'oracle optimality' ); }
            }
        }
    }
}

foreach ( array( 'WRONG_BRAND', 'STATE_CONFLICT', 'AMBIGUOUS_BRIDGE', 'BRIDGE_ID_MISMATCH', 'VENUE_MISMATCH', 'UNVERIFIED_URL' ) as $reason ) {
    $b = bundle();
    if ( $reason === 'WRONG_BRAND' ) { $b['program']['brand'] = 'Milano Sirki'; }
    if ( $reason === 'STATE_CONFLICT' ) { $b['mmc_event']['status'] = 'configured'; }
    if ( $reason === 'AMBIGUOUS_BRIDGE' ) { $b['event_bridges'][] = $b['event_bridges'][0]; }
    if ( $reason === 'BRIDGE_ID_MISMATCH' ) { $b['mmc_event']['program_id'] = 99; }
    if ( $reason === 'VENUE_MISMATCH' ) { $b['venue_matches'] = false; }
    if ( $reason === 'UNVERIFIED_URL' ) { $b['ticket_url'] = 'https://evil.example/etkinlik/test/'; }
    fails( static function() use ( $b, $now ) { Catalogue::offers( $b, $now ); }, $reason );
}
foreach ( array( 'SESSION_CLOSED', 'SESSION_TIME_MISMATCH', 'TICKET_PRICE_OR_MAPPING_CONFLICT', 'SOLD_OUT', 'PRODUCT_NOT_READY', 'UNSUPPORTED_TICKET_CATALOGUE' ) as $reason ) {
    $b = bundle();
    if ( $reason === 'SESSION_CLOSED' ) { $b['sessions'][0]['native_closed'] = true; }
    if ( $reason === 'SESSION_TIME_MISMATCH' ) { $b['mmc_session_times'] = array( '2026-11-12 19:00:00' ); }
    if ( $reason === 'TICKET_PRICE_OR_MAPPING_CONFLICT' ) { $b['sessions'][0]['tickets'][0]['wc_price'] = '491'; }
    if ( $reason === 'SOLD_OUT' ) { $b['sessions'][0]['available_units'] = 0; }
    if ( $reason === 'PRODUCT_NOT_READY' ) { $b['sessions'][0]['product_ready'] = false; }
    if ( $reason === 'UNSUPPORTED_TICKET_CATALOGUE' ) { $b['sessions'][0]['tickets'][] = $b['sessions'][0]['tickets'][0]; }
    $out = Catalogue::offers( $b, $now );
    check( ! $out['offers'] && $out['blocked'][0]['reason'] === $reason, 'session rejection ' . $reason );
}
$b = bundle(); $b['sessions'][0]['sale_end'] = '2026-11-05 06:00:20';
check( Catalogue::offers( $b, $now )['offers'][0]['expires_at'] === $now + 20, 'sale-end bounds TTL' );
$b['mdg_event']['sale_start'] = '2026-11-06 00:00:00';
fails( static function() use ( $b, $now ) { Catalogue::offers( $b, $now ); }, 'SALES_NOT_STARTED' );
$b = bundle(); $b['family_conflict'] = true;
check( ! Catalogue::offers( $b, $now )['offers'][0]['family']['available'], 'native multi-unit conflict disables virtual family' );
$b = bundle(); $b['sessions'][0]['tickets'][0]['code'] = 'YETISKIN'; $b['sessions'][0]['tickets'][1]['code'] = 'COCUK';
check( count( Catalogue::offers( $b, $now )['offers'] ) === 1, 'legacy Turkish codes' );
$b = bundle(); $revision = Catalogue::offers( $b, $now )['offers'][0]['source_revision'];
check( Catalogue::offers( $b, $now + 1 )['offers'][0]['source_revision'] === $revision, 'revision excludes observation time' );
$b['sessions'][0]['available_units']--;
check( Catalogue::offers( $b, $now )['offers'][0]['source_revision'] !== $revision, 'capacity changes invalidate selection' );

// Exercise the production WordPressSource using domain stubs, including real
// family fallback precedence and explicit rejection of database writes.
require __DIR__ . '/wordpress-fixture.php';
require __DIR__ . '/fixture-clock.php';
$GLOBALS['fixture_clock'] = $now;
$native = new WordPressSource();
$normalized = $native->bundle( 26 );
check( $normalized['family']['price_minor'] === 110000, 'native active MMC family precedence' );
check( $normalized['sessions'][0]['tickets'][0]['variation_ready'], 'native variation parent/stock validation' );
$GLOBALS['fixture_family_active'] = false;
check( $native->bundle( 26 )['family']['price_minor'] === 130000, 'inactive MMC row uses event setting' );
$GLOBALS['fixture_family_active'] = true;
$GLOBALS['fixture_can_manage'] = false;
fails( static function() { Service::catalogue(); }, 'FORBIDDEN' );
$GLOBALS['fixture_can_manage'] = true;
$GLOBALS['fixture_mmc_status'] = 'configured';
fails( static function() use ( $native, $now ) { Catalogue::offers( $native->bundle( 26 ), $now ); }, 'STATE_CONFLICT' );
$GLOBALS['fixture_mmc_status'] = 'sales_open';
$GLOBALS['fixture_wrong_parent'] = true;
$out = Catalogue::offers( $native->bundle( 26 ), $now );
check( ! $out['offers'], 'native wrong parent excluded' );
$GLOBALS['fixture_wrong_parent'] = false;
$current = Service::catalogue();
check( count( $current['offers'] ) === 1 && ! $current['blocked'], 'service builds native catalogue' );
$current_offer = $current['offers'][0];
$quoted = Service::quote( 223, array( 'adults' => 2, 'children' => 2 ), $current_offer['source_revision'] );
check( $quoted['recommended']['total_minor'] === 110000 && ! $quoted['reservation'], 'service native quote without cart' );
fails( static function() { Service::quote( 223, array( 'adults' => 2, 'children' => 2 ), str_repeat( '0', 64 ) ); }, 'STALE_SELECTION' );
$GLOBALS['fixture_capacity'] = 3;
fails( static function() use ( $current_offer ) { Service::quote( 223, array( 'adults' => 2, 'children' => 2 ), $current_offer['source_revision'] ); }, 'STALE_SELECTION' );
$GLOBALS['fixture_capacity'] = 500;
$GLOBALS['fixture_can_manage'] = false;
fails( static function() { Service::quote( 223, array( 'adults' => 2, 'children' => 2 ) ); }, 'FORBIDDEN' );
$GLOBALS['fixture_can_manage'] = true;
check( $GLOBALS['wpdb']->writes === 0, 'native adapter writes zero' );
check( $GLOBALS['fixture_cart_calls'] === 0, 'no cart initialization' );
require __DIR__ . '/preview-contract.php';
echo json_encode( array( 'result' => 'PASS', 'assertions' => $assertions, 'native_writes' => $GLOBALS['wpdb']->writes, 'cart_calls' => $GLOBALS['fixture_cart_calls'] ) ) . "\n";

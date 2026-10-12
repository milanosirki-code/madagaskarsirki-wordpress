<?php
/**
 * Standalone brand add-to-cart safety contract; no WordPress database needed.
 * php integrations/milano/tests/brand-checkout-guard-contract.php
 */
error_reporting( E_ALL );
define( 'ABSPATH', __DIR__ );
define( 'DUNYA_BRAND_GUARD_ENABLED', true );
function expect_guard( $ok, $label ) { if ( ! $ok ) { fwrite( STDERR, "FAIL: {$label}\n" ); exit( 1 ); } }
function add_filter( ...$args ) {}
function absint( $v ) { return abs( (int) $v ); }
function home_url( $v ) { return 'https://milanosirki.com/'; }
function wp_parse_url( $url, $component ) { return parse_url( $url, $component ); }
function wc_add_notice( $msg, $type ) {}
class MDG_DB { public static function table( $name ) { return 'wp_mdg_' . $name; } }
class MMC_Program_Service {
    public static function get_program( $id ) {
        if ( 20 === $id ) { return (object) array( 'brand' => 'Milano Sirki' ); }
        if ( 30 === $id ) { return (object) array( 'brand' => 'Madagaskar Sirki' ); }
        return null;
    }
    public static function normalized_program_brand( $v ) { return $v; }
}
class MMC_MDG_Bridge_Service {
    public static function program_for_mdg_event( $event_id ) {
        return 22 === $event_id ? 20 : ( 33 === $event_id ? 30 : 0 );
    }
}
class GuardMockDB {
    public function prepare( $sql, ...$args ) {
        expect_guard( 2 === count( $args ), 'two product identifiers required' );
        return implode( ':', $args );
    }
    public function get_col( $prepared ) {
        if ( '202:101' === $prepared ) { return array( 22 ); }
        if ( '303:101' === $prepared ) { return array( 33 ); }
        if ( '404:101' === $prepared ) { return array( 22, 33 ); }
        return array();
    }
}
$wpdb = new GuardMockDB();
require dirname( __DIR__ ) . '/milano-brand-checkout-guard/milano-brand-checkout-guard.php';
expect_guard( 'milano' === dmbg_site_brand(), 'host to brand' );
expect_guard( 'milano' === dmbg_product_owner( 101, 202 ), 'Milano ticket mapping' );
expect_guard( 'madagaskar' === dmbg_product_owner( 101, 303 ), 'Madagaskar ticket mapping' );
expect_guard( null === dmbg_product_owner( 101, 404 ), 'ambiguous product mapping must fail' );
expect_guard( null === dmbg_product_owner( 101, 999 ), 'unmapped product must fail' );
expect_guard( true === dmbg_validate_add_to_cart( true, 101, 1, 202 ), 'Milano on Milano' );
expect_guard( false === dmbg_validate_add_to_cart( true, 101, 1, 303 ), 'Madagaskar on Milano blocked' );
expect_guard( false === dmbg_validate_add_to_cart( true, 101, 1, 404 ), 'ambiguous blocked' );
expect_guard( false === dmbg_validate_add_to_cart( true, 101, 1, 999 ), 'unmapped blocked' );
expect_guard( false === dmbg_validate_add_to_cart( false, 101, 1, 202 ), 'preserve earlier denial' );
echo "PASS brand-checkout-guard contract\n";
